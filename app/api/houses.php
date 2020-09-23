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

  $oldest_fetch = 99999999999999;

  // Get house details
  $house_results = [];
  $sql = 'SELECT * FROM `houses`';
  if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
      $house_results[$row['id']] = $row;
    }
    $result->free_result();
  }
  if(count($house_results) == 0){
    $oldest_fetch = 0;
  }


  // Other database tables
  foreach($house_results as $house_id => $house){

    $oldest_fetch = min(intval($house['created']), $oldest_fetch);

    // Get commute times
    $house_results[$house_id]['commute_times'] = [];
    if(count($results['commute_locations']) > 0){
      $sql = 'SELECT * FROM `commute_times` WHERE `house_id` = "'.$mysqli->real_escape_string($house_id).'"';
      if ($result = $mysqli->query($sql)) {
        while ($row = $result->fetch_assoc()) {
          if(!array_key_exists($row['commute_id'], $results['commute_locations'])) continue;
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
    }

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
    if(isset($postdata['house_id']) && $house['id'] != $postdata['house_id']) $remove = true;
    if(isset($postdata['kommande']) && $postdata['kommande'] == '1' && @$house['isUpcoming'] != '1') $remove = true;
    if(isset($postdata['kommande']) && $postdata['kommande'] == '-1' && @$house['isUpcoming'] == '1') $remove = true;
    if(isset($postdata['bidding']) && $postdata['bidding'] == '1' && @$house['isBiddingOngoing'] != '1') $remove = true;
    if(isset($postdata['bidding']) && $postdata['bidding'] == '-1' && @$house['isBiddingOngoing'] == '1') $remove = true;
    if(isset($postdata['price_min']) && @$house['askingPrice'] < $postdata['price_min']) $remove = true;
    if(isset($postdata['price_max']) && @$house['askingPrice'] > $postdata['price_max']) $remove = true;
    if(isset($postdata['days_on_hemnet_max']) && @$house['daysOnHemnet'] > $postdata['days_on_hemnet_max']) $remove = true;
    if(isset($postdata['days_on_hemnet_min']) && @$house['daysOnHemnet'] < $postdata['days_on_hemnet_min']) $remove = true;
    if(isset($postdata['size_total_min']) && $house['size_total'] < $postdata['size_total_min']) $remove = true;
    if(isset($postdata['size_tomt_min']) && $house['landArea'] < $postdata['size_tomt_min']) $remove = true;
    if(isset($postdata['hide_ratings'])){
      foreach($postdata['hide_ratings'] as $user_id => $ratings){
        foreach($ratings as $rating){
          if($house['ratings'][$user_id] == $rating) $remove = true;
        }
      }
    }
    if(isset($postdata['hide_failed_commutes'])){
      foreach($postdata['hide_failed_commutes'] as $commute_id){
        if(!$house_results[$house_id]['commute_times'][$commute_id]['pass_threshold']) $remove = true;
      }
    }

    if($remove) unset($house_results[$house_id]);
  }

  // Commute time stats
  $results['commute_time_max'] = 0;
  $results['commute_time_min'] = 1000000;
  $results['commute_time_avg'] = null;
  $num_commutes = 0;
  $total_commute = 0;
  foreach($house_results as $house_id => $house){
    foreach($house['commute_times'] as $commute_id => $commute){
      if($commute['status'] != 'OK') continue;
      $results['commute_time_max'] = max(intval($commute['duration_value']), $results['commute_time_max']);
      $results['commute_time_min'] = min(intval($commute['duration_value']), $results['commute_time_min']);
      $total_commute += intval($commute['duration_value']);
      $num_commutes++;
    }
  }
  if($num_commutes > 0){
    $results['commute_time_avg'] = $total_commute / $num_commutes;
  }

  // Sanity-limit commute range to 30 mins - 2 hours
  // TODO - don't hard-code this
  $results['commute_time_max'] = min((60*60*2), $results['commute_time_max']);
  $results['commute_time_min'] = max((60*30), $results['commute_time_min']);

  $results['oldest_fetch'] = $oldest_fetch;
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
