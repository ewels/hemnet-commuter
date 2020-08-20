<?php $ini_array = parse_ini_file("hemnet_commuter_config.ini"); ?>
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
if(!file_exists("hemnet_commuter_config.ini")){
  die('<div class="m-5 alert alert-danger">Error! Configuration file <code>hemnet_commuter_config.ini</code> not found!</div></body></html>');
}
?>

<div class="container-fluid" id="hemnet_commuter_form" style="display:none;">

  <img id="hemnet-logo" src="hemnet.svg">
  <h1 class="display-4">Hemnet Commuter</h1>
  <p class="lead text-muted mb-3">Given RSS feeds of Hemnet searches, filter for properties that match a commuting preference.</p>
  <p>Find out more about this tool on it's <a href="https://github.com/ewels/hemnet-commuter">GitHub page</a>.</p>

  <p class="text-muted text-center my-5" id="cache_load_text">Loading previous results.. <i class="fa fa-refresh fa-spin fa-fw"></i></p>

  <form>

    <div class="card mb-3" id="api_details">
      <div class="card-body hemnet_rss_card">
        <h4 class="card-title">Step 0: Enter API Credentials</h4>
        <p class="card-text">This tool uses the wonderful API service from <a href="https://www.traveltimeplatform.com" target="_blank">https://www.traveltimeplatform.com/</a>.
        The service is free to use, but you'll need to get your own API keys <a href="https://docs.traveltimeplatform.com/overview/getting-keys" target="_blank">from here</a>.</p>
        <div class="traveltime_rows">
          <div class="input-group mb-1 traveltime_row">
            <span class="input-group-addon">
              TravelTime X-Application-Id
            </span>
            <input type="text" class="form-control traveltime_api" name="traveltime_api_id" id="traveltime_api_id" required value="<?php echo $ini_array['traveltime_api_id']; ?>">
          </div>
          <div class="input-group mb-1 traveltime_row">
            <span class="input-group-addon">
              TravelTime X-Api-Key
            </span>
            <input type="text" class="form-control traveltime_api" name="traveltime_api_key" id="traveltime_api_key" required value="<?php echo $ini_array['traveltime_api_key']; ?>">
          </div>
        </div>
        <p class="card-text">The only downside of the free TravelTime service is that the "geocoding" service that gives a latitude and longitude
        for each address is pretty bad. Google Maps is way better, but you need to put in credit card details to use it now (though you get $300 free
        for a year, which is way more than you will need for this tool). If you want to, put in your Google Maps API key here and you will get much better results.</p>
        <div class="gmap_api_rows">
          <div class="input-group mb-1 gmap_api_row">
            <span class="input-group-addon">
              Google Maps API Key
            </span>
            <input type="text" class="form-control traveltime_api" name="gmap_api_key" id="gmap_api_key" value="<?php echo $ini_array['gmap_api_key']; ?>">
          </div>
        </div>
      </div>
    </div>


    <div class="card mb-3">
      <div class="card-body hemnet_rss_card">
        <h4 class="card-title">Step 1: Enter Hemnet Search RSS Feed URLs</h4>
        <p class="card-text">On <a href="https://www.hemnet.se" target="_blank">hemnet.se</a> create a search and save it.
          Find the link to the RSS feed for that search (on the list page) and paste that here.<br>
          <small class="text-muted"><span class="text-danger"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> Only the <strong>most recent 30</strong> items are returned.</span>
          Enter multiple search RSS addresses to retrieve more results.</small></p>
        <div class="hemnet_rss_rows">
          <div class="mb-1 hemnet_rss_row">
            <p class="hemnet_rss_title form-text text-muted mb-0 mt-3"></p>
            <div class="input-group">
              <input type="url" class="form-control hemnet_rss" name="hemnet_rss[]" required>
              <span class="input-group-btn hemnet_rss_delete" style="display:none;">
                <button class="btn btn-outline-secondary hemnet_rss_delete_btn" type="button"><i class="fa fa-trash-o hemnet_rss_add" aria-hidden="true"></i></button>
              </span>
              <span class="input-group-btn hemnet_rss_add_btn">
                <button class="btn btn-outline-secondary hemnet_rss_add_btn" type="button"><i class="fa fa-plus hemnet_rss_add" aria-hidden="true"></i></button>
              </span>
            </div>
          </div>
        </div>
        <p class="text-muted"><small>For example: <samp>https://www.hemnet.se/mitt_hemnet/sparade_sokningar/00000000.xml</samp></small></p>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <h4 class="card-title">Step 2: Enter Commute Filters</h4>
        <p class="card-text">Enter one or more addresses, where you must commute to. Add the maximum commute time for each.</p>
        <table class="table table-sm table-striped" id="commute-table">
          <thead class="thead-default">
            <tr>
              <th>Work Addresss</th>
              <th style="max-width:300px;">Maximum Commute (<code>hh:mm</code>)</th>
              <th style="width:5%;">Remove</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><input type="text" class="form-control commute_address" id="commute_address_1" name="commute_address_1" placeholder="eg. Riksgatan 1, 100 12 Stockholm" required></td>
              <td><input type="time" class="form-control commute_time" id="commute_time_1" name="commute_time_1" value="00:40" placeholder="hh:mm" required></td>
              <td class="text-center"><button class="btn btn-outline-secondary commute_deleterow" id="commute_deleterow_1" disabled><i class="fa fa-trash-o" aria-hidden="true"></i></button></td>
            </tr>
          </tbody>
        </table>
        <div class="form-inline">
          <button class="btn btn-outline-primary" id="commute_addrow">Add another</button>
          <div class="form-check ml-4">
            <label class="form-check-label">
              <input class="form-check-input" type="checkbox" name="commute_hidemarkers_outside" id="commute_hidemarkers_outside" value="1">
              Hide map markers that are outside commute area
            </label>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body">
        <h4 class="card-title">Step 3: Search!</h4>
        <p class="card-text">When you're ready, press search to show a map with filtered properties.</p>
        <p>
          <input type="submit" class="btn btn-primary" id="search-btn" value="Search">
          <small class="ml-3 text-muted" id="status-text" style="display:none;">Status: <span id="status-msg">Form submitted</span></small>
        </p>
      </div>
    </div>

  </form>
</div>

<div class="container-fluid results_card" style="display:none;">
  <div class="row h-100">
    <div class="col-md-9 h-100 order-2">
      <div class="row">
        <div class="col-sm-4">
          <h4 class="card-title">Results</h4>
          <p>
            <span class="badge badge-pill badge-info num_houses">? houses found</span>
            <span class="badge badge-pill badge-success num_houses_map_shown">? houses shown</span>
            <span class="badge badge-pill badge-warning num_houses_map_hidden">? houses hidden</span>
          </p>
        </div>
        <div class="col-sm-4">
          <div class="form-check">
            <div class="form-check"><input class="form-check-input rating_filter" type="checkbox" checked="checked" id="person_1_unrated"><label class="form-check-label" for="person_1_unrated"><?php echo $ini_array['person_1_name']; ?> Unrated</label></div>
            <div class="form-check"><input class="form-check-input rating_filter" type="checkbox" checked="checked" id="person_1_yes"><label class="form-check-label" for="person_1_yes"><?php echo $ini_array['person_1_name']; ?> Yes</label></div>
            <div class="form-check"><input class="form-check-input rating_filter" type="checkbox" checked="checked" id="person_1_no"><label class="form-check-label" for="person_1_no"><?php echo $ini_array['person_1_name']; ?> No</label></div>
          </div>
        </div>
        <div class="col-sm-4">
          <div class="form-check">
            <div class="form-check"><input class="form-check-input rating_filter" type="checkbox" checked="checked" id="person_2_unrated"><label class="form-check-label" for="person_2_unrated"><?php echo $ini_array['person_2_name']; ?> Unrated</label></div>
            <div class="form-check"><input class="form-check-input rating_filter" type="checkbox" checked="checked" id="person_2_yes"><label class="form-check-label" for="person_2_yes"><?php echo $ini_array['person_2_name']; ?> Yes</label></div>
            <div class="form-check"><input class="form-check-input rating_filter" type="checkbox" checked="checked" id="person_2_no"><label class="form-check-label" for="person_2_no"><?php echo $ini_array['person_2_name']; ?> No</label></div>
          </div>
        </div>
      </div>
      <div id="results_map" class="mb-3"></div>
    </div>
    <div class="col-md-3 order-1">
      <div id="results_focus">
        <p id="results_nofocus" class="text-muted">Click a house marker on the map to see more information here.</p>
        <div id="results_focus_row" style="display:none;">
            <h3><a href="" target="_blank" class="focus_link"><span class="focus_title"></span> <i class="fa fa-external-link" aria-hidden="true"></i></a></h3>
            <h3><span class="badge badge-primary focus_price"></span></h3>
            <h5>
              <span class="badge badge-info small focus_status"></span>
              <span class="badge badge-info small focus_bidding"></span>
            </h5>
            <img src="" style="width:100%;" class="focus_img">
            <dl class="row focus_dl small">
              <dt class="col-xl-5">Location:</dt>
              <dd class="col-xl-7"><span class="focus_unique_districts"></span></dd>

              <dt class="col-xl-5">Published:</dt>
              <dd class="col-xl-7"><span class="badge badge-info focus_published_ago"></span> <span class="badge badge-light focus_published"></span></dd>

              <dt class="col-xl-5">Upcoming open houses:</dt>
              <dd class="col-xl-7"><span class="focus_upcoming_open_houses"></span></dd>

              <dt class="col-xl-5">Size:</dt>
              <dd class="col-xl-7"><span class="badge badge-success focus_living_area"></span> <span class="badge badge-warning focus_supplemental_area"></span> <span class="badge badge-secondary focus_land_area"></span></dd>

              <dt class="col-xl-5">Rooms:</dt>
              <dd class="col-xl-7"><span class="badge badge-info focus_rooms"></span></dd>

              <dt class="col-xl-5">Driftkostnad:</dt>
              <dd class="col-xl-7"><span class="focus_driftkostnad_month"></span> kr/month <small>(<span class="focus_driftkostnad_year"></span> kr/year)</small></dd>

              <dt class="col-xl-5">Construction year:</dt>
              <dd class="col-xl-7"><span class="focus_construction_year"></sppan></dd>

              <dt class="col-xl-5">Tenure:</dt>
              <dd class="col-xl-7"><span class="focus_tenure"></sppan></dd>

            </dl>

            <div class="row my-5 house_ratings" data-house_id="">
              <div class="col-6 rating_person rating_person_1">
                <h4><span class="d-none d-xl-inline">Rating:</span> <?php echo $ini_array['person_1_name']; ?></h4>
                <div class="btn-group d-flex rating_yesno" role="group">
                  <button class="btn w-100 btn-outline-success mb-2 rating_overall_yes"><i class="fa fa-thumbs-up"></i></button>
                  <button class="btn w-100 btn-outline-danger mb-2 rating_overall_no"><i class="fa fa-thumbs-down"></i></button>
                </div>
                <dl class="row">
                  <dt class="col-xl-6">Inside</dt>
                  <dd class="col-xl-6 rating_stars rating_inside"><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i></dd>

                  <dt class="col-xl-6">Outside</dt>
                  <dd class="col-xl-6 rating_stars rating_outside"><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i></dd>

                  <dt class="col-xl-6">Surroundings</dt>
                  <dd class="col-xl-6 rating_stars rating_surroundings"><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i></dd>

                  <dt class="col-xl-6">Commute</dt>
                  <dd class="col-xl-6 rating_stars rating_commute"><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i></dd>

                  <dt class="col-xl-6">Drift costs</dt>
                  <dd class="col-xl-6 rating_stars rating_drift_costs"><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i></dd>
                </dl>
                <textarea class="form-control results_comment"></textarea>
              </div>
              <div class="col-6 rating_person rating_person_2">
                <h4><span class="d-none d-xl-inline">Rating:</span> <?php echo $ini_array['person_2_name']; ?></h4>
                <div class="btn-group d-flex rating_yesno" role="group">
                  <button class="btn w-100 btn-outline-success mb-2 rating_overall_yes"><i class="fa fa-thumbs-up"></i></button>
                  <button class="btn w-100 btn-outline-danger mb-2 rating_overall_no"><i class="fa fa-thumbs-down"></i></button>
                </div>
                <dl class="row">
                  <dt class="col-xl-6">Inside</dt>
                  <dd class="col-xl-6 rating_stars rating_inside"><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i></dd>

                  <dt class="col-xl-6">Outside</dt>
                  <dd class="col-xl-6 rating_stars rating_outside"><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i></dd>

                  <dt class="col-xl-6">Surroundings</dt>
                  <dd class="col-xl-6 rating_stars rating_surroundings"><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i></dd>

                  <dt class="col-xl-6">Commute</dt>
                  <dd class="col-xl-6 rating_stars rating_commute"><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i></dd>

                  <dt class="col-xl-6">Drift costs</dt>
                  <dd class="col-xl-6 rating_stars rating_drift_costs"><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i><i class="fa fa-star text-black-50" aria-hidden="true"></i></dd>
                </dl>
                <textarea class="form-control results_comment"></textarea>
              </div>
            </div>
            <p class="p-3 mb-2 bg-success text-white" style="display:none;" id="saving_ratings_notification">Saving ratings..</p>

            <hr class="my-5">
            <a class="btn btn-outline-secondary btn-sm mb-2" data-toggle="collapse" href="#allJSON_info" role="button" aria-expanded="false" aria-controls="allJSON_info">
              Show all information
            </a>
            <div class="collapse" id="allJSON_info">
              <div class="card card-body">
                <pre class="focus_data small"></pre>
              </div>
            </div>
          </div>
      </div>

    </div>
  </div>

</div>

<footer>
  <div class="container">
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
<script src="hemnet-commuter.js"></script>
</body>
</html>
