<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Update and return house ratings
 */

require_once('users.php');

// Return ratings results
function get_house_ratings($house_id){
  global $mysqli;

  $results = [];
  $users = get_all_users();

  // Get all users, set as rating not set
  foreach($users as $user_id => $user_name){
    $results[$user_id] = 'not_set';
  }

  // Get ratings for this house
  $sql = 'SELECT `user_id`, `rating` FROM `house_ratings` WHERE `house_id` = "'.$mysqli->real_escape_string($house_id).'"';
  if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
      $results[$row['user_id']] = $row['rating'];
    }
    $result->free_result();
  }
  return $results;
}

// Save ratings results
function save_house_rating($house_id, $user_id, $rating){
  global $mysqli;

  // Delete existing rating if it existed
  $sql = '
    DELETE FROM `house_ratings`
    WHERE `house_id` = "'.$mysqli->real_escape_string($house_id).'"
    AND `user_id` = "'.$mysqli->real_escape_string($user_id).'"';
  $mysqli->query($sql);

  // Insert supplied data
  $sql = '
    INSERT INTO `house_ratings`
    SET `house_id` = "'.$mysqli->real_escape_string($house_id).'",
        `user_id` = "'.$mysqli->real_escape_string($user_id).'",
        `rating` = "'.$mysqli->real_escape_string($rating).'"
  ';
  $mysqli->query($sql);
}


/////////
// CALLED DIRECTLY - API usage
/////////
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {

  require_once('_common_api.php');

  // Return ratings results
  if(isset($_GET['house_id']) && strlen($_GET['house_id']) > 0){
    echo json_encode(get_house_ratings($_GET['house_id']), JSON_PRETTY_PRINT);
  }
  // Save ratings results
  else if(is_array($postdata) && isset($postdata['house_id']) && isset($postdata['user_id']) && isset($postdata['rating'])){
    echo json_encode( save_house_rating($postdata['house_id'], $postdata['user_id'], $postdata['rating']), JSON_PRETTY_PRINT);
  } else {
    echo json_encode(array("status"=>"error", "msg" => "Error: No input supplied", "post_data" => $postdata), JSON_PRETTY_PRINT);
  }

}
