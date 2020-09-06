<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Scrape the Hemnet "Saved Search" pagesu / JSON
 */

// Fetch search results from Hemnet
function fetch_hemnet_houses($search_id = false){
  global $mysqli;

  $search_ids = [];
  // Search IDs are supplied
  if($search_id){
    $search_ids = explode(",", $search_id);
    $search_ids = array_map("trim",$search_ids);
  }
  // Fetch search IDs from the database
  else {
    $sql = 'SELECT `search_id` FROM `saved_searches`';
    if ($result = $mysqli->query($sql)) {
      while ($row = $result->fetch_row()) {
        $search_ids[] = $row[0];
      }
      $result->free_result();
    }
  }
  if(count($search_ids) == 0) return array("status"=>"error", "msg" => "No saved search IDs found");

  $houses = [];
  foreach($search_ids as $search_id){

    //// Fetch search key from web page
    // We could do this once and save it in the database, but it changes if anything in the
    // saved search is changed on Hemnet. So to be safe, we fetch it every time.
    $search_key = false;
    $hn_url = "https://www.hemnet.se/bostader?subscription=$search_id";
    $html_page = @file_get_contents($hn_url);
    if($html_page === FALSE) return array("status"=>"error", "msg" => "Could not fetch search page: $hn_url");
    preg_match('/&quot;search_key&quot;:&quot;([\w]+)&quot;/', $html_page, $matches);
    if($matches){
      $search_key = $matches[1];
    }
    if(!$search_key) return array("status"=>"error", "msg" => "Could not find search_key: $hn_url");
    if(strlen($search_key) < 20) return array("status"=>"error", "msg" => "Search key looks wrong: $search_key (from $hn_url)");

    // Load the hemnet JSON
    $hn_json_url = "https://www.hemnet.se/bostader/search/$search_key";
    $json_raw = @file_get_contents($hn_json_url);
    if($json_raw === FALSE) return array("status"=>"error", "msg" => "Could not fetch hemnet JSON: $hn_json_url <br>".print_r(error_get_last(), true));
    $hn_results = @json_decode($json_raw, true);
    if(is_null($hn_results)) return array("status"=>"error", "msg" => "Could not decode hemnet JSON: $hn_json_url <br>".print_r(error_get_last(), true));
    if(!isset($hn_results['properties'])) return array("status"=>"error", "msg" => "Properties not found in hemnet JSON: $hn_json_url");

    // Flatten / parse info from each house ready to go into the db
    $str_keys = [
      'id',
      'address',
      'location_name',
      'typeSummary',
      'age',
      'url',
      'locations_string'
    ];
    $boolean_keys = [
      'project',
      'new_construction',
      'ongoing_bidding',
      'foreclosure',
      'upcoming',
      'has_price_change',
      'deactivated_before_open_house',
      'should_display_showings'
    ];
    foreach($hn_results['properties'] as $house){
      $this_house = array();
      foreach($str_keys as $key){
        $this_house[$key] = $house[$key];
      }
      foreach($boolean_keys as $key){
        $this_house[$key] = $house[$key] ? 1 : 0;
      }
      // More complicated ones
      $this_house['lat'] = $house['coordinate'][0];
      $this_house['lng'] = $house['coordinate'][1];
      $this_house['price'] = preg_replace('/[^0-9]/', '', $house['price']);
      $this_house['rooms'] = str_replace(',', '.', preg_replace('/[^0-9,]/', '', $house['rooms']));
      if($this_house['rooms'] == '') $this_house['rooms'] = 0;
      $this_house['land_area'] = preg_replace('/[^0-9]/', '', $house['land_area']);
      if($this_house['land_area'] == '') $this_house['land_area'] = 0;
      $this_house['living_area'] = preg_replace('/[^0-9]/', '', $house['living_space']['table']['living_area']);
      $this_house['supplemental_area'] = preg_replace('/[^0-9]/', '', $house['living_space']['table']['supplemental_area']);
      if($this_house['supplemental_area'] == '') $this_house['supplemental_area'] = 0;
      $this_house['size_total'] = @intval($this_house['living_area']) + @intval($this_house['supplemental_area']);
      $this_house['image_url'] = str_replace('itemgallery_M', 'itemgallery_cut', $house['medium_image_url']);
      $this_house['created'] = time();

      // Assoc ID with key as house ID in case a house comes up more than once, prevents duplication
      $houses[$house['id']] = $this_house;
    }
  }

  if(count($houses) == 0) return array("status"=>"error", "msg" => "No houses found!");

  // Wipe the database table
  $sql = 'TRUNCATE TABLE `houses`';
  if(!$mysqli->query($sql)) return array("status"=>"error", "msg" => "Could not truncate houses table:<br>$sql<br>".$mysqli->error);

  // Save each house to the db
  foreach($houses as $id => $house){
    $sql = '
      INSERT INTO `houses` (`'.implode('`, `', array_keys($house)).'`)
      VALUES ("'.implode('", "', $house).'")';
    if(!$mysqli->query($sql)) return array("status"=>"error", "msg" => "Could not insert house $id into DB:<br>$sql<br>".$mysqli->error);
  }

  return array("status"=>"success", "msg" => "Found ".count($houses)." houses");
}




/////////
// CALLED DIRECTLY - API usage
/////////
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {

  require_once('_common_api.php');

  // Search ID given?
  $search_id = false;
  if(isset($_REQUEST['s_id']) && is_numeric($_REQUEST['s_id'])) $search_id = $_REQUEST['s_id'];

  echo json_encode(fetch_hemnet_houses($search_id), JSON_PRETTY_PRINT);

}
