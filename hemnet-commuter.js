
//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

hemnet_rss = {};
geocoded_addresses = {};
traveltime_time_maps = {};
scrape_hemnet_results = {};
commute_results = [];
hemnet_results = {};
commute_shapes = {};

$(function(){

  // Load the browser cache
  load_browser_cache();

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
    rss_promise.done(function() {

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
      geocode_commute_promise.done(function() {

        // Get the intersection travel times map
        var commute_intersection_map_promise = get_commute_intersection_map();

        // Travel time intersection map done
        commute_intersection_map_promise.done(function(data){

          // Save to global variable
          commute_shapes = data;

          // Get the hemnet results locations
          var geocode_hemnet_promise = geocode_hemnet_results();

          // Hemnet geocoding done, now plot everything
          geocode_hemnet_promise.done(function() {

            $('.results_card').show();
            make_results_map();
            make_results_table();
            $('#status-msg').text("Found "+$('#results_table tbody tr:not(.table-active)').length+" out of "+$('#results_table tbody tr').length+" properties");
            $('#search-btn').val('Search').prop('disabled', false);

          });

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
  if(i > 25){
    alert('Can only have a maximum of 25 commute locations');
    return false;
  };
  row.find('.commute_address').attr('id', 'commute_address_'+i).attr('name', 'commute_address_'+i).val('');
  row.find('.commute_time').attr('id', 'commute_time_'+i).attr('name', 'commute_time_'+i).val('00:40');
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
      'traveltime_api_id': $('#traveltime_api_id').val(),
      'traveltime_api_key': $('#traveltime_api_key').val(),
      'commute_hidemarkers_outside': $('#commute_hidemarkers_outside').is(':checked'),
      'hemnet_rss': []
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

    // Hide TravelTime API details
    if($('#traveltime_api_id').val() && $('#traveltime_api_key').val()){
      $('#traveltime_api_details').hide();
    }
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

      if(form_data['traveltime_api_id'] != undefined){ $('#traveltime_api_id').val(form_data['traveltime_api_id']); }
      if(form_data['traveltime_api_key'] != undefined){ $('#traveltime_api_key').val(form_data['traveltime_api_key']); }
      if(form_data['traveltime_api_id'] != undefined && form_data['traveltime_api_key'] != undefined){
        $('#traveltime_api_details').hide();
      }
      if(form_data['commute_hidemarkers_outside'] != undefined || form_data['commute_hidemarkers_outside']){
        $('#commute_hidemarkers_outside').attr('checked', true);
      }

      if(form_data['hemnet_rss'] != undefined){

        // Fill in RSS feed values
        for (var i = 0; i < form_data['hemnet_rss'].length; i++) {
          if(i > $('.hemnet_rss_row').length - 1){
            add_rss_row();
          }
          $('.hemnet_rss_row').last().find('.hemnet_rss').val(form_data['hemnet_rss'][i]);
          // Label with RSS name / number of results if we have this cached
          $.each(hemnet_rss_cache, function(rss_url, d){
            if(rss_url == form_data['hemnet_rss'][i]){
              $('.hemnet_rss_row').last().find('.hemnet_rss_title').html('<span class="badge badge-pill badge-info">'+hemnet_rss_cache[rss_url].title+'</span> <span class="badge badge-pill badge-success">'+hemnet_rss_cache[rss_url].item.length+' results</span>');
              if(hemnet_rss_cache[rss_url].item.length >= 30){
                $('.hemnet_rss_row').last().find('.hemnet_rss_title').addClass('bg-danger text-white px-1').removeClass('text-muted');
                $('.hemnet_rss_row').last().find('.hemnet_rss_title .badge').addClass('badge-warning').removeClass('badge-success');
              }
            }
          });
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
 * Load local storage cache in to global variables
 */
function load_browser_cache(){
  if (typeof(Storage) == "undefined") {
    console.log("Can't load localStorage cache - not supported");
    return;
  }

  // HemNet RSS Feeds
  hemnet_rss_cache = localStorage.getItem("hemnet-commuter-hemnet_rss");
  if(hemnet_rss_cache != null){
    hemnet_rss_cache = JSON.parse(hemnet_rss_cache);
    // Delete all old cache results
    var max_age = (new Date()).getTime() - 86400000; // 24 hours in milliseconds
    $.each(hemnet_rss, function(url, data){
      if(data.date_fetched < max_age || !data.hasOwnProperty(date_fetched)){
        delete hemnet_rss[url];
        console.log('Deleted old hemnet RSS cache for '+url);
      } else {
        console.log('Cached hemnet RSS fresh enough ('+data.date_fetched+') for '+url);
      }
    });

    // Only restore those URLs that are in the form
    $('.hemnet_rss').each(function(){
      var rss_url = $(this).val();
      if(hemnet_rss_cache.hasOwnProperty(rss_url)){
        hemnet_rss[rss_url] = hemnet_rss_cache[rss_url];
        console.log('Used browser cache for Hemnet RSS: '+rss_url);
      }
    });
  }

  // Geocoding results
  geocoded_addresses_cache = localStorage.getItem("hemnet-commuter-geocoded_addresses");
  if(geocoded_addresses_cache != null){
    geocoded_addresses = JSON.parse(geocoded_addresses_cache);
    console.log('Restored geocoded_addresses cache: ', geocoded_addresses);
  }

  // TravelTime time maps
  traveltime_time_maps_cache = localStorage.getItem("hemnet-commuter-traveltime_time_maps");
  if(traveltime_time_maps_cache != null){
    traveltime_time_maps = JSON.parse(traveltime_time_maps_cache);
    console.log('Restored traveltime_time_maps cache');
  }

  // Hemnet scrapes
  scrape_hemnet_results_cache = localStorage.getItem("hemnet-commuter-scrape_hemnet_results");
  if(scrape_hemnet_results_cache != null){
    scrape_hemnet_results = JSON.parse(scrape_hemnet_results_cache);
    console.log('Restored hemnet scrapes cache');
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
  $('.hemnet_rss_row').each(function(){
    var hemnet_row = $(this);

    // Match the RSS search ID
    var rss_url = hemnet_row.find('.hemnet_rss').val();
    var matches = rss_url.match(/https:\/\/www.hemnet.se\/mitt_hemnet\/sparade_sokningar\/(\d+)\.xml/);
    if(matches == null){
      dfd.reject("RSS URL did not match expected pattern: "+rss_url);
      return false;
    }

    // Check if we already have this cached
    if(hemnet_rss.hasOwnProperty(rss_url)){
      console.log('Skipping '+rss_url+' as found in the browser cache');
      for (var i = 0; i < hemnet_rss[rss_url].item.length; i++) {
        d = hemnet_rss[rss_url].item[i];
        hemnet_results[d['link']] = d;
      }
      // Show title and how many results this RSS feed had, warn if 30
      hemnet_row.find('.hemnet_rss_title').html('<span class="badge badge-pill badge-info">'+hemnet_rss[rss_url].title+'</span> <span class="badge badge-pill badge-success">'+hemnet_rss[rss_url].item.length+' results</span>');
      if(hemnet_rss[rss_url].item.length >= 30){
        hemnet_row.find('.hemnet_rss_title').addClass('bg-danger text-white px-1').removeClass('text-muted');
        hemnet_row.find('.hemnet_rss_title .badge').addClass('badge-warning').removeClass('badge-success');
      }
      return dfd.resolve();
    }

    // Fetch the RSS via our own PHP script, because of stupid CORS
    var request = $.post( "mirror_hemnet.php",  { s_id: matches[1] }, function( data ) {
      try {
        // Something was wrong
        if(data['status'] == "error"){
          dfd.reject("Could not load RSS "+data['msg']);
          return false;
        }
        // All good - save the results
        hemnet_rss[rss_url] = data;
        hemnet_rss[rss_url].date_fetched = (new Date()).getTime();
        for (var i = 0; i < data['item'].length; i++) {
          d = data['item'][i];
          hemnet_results[d['link']] = d;
        }
        // Show title and how many results this RSS feed had, warn if 30
        hemnet_row.find('.hemnet_rss_title').html('<span class="badge badge-pill badge-info">'+hemnet_rss[rss_url].title+'</span> <span class="badge badge-pill badge-success">'+hemnet_rss[rss_url].item.length+' results</span>');
        if(hemnet_rss[rss_url].item.length >= 30){
          hemnet_row.find('.hemnet_rss_title').addClass('bg-danger text-white px-1').removeClass('text-muted');
          hemnet_row.find('.hemnet_rss_title .badge').addClass('badge-warning').removeClass('badge-success');
        }

        // Cache the results for next time
        if (typeof(Storage) != "undefined") {
          console.log('Caching HemNet RSS for '+data['link']);
          localStorage.setItem("hemnet-commuter-hemnet_rss", JSON.stringify(hemnet_rss));
        }
      } catch (e){
        dfd.reject("Something went wrong whilst parsing the Hemnet RSS.");
        console.error(e);
        console.info(data);
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
    var tparts = $('#commute_time_'+i).val().split(':');
    var tsecs = (tparts[0]*60*60)+(tparts[1]*60);
    commute_results.push({
      'title': $('#commute_address_'+i).val(),
      'max_commute': $('#commute_time_'+i).val(),
      'max_commute_secs': tsecs
    });
    i++;
  }

  $('#status-msg').text("Trying to find "+commute_results.length+" commute locations");

  // Go through each hemnet result and find location
  var promises = [];
  for (var i = 0; i < commute_results.length; i++) {
    promises.push( geocode_address(commute_results[i]['title']) );
  }

  // All requests finished
  $.when.apply($, promises).then(function(d){
    var arguments = (promises.length === 1) ? [arguments] : arguments;
    $.each(arguments, function (i, args) {
      if(args[1] == 'success'){
        if(args[0]['features'].length > 1){
          console.warn("Warning: - more than one location found for address: "+commute_results[i]['title'], args[0]['features']);
        }
        commute_results[i]['locations'] = args[0]['features'][0];
      } else {
        dfd.reject("Error - could not find commute address: "+commute_results[i]['title']);
      }
    });
    dfd.resolve();
  });

  return dfd.promise();

}


/**
 * Get an intersection map of commute times
 */
function get_commute_intersection_map(){

  // jQuery promise
  var dfd = new $.Deferred();

  var api_request = {
    "arrival_searches": [],
    "intersections": [{
        "id": "intersection of commute times",
        "search_ids": []
    }]
  };

  for (var i = 0; i < commute_results.length; i++) {
    api_request.arrival_searches.push({
      "id": "commute from "+commute_results[i]['title'],
      "coords": {
        "lat": commute_results[i]['locations']['geometry']['coordinates'][1],
        "lng": commute_results[i]['locations']['geometry']['coordinates'][0],
      },
      "transportation": { "type": "public_transport" },
      "arrival_time": "2020-01-31T09:00:00Z",
      "range": {
        "enabled": true,
        "width": 3600 // allow arrival between 8 and 9
      },
      "travel_time": commute_results[i]['max_commute_secs']
    });
    api_request.intersections[0].search_ids.push("commute from "+commute_results[i]['title']);
  }
  api_request_json = JSON.stringify(api_request);

  // Get hash of POST vars for caching ID
  function make_hash(obj) {
    var hash = 0, i, chr;
    if (obj.length === 0) return hash;
    for (i = 0; i < obj.length; i++) {
      chr   = obj.charCodeAt(i);
      hash  = ((hash << 5) - hash) + chr;
      hash |= 0; // Convert to 32bit integer
    }
    return hash;
  };
  api_post_hash_id = make_hash(api_request_json);

  // Check if we already have this cached
  if(traveltime_time_maps.hasOwnProperty(api_post_hash_id)){
    console.log('Skipping TimeTravel map as found in the browser cache');
    return $.Deferred().resolve(traveltime_time_maps[api_post_hash_id]);
  }

  var url = 'https://api.traveltimeapp.com/v4/time-map';
  return $.ajax({
    url: url,
    type: 'POST',
    data: api_request_json,
    contentType: "application/json; charset=utf-8",
    dataType: 'json',
    success: function(e) {
      console.info('Getting intersection map worked!');
      // Cache the results for next time
      if (typeof(Storage) != "undefined") {
        console.log('Caching TravelTime map');
        traveltime_time_maps[api_post_hash_id] = e;
        localStorage.setItem("hemnet-commuter-traveltime_time_maps", JSON.stringify(traveltime_time_maps));
      }
    },
    error: function(e) { console.error(e.responseJSON); },
    beforeSend: setTimeTravelAPIHeader
  });
}


/**
 * Geocode the hemnet results to get locations
 */
function geocode_hemnet_results(){

  // jQuery promise
  var dfd = new $.Deferred();

  $('#status-msg').text("Trying to find "+Object.keys(hemnet_results).length+" house locations");

  // Try to scrape hemnet web page for each result
  var hn_promises = [];
  for (var k in hemnet_results){
    hn_promises.push( scrape_hemnet(k) );
  }

  // Go through each hemnet result and find location if not scraped
  $.when.apply(null, hn_promises).then(function(d){
    var keys = [];
    var promises = [];
    var hn_addresses = [];
    for (var k in hemnet_results){
      if(!('locations' in hemnet_results[k])){
        // Strip floor number from address title, eg ", 3tr"
        var address = hemnet_results[k]['title'].replace(/,?\s?\dtr\.?/, '');
        // Append the 'postal_city' from the web scrape if we have it
        try {
          address += ', '+hemnet_results[k].dataLayer.locations.postal_city.replace('+', ' ').split(', ')[0];
        } catch(e) {e}
        hn_addresses.push(address);
        keys.push(k);
        promises.push( geocode_address(address) );
      }
    }

    // No requests needed to be fired
    if(promises.length == 0){
      dfd.resolve();
    }

    // All requests finished
    $.when.apply($, promises).then(function(d){
      var arguments = (promises.length === 1) ? [arguments] : arguments;
      $.each(arguments, function (i, args) {
        var k = keys[i];
        if(args[1] == 'success'){
          if(args[0]['features'].length > 1){
            console.warn("Warning: - more than one location found for address: "+hn_addresses[i], args[0]['features']);
          }
          hemnet_results[k]['locations'] = args[0]['features'][0];
        } else {
          console.warn("Could not find address: "+hemnet_results[k]['title']);
        }
      });
      dfd.resolve();
    });

  });

  return dfd.promise();

}

/**
 * Function to scrape hemnet web page for stuff that's missing from RSS
 */
function scrape_hemnet(url){
  if(scrape_hemnet_results.hasOwnProperty(url)){
    // Found a cached copy
    console.log('Found localstorage cache of Hemnet webpage '+url);
    hemnet_results[url].front_image = scrape_hemnet_results[url].front_image;
    hemnet_results[url].dataLayer = scrape_hemnet_results[url].dataLayer;
    hemnet_results[url].infostring = make_info_string(scrape_hemnet_results[url].dataLayer);
    return new $.Deferred().resolve();

  } else {
    var dfd = new $.Deferred();
    $.post( "mirror_hemnet.php",  { hnurl: url }, function( html ) {
      scrape_hemnet_results[url] = parse_hemnet_scrape(html);
      hemnet_results[url].front_image = scrape_hemnet_results[url].front_image;
      hemnet_results[url].dataLayer = scrape_hemnet_results[url].dataLayer;
      hemnet_results[url].infostring = make_info_string(scrape_hemnet_results[url].dataLayer);
      // Save to cache
      console.log('Caching hemnet page '+url);
      localStorage.setItem("hemnet-commuter-scrape_hemnet_results", JSON.stringify(scrape_hemnet_results));
      dfd.resolve();
    });
    return dfd.promise();
  }
}
function parse_hemnet_scrape(html){
  var scraped_info = {};
  var img_matches = html.match(/<meta property="og:image" content="([^"]+)">/);
  if(img_matches){
    scraped_info.front_image = img_matches[1];
  }
  var data_match = html.match(/dataLayer = ([^;]+);/);
  if(data_match){
    var hd = JSON.parse(data_match[1]);
    try {
      scraped_info.dataLayer = hd[1]['property'];
    } catch(e){
      console.log("Scraped dataLayer but couldn't access hd[1]['property']");
    }
  }
  return scraped_info;
}
function make_info_string(dataLayer){
  var infostring = '';
  // Parse variables
  try{
    // District can be weirdly duplicated
    var districts = dataLayer['locations']['district'].split(', ');
    var unique_districts = [];
    $.each(districts, function(i, el){
      if($.inArray(el, unique_districts) === -1) unique_districts.push(el);
    });
    infostring += '<small class="text-muted">'+unique_districts.join(', ')+'</small>';
  } catch(e){ }
  try { var boarea = dataLayer['living_area'].toLocaleString(); } catch(e) { var boarea = '?'; }
  try { var biarea = dataLayer['supplemental_area'].toLocaleString(); } catch(e) { var biarea = '?'; }
  try { var price = (dataLayer['price']/1000000).toFixed(2); } catch(e) { var price = '?'; }
  try { var driftk = parseInt(dataLayer['driftkostnad'] / 12).toLocaleString(); } catch(e) { var driftk = '?'; }
  try { var const_yr = parseInt(dataLayer['construction_year']); } catch(e) { var const_yr = '?'; }
  try { var avgift = dataLayer['borattavgift'].toLocaleString(); } catch(e) { var avgift = '?'; }

  // Make a string
  infostring += '<br><small class="text-muted">Pris: <strong>'+price+' Mkr</strong>, Drift: '+driftk+' kr/m</small>';
  infostring += '<br><small class="text-muted mr-3">Bo: '+boarea+' m<sup>2</sup>, Bi: '+biarea+' m<sup>2</sup></small>';
  infostring += '<br><small class="text-muted">Built: '+const_yr+'</small>';
  if(avgift != '?') { infostring += '<br><small class="text-muted">'+avgift+' kr avgift</small>'; }

  return infostring;
}


/**
 * Set headers for TravelTime API
 */
function setTimeTravelAPIHeader(xhr) {
  xhr.setRequestHeader('X-Application-Id', $('#traveltime_api_id').val());
  xhr.setRequestHeader('X-Api-Key', $('#traveltime_api_key').val());
  xhr.setRequestHeader('Accept-Language', 'en');
}

/**
 * Function to find lat and long from street address
 */
function geocode_address(address){
  // Check if we already have this cached
  if(geocoded_addresses.hasOwnProperty(address)){
    console.log('Skipping geocoding '+address+' as found in the browser cache');
    return $.Deferred().resolve([geocoded_addresses[address], 'success']);
  }

  // Start centred on Stockholm
  var focus_lat = '59.322619'
  var focus_lng = '18.073022';
  var url = 'https://api.traveltimeapp.com/v4/geocoding/search?within.country=SWE&query='+encodeURIComponent(address)+'&focus.lat='+focus_lat+'&focus.lng='+focus_lng;
  return $.ajax({
    url: url,
    type: 'GET',
    dataType: 'json',
    success: function(e) {
      console.info('Geocoding worked: '+address, e.features);
      // Cache the results for next time
      if (typeof(Storage) != "undefined") {
        console.log('Caching geocoding for '+address);
        geocoded_addresses[address] = e;
        localStorage.setItem("hemnet-commuter-geocoded_addresses", JSON.stringify(geocoded_addresses));
      }
    },
    error: function(e) { console.error(e.responseJSON); alert('Could not geocode address: '+address); },
    beforeSend: setTimeTravelAPIHeader
  });
}


/**
 * Function to print the results table once everything has been done
 */
// TODO - update this table to show something useful and not missing values
function make_results_table(){
  // Add header columns for commutes
  for (var i = 0; i < commute_results.length; i++) {
    $('#results_table thead tr').append('<th>'+commute_results[i]['title']+'</th>');
  }
  // Collect result table rows
  var trows = [];
  for (var k in hemnet_results){
    var ccols = '';
    var commute_ok = false;
    var max_commute_secs = false;
    var max_commute = '<td></td>';
    for (var i = 0; i < commute_results.length; i++) {
      var ctime = '';
      var csecs = false;
      var cclass = 'danger';
      var tdclass = 'active';
      try {
        ctime = hemnet_results[k]['locations']['commutes'][i]['duration']['text'];
        csecs = hemnet_results[k]['locations']['commutes'][i]['duration']['value'];
        if(csecs > commute_results[i]['max_commute_secs']){
          tdclass = 'danger';
        } else {
          tdclass = 'success';
        }
        if(hemnet_results[k]['locations']['commute_ok']){
          cclass = 'success';
        }
      } catch(e){
        ctime = '?'
        csecs = 9999999999999999999;
        cclass = 'active';
      }
      if(!max_commute_secs || csecs > max_commute_secs){
        max_commute_secs = csecs;
        max_commute = '<td class="table-'+cclass+'">'+ctime+'</td>';
        hemnet_results[k]['max_commute'] = ctime;
      }
      ccols += '<td class="table-'+tdclass+'">'+ctime+'</td>';
    }
    if(hemnet_results[k]['front_image'] == undefined){
      img_thumb = '&nbsp;';
    } else {
      img_thumb = '<a href="'+k+'" target="_blank"><img src="'+hemnet_results[k]['front_image']+'"></a>';
    }
    trows.push([max_commute_secs, ' \
      <tr> \
        <td class="hn_thumb table-'+cclass+'">'+img_thumb+'</td> \
        <td class="table-'+cclass+'"> \
          <a href="'+k+'" target="_blank">'+hemnet_results[k]['title']+'</a> <br> \
          '+ hemnet_results[k]['infostring'] + '\
        </td> \
        '+max_commute+'\
        '+ccols+'\
      </tr> \
    ']);
  }
  // Sort and print to table
  trows.sort(function(left, right) {
    return left[0] < right[0] ? -1 : 1;
  });
  for (var i = 0; i < trows.length; i++) {
    $('#results_table tbody').append(trows[i][1]);
  }
}


/**
 * Build Leaflet Map to display results
 */
var infowindow = null;
function make_results_map() {

  // Make the base map
  var map = L.map('results_map').setView([59.334591, 18.063240], 10);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(map);

  //
  // Plot the TravelTime shapes
  //
  var traveltime_shapes = {};
  // Loop through each commute location result, including intersection
  for (var k in commute_shapes.results){
    tt_polygons = [];
    // Loop through each shape
    for(var s in commute_shapes.results[k].shapes){
      var shell = [];
      for(var c in commute_shapes.results[k].shapes[s].shell){
        shell.push([
          commute_shapes.results[k].shapes[s].shell[c].lat,
          commute_shapes.results[k].shapes[s].shell[c].lng
        ]);
      }
      var coords = [shell];
      // Loop through each hole in this shape
      for(var h in commute_shapes.results[k].shapes[s].holes){
        var hole = [];
        // Loop through the hole
        for(var c in commute_shapes.results[k].shapes[s].holes[h]){
          hole.push([
            commute_shapes.results[k].shapes[s].holes[h][c].lat,
            commute_shapes.results[k].shapes[s].holes[h][c].lng
          ]);
        }
        coords.push(hole);
      }
      // Make a polygon with the shell and all of the holes
      tt_polygons.push(L.polygon(coords));
    }
    // Make a group from the polygons and store it
    traveltime_shapes[commute_shapes.results[k].search_id] = L.layerGroup(tt_polygons);

    // Only show the intersection by default
    if(commute_shapes.results[k].search_id == "intersection of commute times"){
      traveltime_shapes[commute_shapes.results[k].search_id].addTo(map);
    }
  }

  // Make a map control box to be able to select travel time polygons
  L.control.layers(null, traveltime_shapes).addTo(map);

  //
  // Hemnet result markers
  //

  // Custom colour marker icons
  function make_markerconfig(mcol){
    return markerconfig = {
      iconUrl: 'https://cdn.rawgit.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-'+mcol+'.png',
      shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
      iconSize: [25, 41],
      iconAnchor: [12, 41],
      popupAnchor: [1, -34],
      shadowSize: [41, 41]
    };
  }

  // Plot the hemnet results
  var mapmarkers = [];
  for (var k in hemnet_results){
    // Check we have a location
    if(!('locations' in hemnet_results[k])){
      continue;
    }

    try {

      // Plot the marker
      var loc = hemnet_results[k]['locations']['geometry']['coordinates'];
      if($('#commute_hidemarkers_outside').not(':checked') || in_polygon){
        var markerIcon = new L.Icon(make_markerconfig('yellow'));
        if(hemnet_results[k]['locations']['commute_ok'] === undefined){
          markerIcon = new L.Icon(make_markerconfig('blue'));
        } else if(hemnet_results[k]['locations']['commute_ok'] === true){
          markerIcon = new L.Icon(make_markerconfig('green'));
        } else {
          markerIcon = new L.Icon(make_markerconfig('red'));
        }
        mapmarkers.push( L.marker(
          [parseFloat(loc[1]), parseFloat(loc[0])],
          {icon: markerIcon}
        ).bindPopup(
          '<h5><a href="'+k+'" target="_blank">'+hemnet_results[k]['title']+'</a></h5> \
          <p><img src="'+hemnet_results[k]['front_image']+'" style="width:100%"></p> \
          <p style="font-size:130%">'+hemnet_results[k]['infostring']+'</p>'
        ) );
      }
    } catch(e){
      console.warn("Couldn't plot map marker", hemnet_results[k])
    }

  }

  // Plot the markers and scale the map
  var mapmarkers_group = L.featureGroup(mapmarkers);
  mapmarkers_group.addTo(map);
  map.fitBounds(mapmarkers_group.getBounds());
}
