<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Update and return house ratings
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$ini_array = parse_ini_file("hemnet_commuter_config.ini");

$mysqli = new mysqli("localhost", $ini_array['db_user'], $ini_array['db_password'], $ini_array['db_name']);
if ($mysqli->connect_errno) {
  die("Failed to connect to MySQL: " . $mysqli->connect_error);
}

// Return ratings results
if(isset($_GET['house_id']) && strlen($_GET['house_id']) > 0){
  $sql = 'SELECT `ratings` FROM `ratings` WHERE `house_id` = "'.$mysqli->real_escape_string($_GET['house_id']).'"';
  if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_row()) {
      echo $row[0];
    }
    $result->free_result();
  }
}

// Save ratings results
if(isset($_POST['house_id']) && strlen($_POST['house_id']) > 0){

  $sql = 'DELETE FROM `ratings` WHERE `house_id` = "'.$mysqli->real_escape_string($_POST['house_id']).'"';
  $mysqli->query($sql);

  $sql = '
    INSERT INTO `ratings`
    SET `house_id` = "'.$mysqli->real_escape_string($_POST['house_id']).'",
        `ratings` = "'.$mysqli->real_escape_string($_POST['ratings']).'"
  ';

  if (!$mysqli->query($sql)) {
    if($mysqli->affected_rows < 0)
      print_r($mysqli->error_list);
    else
      printf("%d Row inserted.\n", $mysqli->affected_rows);
  }
}

$mysqli->close();
