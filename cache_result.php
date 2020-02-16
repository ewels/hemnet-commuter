<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$ini_array = parse_ini_file("hemnet_commuter_config.ini");

$mysqli = new mysqli("localhost", $ini_array['db_user'], $ini_array['db_password'], $ini_array['db_name']);
if ($mysqli -> connect_errno) {
  echo "Failed to connect to MySQL: " . $mysqli -> connect_error;
  exit();
}

// Return hncache results
if(isset($_GET['ckey']) && strlen($_GET['ckey']) > 0){
  $sql = 'SELECT `hncache` FROM `hncache` WHERE `ckey` = "'.$mysqli->real_escape_string($_GET['ckey']).'"';
  if ($result = $mysqli -> query($sql)) {
    while ($row = $result -> fetch_row()) {
      echo $row[0];
    }
    $result -> free_result();
  } else {
    echo '{}';
  }
}

// Save hncache results
if(isset($_POST['ckey']) && strlen($_POST['ckey']) > 0){

  $sql = 'DELETE FROM `hncache` WHERE `ckey` = "'.$mysqli->real_escape_string($_POST['ckey']).'"';
  $mysqli -> query($sql);

  $sql = '
    INSERT INTO `hncache`
    SET `ckey` = "'.$mysqli->real_escape_string($_POST['ckey']).'",
        `hncache` = "'.$mysqli->real_escape_string($_POST['hncache']).'"
  ';

  if (!$mysqli -> query($sql)) {
    if($mysqli->affected_rows < 0)
      print_r($mysqli -> error_list);
    else
      printf("%d Row inserted.\n", $mysqli->affected_rows);
  }
}

$mysqli -> close();
