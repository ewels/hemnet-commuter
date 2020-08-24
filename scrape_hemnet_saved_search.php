<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Scrape the Hemnet "Saved Search" HTML page
 */


// Connect to the database
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$ini_array = parse_ini_file("hemnet_commuter_config.ini");

$mysqli = new mysqli("localhost", $ini_array['db_user'], $ini_array['db_password'], $ini_array['db_name']);
if ($mysqli->connect_errno) {
  die("Failed to connect to MySQL: " . $mysqli->connect_error);
}

// Truncate the house search results table if requested
if(isset($_REQUEST['truncate']) && $_REQUEST['truncate'] == 'true'){
  $sql = 'TRUNCATE TABLE `search_result_urls`';
  $mysqli->query($sql);
}

// Scrape hemnet
if(isset($_REQUEST['s_page_id'])){

  $house_urls = [];
  $numberOfItems = false;
  $page_number = 1;
  $max_pages = 20;
  while($numberOfItems === false || count($house_urls) < $numberOfItems){
    $hn_url = "https://www.hemnet.se/bostader?subscription=".$_REQUEST['s_page_id']."&page=".$page_number;
    $html_page = file_get_contents($hn_url);
    preg_match_all('/<script type="application\/ld\+json">(?P<json>(.|\n)*?)<\/script>/', $html_page, $matches);
    if($matches && isset($matches['json'])){
      foreach($matches['json'] as $json_str){
        $schema = json_decode($json_str);
        if(isset($schema->numberOfItems)) $numberOfItems = $schema->numberOfItems;
        if(isset($schema->itemListElement)){
          foreach($schema->itemListElement as $house){
            if(!in_array($house->url, $house_urls)){
              $house_urls[] = $house->url;
            }
          }
        }
      }
    }
    $page_number++;
    // Safety stop for infinite loop
    if($page_number > $max_pages){
      break;
    }
  }

  // Save to the database
  foreach($house_urls as $url){

    // Get house ID from URL
    preg_match('/(\d+)$/', $url, $matches);
    if(!$matches) continue;
    $house_id = $matches[1];

    $sql = 'INSERT INTO `search_result_urls` SET `id` = "'.$mysqli->real_escape_string($house_id).'", `url` = "'.$mysqli->real_escape_string($url).'"';
    $mysqli->query($sql);
  }

  // Return success message
  echo json_encode(array("status"=>"success", "msg" => "Found ".count($house_urls)." houses", "num_houses_found" => count($house_urls)));


} else {
  header("Content-type: text/json; charset=utf-8");
  echo json_encode(array("status"=>"error", "msg" => "Error: No input supplied"));
}
