<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Common API setup
 */

// Send headers for JSON output
header("Content-type: text/json; charset=utf-8");

// Enable all the logging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get secrets from the config file
$ini_array = parse_ini_file("../config.ini");

// Connect to the database
$mysqli = new mysqli("localhost", $ini_array['db_user'], $ini_array['db_password'], $ini_array['db_name']);
if ($mysqli->connect_errno) {
  die("Failed to connect to MySQL: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8");

// Prep the weird-looking AngularJS POST data
$postdata = json_decode(file_get_contents("php://input"), true);
if(is_null($postdata)) $postdata = [];
$postdata = array_merge($_GET, $postdata);
