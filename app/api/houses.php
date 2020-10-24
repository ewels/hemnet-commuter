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
  global $ini_array;

  require_once('comments.php');
  require_once('commute_locations.php');
  require_once('ratings.php');
  require_once('tags.php');
  require_once('users.php');

  $results = array("status" => "success");

  // Filter sanity checks
  if(isset($postdata['open_house_before']) && strtotime($postdata['open_house_before']) == false){
    $results['status'] = 'error';
    $results['msg'] = 'Open house before filter string "'.$postdata['open_house_before'].'" could not be parsed';
    return($results);
  }
  if(isset($postdata['open_house_after']) && strtotime($postdata['open_house_after']) == false){
    $results['status'] = 'error';
    $results['msg'] = 'Open house after filter string "'.$postdata['open_house_after'].'" could not be parsed';
    return($results);
  }

  // Build simple WHERE filters
  $houses_where = [];
  if(isset($postdata['house_id'])) $houses_where[] = '`id` = '.$mysqli->real_escape_string($postdata['house_id']);
  if(isset($postdata['kommande']) && $postdata['kommande'] == '1') $houses_where[] = '`isUpcoming` = 1';
  if(isset($postdata['kommande']) && $postdata['kommande'] == '-1') $houses_where[] = '`isUpcoming` = 0';
  if(isset($postdata['bidding']) && $postdata['bidding'] == '1') $houses_where[] = '`isBiddingOngoing` = 1';
  if(isset($postdata['bidding']) && $postdata['bidding'] == '-1') $houses_where[] = '`isBiddingOngoing` = 0';
  if(isset($postdata['price_min'])) $houses_where[] = '`askingPrice` > '.$mysqli->real_escape_string($postdata['price_min']);
  if(isset($postdata['price_max'])) $houses_where[] = '`askingPrice` < '.$mysqli->real_escape_string($postdata['price_max']);
  if(isset($postdata['days_on_hemnet_min'])) $houses_where[] = '`daysOnHemnet` > '.$mysqli->real_escape_string($postdata['days_on_hemnet_min']);
  if(isset($postdata['days_on_hemnet_max'])) $houses_where[] = '`daysOnHemnet` < '.$mysqli->real_escape_string($postdata['days_on_hemnet_max']);
  if(isset($postdata['size_total_min'])) $houses_where[] = '`size_total` > '.$mysqli->real_escape_string($postdata['size_total_min']);
  if(isset($postdata['size_total_max'])) $houses_where[] = '`size_total` < '.$mysqli->real_escape_string($postdata['size_total_max']);
  if(isset($postdata['size_tomt_min'])) $houses_where[] = '`landArea` > '.$mysqli->real_escape_string($postdata['size_tomt_min']);
  if(isset($postdata['size_tomt_max'])) $houses_where[] = '`landArea` < '.$mysqli->real_escape_string($postdata['size_tomt_max']);
  if(isset($postdata['has_upcoming_open_house']) && $postdata['has_upcoming_open_house'] == '1') $houses_where[] = '`nextOpenHouse` IS NOT NULL';
  if(isset($postdata['has_upcoming_open_house']) && $postdata['has_upcoming_open_house'] == '-1') $houses_where[] = '`nextOpenHouse` IS NULL';


  ///
  /// NB: I should do the more complicated filters in joins but it's late and I'm a bad person.
  ///

  $oldest_fetch = 99999999999999;

  // Get house details
  $house_results = [];
  $sql = 'SELECT * FROM `houses`';
  if(count($houses_where) > 0){
    $sql .= ' WHERE '.implode(' AND ', $houses_where);
  }
  if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
      $house_results[$row['id']] = $row;
    }
    $result->free_result();
  }
  if(count($house_results) == 0){
    $oldest_fetch = 0;
  }

  $commute_locations = get_commute_locations();

  // Other database tables
  foreach($house_results as $house_id => $house){

    $oldest_fetch = min(intval($house['created']), $oldest_fetch);

    // Get commute times
    $house_results[$house_id]['commute_times'] = [];
    if(count($commute_locations) > 0){
      $sql = 'SELECT * FROM `commute_times` WHERE `house_id` = "'.$mysqli->real_escape_string($house_id).'"';
      if ($result = $mysqli->query($sql)) {
        while ($row = $result->fetch_assoc()) {
          if(!array_key_exists($row['commute_id'], $commute_locations)) continue;
          if($row['duration_value'] == null){
            $passes_threshold = null;
          } else {
            $passes_threshold = $row['duration_value'] < $commute_locations[ $row['commute_id'] ]['max_time'];
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

    // Visning filter times in variable text strings
    if(isset($postdata['open_house_before'])){
      if(is_null($house['nextOpenHouse']) || floatval($house['nextOpenHouse']) == 0) $remove = true;
      else if(floatval($house['nextOpenHouse']) > strtotime($postdata['open_house_before'])) $remove = true;
    }
    if(isset($postdata['open_house_after'])){
      if(is_null($house['nextOpenHouse']) || floatval($house['nextOpenHouse']) == 0) $remove = true;
      else {
        $has_oh_after = false;
        foreach(explode(',', $house['upcomingOpenHouses']) as $oh) {
          if(floatval($oh) > strtotime($postdata['open_house_after'])) $has_oh_after = true;
        }
        if(!$has_oh_after) $remove = true;
      }
    }

    // Filters that should be written as SQL joins
    $users = get_all_users();
    if(isset($postdata['min_combined_rating_score'])){
      $score = 0;
      foreach($users as $user_id => $user_name){
        if ($house['ratings'][$user_id] == 'yes') $score += 1;
        if ($house['ratings'][$user_id] == 'no') $score += -1;
      }
      if($score < $postdata['min_combined_rating_score']) $remove = true;
    }
    if(isset($postdata['min_num_ratings'])){
      $num_ratings = 0;
      foreach($users as $user_id => $user_name){
        if ($house['ratings'][$user_id] != 'not_set') $num_ratings++;
      }
      if($num_ratings < $postdata['min_num_ratings']) $remove = true;
    }
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
