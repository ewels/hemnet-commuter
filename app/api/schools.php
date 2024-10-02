<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/*
 * Get School information from skolverket.se
 *  - https://www.skolverket.se/om-oss/oppna-data/api-for-skolenhetsregistre
 *  - https://api.skolverket.se/skolenhetsregistret/swagger-ui/index.html#/
 */

// Fetch search results from Hemnet

function get_schools_list(){
    global $ini_array;

    // Check we have some school kommun names in the config
    if(!isset($ini_array['school_kommuns']) || count($ini_array['school_kommuns']) == 0){
        return [];
    }

    // Get county IDs
    $kommun_ids = [];
    $kommun_api = 'https://api.skolverket.se/skolenhetsregistret/v1/kommun';
    $results = @json_decode(@file_get_contents($kommun_api));
    foreach($results->Kommuner as $kommun){
        if(in_array($kommun->Namn, $ini_array['school_kommuns']))
            $kommun_ids[] = $kommun->Kommunkod;
    }

    // Get schools for each kommun
    $schools = [];
    foreach($kommun_ids as $kommun_id){
        // Load cache if it exists
        if(file_exists('cache/schools_'.$kommun_id.'.json')){
            $cache = json_decode(file_get_contents('cache/schools_'.$kommun_id.'.json'));
            if(time() - $cache->timestamp < (60*60*24*30)){
                $schools = array_merge($schools, (array) $cache->schools);
                continue;
            }
        }

        // Get school IDs
        $kommun_api = 'https://api.skolverket.se/skolenhetsregistret/v1/kommun/'.$kommun_id;
        $results = @json_decode(@file_get_contents($kommun_api));
        $school_ids = [];
        if ($results && isset($results->Skolenheter)) {
            foreach($results->Skolenheter as $skola){
                if($skola->Status == 'Aktiv'){
                    $school_ids[] = $skola->Skolenhetskod;
                }
            }
        } else {
            error_log('Error: Skolverket did not return any schools');
        }

        // Get detailed info for every school
        foreach($school_ids as $school_id){
            $school_api = 'https://api.skolverket.se/skolenhetsregistret/v1/skolenhet/'.$school_id;
            $results = @json_decode(@file_get_contents($school_api));
            $schools['school_'.$school_id] = $results;
        }

        // Save cache
        // TODO - Save to DB instead?
        $cache = [
            'timestamp' => time(),
            'school_kommun' => $kommun_id,
            'schools' => $schools
        ];
        file_put_contents('cache/schools_'.$kommun_id.'.json', json_encode($cache, JSON_PRETTY_PRINT));
    }

    return $schools;
}

function school_year_badges($years){
    $badges = '';
    foreach($years as $year){
        $bcol = 'warning';
        if($year == 'F'){
            $bcol = 'primary';
        }
        $badges .= '<span class="border border-'.$bcol.' rounded-circle p-1 text-center mr-1" style="display: inline-block; width:25px;">'.$year.'</span>';
    }
    return $badges;
}

function get_school_markers(){
    global $ini_array;

    $schools = get_schools_list();
    $markers = [];
    $relevant_school_years = ['Ak1', 'Ak2', 'Ak3', 'Ak4', 'Ak5', 'Ak6', 'Ak7', 'Ak8', 'Ak9'];
    foreach($schools as $school_id => $school){
        if(!$school){
            continue;
        }
        $school_types = [];
        $school_years = [];
        $check_school_type = array_key_exists('school_types', $ini_array) && count($ini_array['school_types']) > 0;
        $show_school_type = false;
        foreach($school->SkolenhetInfo->Skolformer as $skolform){
            $school_types[] = $skolform->Benamning;
            if($check_school_type && in_array($skolform->Benamning, $ini_array['school_types'])){
                $show_school_type = true;
            }
            if($skolform->type == "Forskoleklass"){
                $school_years[] = 'F';
            }
            foreach($relevant_school_years as $year){
                if(property_exists($skolform, $year) && $skolform->$year == true){
                    $school_years[] = str_replace('Ak', '', $year);
                }
            }
        }
        // Sort school years
        $school_years = array_unique($school_years);
        sort($school_years);
        // Move F to start of array for $school_years
        if(($key = array_search('F', $school_years)) !== false) {
            unset($school_years[$key]);
            array_unshift($school_years, 'F');
        }

        // Ignore if not in the list of school types
        if($check_school_type && !$show_school_type){
            continue;
        }
        // Ignore if not lat/lng
        if(!isset($school->SkolenhetInfo->Besoksadress->GeoData->Koordinat_WGS84_Lat) || !isset($school->SkolenhetInfo->Besoksadress->GeoData->Koordinat_WGS84_Lng)){
            continue;
        }
        $school_name = $school->SkolenhetInfo->Namn;
        $school_id = $school->SkolenhetInfo->Skolenhetskod;
        $school_typ = $school->SkolenhetInfo->Huvudman->Typ;
        $description =
            '<h6><a href="https://utbildningsguiden.skolverket.se/skolenhet?schoolUnitID='.$school_id.'" target="_blank">'.
                $school_name.
            ' <i class="fa fa-external-link" aria-hidden="true"></i></a></h6>
            <p class="mt-0 mb-1">'.
                '<span class="badge badge-'.($school_typ == 'Kommun' ? 'secondary' : 'primary').'">'.$school_typ.'</span>
            </p>
            <p class="mt-0 mb-1">'.
                implode(', ', $school_types).
            '</p>
            <p class="my-0">'.school_year_badges($school_years).'</p>';
        $markers[] = array(
            'lat' => $school->SkolenhetInfo->Besoksadress->GeoData->Koordinat_WGS84_Lat,
            'lng' => $school->SkolenhetInfo->Besoksadress->GeoData->Koordinat_WGS84_Lng,
            'name' => $school_name,
            'description' => $description,
            'id' => $school_id,
            'years' => $school_years,
        );
    }
    return $markers;
}



/////////
// CALLED DIRECTLY - API usage
/////////
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {

    require_once('_common_api.php');
    if(!check_auth_token()){
        echo json_encode(array("status"=>"error", "msg" => "Error: Invalid authentication"), JSON_PRETTY_PRINT);
    }
    else {
        // echo json_encode(get_schools_list(), JSON_PRETTY_PRINT);
        echo json_encode(get_school_markers(), JSON_PRETTY_PRINT);
    }

}
