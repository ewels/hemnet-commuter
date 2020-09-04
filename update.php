<?php

// Alerts to print to top of page
$msgs = [];

// Connect to the database etc.
$not_json = true;
require_once('api/_common_api.php');


///////////////////////////
// SAVED SEARCHES
///////////////////////////
require_once('api/scrape_hemnet_saved_search.php');

// POST - update search results
if(isset($_POST['saved_search_ids'])){
  // Truncate the search ID table
  $sql = 'TRUNCATE TABLE `saved_searches`';
  $mysqli->query($sql);

  // Update the search IDs themselves
  $search_ids = explode(",", $_POST['saved_search_ids']);
  $search_ids = array_map("trim",$search_ids);
  foreach($search_ids as $search_id){
    if(is_numeric(trim($search_id)) && trim($search_id) > 0){
      $sql = 'INSERT INTO `saved_searches` SET `search_id` = "'.$mysqli->real_escape_string($search_id).'"';
      $mysqli->query($sql);
    }
  }
  $msgs[] = ['success', 'Updated saved search IDs'];

  // Truncate existing search results
  scrape_hemnet_search_table();

  // Update results for each saved search
  foreach($search_ids as $search_id){
    $house_urls = scrape_hemnet_search($search_id);
    $msgs[] = ['success', "Found ".count($house_urls).' houses with search <a href="https://www.hemnet.se/bostader?subscription='.$search_id.'" target="_blank">'.$search_id.'</a>.'];
  }
}

// Get current search IDs
$search_ids = [];
$sql = 'SELECT `search_id` FROM `saved_searches`';
if ($result = $mysqli->query($sql)) {
  while ($row = $result->fetch_row()) {
    $search_ids[] = $row[0];
  }
  $result->free_result();
}

// Get current search results
$oldest_saved_search_fetch = false;
$newest_saved_search_fetch = false;
$num_houses = 0;
$sql = 'SELECT `created` FROM `search_result_urls`';
if ($result = $mysqli->query($sql)) {
  while ($row = $result->fetch_row()) {
    $created = strtotime($row[0]);
    if(!$oldest_saved_search_fetch) $oldest_saved_search_fetch = $created;
    if(!$newest_saved_search_fetch) $newest_saved_search_fetch = $created;
    $oldest_saved_search_fetch = min($oldest_saved_search_fetch, $created);
    $newest_saved_search_fetch = max($newest_saved_search_fetch, $created);
    $num_houses++;
  }
  $result->free_result();
}




///////////////////////////
// HOUSE DETAILS
///////////////////////////
require_once('api/scrape_hemnet_house.php');

// POST - Delete old detail fetches
if(isset($_POST['house_detail_fetch_expiry']) && isset($_POST['house_detail_delete_old'])){
  $sql = '
    DELETE FROM `house_details`
    WHERE UNIX_TIMESTAMP(`created`) < '.$mysqli->real_escape_string(strtotime($_POST['house_detail_fetch_expiry'])).'
  ';
  $mysqli->query($sql);
  $msgs[] = ['success', "Deleted ".$mysqli->affected_rows." old detail fetches"];
}

// Get current search IDs
$sql = '
  SELECT house_details.created
  FROM house_details
  INNER JOIN search_result_urls ON house_details.id=search_result_urls.id;
';
$num_houses_with_detail = 0;
$oldest_house_detail_fetch = false;
$newest_house_detail_fetch = false;
if ($result = $mysqli->query($sql)) {
  while ($row = $result->fetch_row()) {
    $created = strtotime($row[0]);
    if(!$oldest_house_detail_fetch) $oldest_house_detail_fetch = $created;
    if(!$newest_house_detail_fetch) $newest_house_detail_fetch = $created;
    $oldest_house_detail_fetch = max($oldest_house_detail_fetch, $created);
    $newest_house_detail_fetch = min($newest_house_detail_fetch, $created);
    $num_houses_with_detail++;
  }
  $result->free_result();
}

// Get missing detail house IDs
$sql = '
  SELECT search_result_urls.id FROM search_result_urls
  LEFT OUTER JOIN house_details ON house_details.id = search_result_urls.id
  WHERE house_details.id IS NULL
';
$missing_detail_ids = [];
if ($result = $mysqli->query($sql)) {
  while ($row = $result->fetch_row()) {
    $missing_detail_ids[] = $row[0];
  }
  $result->free_result();
}

// POST - update detail fetches
if(isset($_POST['house_detail_fetch_expiry']) && isset($_POST['house_detail_fetch_missing'])){

  // Fetch missing details
  $success_count = 0;
  foreach($missing_detail_ids as $house_id){
    if(!scrape_hemnet_house($house_id)){
      $msgs[] = ['warning', 'Failed getting detailed fetch for <a href="https://www.hemnet.se/bostad/'.$house_id.'" target="_blank">'.$house_id.'</a>'];
    } else {
      $success_count++;
    }
  }
  if($success_count > 0){
    $msgs[] = ['success', 'Fetched details for '.$success_count.' houses'];
  }
}






///////////////////////////
// GEOCODING
///////////////////////////
require_once('api/geocode_address.php');

// Get current geocodes
$sql = 'SELECT house_id, lat, lng, address, location_type FROM geocoding_results';
$geocode_results = [];
if ($result = $mysqli->query($sql)) {
  while ($row = $result->fetch_assoc()) {
    if(!in_array($row['location_type'], array_keys($geocode_results))){
      $geocode_results[$row['location_type']] = [];
    }
    $geocode_results[$row['location_type']][] = $row;
  }
  $result->free_result();
}

// Get missing detail house IDs
$sql = '
  SELECT search_result_urls.id FROM search_result_urls
  LEFT OUTER JOIN geocoding_results ON geocoding_results.house_id = search_result_urls.id
  WHERE geocoding_results.house_id IS NULL
';
$missing_geocode_ids = [];
if ($result = $mysqli->query($sql)) {
  while ($row = $result->fetch_row()) {
    $missing_geocode_ids[] = $row[0];
  }
  $result->free_result();
}

// POST - update detail fetches
if(isset($_POST['house_geocode_fetch_missing'])){
  // Fetch missing details
  $success_count = 0;
  foreach($missing_geocode_ids as $house_id){
    $loc = geocode_house_address($house_id);
    if(isset($loc['status']) && $loc['status'] == 'error'){
      $msgs[] = ['danger', $loc['msg'].' - <strong><a href="#" class="alert-link geocode_manual" data-houseid="'.$house_id.'">Click here to manually enter</a></strong>'];
    } else {
      $success_count++;
    }
  }
  if($success_count > 0){
    $msgs[] = ['success', 'Got geocoded locations for '.$success_count.' houses'];
  }
}






///////////////////////////
// COMMUTE TIMES
///////////////////////////
require_once('api/commute_times.php');

// POST - update commute times fetches
if(isset($_POST['commute_times_fetch_missing'])){
  $result = update_commute_times();
  if(isset($result['status']) && $result['status'] == 'error'){
    $msgs[] = ['danger', $result['msg']];
  } else {
    $msgs[] = ['success', $result['msg']];
  }
}

$commute_times = update_commute_times(false, false, true);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <!-- Bootstrap & FontAwesome CSS -->
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
  <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet" integrity="sha384-wvfXpqpZZVQGK6TAh5PVlGOfQNHSoD2xbE+QkPxCAFlNEevoEH3Sl0sibVcOQVnN" crossorigin="anonymous">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.6.0/dist/leaflet.css" />
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php
if(!file_exists("config.ini")){
  die('<div class="m-5 alert alert-danger">Error! Configuration file <code>config.ini</code> not found!</div></body></html>');
}
?>

<div class="container-fluid">

  <img src="hemnet.svg" class="float-right" style="width: 100px;">
  <h1 class="display-4">Hemnet Commuter</h1>
  <p class="lead text-muted mb-3">Update your search results.</p>

  <?php // Show status messages
  foreach($msgs as $msg){
    echo '<div class="my-3 alert alert-'.$msg[0].'">'.$msg[1].'</div>';
  }
  ?>

  <h3>Search results</h3>
  <p>Number of houses in DB: <span class="badge badge-info"><?php echo $num_houses; ?></span></p>
  <ul>
    <li>Oldest result fetch time: <span class="badge badge-secondary"><?php echo date("Y-m-d H:i:s", $oldest_saved_search_fetch); ?></span></li>
    <li>Newest result fetch time: <span class="badge badge-secondary"><?php echo date("Y-m-d H:i:s", $newest_saved_search_fetch); ?></span></li>
  </ul>
  <form action="" method="post">
    <div class="form-group">
      <label for="saved_search_ids">Saved Search ID numbers</label>
      <input type="text" class="form-control" id="saved_search_ids" name="saved_search_ids" value="<?php echo implode(", ", $search_ids); ?>">
      <small class="form-text text-muted">Comma separated IDs from saved searches - eg. the <code>subscription</code> number at the end of URLs such as <code>https://www.hemnet.se/bostader?by=creation&order=desc&subscription=12345678</code></small>
    </div>
    <button type="submit" class="btn btn-primary">Update search results</button>
  </form>


  <h3 class="mt-5">House details</h3>
  <p>Hemnet Commuter scrapes the Hemnet website to get details about each house. This is a separate process to fetching the search results.</p>
  <p>Number of current search result houses with detailed fetches: <span class="badge badge-info"><?php echo $num_houses_with_detail; ?></span> (missing: <span class="badge badge-danger"><?php echo count($missing_detail_ids); ?></span>)</p>
  <ul>
    <li>Oldest detail fetch time: <span class="badge badge-secondary"><?php echo date("Y-m-d H:i:s", $oldest_house_detail_fetch); ?></span></li>
    <li>Newest detail fetch time: <span class="badge badge-secondary"><?php echo date("Y-m-d H:i:s", $newest_house_detail_fetch); ?></span></li>
  </ul>

  <form action="" method="post">
    <div class="form-group">
      <label for="house_detail_fetch_expiry">Refresh pulls for any older than:</label>
      <input type="text" class="form-control" id="house_detail_fetch_expiry" name="house_detail_fetch_expiry" value="<?php echo date("Y-m-d H:i:s", strtotime('-96 hours', time())); ?>">
      <small class="form-text text-muted">Default: 4 days old</small>
    </div>
    <button type="submit" name="house_detail_delete_old" class="btn btn-primary">Delete outdated house details</button>
    <button type="submit" name="house_detail_fetch_missing" class="btn btn-primary">Fetch missing house details</button>
    <small class="form-text text-muted">Warning: Fetching a lot of detail pages can take a while...</small>
  </form>



  <h3 class="mt-5">House geocoding</h3>
  <p>The Hemnet HTML pages don't contain house coordinates, so we use the Google Maps API to do "geocoding" from the address. This allows us to plot a marker on a map.</p>
  <p>Missing geocode locations in current search results: <span class="badge badge-danger"><?php echo count($missing_geocode_ids); ?></span></p>
  <form action="" method="post">
    <button type="submit" name="house_geocode_fetch_missing" class="btn btn-primary">Fetch missing geocoded locations</button>
    <small class="form-text text-muted">Warning: Fetching a lot of geocoded addresses can take a while...</small>
  </form>

  <p>Location precisions:</p>
  <ul>
    <?php
    foreach($geocode_results as $loc_type => $houses){
      echo '<li><a data-toggle="collapse" href="#geo_loctype_houses_'.$loc_type.'" role="button">'.$loc_type.'</a> - <span class="badge badge-secondary">'.count($houses).'</span> (click to view / edit) <ul class="collapse" id="geo_loctype_houses_'.$loc_type.'">';
      foreach($houses as $house){
        echo '<li class="my-2">'.$house['address'].' - <a href="https://www.hemnet.se/bostad/'.$house['house_id'].'" target="_blank">Hemnet</a> / <a href="https://www.google.com/maps?q=loc:'.$house['lat'].','.$house['lng'].'" target="_blank">Current G Maps</a> <small>(<a href="#" class="geocode_manual" data-houseid="'.$house['house_id'].'">Edit</a>)</small></li>';
      }
      echo '</ul></li>';
    }
    ?>
  </ul>

  <h3 class="mt-5">Commute times</h3>
  <p>To work with commute times, we use the Google Maps API <em>Distance Matrix</em> API. We fetch the fastest public transport time going at 9am "next Friday" (whenever that is).</p>
  <p>
    Number of commute locations combinations in current search results: <span class="badge badge-info"><?php echo $commute_times['num_db']; ?></span>
    (missing: <span class="badge badge-danger"><?php echo $commute_times['num_missing']; ?></span>)
  </p>
  <form action="" method="post">
    <button type="submit" name="commute_times_fetch_missing" class="btn btn-primary">Fetch missing commute times</button>
    <small class="form-text text-muted">Warning: Fetching a lot of commute timess can take a while...</small>
  </form>


</div>

<footer>
  <div class="container-fluid">
    Hemnet-Commuter was written by <a href="http://phil.ewels.co.uk">Phil Ewels</a>. It is in no way connected with, or endorsed by,
    <a href="https://www.hemnet.se">hemnet.se</a>.<br>
    The code for this website is released with the MIT open-source licence and can be
    viewed on GitHub: <a href="https://github.com/ewels/hemnet-commuter">https://github.com/ewels/hemnet-commuter</a>.
  </div>
</footer>

<!-- jQuery, then Tether, Bootstrap JS, Google Maps, custom JS -->
<script src="https://code.jquery.com/jquery-3.2.1.min.js" integrity="sha256-hwg4gsxgFZhOsEEamdOYGBf13FyQuiTwlAQgxVSNgt4=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tether/1.4.0/js/tether.min.js" integrity="sha384-DztdAPBWPRXSA/3eYEEUWrWCy7G5KFbe8fFjk5JAIxUYHKkDx6Qin1DkWx51bBrb" crossorigin="anonymous"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-alpha.6/js/bootstrap.min.js" integrity="sha384-vBWWzlZJ8ea9aCX4pEW3rVHjgjt7zpkNpZk+02D9phzyeVkE+jo0ieGizqPLForn" crossorigin="anonymous"></script>
<script src="https://unpkg.com/leaflet@1.6.0/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.24.0/moment.min.js" integrity="sha256-4iQZ6BVL4qNKlQ27TExEhBN1HFPvAvAMbFavKKosSWQ=" crossorigin="anonymous"></script>

<script type="text/javascript">
$(function(){
  $('.geocode_manual').click(function(e){
    e.preventDefault();
    var house_id = $(this).data('houseid');
    var lat = false;
    var lng = false;
    var gmaps_link = prompt("Google maps link (leave blank for manual lat / lng)");
    if(gmaps_link != null){
      var matches = gmaps_link.match(/https?:\/\/www\.google\.com\/maps\/\@(\d+\.\d+),(\d+\.\d+)/);
      if(matches != null){
        lat = matches[1];
        lng = matches[2];
        if(!confirm("Got lat "+lat+" and lng "+lng+" from Google maps URL")){
          return;
        };
      }
    }
    if(!lat || !lng){
      lat = prompt("Latitute");
      if (lat == null) { return; }
      lng = prompt("Longitude");
      if (lng == null) { return; }
    }
    if(!parseFloat(lat) > 0 || !parseFloat(lat) > 0){
      alert("Lat and lng should be numeric. Got "+lat+" and "+lng);
      return;
    }

    var api_url = "api/geocode_address.php?id="+house_id+"&fix_lat="+lat+"&fix_lng="+lng;

    $.getJSON(api_url, function( data ) {
      alert("Status: "+data.status+": "+data.msg+". Refresh the page to see the new count totals.");
    });
  });
});
</script>

</body>
</html>
