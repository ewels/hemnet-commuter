<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Calculate commute times using the Google Maps API
 */

function get_traveltime_maps(){

  global $ini_array;
  global $mysqli;

  // Get the commute locations
  require_once('commute_locations.php');
  $commute_locations = get_commute_locations();

  // Build the JSON request
  $request_hash_strs = [];
  $postdata = array(
    'arrival_searches' => [],
    'intersections' => [
      array(
        'id' => 'intersection',
        'search_ids' => []
      )
    ]
  );

  // Add each commute location
  foreach($commute_locations as $commute_id => $loc){
    $postdata['arrival_searches'][] = array(
      'id' => $loc['address'],
      'coords' => array(
        'lat' => floatval($loc['lat']),
        'lng' => floatval($loc['lng']),
      ),
      'transportation' => array('type' => 'public_transport'),
      'arrival_time' => date('c', strtotime('next friday, 9am CET')),
      'range' => array(
        'enabled' => true,
        'width' => 3600 // allow arrival between 8 and 9
      ),
      'travel_time' => floatval($loc['max_time'])
    );
    $postdata['intersections'][0]['search_ids'][] = $loc['address'];
    $request_hash_strs[] = $loc['lat'].','.$loc['lng'].','.$loc['max_time'];
  }

  if(count($postdata['arrival_searches']) == 0){
    return array('status'=>'success', 'msg' => 'No commute locations found');
  }

  // Figure out the request hash and check if we have it in the DB
  $request_str = implode('|', $request_hash_strs);
  $sql = 'SELECT `map_id`, `commute_id`, `layer_name`, `result` FROM `commute_map` WHERE `request_str` = "'.$mysqli->real_escape_string($request_str).'"';
  $results = [];
  $layer_names = [];
  if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
      $id = 'not_set';
      if(is_null($row['commute_id'])) $id = $row['map_id'];
      else $id = $row['commute_id'];
      $results[$id] = json_decode($row['result']);
      $layer_names[$id] = $row['layer_name'];
    }
    $result->free_result();
  }
  if(count($results) == count($postdata['arrival_searches']) + 1){
    return array('status'=>'success', 'method'=> 'database', 'layer_names' => $layer_names,  'results' => $results);
  }

  // Not in DB - fetch from the TravelTime API
  $tt_api_url = 'https://api.traveltimeapp.com/v4/time-map';
  $curl = curl_init($tt_api_url);
  curl_setopt($curl, CURLOPT_HEADER, false);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_HTTPHEADER, array(
    'Content-type: application/json',
    'Accept: application/json',
    'X-Application-Id: '.$ini_array['traveltime_api_id'],
    'X-Api-Key: '.$ini_array['traveltime_api_key'],
  ));
  curl_setopt($curl, CURLOPT_POST, true);
  curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postdata));

  // Essentially disable SSL to get around expired certificate problem
  curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

  $result_raw = curl_exec($curl);
  $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
  if($status != 200){
    return array(
      'status'=>'error',
      'msg' => 'TravelTime API call failed',
      'curl_status' => $status,
      'curl_error' => curl_error($curl),
      'curl_errno' => curl_errno($curl),
      'url' => $tt_api_url,
      'result' => @json_decode($result_raw, true),
      'result_raw' => $result_raw
    );
  }
  curl_close($curl);

  $results_json = @json_decode($result_raw, true);
  if(!isset($results_json['results']) || count($results_json['results']) == 0){
    return array('status'=>'error', 'msg'=>'TravelTime returned zero results', 'data'=>$result_raw);
  }

  // Delete existing commute location if it existed
  $sql = 'DELETE FROM `commute_map` WHERE `request_str` = "'.$mysqli->real_escape_string($request_str).'"';
  $mysqli->query($sql);

  // Insert into the database
  $results = [];
  $layer_names = [];
  foreach($results_json['results'] as $result){
    // Get the commute ID
    $commute_id = null;
    $layer_name = 'Intersection of commutes';
    foreach($commute_locations as $c_id => $loc){
      if($loc['address'] == $result['search_id']){
        $commute_id = $c_id;
        $hrs = floor($loc['max_time'] / 3600);
        $mins = floor(($loc['max_time'] / 60) % 60);
        $layer_name = $hrs.'hr '.$mins.' commute to "'.$loc['address'].'"';
      }
    }
    // Insert
    $sql = '
      INSERT INTO `commute_map`
      SET `request_str` = "'.$mysqli->real_escape_string($request_str).'",
          `map_id` = "'.$mysqli->real_escape_string($result['search_id']).'",
          `layer_name` = "'.$mysqli->real_escape_string($layer_name).'",
          `result` = "'.$mysqli->real_escape_string(json_encode($result)).'"';
    if(!is_null($commute_id)){
      $sql .= ', `commute_id` = "'.$mysqli->real_escape_string($commute_id).'"';
    }
    if(!$mysqli->query($sql)){
      return array('status' => 'error', 'error_msg' => $mysqli->error, 'sql' => $sql);
    } else {
      // Save to array so that we can return directly
      if(is_null($row['commute_id'])) $id = $result['search_id'];
      else $id = $row['commute_id'];
      $results[$id] = $result;
      $layer_names[$id] = $row['layer_name'];
    }
  }

  return array('status'=>'success', 'method'=> 'api', 'layer_names' => $layer_names, 'results' => $results);

}

/////////
// CALLED DIRECTLY - API usage
/////////
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {

  require_once('_common_api.php');

  echo json_encode(get_traveltime_maps());

}
