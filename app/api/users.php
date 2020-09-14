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

  require_once('_common_api.php');

  // Return all users
  echo json_encode(get_users(), JSON_PRETTY_PRINT);

}
