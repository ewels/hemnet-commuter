<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Use the Hemnet GraphQL API to pull house details from "Saved Searches"
 */

// Fetch search results from Hemnet
function fetch_hemnet_houses(){
  global $mysqli;

  // Get the saved-search details from the DB
  require_once('saved_searches.php');
  $saved_searches = fetch_saved_searches();
  if($saved_searches['status'] !== 'success'){
    return array("status"=>"error", "msg" => "Could not fetch saved searches!", "saved_searches" => $saved_searches);
  }
  if(count($saved_searches['saved_searches']) == 0){
    return array("status"=>"no_saved_searches", "msg" => "No saved search IDs found! Please add some in the 'Map' panel.");
  }

  $houses = [];

  $str_keys = [
    'id',
    'title',
    'type'
  ];
  $bool_keys = [
    'isUpcoming',
    'isNewConstruction',
    'isForeclosure',
    'isBiddingOngoing',
  ];
  $num_keys = [
    'livingArea',
    'landArea',
    'supplementalArea',
    'daysOnHemnet',
    'numberOfRooms'
  ];

  // Kick off API calls for each saved search
  foreach($saved_searches['saved_searches'] as $saved_search){

    $api_limit = 200;
    $api_offset = 0;
    $num_results = false;

    // We can't fetch all results in one query, so we need to loop through the offset pages
    while($num_results === false || $api_offset < $num_results){

      $postdata = array(
        'query' => graphql_iOSSearchQuery(),
        'variables' => array(
          "sort" => "NEWEST",
          "limit" => $api_limit,
          "offset" => $api_offset,
        )
      );

      $postdata['variables']['searchInput'] = array();
      // We need this, otherwise we get all of Sweden
      $postdata['variables']['searchInput']['locationIds'] = [];

      // Build query from saved search metadata
      foreach($saved_search['search'] as $k => $v){
        // Empty fields
        if(is_null($v)) continue;
        if(is_array($v) && count($v) == 0) continue;
        if(is_string($v) && strlen($v) == 0) continue;
        // Location IDs
        if($k == 'locations'){
          foreach($v as $loc){
            $postdata['variables']['searchInput']['locationIds'][] = $loc['id'];
          }
          continue;
        }
        // Other fields
        $postdata['variables']['searchInput'][$k] = $v;
      }

      // Build POST API call using cURL
      $api_url = 'https://www.hemnet.se/graphql';
      $curl = curl_init($api_url);
      curl_setopt($curl, CURLOPT_HEADER, false);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'Content-type: application/json',
        'Accept: */*'
      ));
      curl_setopt($curl, CURLOPT_POST, true);
      curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postdata));

      // Fetch cURL API response
      $result_raw = curl_exec($curl);
      $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
      if($status != 200){
        return array(
          'status'=>'error',
          'msg' => 'Hemnet GraphQL API call failed',
          'curl_status' => $status,
          'curl_error' => curl_error($curl),
          'curl_errno' => curl_errno($curl),
          'url' => $api_url,
          'result' => @json_decode($result_raw, true),
          'result_raw' => $result_raw
        );
      }
      curl_close($curl);

      // Parse the response
      $results_json = @json_decode($result_raw, true);
      $num_results = $results_json['data']['searchListings']['total'];
      
      $all_lat_lng = [];

      foreach($results_json['data']['searchListings']['listings'] as $listing){

        $this_house = [];

        foreach($str_keys as $k){
          if(!array_key_exists($k, $listing)) {
            $this_house[$k] = '';
            continue;
          }
          $this_house[$k] = @$listing[$k];
        }
        foreach($bool_keys as $k){
          if(!array_key_exists($k, $listing)) {
            $this_house[$k] = 0;
            continue;
          }
          $this_house[$k] = @$listing[$k] ? 1 : 0;
        }
        foreach($num_keys as $k){
          if(!array_key_exists($k, $listing)) {
            $this_house[$k] = 0;
            continue;
          }
          $this_house[$k] = is_null($listing[$k]) ? 0 : $listing[$k];
        }

        // Array keys two-deep
        $this_house['lat'] = $listing['coordinates']['lat'];
        $this_house['lng'] = $listing['coordinates']['long'];

        // Check if the coordinates already exist in the $houses array
        $lat_lng_key = $this_house['lat'] . ',' . $this_house['lng'];
        if (isset($all_lat_lng[$lat_lng_key])) {
            // Coordinates already exist, adjust them slightly to move the house 5 meters away

            $newCoordinates = moveHouseCoordinates($this_house['lat'], $this_house['lng'], 5); // 5 meters

            $this_house['lat'] = $newCoordinates['lat'];
            $this_house['lng'] = $newCoordinates['lng'];
        }
        $lat_lng_key = $this_house['lat'] . ',' . $this_house['lng'];  // Update key with possibly new coordinates
        $all_lat_lng[$lat_lng_key] = $this_house;


        $this_house['askingPrice'] = 0;
        if(array_key_exists('askingPrice', $listing) && !is_null($listing['askingPrice'])) $this_house['askingPrice'] = $listing['askingPrice']['amount'];
        $this_house['runningCosts'] = 0;
        if(array_key_exists('runningCosts', $listing) && !is_null($listing['runningCosts'])) $this_house['runningCosts'] = $listing['runningCosts']['amount'];
        if(array_key_exists('thumbnail', $listing) && !is_null($listing['thumbnail'])) $this_house['image_url'] = $listing['thumbnail']['itemGalleryLarge'];

        // Closest visning date
        $this_house['nextOpenHouse'] = null;
        $upcomingOpenHouses = [];
        if(array_key_exists('upcomingOpenHouses', $listing)){
          foreach($listing['upcomingOpenHouses'] as $oh){
            $timestamp = floatval($oh['start']);
            if(is_null($this_house['nextOpenHouse'])){
              $this_house['nextOpenHouse'] = $timestamp;
            } else {
              $this_house['nextOpenHouse'] = min($timestamp, $this_house['nextOpenHouse']);
            }
            $upcomingOpenHouses[] = $timestamp;
          }
        }
        $this_house['upcomingOpenHouses'] = implode(',', $upcomingOpenHouses);
        $this_house['size_total'] = $this_house['livingArea'] + $this_house['supplementalArea'];
        $this_house['created'] = time();

        // Assoc ID with key as house ID in case a house comes up more than once in different searches, prevents duplication
        $houses[$this_house['id']] = $this_house;

      }

      // Push up the offset for the next loop
      $api_offset += $api_limit;
    }
  }

  if(count($houses) == 0) return array("status"=>"error", "msg" => "No houses found!");

  // Wipe the database table
  $sql = 'TRUNCATE TABLE `houses`';
  if(!$mysqli->query($sql)) return array("status"=>"error", "msg" => "Could not truncate houses table:<br>$sql<br>".$mysqli->error);

  // Save each house to the db
  foreach($houses as $id => $house){
    // Remove null values
    $house = array_filter($house, function($value) { return !is_null($value); });

    // Prep SQL statement
    $sql = '
      INSERT INTO `houses` (`'.implode('`, `', array_keys($house)).'`)
      VALUES ("'.implode('", "', $house).'")';
    if(!$mysqli->query($sql)) return array("status"=>"error", "msg" => "Could not insert house $id into DB!", "sql" => $sql, "sql_error" => $mysqli->error, "house" => $house);
  }

  return array("status"=>"success", "msg" => "Found ".count($houses)." houses");
}

/**
 * Function to move coordinates a specified distance in meters
 * @param float $lat Latitude of the original location
 * @param float $lng Longitude of the original location
 * @param float $distance Distance in meters to move
 * @return array New latitude and longitude
 */
function moveHouseCoordinates($lat, $lng, $distance) {
  // Earth’s radius, sphere
  $earthRadius = 6378137; // Radius in meters

  // Coordinate offsets in radians
  $dLat = $distance / $earthRadius;
  $dLng = $distance / ($earthRadius * cos(pi() * $lat / 180));

  // OffsetPosition, decimal degrees
  $newLat = $lat + ($dLat * 180 / pi());
  $newLng = $lng + ($dLng * 180 / pi());

  return ['lat' => $newLat, 'lng' => $newLng];
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



function graphql_iOSSearchQuery(){
    // This query returns as few fields as possible - only the stuff that we may want to filter on in the DB
    // Everything else can be fetched when a single house is clicked on in the map.
    return '
      query iOSSearchQuery($searchInput: ListingSearchInput!, $limit: Int!, $offset: Int, $sort: ListingSearchSorting) {
        searchListings(search: $searchInput, limit: $limit, offset: $offset, sort: $sort) {
          total
          offset
          limit
          listings {
            ...PartialPropertyListingFragment
          }
        }
      }

      fragment MoneyFragment on Money {
        amount
      }

      fragment PartialPropertyListingFragment on PropertyListing {
        id
        title
        type: primaryTypeGroup
        isUpcoming

        coordinates {
          lat
          long
        }

        ... on ProjectUnit {
          askingPrice {
            ...MoneyFragment
          }
          runningCosts {
            ...MoneyFragment
          }

          livingArea
          landArea
          supplementalArea
          daysOnHemnet
          isBiddingOngoing
          numberOfRooms

          thumbnail {
            ...ThumbnailFragment
          }

          upcomingOpenHouses {
            start
          }
        }

        ... on ActivePropertyListing {
          askingPrice {
            ...MoneyFragment
          }
          runningCosts {
            ...MoneyFragment
          }

          livingArea
          landArea
          supplementalArea
          daysOnHemnet
          isNewConstruction
          isForeclosure
          isBiddingOngoing
          numberOfRooms

          thumbnail {
            ...ThumbnailFragment
          }

          upcomingOpenHouses {
            start
          }
        }
      }

      fragment ThumbnailFragment on ListingImage {
        itemGalleryLarge: url(format: ITEMGALLERY_L)
      }
    ';
}