<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Update and return house tags
 */

function get_all_tags(){

  global $mysqli;

  // Get all tags
  $results = [];
  $sql = 'SELECT `id`, `tag` FROM `tags`';
  if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
      $results[$row['id']] = $row['tag'];
    }
    $result->free_result();
  }
  return $results;
}

function get_house_tags($house_id){
  global $mysqli;

  $results = [];
  $tags = get_all_tags();

  // Get all tags, set as not selected
  foreach($tags as $tag_id => $tag_name){
    $results[$tag_id] = false;
  }

  // Get selected tags for this house
  $sql = 'SELECT `tag_id` FROM `house_tags` WHERE `house_id` = "'.$mysqli->real_escape_string($house_id).'"';
  if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_row()) {
      // Skip old deleted tag IDs
      if(array_key_exists($row[0], $results)){
        $results[$row[0]] = true;
      }
    }
    $result->free_result();
  }
  return $results;
}

// Save tag status for a house
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

// Save a new tag
function save_new_tag($new_tag){
  global $mysqli;
  $sql = 'INSERT INTO `tags` (`tag`) VALUES ("'.$mysqli->real_escape_string($new_tag).'")';
  if ($mysqli->query($sql) === TRUE) {
    return array('status' => 'success', 'new_tag_id' => $mysqli->insert_id);
  } else {
    return array('status' => 'error', 'error_msg' => $mysqli->error);
  }
}


/////////
// CALLED DIRECTLY - API usage
/////////
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {

  require_once('_common_api.php');

  // Return tags for a house
  if(isset($_GET['house_id']) && strlen($_GET['house_id']) > 0){
    echo json_encode(get_house_tags($_GET['house_id']), JSON_PRETTY_PRINT);
  }
  // Save a tag status for a house
  else if(is_array($postdata) && isset($postdata['house_id']) && isset($postdata['tag_id']) && isset($postdata['selected'])){
    echo json_encode( save_house_tag($postdata['house_id'],$postdata['tag_id'],$postdata['selected']), JSON_PRETTY_PRINT);
  }
  // Save a new tag
  else if(is_array($postdata) && isset($postdata['new_tag'])){
    echo json_encode( save_new_tag($postdata['new_tag']), JSON_PRETTY_PRINT);
  }
  // No action - give error status
  else {
    echo json_encode(array("status"=>"error", "msg" => "Error: No input supplied", "post_data" => $postdata), JSON_PRETTY_PRINT);
  }

}
