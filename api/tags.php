<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Update and return house ratings
 */


// Return ratings results
function get_house_tags($house_id){
  global $mysqli;

  $results = [];

  // Get all tags, set as not selected
  $sql = 'SELECT `id`, `tag` FROM `tags`';
  if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
      $results[$row['id']] = array(
        'label' => $row['tag'],
        'selected' => false
      );
    }
    $result->free_result();
  }

  // Get selected tags for this house
  $sql = 'SELECT `tag_id` FROM `house_tags` WHERE `house_id` = "'.$mysqli->real_escape_string($house_id).'"';
  if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_row()) {
      $results[$row[0]]['selected'] = true;
    }
    $result->free_result();
  }
  return $results;
}

// Save ratings results
function save_house_tag($house_id, $tag_id, $selected){
  global $mysqli;

  // Delete existing house tag if it existed
  $sql = '
    DELETE FROM `house_tags`
    WHERE `house_id` = "'.$mysqli->real_escape_string($house_id).'"
    AND `tag_id` = "'.$mysqli->real_escape_string($tag_id).'"';
  $mysqli->query($sql);

  // Insert tag if selected
  if($selected){
    $sql = '
      INSERT INTO `house_tags`
      SET `house_id` = "'.$mysqli->real_escape_string($house_id).'",
          `tag_id` = "'.$mysqli->real_escape_string($tag_id).'"
    ';
    $mysqli->query($sql);
  }
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

  // AngularJS POST data looks weird
  $postdata = json_decode(file_get_contents("php://input"), true);

  // Return ratings results
  if(isset($_GET['house_id']) && strlen($_GET['house_id']) > 0){
    echo json_encode(get_house_tags($_GET['house_id']), JSON_PRETTY_PRINT);
  }
  // Save ratings results
  else if(is_array($postdata) && isset($postdata['house_id']) && isset($postdata['tag_id']) && isset($postdata['selected'])){
    echo json_encode( save_house_tag($postdata['house_id'],$postdata['tag_id'],$postdata['selected']), JSON_PRETTY_PRINT);
  }
  // No action - give error status
  else {
    echo json_encode(array("status"=>"error", "msg" => "Error: No input supplied", "post_data" => $postdata), JSON_PRETTY_PRINT);
  }

}
