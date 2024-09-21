<?php

//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Scrape the Hemnet "Saved Search" pagesu / JSON
 */

// Fetch the saved searches from the DB
function fetch_saved_searches(){
  global $mysqli;

  // Get ratings for this house
  $results = [];
  $sql = 'SELECT `search_id`, `name`, `search`, `created` FROM `saved_searches`';
  if ($result = $mysqli->query($sql)) {
    while ($row = $result->fetch_assoc()) {
      $results[$row['search_id']] = array(
        'search_id' => $row['search_id'],
        'name' => $row['name'],
        'search' => json_decode($row['search'], true),
        'created' => $row['created']
      );
    }
    $result->free_result();
  } else {
    return array("status"=>"error", "msg" => "Could not fetch saved search from DB:<br>$sql<br>".$mysqli->error);
  }
  return array("status"=>"success", "msg" => "Found ".count($results)." saved searches", 'saved_searches' => $results);
}

// Save new saved searches to the DB
function update_saved_searches($postdata){
  global $mysqli;

  $searches = [];
  foreach($postdata['savedSearches'] as $saved_search){
    $searches[] = array(
      'search_id' => $saved_search['id'],
      'name' => $saved_search['name'],
      'search' => json_encode($saved_search['search']),
    );
  }

  if(count($searches) == 0){
    return array("status"=>"error", "msg" => "No new searches found in POST data.", 'postdata' => $postdata);
  }

  // Wipe the database table
  $sql = 'TRUNCATE TABLE `saved_searches`';
  if(!$mysqli->query($sql)) return array("status"=>"error", "msg" => "Could not truncate saved_searches table:<br>$sql<br>".$mysqli->error);

  // Save each house to the db
  foreach($searches as $saved_search){
    $sql = '
      INSERT INTO `saved_searches`
      SET `search_id` = "'.$mysqli->real_escape_string($saved_search['search_id']).'",
          `name` = "'.$mysqli->real_escape_string($saved_search['name']).'",
          `search` = "'.$mysqli->real_escape_string($saved_search['search']).'",
          `created` = "'.time().'"';
    if(!$mysqli->query($sql)) return array("status"=>"error", "msg" => "Could not insert saved search into DB:<br>$sql<br>".$mysqli->error);
  }

  return array("status"=>"success", "msg" => "Saved ".count($searches)." searches to DB");
}




/////////
// CALLED DIRECTLY - API usage
/////////
if ( basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {

  require_once('_common_api.php');
  if(!check_auth_token()){
    echo json_encode(array("status"=>"error", "msg" => "Error: Invalid authentication"), JSON_PRETTY_PRINT);
  }

  else if(array_key_exists('savedSearches', $postdata)){
    echo json_encode(update_saved_searches($postdata), JSON_PRETTY_PRINT);
  } else {
    echo json_encode(fetch_saved_searches($postdata), JSON_PRETTY_PRINT);
  }

}
