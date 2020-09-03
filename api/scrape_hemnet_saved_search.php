<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Scrape the Hemnet "Saved Search" HTML page
 */

// Scrape hemnet
function scrape_hemnet_search($search_id){
  global $mysqli;

  $house_urls = [];
  $numberOfItems = false;
  $page_number = 1;
  $max_pages = 20;
  while($numberOfItems === false || count($house_urls) < $numberOfItems){
    $hn_url = "https://www.hemnet.se/bostader?subscription=".$search_id."&page=".$page_number;
    $html_page = @file_get_contents($hn_url);
    if($html_page === FALSE){ break; }
    preg_match_all('/<script type="application\/ld\+json">(?P<json>[^<]+)<\/script>/', $html_page, $matches);
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

  return $house_urls;
}

// Truncate the house search table
function scrape_hemnet_search_table(){
  global $mysqli;
  $sql = 'TRUNCATE TABLE `search_result_urls`';
  $mysqli->query($sql);
}



/////////
// CALLED DIRECTLY - API usage
/////////
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {

  require_once('_common_api.php');

  // Truncate the house search results table if requested
  if(isset($_REQUEST['truncate']) && $_REQUEST['truncate'] == 'true'){
    scrape_hemnet_search_table();
  }

  // Scrape hemnet
  if(isset($_REQUEST['s_page_id'])){

    $house_urls = scrape_hemnet_search($_REQUEST['s_page_id']);

    // Return success message
    echo json_encode(array("status"=>"success", "msg" => "Found ".count($house_urls)." houses", "num_houses_found" => count($house_urls)), JSON_PRETTY_PRINT);


  } else {
    echo json_encode(array("status"=>"error", "msg" => "Error: No input supplied"));
  }
}
