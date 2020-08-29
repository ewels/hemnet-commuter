<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Scrape a Hemnet house HTML page
 */

function scrape_hemnet_house($house_id){
  global $mysqli;

  $address = false;
  $data_layer = false;
  $front_image = false;
  $days_on_hemnet = false;

  // Get house URL
  $hn_url = false;
  $sql = 'SELECT `url` FROM `search_result_urls` WHERE `id` = "'.$mysqli->real_escape_string($house_id).'"';
  if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_row()) {
      $hn_url = $row[0];
    }
    $result->free_result();
  }

  if(!$hn_url) return false;
  $html_page = file_get_contents($hn_url);

  // Find details from javascript chunk in HTML
  preg_match('/dataLayer = ([^;]+);/', $html_page, $matches);
  if($matches){
    $data_layer_json = json_decode($matches[1]);
    if(isset($data_layer_json[1]) && isset($data_layer_json[1]->property)){
      $data_layer = $data_layer_json[1]->property;
    }
  }

  // Parse all JSON schemas
  preg_match_all('/<script type="application\/ld\+json">(?P<json>(.|\n)*?)<\/script>/', $html_page, $matches);
  if($matches && isset($matches['json'])){
    foreach($matches['json'] as $json_str){
      // Fix broken hemnet JSON
      $json_str = preg_replace('/"postalCode"\s*:\s*\n/', '"postalCode": ""', $json_str);
      // Try to parse JSON
      $schema = json_decode($json_str);
      // Property address
      if(isset($schema->{'@type'}) && $schema->{'@type'} == "Place" && isset($schema->address)){
        $address = $schema->address;
      }
    }
  }

  // Get front image
  preg_match('/<meta property="og:image" content="([^"]+)">/', $html_page, $matches);
  if($matches){
    $front_image = $matches[1];
  }

  // Get days on hemnet
  preg_match_all('/<div class="property-visits-counter__row-label">(.+?<\/div>.+?)<\/div>/s', $html_page, $matches);
  if($matches){
    foreach($matches as $match){
      if(isset($match[1]) && strpos($match[1], 'Dagar på Hemnet')){
        preg_match('/<div class="property-visits-counter__row-value">(.+?)<\/div>/s', $match[1], $nested_matches);
        if($nested_matches){
          $days_on_hemnet = trim($nested_matches[1]);
        }
      }
    }
  }

  // Build the SQL insert statements
  $sql_fields = [];
  if($address) $sql_fields['address'] = json_encode($address);
  if($data_layer) $sql_fields['data_layer'] = json_encode($data_layer);
  if($front_image) $sql_fields['front_image'] = $front_image;
  if($days_on_hemnet) $sql_fields['days_on_hemnet'] = $days_on_hemnet;

  if(isset($address->streetAddress)) $sql_fields['streetAddress'] = $address->streetAddress;
  if(isset($address->addressLocality)) $sql_fields['addressLocality'] = $address->addressLocality;
  if(isset($address->postalCode)) $sql_fields['postalCode'] = $address->postalCode;

  $data_layer_fields = [
    'new_production',
    'offers_selling_price',
    'status',
    'housing_form',
    'tenure',
    'rooms',
    'living_area',
    'supplemental_area',
    'land_area',
    'driftkostnad',
    'price',
    'has_price_change',
    'upcoming_open_houses',
    'bidding',
    'publication_date',
    'construction_year',
    'listing_package_type',
    'water_distance'
  ];

  foreach($data_layer_fields as $field){
    if(isset($data_layer->{$field})) $sql_fields[$field] = $data_layer->{$field};
  }

  if(count($sql_fields) == 0) return false;

  // Delete any existing entries that we have in the database
  $sql = 'DELETE FROM `house_details` WHERE `id` = "'.$mysqli->real_escape_string($house_id).'"';
  $mysqli->query($sql);

  // Save to the database
  $sql = 'INSERT INTO `house_details` SET `id` = "'.$mysqli->real_escape_string($house_id).'"';
  foreach($sql_fields as $field => $var){
    $sql .= ", `$field` = '".$mysqli->real_escape_string($var)."'";
  }
  $mysqli->query($sql);

  return array(
    'front_image' => $front_image,
    'days_on_hemnet' => $days_on_hemnet,
    'address' => $address,
    'data_layer' => $data_layer,
  );
}

/////////
// CALLED DIRECTLY - API usage
/////////
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {

  if(isset($_REQUEST['id'])){

    require_once('_common_api.php');

    // Call the function
    $results = scrape_hemnet_house($_REQUEST['id']);

    // Return success message
    echo json_encode(array("status"=>"success", "msg" => "Found house details", "results" => $results), JSON_PRETTY_PRINT);


  } else {
    echo json_encode(array("status"=>"error", "msg" => "Error: No input supplied"));
  }

}
