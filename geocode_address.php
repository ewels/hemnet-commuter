<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Geocode an address using the Google Maps API
 */

function geocode_house_address($house_id){
  global $mysqli;

  // Get house address
  $house_address = false;
  $sql = 'SELECT `address`,`streetAddress`,`addressLocality`,`postalCode` FROM `house_details` WHERE `id` = "'.$mysqli->real_escape_string($house_id).'"';
  if ($result = $mysqli->query($sql)) {
    $house_address = $result->fetch_object();
    $result->free_result();
  }

  $address_str = false;
  if(!is_null($house_address->streetAddress) && !is_null($house_address->postalCode)){
    $address_str = $house_address->streetAddress.", ".$house_address->postalCode;
  }

  if(!$address_str){
    die(json_encode(array("status"=>"error", "msg" => "Could not find house address")));
  }

  return geocode_address($address_str, $house_id);
}

function geocode_address($address, $house_id=false){
  global $mysqli;
  $loc = array("lat" => false, "lng" => false);

  // Check if we already have this one
  $sql = 'SELECT `lat`,`lng` FROM `geocoding_results` WHERE `address` = "'.$mysqli->real_escape_string($address).'"';
  if($house_id){
    $sql = 'SELECT `lat`,`lng` FROM `geocoding_results` WHERE `house_id` = "'.$mysqli->real_escape_string($house_id).'"';
  }
  if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_row()) {
      $loc = array("lat" => $row[0], "lng" => $row[1]);
    }
    $result->free_result();
    if($loc['lat'] && $loc['lng']){
      return $loc;
    }
  }

  $ini_array = parse_ini_file("hemnet_commuter_config.ini");

  $google_url = 'https://maps.googleapis.com/maps/api/geocode/json?key='.$ini_array['gmap_api_key'].'&address='.urlencode($address);
  $results = json_decode(file_get_contents($google_url));
  if(isset($results->results)){
    $loc['lat'] = $results->results[0]->geometry->location->lat;
    $loc['lng'] = $results->results[0]->geometry->location->lng;
  }

  // Save to the database
  if($loc['lat'] && $loc['lng']){
    $sql = '
      INSERT INTO `geocoding_results` SET
        `address` = "'.$mysqli->real_escape_string($address).'",
        `lat` = "'.$mysqli->real_escape_string($loc['lat']).'",
        `lng` = "'.$mysqli->real_escape_string($loc['lng']).'"
    ';
    if($house_id){
      $sql .= ', `house_id` = "'.$mysqli->real_escape_string($house_id).'"';
    }
    $mysqli->query($sql);
  }

  return $loc;
}


function fix_geocode_result($house_id, $lat, $lng){
  global $mysqli;
  $sql = '
  UPDATE `geocoding_results`
    SET
      `lat` = "'.$mysqli->real_escape_string($lat).'",
      `lng` = "'.$mysqli->real_escape_string($lng).'"
    WHERE
      `house_id` = "'.$mysqli->real_escape_string($house_id).'"
  ';
  $mysqli->query($sql);
}



/////////
// CALLED DIRECTLY - API usage
/////////
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {

  // Connect to the database
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);

  $ini_array = parse_ini_file("hemnet_commuter_config.ini");

  $mysqli = new mysqli("localhost", $ini_array['db_user'], $ini_array['db_password'], $ini_array['db_name']);
  if ($mysqli->connect_errno) {
    die("Failed to connect to MySQL: " . $mysqli->connect_error);
  }

  // Geocode a house - get address from database and save results to DB
  if(isset($_REQUEST['id'])){

    if(isset($_REQUEST['fix_lat']) && isset($_REQUEST['fix_lng'])){
      fix_geocode_result($_REQUEST['id'], $_REQUEST['fix_lat'], $_REQUEST['fix_lng']);
      die(json_encode(array("status"=>"success", "msg" => "Fixed geocode")));
    }

    // Call the function
    $loc = geocode_house_address($_REQUEST['id']);

    // Return success message
    echo json_encode(array("status"=>"success", "msg" => "Found house geocode", "lat" => $loc['lat'], "lng" => $loc['lng']));

  } else if(isset($_REQUEST['address'])){

    // Call the function
    $loc = geocode_address($_REQUEST['address']);

    // Return success message
    echo json_encode(array("status"=>"success", "msg" => "Found geocode", "lat" => $loc['lat'], "lng" => $loc['lng']));

  } else {
    header("Content-type: text/json; charset=utf-8");
    echo json_encode(array("status"=>"error", "msg" => "Error: No input supplied"));
  }

}
