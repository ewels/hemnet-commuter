<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Return the houses from the database
 */

function get_houses($postdata){

  global $mysqli;

  require_once('comments.php');
  require_once('commute_locations.php');
  require_once('geocode_address.php');
  require_once('ratings.php');
  require_once('tags.php');
  require_once('users.php');

  $results = array(
    "status" => "success",
    "tags" => get_all_tags(),
    "users" => get_all_users(),
    "commute_locations" => get_commute_locations(),
  );

  ///
  /// NB: I should do all of this in joins but it's late and I'm a bad person.
  ///

  // Get search results
  $oldest_saved_search_fetch = false;
  $sql = 'SELECT `id`, `url`, `created` FROM `search_result_urls`';
  $house_results = [];
  if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_row()) {
      $house_results[$row[0]] = array('house_id' => $row[0], 'url' => $row[1]);
      $created = strtotime($row[2]);
      if(!$oldest_saved_search_fetch) $oldest_saved_search_fetch = $created;
      $oldest_saved_search_fetch = min($oldest_saved_search_fetch, $created);
    }
    $result->free_result();
  }

  // Go over each house
  foreach($house_results as $house_id => $house){

    // Get house details
    $sql = 'SELECT * FROM house_details WHERE `id` = "'.$mysqli->real_escape_string($house_id).'"';
    if ($result = $mysqli->query($sql)) {
      while ($row = $result->fetch_assoc()) {
        $house_results[$house_id] = array_merge($house_results[$house_id], $row);
      }
      $result->free_result();
    }

    // Get commute times
    $house_results[$house_id]['commute_times'] = [];
    $sql = 'SELECT * FROM `commute_times` WHERE `house_id` = "'.$mysqli->real_escape_string($house_id).'"';
    if ($result = $mysqli->query($sql)) {
      while ($row = $result->fetch_assoc()) {
        if($row['duration_value'] == null){
          $passes_threshold = null;
        } else {
          $passes_threshold = $row['duration_value'] < $results['commute_locations'][ $row['commute_id'] ]['max_time'];
        }
        $house_results[$house_id]['commute_times'][ $row['commute_id'] ] = array(
          'status' => $row['status'],
          'distance_text' => $row['distance_text'],
          'distance_value' => $row['distance_value'],
          'duration_text' => $row['duration_text'],
          'duration_value' => $row['duration_value'],
          'pass_threshold' => $passes_threshold
        );
      }
      $result->free_result();
    }

    // Unencode JSON strings
    $house_results[$house_id]['address'] = @json_decode($house_results[$house_id]['address']);
    $house_results[$house_id]['data_layer'] = @json_decode($house_results[$house_id]['data_layer']);

    // Total living area
    $house_results[$house_id]['size_total'] = @$house_results[$house_id]['living_area'] + @$house_results[$house_id]['supplemental_area'];

  }

  // Other database tables
  foreach($house_results as $house_id => $house){

    // Get geocode results
    $house_results[$house_id] = array_merge($house_results[$house_id], geocode_house_address($house_id));

    // Get ratings
    $house_results[$house_id]['ratings'] = get_house_ratings($house_id);

    // Get comments
    $house_results[$house_id]['comments'] = get_house_comments($house_id);

    // Get tags
    $house_results[$house_id]['tags'] = get_house_tags($house_id);

  }

  // FILTERS
  foreach($house_results as $house_id => $house){
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

    if($remove) unset($house_results[$house_id]);
  }

  $results['oldest_search_result'] = $oldest_saved_search_fetch;
  $results['num_results'] = count($house_results);
  $results['results'] = $house_results;

  return $results;

}


  /////////
  // CALLED DIRECTLY - API usage
  /////////
  if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {

    require_once('_common_api.php');

    echo json_encode(get_houses($postdata), JSON_PRETTY_PRINT);

  }
