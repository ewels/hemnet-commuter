<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Update and return house comments
 */

require_once('users.php');

// Return house comments
function get_house_comments($house_id){
  global $mysqli;

  $results = [];
  $users = get_all_users();

  // Get all users, set as empty comment
  foreach($users as $user_id => $user_name){
    $results[$user_id] = '';
  }

  // Get comments for this house
  $sql = 'SELECT `user_id`, `comment` FROM `house_comments` WHERE `house_id` = "'.$mysqli->real_escape_string($house_id).'"';
  if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
      $results[$row['user_id']] = $row['comment'];
    }
    $result->free_result();
  }
  return $results;
}

// Save comment
function save_house_comment($house_id, $user_id, $comment){
  global $mysqli;

  // Delete existing comment if it existed
  $sql = '
    DELETE FROM `house_comments`
    WHERE `house_id` = "'.$mysqli->real_escape_string($house_id).'"
    AND `user_id` = "'.$mysqli->real_escape_string($user_id).'"';
  $mysqli->query($sql);

  // Insert supplied data
  $sql = '
    INSERT INTO `house_comments`
    SET `house_id` = "'.$mysqli->real_escape_string($house_id).'",
        `user_id` = "'.$mysqli->real_escape_string($user_id).'",
        `comment` = "'.$mysqli->real_escape_string($comment).'"
  ';
  $mysqli->query($sql);
  return [$sql];
}


/////////
// CALLED DIRECTLY - API usage
/////////
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {

  require_once('_common_api.php');
  if(!check_auth_token()){
    echo json_encode(array("status"=>"error", "msg" => "Error: Invalid authentication"), JSON_PRETTY_PRINT);
  }

  // Return comments for a house
  else if(isset($_GET['house_id']) && strlen($_GET['house_id']) > 0){
    echo json_encode(get_house_comments($_GET['house_id']), JSON_PRETTY_PRINT);
  }
  // Save comment
  else if(is_array($postdata) && isset($postdata['house_id']) && isset($postdata['user_id']) && isset($postdata['comment'])){
    echo json_encode( save_house_comment($postdata['house_id'], $postdata['user_id'], $postdata['comment']), JSON_PRETTY_PRINT);
  } else {
    echo json_encode(array("status"=>"error", "msg" => "Error: No input supplied", "post_data" => $postdata), JSON_PRETTY_PRINT);
  }

}
