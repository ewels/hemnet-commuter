<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Update and return commute locations
 */

require_once('commute_times.php');

function get_commute_locations($id=false){

  global $mysqli;

  $results = [];
  $sql = 'SELECT * FROM `commute_locations`';
  if($id !== false){
    $sql .= ' WHERE commute_locations.id = "'.$mysqli->real_escape_string($id).'"';
  }
  if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
      $results[$row['id']] = $row;
    }
    $result->free_result();
  }
  return $results;
}

function add_commute_location($address, $nickname){
  global $mysqli;
  global $ini_array;

  // Get location with Google Maps geocoding
  $google_url = 'https://maps.googleapis.com/maps/api/geocode/json?key='.$ini_array['gmap_api_key'].'&address='.urlencode($address);
  $results = @json_decode(@file_get_contents($google_url));
  if(!isset($results->results)) return array("status"=>"error", "msg" => "Error: Empty result for search $address", "data"=>$results);
  if(count($results->results) == 0) return array("status"=>"error", "msg" => "Error: Empty result for search $address", "data"=>$results);
  $lat = $results->results[0]->geometry->location->lat;
  $lng = $results->results[0]->geometry->location->lng;

  // Insert
  $sql = '
    INSERT INTO `commute_locations`
    SET `nickname` = "'.$mysqli->real_escape_string($nickname).'",
        `address` = "'.$mysqli->real_escape_string($address).'",
        `max_time` = "3600",
        `lat` = "'.$mysqli->real_escape_string($lat).'",
        `lng` = "'.$mysqli->real_escape_string($lng).'"
  ';
  if(!$mysqli->query($sql)) return array('status' => 'error', 'error_msg' => $mysqli->error);

  $new_commute_id = $mysqli->insert_id;

  // Fetch commute times
  $commute_times = update_commute_times(false, $new_commute_id);
  if($commute_times['status'] != 'success') return $commute_times;
  else return array('status' => 'success', 'msg' => 'Added new commute '.$nickname.', with ID '.$new_commute_id, 'new_commute_id' => $new_commute_id, 'commute_times' => $commute_times);

}

function update_commute_time($id, $max_time){
  global $mysqli;

  // Delete existing commute location if it existed
  $sql = '
    UPDATE `commute_locations`
    SET `max_time` = "'.$mysqli->real_escape_string($max_time).'"
    WHERE `id` = "'.$mysqli->real_escape_string($id).'"';

  if(!$mysqli->query($sql)) return array('status' => 'error', 'error_msg' => $mysqli->error);

  $new_commute_id = $mysqli->insert_id;

  // Fetch commute times
  $commute_times = update_commute_times(false, $id);
  if($commute_times['status'] != 'success') return $commute_times;
  else return array('status' => 'success', 'msg' => 'Updated time for commute ID '.$id, 'commute_times' => $commute_times);


}

function delete_commute_location($id){
  global $mysqli;
  $sql = 'DELETE FROM `commute_locations` WHERE `id` = "'.$mysqli->real_escape_string($id).'"';
  $mysqli->query($sql);
}


/////////
// CALLED DIRECTLY - API usage
/////////
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {

  require_once('_common_api.php');

  // Save a new commute location
  if(isset($postdata['add_address']) && isset($postdata['nickname'])){
    echo json_encode( add_commute_location($postdata['add_address'], $postdata['nickname']), JSON_PRETTY_PRINT);
  }
  // Update a commute max-time
  else if(isset($postdata['id']) && isset($postdata['max_time'])){
    echo json_encode( update_commute_time($postdata['id'], $postdata['max_time']), JSON_PRETTY_PRINT);
  }
  // Delete a commute location
  else if(isset($postdata['delete'])){
    echo json_encode( delete_commute_location($postdata['delete']), JSON_PRETTY_PRINT);
  }
  // No action - return all commute locations
  else {
    $commute_id = false;
    if(isset($_REQUEST['id'])) $commute_id = $_REQUEST['id'];
    echo json_encode(get_commute_locations($commute_id), JSON_PRETTY_PRINT);
  }

}
