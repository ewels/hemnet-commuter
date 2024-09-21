<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Get School information from skolverket.se
 *  - https://www.skolverket.se/om-oss/oppna-data/api-for-skolenhetsregistre
 *  - https://api.skolverket.se/skolenhetsregistret/swagger-ui/index.html#/
 */

// Fetch search results from Hemnet

function get_schools_list(){
    global $ini_array;

    // Check we have some school kommun names in the config
    if(!isset($ini_array['school_kommuns']) || count($ini_array['school_kommuns']) == 0){
        return array('status'=>'error', 'msg'=>'No school kommun names found in config.ini');
    }

    // Load cache if it exists
    if(file_exists('cache/schools.json')){
        return json_decode(file_get_contents('cache/schools.json'));
    }

    // Get county IDs
    $kommun_ids = [];
    $kommun_api = 'https://api.skolverket.se/skolenhetsregistret/v1/kommun';
    $results = @json_decode(@file_get_contents($kommun_api));
    foreach($results->Kommuner as $kommun){
        if(in_array($kommun->Namn, $ini_array['school_kommuns']))
            $kommun_ids[] = $kommun->Kommunkod;
    }

    // Get school IDs
    $kommun_api = 'https://api.skolverket.se/skolenhetsregistret/v1/skolenhet';
    $results = @json_decode(@file_get_contents($kommun_api));
    // Filter out schools that aren't in the right kommun
    $school_ids = [];
    foreach($results->Skolenheter as $skola){
        if(in_array($skola->Kommunkod, $kommun_ids) && $skola->Status == 'Aktiv'){
            $school_ids[] = $skola;
        }
    }

    // Get detailed info for every school
    $schools = [];
    foreach($school_ids as $school){
        $school_api = 'https://api.skolverket.se/skolenhetsregistret/v1/skolenhet/'.$school->Skolenhetskod;
        $results = @json_decode(@file_get_contents($school_api));
        $schools[] = $results;
    }

    // TODO - Filter by type of school, available years etc.

    // Save cache
    // TODO - Save to DB instead
    file_put_contents('cache/schools.json', json_encode($schools, JSON_PRETTY_PRINT));

    return $schools;
}



/////////
// CALLED DIRECTLY - API usage
/////////
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {

    require_once('_common_api.php');
    echo json_encode(get_schools_list(), JSON_PRETTY_PRINT);

}
