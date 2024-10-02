<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Update and return school comments
 */

require_once('users.php');

// Return school comments
function get_school_comments($school_id = false){
  global $mysqli;

  $results = [];
  $users = get_all_users();

  // Get all users, set as empty array
  foreach($users as $user_id => $user_name){
    $results[$user_id] = [];
  }

  // Get comments for this school
  $sql = 'SELECT `school_id`, `user_id`, `comment` FROM `school_comments`';
  if($school_id) $sql .= ' WHERE `school_id` = "'.$mysqli->real_escape_string($school_id).'"';
  if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
      $results[$row['user_id']][$row['school_id']] = $row['comment'];
    }
    $result->free_result();
  }
  return $results;
}

// Save comment
function save_school_comment($school_id, $user_id, $comment){
  global $mysqli;

  // Delete existing comment if it existed
  $sql = '
    DELETE FROM `school_comments`
    WHERE `school_id` = "'.$mysqli->real_escape_string($school_id).'"
    AND `user_id` = "'.$mysqli->real_escape_string($user_id).'"';
  $mysqli->query($sql);

  // Insert supplied data
  $sql = '
    INSERT INTO `school_comments`
    SET `school_id` = "'.$mysqli->real_escape_string($school_id).'",
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

  // Return comments for a school
  else if(isset($_GET['school_id']) && strlen($_GET['school_id']) > 0){
    echo json_encode(get_school_comments($_GET['school_id']), JSON_PRETTY_PRINT);
  }
  // Save comment
  else if(is_array($postdata) && isset($postdata['school_id']) && isset($postdata['user_id']) && isset($postdata['comment'])){
    echo json_encode( save_school_comment($postdata['school_id'], $postdata['user_id'], $postdata['comment']), JSON_PRETTY_PRINT);
  } else {
    echo json_encode(get_school_comments(), JSON_PRETTY_PRINT);
  }

}
