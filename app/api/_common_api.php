<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Common API setup
 */

// Send headers for JSON output
if(!isset($not_json) || $not_json = false){
  header("Content-type: text/json; charset=utf-8");
}

// Check that the config.ini file exists
$ini_fn = dirname(__DIR__)."/config.ini";
if(!file_exists($ini_fn)){
  $ini_fn = dirname(__DIR__)."/config_defaults.ini";
}
if(!file_exists($ini_fn)){
  echo json_encode(array(
    'status' => 'error',
    'msg' => 'No config file found! Should be at "app/config.ini" - see README.md'
  ), JSON_PRETTY_PRINT);
  die;
}

// Enable all the logging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('html_errors', 0);
error_reporting(E_ALL);

// Get secrets from the config file
$ini_array = parse_ini_file($ini_fn);

// Connect to the database
$mysqli = @new mysqli("localhost", $ini_array['db_user'], $ini_array['db_password'], $ini_array['db_name']);
if ($mysqli->connect_errno) {
  echo json_encode(array(
    'status' => 'error',
    'msg' => 'Failed to connect to MySQL: '.$mysqli->connect_error
  ), JSON_PRETTY_PRINT);
  die;
}
$mysqli->set_charset("utf8");

// Prep the weird-looking AngularJS POST data
$postdata = json_decode(file_get_contents("php://input"), true);
if(is_null($postdata)) $postdata = [];
$postdata = array_merge($_GET, $postdata);
