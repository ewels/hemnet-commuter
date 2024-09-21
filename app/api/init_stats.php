<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Get the base state of Hemnet Commuter for page load
 */

function get_init_stats(){

  global $mysqli;
  global $ini_array;

  require_once('comments.php');
  require_once('commute_locations.php');
  require_once('schools.php');
  require_once('ratings.php');
  require_once('tags.php');
  require_once('users.php');

  $results = array(
    "status" => "success",
    "tags" => get_all_tags(),
    "users" => get_all_users(),
    "commute_locations" => get_commute_locations(),
    "school_locations" => get_school_markers(),
    "translate_target_language" => $ini_array['translate_target_language'],
    "stats" => array()
  );

  // House statistics
  $sql = 'SELECT
    COUNT(id) as num_houses,
    MIN(askingPrice) as price_min,
    MAX(askingPrice) as price_max,
    MIN(size_total) as size_total_min,
    MAX(size_total) as size_total_max,
    MIN(landArea) as size_tomt_min,
    MAX(landArea) as size_tomt_max,
    MIN(daysOnHemnet) as days_on_hemnet_min,
    MAX(daysOnHemnet) as days_on_hemnet_max,
    MIN(nextOpenHouse) as nextOpenHouse_min,
    MAX(nextOpenHouse) as nextOpenHouse_max
  FROM `houses`';
  if ($result = $mysqli->query($sql)) {
    $results['stats'] = $result->fetch_assoc();
    $result->free_result();
  }

  return $results;
}

/////////
// CALLED DIRECTLY - API usage
/////////
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {
  require_once('_common_api.php');
  if(!check_auth_token()){
    echo json_encode(array("status"=>"error", "msg" => "Error: Invalid authentication"), JSON_PRETTY_PRINT);
  } else {
    echo json_encode(get_init_stats(), JSON_PRETTY_PRINT);
  }
}
