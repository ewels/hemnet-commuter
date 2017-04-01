
//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

commute_results = [];
hemnet_results = {};

$(function(){

  // Load previous form inputs if we have them saved
  load_form_inputs();

  // Add Hemnet RSS searches
  $(".hemnet_rss_card").on('click', '.hemnet_rss_add_btn', function(e){ e.preventDefault(); e.stopPropagation(); add_rss_row(); });
  $(".hemnet_rss_card").on('click', '.hemnet_rss_delete_btn', function(e){ e.preventDefault(); e.stopPropagation(); remove_rss_row(); });

  // Add and remove commute rows
  $('#commute_addrow').click(function(e){ e.preventDefault(); add_commute_row(); });
  $("#commute-table tbody").on('click', '.commute_deleterow', function(e){ e.preventDefault(); remove_commute_row(); });

  // Search!
  $('form').submit(function(e){
    e.preventDefault();
    save_form_inputs();
    var rss_promise = load_hemnet_rss();

    // Something went wrong getting the RSS results
    rss_promise.fail(function(message) {
      alert(message);
      $('#status-msg').text(message);
      $('#search-btn').val('Search').prop('disabled', false);
      commute_results = [];
      hemnet_results = {};
    });

    // Hemnet results fetch worked
    rss_promise.done(function(message) {

      // Get the commute locations
      var geocode_commute_promise = geocode_commute_entries();

      // Something went wrong with getting the commute locations
      geocode_commute_promise.fail(function(message) {
        alert(message);
        $('#status-msg').text(message);
        $('#search-btn').val('Search').prop('disabled', false);
        commute_results = [];
        hemnet_results = {};
      });

      // Commute locations worked
      geocode_commute_promise.done(function(message) {

        // Get the hemnet results locations
        var geocode_hemnet_promise = geocode_hemnet_results();

        // Hemnet geocoding done, now get commute times
        geocode_hemnet_promise.done(function(message) {
          console.log(commute_results);
          console.log(hemnet_results);
          // TODO - See https://developers.google.com/maps/documentation/distance-matrix/
        });

      });

    });

  });

});


/**
 * Add a row to the commute table
 */
function add_commute_row(){
  // Make a copy of the first row in the commute table
  var row = $('#commute-table tbody tr:first-child').clone();
  var i = $('#commute-table tbody tr').length + 1;
  row.find('.commute_address').attr('id', 'commute_address_'+i).attr('name', 'commute_address_'+i).val('');
  row.find('.commute_time').attr('id', 'commute_time_'+i).attr('name', 'commute_time_'+i).val('01:00');
  row.find('.commute_deleterow').attr('id', 'commute_deleterow_'+i).prop('disabled', false).show();
  row.hide().appendTo($('#commute-table tbody')).fadeIn('fast');
  $("#commute-table tbody tr:not(:last-child) .commute_deleterow").hide();
}
/**
 * Remove a row from the commute table
 */
function remove_commute_row(){
  $("#commute-table tbody tr:last-child").remove();
  $("#commute-table tbody tr:last-child .commute_deleterow").show();
}

/**
 * Add a row to the RSS feeds
 */
function add_rss_row(){
  // Make a copy of the first row in the commute table
  var row = $('.hemnet_rss_card .hemnet_rss_row').first().clone();
  row.find('.hemnet_rss').val('');
  row.find('.hemnet_rss_delete, .hemnet_rss_add_btn').show();
  row.hide().appendTo($('.hemnet_rss_rows')).fadeIn('fast');
  $(".hemnet_rss_card .hemnet_rss_row:not(:last-child) .hemnet_rss_add_btn").hide();
  $(".hemnet_rss_card .hemnet_rss_row:not(:last-child) .hemnet_rss_delete").hide();
}
/**
 * Remove a row from the RSS feeds
 */
function remove_rss_row(){
  $(".hemnet_rss_card .hemnet_rss_row:last-child").remove();
  $(".hemnet_rss_card .hemnet_rss_row:last-child .hemnet_rss_add_btn").show();
  $(".hemnet_rss_card .hemnet_rss_row:last-child .hemnet_rss_delete").show();
  if($(".hemnet_rss_card .hemnet_rss_row").length == 1){
    $(".hemnet_rss_delete").hide();
  }
}

/**
 * Save the entered form inputs to browser localstorage
 */
function save_form_inputs(){
  if (typeof(Storage) == "undefined") {
    console.log("localstorage not supported in this browser");
    return false;
  } else {
    // Get form values
    form_data = {
      'hemnet_rss': [],
      'hemnet_append_address': $('#hemnet_append_address').val()
    };
    $('.hemnet_rss').each(function(){
      form_data['hemnet_rss'].push($(this).val());
    });
    var i = 1;
    while($('#commute_address_'+i).length){
      form_data['commute_address_'+i] = $('#commute_address_'+i).val();
      form_data['commute_time_'+i] = $('#commute_time_'+i).val();
      i++;
    }
    // Encode and save with local storage
    form_json = JSON.stringify(form_data);
    localStorage.setItem("hemnet-commuter", form_json);
  }
}
/**
 * Load the localstorage form inputs if found, and populate table
 */
function load_form_inputs(){
  if (typeof(Storage) == "undefined") {
    console.log("localstorage not supported in this browser");
    return false;
  } else {
    form_json = localStorage.getItem("hemnet-commuter");
    if(form_json != null){
      form_data = JSON.parse(form_json);
      if(form_data['hemnet_rss'] != undefined){

        // Append to Address
        $('#hemnet_append_address').val(form_data['hemnet_append_address']);

        // Fill in RSS feed values
        for (var i = 0; i < form_data['hemnet_rss'].length; i++) {
          if(i > $('.hemnet_rss_row').length - 1){
            add_rss_row();
          }
          $('.hemnet_rss_row').last().find('.hemnet_rss').val(form_data['hemnet_rss'][i]);
        }
        // Fill in commute values
        var i = 1;
        while(form_data['commute_address_'+i] != undefined){
          if($('#commute_address_'+i).length == 0){ add_commute_row(); }
          $('#commute_address_'+i).val(form_data['commute_address_'+i]);
          $('#commute_time_'+i).val(form_data['commute_time_'+i]);
          i++;
        }
      }
    } else {
      console.info("No localstorage results found");
    }
  }
}

/**
 * Main hemnet-commuter search function
 */
function load_hemnet_rss(){

  // jQuery promise
  var dfd = new $.Deferred();

  // Disable search button and show loading status
  $('#search-btn').val('Searching..').prop('disabled', true);
  $('#status-text').show();

  $('#status-msg').text("Fetching search data");
  var promises = [];
  $('.hemnet_rss').each(function(){

    // Match the RSS search ID
    var matches = $(this).val().match(/https:\/\/www.hemnet.se\/mitt_hemnet\/sparade_sokningar\/(\d+)\.xml/);
    if(matches == null){
      dfd.reject("RSS URL did not match expected pattern: "+$(this).val());
      return false;
    }

    // Fetch the RSS via our own PHP script, because of stupid CORS
    var request = $.post( "mirror_hemnet.php",  { s_id: matches[1] }, function( data ) {
      try {
        if(data['status'] == "error"){
          dfd.reject("Could not load RSS "+data['msg']);
          return false;
        }
        for (var i = 0; i < data['item'].length; i++) {
          d = data['item'][i];
          hemnet_results[d['link']] = d;
        }
      } catch (e){
        dfd.reject("Something went wrong whilst parsing the Hemnet RSS.");
        console.error(e);
        return false;
      }
    });
    promises.push(request);
  });

  // Wait for all AJAX calls to complete
  $.when.apply(null, promises).done(function(){

    $('#status-msg').text("Finished retrieving Hemnet results");

    // Check that we got some results
    var num_results = Object.keys(hemnet_results).length;
    if(num_results == 0){
      dfd.reject("Error - could not retrieve any results from Hemnet");
    } else {
      $('#status-msg').text("Found "+num_results+" Hemnet results");
      dfd.resolve();
    }

  });

  return dfd.promise();
}


/**
 * Geocode the commute inputs to get locations
 */
function geocode_commute_entries(){

  // jQuery promise
  var dfd = new $.Deferred();

  // Parse commute inputs
  var i = 1;
  while($('#commute_address_'+i).length){
    commute_results.push({
      'title': $('#commute_address_'+i).val(),
      'max_commute': $('#commute_time_'+i).val()
    });
    i++;
  }

  $('#status-msg').text("Trying to find "+commute_results.length+" commute locations with google");

  // Go through each hemnet result and find location
  var promises = [];
  for (var i = 0; i < commute_results.length; i++) {
    promises.push( geocode_address(commute_results[i]['title']) );
  }

  // All requests finished
  $.when.apply($, promises).then(function(d){
    var arg = (promises.length === 1) ? [arguments] : arguments;
    $.each(arg, function (i, args) {
      if(args[0]['status'] == 'OK'){
        if(args[0]['results'].length > 1){
          dfd.reject("Error - more than one location found for address: "+commute_results[i]['title']);
        } else {
          commute_results[i]['locations'] = args[0]['results'];
        }
      } else {
        dfd.reject("Error - could not find commute address: "+commute_results[i]['title']);
      }
    })
    dfd.resolve();
  });

  return dfd.promise();

}

/**
 * Geocode the hemnet results to get locations
 */
function geocode_hemnet_results(){

  // jQuery promise
  var dfd = new $.Deferred();

  $('#status-msg').text("Trying to find "+Object.keys(hemnet_results).length+" house locations with google");

  // Go through each hemnet result and find location
  var keys = [];
  var promises = [];
  for (var k in hemnet_results){
    var address = hemnet_results[k]['title'].replace(/,?\s?\dtr\.?/, '') + ", "+$('#hemnet_append_address').val();
    keys.push(k);
    promises.push( geocode_address(address) );
  }

  // All requests finished
  $.when.apply($, promises).then(function(d){
    var arg = (promises.length === 1) ? [arguments] : arguments;
    $.each(arg, function (idx, args) {
      var k = keys[idx];
      if(args[0]['status'] == 'OK'){
        hemnet_results[k]['locations'] = args[0]['results'];
      } else {
        console.warn("Could not find address: "+hemnet_results[k]['title']);
      }
    })
    dfd.resolve();
  });

  return dfd.promise();

}


/**
 * Function to find lat and long from street address
 */
function geocode_address(address){
  var url = 'https://maps.googleapis.com/maps/api/geocode/json?key=AIzaSyC7je4NbKJRUZPD57mGqYHjzAloEZlNgYs&address='+encodeURIComponent(address);
  return $.getJSON(url);
}



