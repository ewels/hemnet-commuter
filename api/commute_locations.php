<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Update and return commute locations
 */

function get_commute_locations($id=false){

  global $mysqli;

  $results = [];
  $sql = '
    SELECT commute_locations.id, commute_locations.address, commute_locations.max_time, geocoding_results.lat, geocoding_results.lng FROM `commute_locations`
    LEFT JOIN `geocoding_results`
    ON commute_locations.address = geocoding_results.address
  ';
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

function add_commute_location($address, $max_time){
  global $mysqli;

  // Delete existing commute location if it existed
  $sql = 'DELETE FROM `commute_locations` WHERE `address` = "'.$mysqli->real_escape_string($address).'"';
  $mysqli->query($sql);

  // Insert
  $sql = '
    INSERT INTO `commute_locations`
    SET `address` = "'.$mysqli->real_escape_string($address).'",
        `max_time` = "'.$mysqli->real_escape_string($max_time).'"
  ';
  if(!$mysqli->query($sql)){
    return array('status' => 'error', 'error_msg' => $mysqli->error);
  } else {

    $new_id = $mysqli->insert_id;

    // Get the geocoded location - will be saved to the database if it's not already there.
    require_once('geocode_address.php');
    geocode_address($address);

    return get_commute_locations();
  }

}

function update_commute_location($id, $address, $max_time){
  global $mysqli;

  // Delete existing commute location if it existed
  $sql = '
    UPDATE `commute_locations`
    SET `address` = "'.$mysqli->real_escape_string($address).'", `max_time` = "'.$mysqli->real_escape_string($max_time).'"
    WHERE `id` = "'.$mysqli->real_escape_string($id).'"';
  $mysqli->query($sql);

  if(!$mysqli->query($sql)){
    return array('status' => 'error', 'error_msg' => $mysqli->error);
  } else {

    // Get the geocoded location - will be saved to the database if it's not already there.
    require_once('geocode_address.php');
    geocode_address($address);

    return get_commute_locations();
  }

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
  if(isset($postdata['add_address']) && isset($postdata['max_time'])){
    echo json_encode( add_commute_location($postdata['add_address'], $postdata['max_time']), JSON_PRETTY_PRINT);
  }
  // Update a commute location
  else if(isset($postdata['id']) && isset($postdata['update_address']) && isset($postdata['max_time'])){
    echo json_encode( update_commute_location($postdata['id'], $postdata['update_address'], $postdata['max_time']), JSON_PRETTY_PRINT);
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
