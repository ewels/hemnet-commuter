<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Mirror Hemnet RSS XML to remove stupid 'Access-Control-Allow-Origin' headers
 */

if(isset($_POST['s_id']) ){
  $xml = file_get_contents(sprintf('https://www.hemnet.se/mitt_hemnet/sparade_sokningar/%d.xml', $_POST['s_id']));
  $data = simplexml_load_string($xml) or die(json_encode(array("status"=>"error", "msg" => "Error: Cannot parse RSS. Is the URL correct?")));
  header("Content-type: text/json; charset=utf-8");
  echo json_encode($data->channel);
} elseif(isset($_POST['hnurl'])){
  header("Content-type: text/html; charset=utf-8");
  echo file_get_contents($_POST['hnurl']);
} elseif(isset($_POST['gmaps'])){
  header("Content-type: text/json; charset=utf-8");
  echo file_get_contents($_POST['gmaps']);
} else {
  header("Content-type: text/json; charset=utf-8");
  echo json_encode(array("status"=>"error", "msg" => "Error: No input supplied"));
}