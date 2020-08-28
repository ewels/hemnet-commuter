<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Update and return users
 */

function get_all_users(){
  global $mysqli;

  $results = [];

  // Get all tags, set as not selected
  $sql = 'SELECT `id`, `name` FROM `users`';
  if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
      $results[$row['id']] = $row['name'];
    }
    $result->free_result();
  }
  return $results;
}

/////////
// CALLED DIRECTLY - API usage
/////////
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {

  header("Content-type: text/json; charset=utf-8");

  // Connect to the database
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);

  $ini_array = parse_ini_file("../hemnet_commuter_config.ini");

  $mysqli = new mysqli("localhost", $ini_array['db_user'], $ini_array['db_password'], $ini_array['db_name']);
  if ($mysqli->connect_errno) {
    die("Failed to connect to MySQL: " . $mysqli->connect_error);
  }

  // Return all users
  echo json_encode(get_users(), JSON_PRETTY_PRINT);

}
