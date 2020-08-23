<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Mirror Hemnet RSS XML to remove stupid 'Access-Control-Allow-Origin' headers
 */

if(isset($_REQUEST['s_id']) ){
  $xml = file_get_contents(sprintf('https://www.hemnet.se/mitt_hemnet/sparade_sokningar/%d.xml', $_REQUEST['s_id']));
  $data = simplexml_load_string($xml) or die(json_encode(array("status"=>"error", "msg" => "Error: Cannot parse RSS. Is the URL correct?")));
  header("Content-type: text/json; charset=utf-8");
  echo json_encode($data->channel);
} elseif(isset($_REQUEST['hnurl'])){
  header("Content-type: text/html; charset=utf-8");
  echo file_get_contents($_REQUEST['hnurl']);
} elseif(isset($_REQUEST['gmaps'])){
  header("Content-type: text/json; charset=utf-8");
  echo file_get_contents($_REQUEST['gmaps']);

// Scrape the search web page in one go
} elseif(isset($_REQUEST['s_page_id'])){
  $base_url = "https://www.hemnet.se/bostader?subscription=".$_REQUEST['s_page_id'];
  $page = file_get_contents($base_url);
  preg_match('/<script type="application\/ld\+json">(.|\n)*?<\/script>/', $page, $matches);
  foreach($matches as $match){
    $schema = json_decode($match);
    if(!isset($schema['itemListElement'])){
      echo("Nope");
      echo($match);
      continue;
    }
    print("FOund something!");
  }


} else {
  header("Content-type: text/json; charset=utf-8");
  echo json_encode(array("status"=>"error", "msg" => "Error: No input supplied"));
}
