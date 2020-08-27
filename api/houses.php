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

require_once('geocode_address.php');
require_once('ratings.php');
require_once('comments.php');
require_once('tags.php');

// AngularJS POST data looks weird
$postdata = json_decode(file_get_contents("php://input"), true);
if(is_null($postdata)) $postdata = [];
$postdata = array_merge($_GET, $postdata);

///
/// NB: I should do all of this in joins but it's late and I'm a bad person.
///

// Get search results
$sql = 'SELECT `id`, `url` FROM `search_result_urls`';
$results = [];
if ($result = $mysqli->query($sql)) {
  while ($row = $result->fetch_row()) {
    $results[$row[0]] = array('house_id' => $row[0], 'url' => $row[1]);
  }
  $result->free_result();
}

// Go over each house
foreach($results as $house_id => $house){

  // Get house details
  $sql = 'SELECT * FROM house_details WHERE `id` = "'.$mysqli->real_escape_string($house_id).'"';
  if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
      $results[$house_id] = array_merge($results[$house_id], $row);
    }
    $result->free_result();
  }

  // Unencode JSON strings
  $results[$house_id]['address'] = @json_decode($results[$house_id]['address']);
  $results[$house_id]['data_layer'] = @json_decode($results[$house_id]['data_layer']);

  // Total living area
  $results[$house_id]['size_total'] = @$results[$house_id]['living_area'] + @$results[$house_id]['supplemental_area'];

}

foreach($results as $house_id => $house){
  $remove = false;
  // FILTERS
  if(isset($postdata['price_min']) && @$house['price'] < $postdata['price_min']) $remove = true;
  if(isset($postdata['price_max']) && @$house['price'] > $postdata['price_max']) $remove = true;
  if(isset($postdata['size_total_min']) && $house['size_total'] < $postdata['size_total_min']) $remove = true;

  if($remove) unset($results[$house_id]);
}

foreach($results as $house_id => $house){

  // Get geocode results
  $results[$house_id] = array_merge($results[$house_id], geocode_address(null, $house_id));

  // Get ratings
  $results[$house_id]['ratings'] = get_house_ratings($house_id);

  // Get comments
  $results[$house_id]['comments'] = get_house_comments($house_id);

  // Get tags
  $results[$house_id]['tags'] = get_house_tags($house_id);

}

echo json_encode(array("status"=>"success", "num_results"=>count($results), "results"=>$results), JSON_PRETTY_PRINT);
