<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Update and return house ratings
 */


// Return ratings results
function get_house_ratings($house_id){
  global $mysqli;

  // Get all tags
  $sql = 'SELECT `id`, `tag` FROM `tags`';
  $tags = [];
  if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
      $tags[$row['id']] = array(
        'label' => $row['tag'],
        'selected' => false
      );
    }
    $result->free_result();
  }

  // Get ratings for this house
  $sql = 'SELECT * FROM `house_ratings` WHERE `house_id` = "'.$mysqli->real_escape_string($house_id).'"';
  $results = array('tags' => $tags, 'users' => [], );
  if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
      // Tags - not user specific
      if(substr($row['r_key'], 0, 4) == 'tag_'){
        $tag_id = substr($row['r_key'], 4);
        $results['tags'][$tag_id]['selected'] = $row['r_value'] == '1';
      }
      // Other field - user specific
      else {
        if(is_null($row['user_id'])) continue;
        // New user id
        if(!in_array($row['user_id'], $results)){
          $results['users'][$row['user_id']] = [];
        }
        $results['users'][$row['r_key']] = $row['r_value'];
      }
    }
    $result->free_result();
  }
  return $results;
}

// Save ratings results
function save_house_rating($house_id, $key, $value, $user_id=false){
  global $mysqli;

  if($value === true) $value = 1;
  if($value === false) $value = 0;

  // Delete existing rating if it existed
  $sql = '
    DELETE FROM `house_ratings`
    WHERE `house_id` = "'.$mysqli->real_escape_string($house_id).'"
    AND `r_key` = "'.$mysqli->real_escape_string($key).'"';
  if($user_id){
    $sql .= ' AND `user_id` = "'.$mysqli->real_escape_string($user_id).'"';
  }
  $mysqli->query($sql);

  // Insert supplied data
  $sql = '
    INSERT INTO `house_ratings`
    SET `house_id` = "'.$mysqli->real_escape_string($house_id).'",
        `r_key` = "'.$mysqli->real_escape_string($key).'",
        `r_value` = "'.$mysqli->real_escape_string($value).'"
  ';
  if($user_id){
    $sql .= ', `user_id` = "'.$mysqli->real_escape_string($user_id).'"';
  }


  if (!$mysqli->query($sql)) {
    if($mysqli->affected_rows < 0)
      return array("status" => "error", "msg" => print_r($mysqli->error_list, true));
    else
      return array("status" => "success", "msg" => "Rating saved.");
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
  if(is_array($postdata) && !array_key_exists('user_id', $postdata)) $postdata['user_id'] = null;

  // Return ratings results
  if(isset($_GET['house_id']) && strlen($_GET['house_id']) > 0){
    echo json_encode(get_house_ratings($_GET['house_id']));
  }
  // Save ratings results
  else if(is_array($postdata)  && isset($postdata['house_id']) && strlen($postdata['house_id']) > 0 && isset($postdata['r_key']) && isset($postdata['r_value'])){
    echo json_encode(
      save_house_rating(
        $postdata['house_id'],
        $postdata['r_key'],
        $postdata['r_value'],
        $postdata['user_id']
      )
    );
  } else {
    echo json_encode(array("status"=>"error", "msg" => "Error: No input supplied", "post_data" => json_encode($postdata)));
  }

}
