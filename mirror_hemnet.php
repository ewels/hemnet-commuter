<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Mirror Hemnet RSS XML to remove stupid 'Access-Control-Allow-Origin' headers
 */

header("Content-type: text/json; charset=utf-8");
if(isset($_POST['s_id']) ){
  $xml = file_get_contents(sprintf('https://www.hemnet.se/mitt_hemnet/sparade_sokningar/%d.xml', $_POST['s_id']));
  $data = simplexml_load_string($xml) or die(json_encode(array("status"=>"error", "msg" => "Error: Cannot parse RSS. Is the URL correct?")));
  echo json_encode($data->channel);
} elseif(isset($_POST['gmaps'])){
  echo file_get_contents($_POST['gmaps']);
} else {
  echo json_encode(array("status"=>"error", "msg" => "Error: No input supplied"));
}