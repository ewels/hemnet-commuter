<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Return the houses from the database
 */

require_once('_common_api.php');
require_once('comments.php');
require_once('commute_locations.php');
require_once('geocode_address.php');
require_once('ratings.php');
require_once('tags.php');
require_once('users.php');

///
/// NB: I should do all of this in joins but it's late and I'm a bad person.
///

// Get search results
$oldest_saved_search_fetch = false;
$sql = 'SELECT `id`, `url`, `created` FROM `search_result_urls`';
$results = [];
if ($result = $mysqli->query($sql)) {
  while ($row = $result->fetch_row()) {
    $results[$row[0]] = array('house_id' => $row[0], 'url' => $row[1]);
    $created = strtotime($row[2]);
    if(!$oldest_saved_search_fetch) $oldest_saved_search_fetch = $created;
    $oldest_saved_search_fetch = min($oldest_saved_search_fetch, $created);
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

// Other database tables
foreach($results as $house_id => $house){

  // Get geocode results
  $results[$house_id] = array_merge($results[$house_id], geocode_house_address($house_id));

  // Get ratings
  $results[$house_id]['ratings'] = get_house_ratings($house_id);

  // Get comments
  $results[$house_id]['comments'] = get_house_comments($house_id);

  // Get tags
  $results[$house_id]['tags'] = get_house_tags($house_id);

}

// FILTERS
foreach($results as $house_id => $house){
  $remove = false;
  if(isset($postdata['house_id']) && $house['house_id'] != $postdata['house_id']) $remove = true;
  if(isset($postdata['kommande']) && $postdata['kommande'] == '1' && @$house['status'] != 'upcoming') $remove = true;
  if(isset($postdata['kommande']) && $postdata['kommande'] == '-1' && @$house['status'] == 'upcoming') $remove = true;
  if(isset($postdata['bidding']) && $postdata['bidding'] == '1' && @$house['bidding'] != '1') $remove = true;
  if(isset($postdata['bidding']) && $postdata['bidding'] == '-1' && @$house['bidding'] == '1') $remove = true;
  if(isset($postdata['price_min']) && @$house['price'] < $postdata['price_min']) $remove = true;
  if(isset($postdata['price_max']) && @$house['price'] > $postdata['price_max']) $remove = true;
  if(isset($postdata['size_total_min']) && $house['size_total'] < $postdata['size_total_min']) $remove = true;
  if(isset($postdata['hide_ratings'])){
    foreach($postdata['hide_ratings'] as $user_id => $ratings){
      foreach($ratings as $rating){
        if($house['ratings'][$user_id] == $rating) $remove = true;
      }
    }
  }

  if($remove) unset($results[$house_id]);
}

echo json_encode(
  array(
    "status" => "success",
    "num_results" => count($results),
    "oldest_search_result" => $oldest_saved_search_fetch,
    "tags" => get_all_tags(),
    "users" => get_all_users(),
    "commute_locations" => get_commute_locations(),
    "results" => $results
  ), JSON_PRETTY_PRINT);
