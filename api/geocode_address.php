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
  $address_str = false;
  $house_address = false;
  $sql = 'SELECT `address`,`streetAddress`,`addressLocality`,`postalCode` FROM `house_details` WHERE `id` = "'.$mysqli->real_escape_string($house_id).'"';
  if ($result = $mysqli->query($sql)) {
    while($house_address = $result->fetch_object()){
      // Main street address
      if(!is_null($house_address->streetAddress) && strlen(trim($house_address->streetAddress)) > 0){
        $address_str = $house_address->streetAddress;
        // Post code
        if(!is_null($house_address->postalCode) && strlen(trim($house_address->postalCode)) > 0){
          $address_str .= ', '.$house_address->postalCode;
        }
        // Locality if no post code
        else if(!is_null($house_address->addressLocality) && strlen(trim($house_address->addressLocality)) > 0){
          $address_str .= ', '.$house_address->addressLocality;
        }
      }
    }
    $result->free_result();
  }

  return geocode_address($address_str, $house_id);
}

function geocode_address($address, $house_id=false){
  global $ini_array;
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

  if($address){
    $google_url = 'https://maps.googleapis.com/maps/api/geocode/json?key='.$ini_array['gmap_api_key'].'&address='.urlencode($address);
    $results = @json_decode(@file_get_contents($google_url));
    if(isset($results->results)){
      $err_address = '<code>'.$address.'</code>';
      if($house_id){
        $err_address = '<a href="https://www.hemnet.se/bostad/'.$house_id.'" target="_blank">"'.$address.'"</a>';
      }
      if(count($results->results) == 0){
        return array("status"=>"error", "msg" => 'Error: Empty result for search '.$err_address.'<!-- '.$google_url."\n".print_r($results, true).' -->');
      }

      $loc['lat'] = $results->results[0]->geometry->location->lat;
      $loc['lng'] = $results->results[0]->geometry->location->lng;
      $loc['location_type'] = $results->results[0]->geometry->location_type;
    }

    // Save to the database
    if($loc['lat'] && $loc['lng']){
      $sql = '
        INSERT INTO `geocoding_results` SET
          `address` = "'.$mysqli->real_escape_string($address).'",
          `lat` = "'.$mysqli->real_escape_string($loc['lat']).'",
          `lng` = "'.$mysqli->real_escape_string($loc['lng']).'",
          `location_type` = "'.$mysqli->real_escape_string($loc['location_type']).'"
      ';
      if($house_id){
        $sql .= ', `house_id` = "'.$mysqli->real_escape_string($house_id).'"';
      }
      $mysqli->query($sql);
    }
  }

  return $loc;
}


function fix_geocode_result($house_id, $lat, $lng){
  global $mysqli;

  // Delete any existing entries that we have in the database
  $sql = 'DELETE FROM `geocoding_results` WHERE `house_id` = "'.$mysqli->real_escape_string($house_id).'"';
  $mysqli->query($sql);

  // Insert manually fixed lat/lng
  $sql = '
    INSERT INTO `geocoding_results`
    SET
      `lat` = "'.$mysqli->real_escape_string($lat).'",
      `lng` = "'.$mysqli->real_escape_string($lng).'",
      `location_type` = "MANUAL",
      `house_id` = "'.$mysqli->real_escape_string($house_id).'"
  ';
  $mysqli->query($sql);
}



/////////
// CALLED DIRECTLY - API usage
/////////
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {

  require_once('_common_api.php');

  // Geocode a house - get address from database and save results to DB
  if(isset($postdata['id'])){

    if(isset($postdata['fix_lat']) && isset($postdata['fix_lng'])){
      fix_geocode_result($postdata['id'], $postdata['fix_lat'], $postdata['fix_lng']);
      die(json_encode(array("status"=>"success", "msg" => "Fixed geocode")));
    }

    // Call the function
    $loc = geocode_house_address($postdata['id']);

    // Return success message
    echo json_encode(array("status"=>"success", "msg" => "Found house geocode", "lat" => $loc['lat'], "lng" => $loc['lng']), JSON_PRETTY_PRINT);

  } else if(isset($postdata['address'])){

    // Call the function
    $loc = geocode_address($postdata['address']);

    // Return success message
    echo json_encode(array("status"=>"success", "msg" => "Found geocode", "lat" => $loc['lat'], "lng" => $loc['lng']), JSON_PRETTY_PRINT);

  } else {
    echo json_encode(array("status"=>"error", "msg" => "Error: No input supplied"), JSON_PRETTY_PRINT);
  }

}
