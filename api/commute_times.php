<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Calculate commute times using the Google Maps API
 */

function update_commute_times($only_house_id=false, $only_commute_id=false, $db_only=false){

  // Pull required info from the DB
  require_once('houses.php');
  $houses = get_houses([]);

  // Get origins and destinations
  $origins = [];
  $destinations = [];
  $num_fetched = 0;
  $num_db = 0;
  $num_missing = 0;
  $result = [];
  foreach($houses['results'] as $house_id => $house){
    if($only_house_id && $only_house_id != $house_id){
      continue;
    }
    foreach($houses['commute_locations'] as $commute_id => $commute_loc){
      if($only_commute_id && $only_commute_id != $commute_id) continue;
      // Only look up if we don't already have this commute location
      if(array_key_exists($commute_id, $house['commute_times'])){
        $num_db += 1;
      } else {
        if($db_only){
          $num_missing += 1;
          continue;
        }
        $origins[$commute_id] = $commute_loc['lat'].','.$commute_loc['lng'];
        $destinations[$house['house_id']] = $house['lat'].','.$house['lng'];
      }
      // API limits: https://developers.google.com/maps/documentation/distance-matrix/usage-and-billing#other-usage-limits
      if(count($origins) >= 25 || count($destinations) >= 25 || count($origins) * count($destinations) >= 100){
        $result = fetch_distance_matrix_results($origins, $destinations);
        if($result['status'] == 'success'){
          $num_fetched += $result['num_results'];
          $origins = [];
          $destinations = [];
        } else {
          $result['num_results'] = $num_fetched;
          return $result;
        }
      }
    }
  }

  // Final batch
  if(count($origins) > 0 && count($destinations) > 0){
    $result = fetch_distance_matrix_results($origins, $destinations);
    if($result['status'] == 'success'){
      $num_fetched += $result['num_results'];
    } else {
      $result['num_results'] = $num_fetched;
      return $result;
    }
  }

  return array(
    "status"=>"success",
    "msg" => "$num_fetched commute results fetched, $num_db found in the database",
    "num_fetched" => $num_fetched,
    "num_db" => $num_db,
    "num_missing" => $num_missing,
    "only_house_id" => $only_house_id,
    "only_commute_id" => $only_commute_id,
  );

}

function fetch_distance_matrix_results($origins, $destinations){

  global $ini_array;
  global $mysqli;

  // Choose an arrival time - next Friday, 9am
  $arrival_time = strtotime('next friday, 9am CET');

  // Split associative array into two flat arrays (order is important to understand results)
  $origin_ids = [];
  $origin_latlngs = [];
  foreach($origins as $id => $latlng){
    $origin_ids[] = $id;
    $origin_latlngs[] = $latlng;
  }
  $destination_ids = [];
  $destination_latlngs = [];
  foreach($destinations as $id => $latlng){
    $destination_ids[] = $id;
    $destination_latlngs[] = $latlng;
  }

  // Get results
  $google_url = 'https://maps.googleapis.com/maps/api/distancematrix/json?key='.$ini_array['gmap_api_key'].'&mode=transit&arrival_time='.$arrival_time.'&origins='.implode('|', $origin_latlngs).'&destinations='.implode('|', $destination_latlngs);
  $api_result = @json_decode(@file_get_contents($google_url));
  $results = [];
  $num_results = 0;
  if($api_result->status == 'OK' && isset($api_result->rows)){
    foreach($api_result->rows as $origin_idx => $row){
      foreach($row->elements as $destination_idx => $el){

        // Collect results into flat array
        $result = [];
        $result['status'] = $el->status;
        if($el->status == 'OK'){
          $result['distance_text'] = $el->distance->text;
          $result['distance_value'] = $el->distance->value;
          $result['duration_text'] = $el->duration->text;
          $result['duration_value'] = $el->duration->value;
        }

        // Convert array idx to commute / house ID
        $result['commute_id'] = $origin_ids[$origin_idx];
        $result['house_id'] = $destination_ids[$destination_idx];

        // Delete any duplicate rows in the database if they exist
        $sql = '
          DELETE FROM `commute_times`
          WHERE `commute_id` = "'.$mysqli->real_escape_string($result['commute_id']).'"
          AND `house_id` = "'.$mysqli->real_escape_string($result['house_id']).'"';
        $mysqli->query($sql);

        // Insert into the database
        $sql_fields = [];
        foreach($result as $field => $val){
          $sql_fields[] = '`'.$field.'` = "'.$mysqli->real_escape_string($val).'"';
        }
        $sql = 'INSERT INTO `commute_times` SET '.implode(', ', $sql_fields);
        if(!$mysqli->query($sql)){
          echo "SQL error - ".$mysqli->error."\n$sql\n\n";
        }

        $num_results++;
      }
    }
    return array("status"=>"success", "msg" => "$num_results commute results found", "num_results" => $num_results);
  } else {
    return array("status"=>"error", "msg" => "Error with google API result:\n".json_encode($api_result, JSON_PRETTY_PRINT));
  }
}


/////////
// CALLED DIRECTLY - API usage
/////////
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {

  require_once('_common_api.php');

  $only_house_id = isset($_GET['house_id']) ? $_GET['house_id'] : false;
  $only_commute_id = isset($_GET['commute_id']) ? $_GET['commute_id'] : false;

  echo json_encode(update_commute_times($only_house_id, $only_commute_id), JSON_PRETTY_PRINT);

}
