<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Return the houses from the database
 */

// Prepare for JSON output
header("Content-type: text/json; charset=utf-8");

// Connect to the database
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$ini_array = parse_ini_file("../hemnet_commuter_config.ini");

$mysqli = new mysqli("localhost", $ini_array['db_user'], $ini_array['db_password'], $ini_array['db_name']);
if ($mysqli->connect_errno) {
   die("Failed to connect to MySQL: " . $mysqli->connect_error);
}

// Get houses
$sql = '
  SELECT *
  FROM house_details
  INNER JOIN search_result_urls ON house_details.id = search_result_urls.id
  LEFT JOIN geocoding_results ON house_details.id = geocoding_results.house_id;
';
$results = [];
if ($result = $mysqli->query($sql)) {
  while ($row = $result->fetch_assoc()) {
    $results[$row['house_id']] = $row;
  }
  $result->free_result();
}

// Get ratings
require_once('ratings.php');
foreach($results as $house_id => $house){
  $results[$house_id]['ratings'] = get_house_ratings($house_id);
}

// Get tags
require_once('tags.php');
foreach($results as $house_id => $house){
  $results[$house_id]['tags'] = get_house_tags($house_id);
}

echo json_encode(array("status"=>"success", "results"=>$results), JSON_PRETTY_PRINT);
