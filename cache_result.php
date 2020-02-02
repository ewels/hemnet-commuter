<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$mysqli = new mysqli("localhost", "hemnet_commuter_cache", "hemnet_commuter_cache", "hemnet_commuter_cache");
if ($mysqli -> connect_errno) {
  echo "Failed to connect to MySQL: " . $mysqli -> connect_error;
  exit();
}

// Return cache results
if(isset($_GET['ckey']) && strlen($_GET['ckey']) > 0){
  $sql = 'SELECT `cache` FROM `cache` WHERE `ckey` = "'.$mysqli->real_escape_string($_GET['ckey']).'"';
  if ($result = $mysqli -> query($sql)) {
    while ($row = $result -> fetch_row()) {
      echo $row[0];
    }
    $result -> free_result();
  } else {
    echo 'no results';
  }
}

// Save cache results
if(isset($_POST['ckey']) && strlen($_POST['ckey']) > 0){

  $sql = 'DELETE FROM `cache` WHERE `ckey` = "'.$mysqli->real_escape_string($_POST['ckey']).'"';
  $mysqli -> query($sql);

  $sql = '
    INSERT INTO `cache`
    SET `ckey` = "'.$mysqli->real_escape_string($_POST['ckey']).'",
        `cache` = "'.$mysqli->real_escape_string($_POST['cache']).'"
  ';

  if (!$mysqli -> query($sql)) {
    if($mysqli->affected_rows < 0)
      print_r($mysqli -> error_list);
    else
      printf("%d Row inserted.\n", $mysqli->affected_rows);
  }
}

$mysqli -> close();
