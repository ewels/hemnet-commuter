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


function add_user($name){
  global $mysqli;

  // Insert
  $sql = '
    INSERT INTO `users`
    SET `name` = "'.$mysqli->real_escape_string($name).'"
  ';
  if(!$mysqli->query($sql)) return array('status' => 'error', 'error_msg' => $mysqli->error);
  return get_all_users();
}

function update_user($id, $name){
  global $mysqli;

  $sql = '
    UPDATE `users`
    SET `name` = "'.$mysqli->real_escape_string($name).'"
    WHERE `id` = "'.$mysqli->real_escape_string($id).'"';

  if(!$mysqli->query($sql)) return array('status' => 'error', 'error_msg' => $mysqli->error);
  return get_all_users();
}

function delete_user($id){
  global $mysqli;
  $sql = 'DELETE FROM `users` WHERE `id` = "'.$mysqli->real_escape_string($id).'"';
  $mysqli->query($sql);
  return get_all_users();
}



/////////
// CALLED DIRECTLY - API usage
/////////
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {

  require_once('_common_api.php');

  // Save a new user
  if(isset($postdata['add_user_name'])){
    echo json_encode( add_user($postdata['add_user_name']), JSON_PRETTY_PRINT);
  }
  // Update a user
  else if(isset($postdata['update_id']) && isset($postdata['user_name'])){
    echo json_encode( update_user($postdata['update_id'], $postdata['user_name']), JSON_PRETTY_PRINT);
  }
  // Delete a user
  else if(isset($postdata['delete_id'])){
    echo json_encode( delete_user($postdata['delete_id']), JSON_PRETTY_PRINT);
  }
  // Return all users
  else {
    echo json_encode( get_all_users(), JSON_PRETTY_PRINT);
  }
}
