
//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

hemnet_results = {};

$(function(){

  // Load previous form inputs if we have them saved
  load_form_inputs();

  // Load a base-map
  initMap();

  // Add Hemnet RSS searches

  // Add and remove commute rows
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
      $('#search-btn').val('Search').prop('disabled', false);
    });

    // Hemnet results fetched, now filter with Google Maps
    rss_promise.done(function(message) {
      var commute_filter_promise = filter_with_commutes();
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
    form_data = {'hemnet_rss': []};
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
      console.error("RSS URL did not match expected pattern: "+$(this).val())
      return true;
    }

    // Fetch the RSS via our own PHP script, because of stupid CORS
    var request = $.post( "mirror_hemnet.php",  { s_id: matches[1] }, function( data ) {
      try {
        if(data['status'] == "error"){
          console.error(data['msg']);
          return true;
        }
        for (var i = 0; i < data['item'].length; i++) {
          d = data['item'][i];
          hemnet_results[d['link']] = d;
        }
      } catch (e){
        console.error("Something went wrong whilst parsing the Hemnet RSS.");
        console.error(e);
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
 * Function to take the hemnet results and filter by commute times
 */
function filter_with_commutes(){

  // jQuery promise
  var dfd = new $.Deferred();


  return dfd.promise();

}


/**
 * Function to initialise the Google Map (before anything happens)
 */
// function initMap() {
//   // Create a map object and specify the DOM element for display.
//   var map = new google.maps.Map(document.getElementById('map'), {
//     // center: {lat: -34.397, lng: 150.644},
//     scrollwheel: false,
//     zoom: 8
//   });
// }