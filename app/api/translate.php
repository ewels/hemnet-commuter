<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Translate description text
 */

function translate_text($query, $db_only=false){

  global $mysqli;
  global $ini_array;

  // Sanity check
  if(!$query or strlen($query) == 0){
    return array("status"=>"error", "msg" => "Empty text input");
  }
  if(!isset($ini_array['translate_target_language']) or strlen($ini_array['translate_target_language']) == 0){
    return array("status"=>"error", "msg" => "Target language not set");
  }

  // Try to find existing translation in the DB
  $sql = 'SELECT `translatedText` FROM `translations` WHERE `query` = "'.$mysqli->real_escape_string($query).'"';
  if ($db_result = $mysqli->query($sql)) {
    while ($row = $db_result->fetch_assoc()) {
      return array("status"=>"success", "translatedText" => $row['translatedText'], "method" => "database");
    }
    $db_result->free_result();
  }

  // No results - fetch from Google API
  $api_result = fetch_google_transation($query);

  // Save to DB if it worked
  if($api_result['status'] == 'success'){
    // Insert
    $sql = '
      INSERT INTO `translations`
      SET `query` = "'.$mysqli->real_escape_string($query).'",
          `translatedText` = "'.$mysqli->real_escape_string($api_result['translatedText']).'",
          `created` = "'.time().'"
    ';
    if(!$mysqli->query($sql)) return array('status' => 'error', 'error_msg' => $mysqli->error);
  }

  return $api_result;
}


function fetch_google_transation($query){

  global $ini_array;

  if(!$query or strlen($query) == 0){
    return array("status"=>"error", "msg" => "Empty text input");
  }
  if(!isset($ini_array['translate_target_language']) or strlen($ini_array['translate_target_language']) == 0){
    return array("status"=>"error", "msg" => "Target language not set");
  }

  // Get results
  $google_url = 'https://translation.googleapis.com/language/translate/v2?q='.urlencode($query).'&format=text&source=sv&target='.$ini_array['translate_target_language'].'&key='.$ini_array['gmap_api_key'];
  $api_result_raw = @file_get_contents($google_url);
  $api_result = @json_decode($api_result_raw);
  $header_parts = explode(' ', $http_response_header[0]);
  if($header_parts[1] != '200'){
    return array("status"=>"error", "msg" => "Error with google API result", "results_raw" => $api_result_raw, "results" => $api_result);
  }

  $translatedText = $api_result->data->translations[0]->translatedText;

  return array("status"=>"success", "translatedText" => $translatedText, "method" => "api");
}


/////////
// CALLED DIRECTLY - API usage
/////////
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {

  require_once('_common_api.php');

  $db_only = isset($postdata['db_only']) && $postdata['db_only'] ? true : false;

  echo json_encode(translate_text($postdata['query'], $db_only), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

}
