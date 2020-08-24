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
} else {
  header("Content-type: text/json; charset=utf-8");
  echo json_encode(array("status"=>"error", "msg" => "Error: No input supplied"));
}
