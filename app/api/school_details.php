<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/*
 * Get School details for a single school from skolverket.se
 * - https://www.skolverket.se/om-oss/oppna-data/uppgifter-om-skolenheter-studievagar-och-statistik
 * - https://api.skolverket.se/planned-educations/swagger-ui/
 */

function get_school_details($id){
    // Load cache if it exists
    $cache_fn = 'cache/school_details_'.$id.'.json';
    if(file_exists($cache_fn)){
        $cache = json_decode(file_get_contents($cache_fn));
        if(time() - $cache->timestamp < (60*60*24*30)){
            return $cache->data;
        }
    }

    $school_details = [];

    // Get school report PDFs
    $reports_api = 'https://api.skolverket.se/planned-educations/v3/school-units/'.$id.'/documents';
    $results = @json_decode(@file_get_contents($reports_api));
    foreach($results->body as $type){
        foreach($type->documents as $doc){
            $school_details['reports'][$type->typeOfSchoolingCode] = $doc->url;
            break;
        }
    }

    // Get school statistics
    $stats_api = 'https://api.skolverket.se/planned-educations/v3/school-units/'.$id.'/statistics/gr';
    $results = @json_decode(@file_get_contents($stats_api));
    $school_details['full_statistics'] = $results->body;
    $school_details['stats'] = [
        'studentsPerTeacherQuota' => $results->body->studentsPerTeacherQuota[0]->value,
        'certifiedTeachersQuota' => $results->body->certifiedTeachersQuota[0]->value,
        'hasLibrary' => $results->body->hasLibrary,
        'totalNumberOfPupils' => str_replace('cirka ', '', $results->body->totalNumberOfPupils[0]->value),
        'ratioOfPupilsIn6thGradeWithAllSubjectsPassed' => $results->body->ratioOfPupilsIn6thGradeWithAllSubjectsPassed[0]->value,
    ];

    // Save cache
    // TODO - Save to DB instead?
    $cache = [
        'timestamp' => time(),
        'school_id' => $id,
        'data' => $school_details
    ];
    file_put_contents($cache_fn, json_encode($cache, JSON_PRETTY_PRINT));

    return $school_details;
}

/////////
// CALLED DIRECTLY - API usage
/////////
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {

    require_once('_common_api.php');
    if(!check_auth_token()){
        echo json_encode(array("status"=>"error", "msg" => "Error: Invalid authentication"), JSON_PRETTY_PRINT);
    }
    else if(isset($_GET['school_id']) && strlen($_GET['school_id']) > 0){
        echo json_encode(get_school_details($_GET['school_id']), JSON_PRETTY_PRINT);
    }
    else {
        echo json_encode(array("status"=>"error", "msg" => "Error: No input supplied", "post_data" => $postdata), JSON_PRETTY_PRINT);
    }

}
