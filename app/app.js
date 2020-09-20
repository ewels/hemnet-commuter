//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Hemnet Commuter AngularJS code
 */

var app = angular.module("hemnetCommuterApp", ['ui-leaflet']);
app.controller("hemnetCommuterController", ['$scope', '$compile', '$http', '$timeout', function ($scope, $compile, $http, $timeout) {

  // Filters
  $scope.filters = {
    hide_ratings: {},
    kommande: "0",
    bidding: "0",
    price_min: 0,
    price_max: 10000000,
    days_on_hemnet_max: 999999999,
    days_on_hemnet_min: 0,
    size_total_min: 0,
    size_tomt_min: 0,
    hide_failed_commutes: [],
  }
  // https://stackoverflow.com/a/28246130/713980
  $scope.$watch('filters.price_min', function () { $scope.filters.price_min = parseFloat($scope.filters.price_min); });
  $scope.$watch('filters.price_max', function () { $scope.filters.price_max = parseFloat($scope.filters.price_max); });
  $scope.$watch('filters.days_on_hemnet_max', function () { $scope.filters.days_on_hemnet_max = parseFloat($scope.filters.days_on_hemnet_max); });
  $scope.$watch('filters.days_on_hemnet_min', function () { $scope.filters.days_on_hemnet_min = parseFloat($scope.filters.days_on_hemnet_min); });
  $scope.$watch('filters.size_total_min', function () { $scope.filters.size_total_min = parseFloat($scope.filters.size_total_min); });
  $scope.$watch('filters.size_tomt_min', function () { $scope.filters.size_tomt_min = parseFloat($scope.filters.size_tomt_min); });

  $scope.stats = {
    price: [0, 10000000],
    size_total: [0, 10000],
    size_tomt: [0, 3000],
    days_on_hemnet: [0, 999999999],
  }
  $scope.initialising = false;
  $scope.sidebar = false;

  // Settings
  $scope.map_settings = {
    marker_colour_icon: 'none',
    marker_colour: 'none',
    marker_icon: 'none'
  }
  // Build the dropdowns
  var base_setting_select = {
    "None": { none: "None" },
    "Ratings": { rating_combined: 'Rating: Combined' },
    "Commutes": { commute_threshold_combined: 'Commute threshold: Combined' },
    "Stats": {
      status: 'Kommande/Bidding',
      price: 'Price',
      size_total: 'Total size',
      size_tomt: 'Land area',
      rooms: 'Rooms',
      days_on_hemnet: 'Days on Hemnet',
    }
  };
  $scope.map_setting_selects = {
    marker_colour_icon: JSON.parse(JSON.stringify(base_setting_select)),
    marker_colour: JSON.parse(JSON.stringify(base_setting_select)),
    marker_icon: JSON.parse(JSON.stringify(base_setting_select))
  }
  $scope.map_setting_selects.marker_colour.Commutes.commute_combined = 'Commute time: Combined';

  // Functions to colour markers
  $scope.base_marker_colour = '#aab2b9';
  $scope.set_marker_colour = {
    'none': function (house) { return $scope.base_marker_colour; },
    'rating_combined': function (house) {
      var score = null;
      for (let user_id in $scope.users) {
        if (house.ratings[user_id] == 'yes') { score += 1; }
        if (house.ratings[user_id] == 'maybe') { score += 0; }
        if (house.ratings[user_id] == 'no') { score += -1; }
      }
      if (score >= 2) { return '#28a745'; }
      if (score == 1) { return '#c3e6cb'; }
      if (score == 0) { return '#17a2b8'; }
      if (score == -1) { return '#f5c6cb'; }
      if (score <= -2) { return '#dc3545'; }
      return $scope.base_marker_colour;
    },
    'commute_threshold_combined': function (house) {
      var passes_threshold = null;
      for (let commute_id in $scope.commute_locations) {
        // Already failed another location
        if (passes_threshold == false) { continue; }
        if (house.commute_times[commute_id].pass_threshold == true) { passes_threshold = true; }
        if (house.commute_times[commute_id].pass_threshold == false) { passes_threshold = false; }
      }
      if (passes_threshold == true) { return '#28a745'; }
      else if (passes_threshold == false) { return '#dc3545'; }
      return $scope.base_marker_colour;
    },
    'commute_combined': function (house) {
      var num_commutes = 0;
      var total_time = 0;
      for (let commute_id in $scope.commute_locations) {
        // Skip if no commute time found
        if (house.commute_times[commute_id].status != 'OK') { continue; }
        total_time += parseFloat(house.commute_times[commute_id].duration_value);
        num_commutes++;
      }
      if (num_commutes > 0) {
        var avg_commute = total_time / num_commutes;
        return $scope.marker_colour_scale_commute(avg_commute).hex();
      }
      return $scope.base_marker_colour;
    },
    'status': function (house) {
      if (house.upcoming == '1') { return '#28a745'; }
      if (house.ongoing_bidding == '1') { return '#67458c'; }
      return $scope.base_marker_colour;
    },
    'price': function (house) { return $scope.marker_colour_scale_price(parseFloat(house.price)).hex(); },
    'size_total': function (house) { return $scope.marker_colour_scale_size_total(house.size_total).hex(); },
    'size_tomt': function (house) { return $scope.marker_colour_scale_size_tomt(house.land_area).hex(); },
    'rooms': function (house) { return $scope.marker_colour_scale_rooms(house.rooms).hex(); },
    'days_on_hemnet': function (house) { return $scope.marker_colour_scale_age(house.age).hex(); },
  }

  // Functions to assign icons to markers
  $scope.base_marker_icon = 'fa-circle';
  $scope.set_marker_icon = {
    'none': function (house) { return [$scope.base_marker_icon]; },
    'status': function (house) {
      if (house.upcoming == '1') { return ['fa-bolt']; }
      if (house.ongoing_bidding == '1') { return ['fa-gavel']; }
      return [$scope.base_marker_icon];
    },
    'rating_combined': function (house) {
      var col = $scope.set_marker_colour['rating_combined'](house);
      if (col == '#28a745') { return ['fa-star']; }
      if (col == '#c3e6cb') { return ['fa-thumbs-up']; }
      if (col == '#17a2b8') { return ['fa-question']; }
      if (col == '#f5c6cb') { return ['fa-thumbs-down']; }
      if (col == '#dc3545') { return ['fa-trash']; }
      return [$scope.base_marker_icon];
    },
    'commute_threshold_combined': function (house) {
      var col = $scope.set_marker_colour['commute_threshold_combined'](house);
      if (col == '#28a745') { return ['fa-check']; }
      else if (col == '#dc3545') { return ['fa-times']; }
      return ['fa-question'];
    },
    'price': function (house) { return ['fa-number', (house.price / 1000000).toFixed(1)] },
    'size_total': function (house) { return ['fa-number', house.size_total]; },
    'size_tomt': function (house) { return ['fa-number', house.land_area]; },
    'rooms': function (house) { return ['fa-number', house.rooms] },
    'days_on_hemnet': function (house) { return ['fa-number', house.age] },
  }

  // House results
  $scope.error_msg = false;
  $scope.update_results_call_active = false;
  $scope.update_results_call_requested = false;
  $scope.active_id = false;
  $scope.active_house = false;
  $scope.num_total_results = 0;
  $scope.all_results = [];
  $scope.num_results = 0;
  $scope.oldest_fetch = 0;
  $scope.needs_update = false;
  $scope.results = [];
  $scope.recent_ratings = [];
  $scope.recent_ratings_filter_user = '';
  $scope.recent_ratings_filter_type = '';
  $scope.missing_geo = [];
  $scope.users = {};
  $scope.tags = {};
  $scope.commute_locations = {};
  $scope.commute_map_call_active = false;
  $scope.hemnet_results_updating = false;
  $scope.hemnet_results_update_btn_text = 'Update';

  // Build custom leaflet buttons for map settings
  L.Control.HncBtn = L.Control.extend({
    options: {
      title: '',
      ngclick: '',
      ngclass: '',
      icon_class: ''
    },
    onAdd: function (map) {
      opts = this.options;
      var btn_link = `<div class="rounded shadow" style="background-color:white;">
        <button ng-click="${opts.ngclick}" ng-class="${opts.ngclass}" class="btn px-2" title="${opts.title}">
          <i class="fa fa-fw ${opts.icon_class}"></i>
        </button>
      </div>`;
      var linkFn = $compile(btn_link);
      var content = linkFn($scope);
      return content[0];
    }
  });


  // Set up the map
  angular.extend($scope, {
    center: {
      lat: 59.325199,
      lng: 18.071480,
      zoom: 8
    },
    markers: {},
    layers: {
      baselayers: {
        osm: {
          name: 'OpenStreetMap',
          url: 'http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
          type: 'xyz'
        }
      },
      overlays: {}
    },
    controls: {
      custom: [
        new L.Control.HncBtn({
          'title': 'Recent Ratings',
          'ngclick': `sidebar = sidebar == 'recent_ratings' ? false : 'recent_ratings'; update_recent_ratings();`,
          'ngclass': `sidebar == 'recent_ratings' ? 'btn-success' : 'btn-outline-success'`,
          'icon_class': 'fa-clock-o'
        }),
        new L.Control.HncBtn({
          'title': 'Filters',
          'ngclick': `sidebar = sidebar == 'filters' ? false : 'filters'`,
          'ngclass': `sidebar == 'filters' ? 'btn-secondary' : 'btn-outline-secondary'`,
          'icon_class': 'fa-filter'
        }),
        new L.Control.HncBtn({
          'title': 'Commutes',
          'ngclick': `sidebar = sidebar == 'commute' ? false : 'commute'`,
          'ngclass': `sidebar == 'commute' ? 'btn-primary' : 'btn-outline-primary'`,
          'icon_class': 'fa-bus'
        }),
        new L.Control.HncBtn({
          'title': 'Map Settings',
          'ngclick': `sidebar = sidebar == 'map' ? false : 'map'`,
          'ngclass': `sidebar == 'map' ? 'btn-info' : 'btn-outline-info'`,
          'icon_class': 'fa-map-marker'
        }),
      ]
    }
  });

  // Get the map markers
  $scope.update_results = function (init_call) {

    if ($scope.initialising) {
      console.log("Ignoring update_results - still initialising");
      return;
    }

    // Don't fire too frequently
    if ($scope.update_results_call_active) {
      $scope.update_results_call_requested = true;
      return;
    }
    $scope.update_results_call_active = true;
    $scope.update_results_call_requested = false;

    // Build filters POST data
    var postdata = {};
    if ($scope.filters.kommande != "0") {
      postdata.kommande = $scope.filters.kommande;
    }
    if ($scope.filters.bidding != "0") {
      postdata.bidding = $scope.filters.bidding;
    }
    if ($scope.filters.price_min != $scope.stats.price[0]) {
      postdata.price_min = $scope.filters.price_min;
    }
    if ($scope.filters.price_max != $scope.stats.price[1]) {
      postdata.price_max = $scope.filters.price_max;
    }
    if ($scope.filters.days_on_hemnet_min != $scope.stats.days_on_hemnet[0]) {
      postdata.days_on_hemnet_min = $scope.filters.days_on_hemnet_min;
    }
    if ($scope.filters.days_on_hemnet_max != $scope.stats.days_on_hemnet[1]) {
      postdata.days_on_hemnet_max = $scope.filters.days_on_hemnet_max;
    }
    if ($scope.filters.size_total_min != $scope.stats.size_total[0]) {
      postdata.size_total_min = $scope.filters.size_total_min;
    }
    if ($scope.filters.size_tomt_min != $scope.stats.size_tomt[0]) {
      postdata.size_tomt_min = $scope.filters.size_tomt_min;
    }
    for (var user_id in $scope.users) {
      var ratings = [];
      if ($scope.filters.hide_ratings[user_id]['yes']) { ratings.push('yes'); }
      if ($scope.filters.hide_ratings[user_id]['maybe']) { ratings.push('maybe'); }
      if ($scope.filters.hide_ratings[user_id]['no']) { ratings.push('no'); }
      if ($scope.filters.hide_ratings[user_id]['not_set']) { ratings.push('not_set'); }
      if (ratings.length > 0) {
        if (!postdata.hasOwnProperty('ratings')) {
          postdata.hide_ratings = {};
        }
        postdata.hide_ratings[user_id] = ratings;
      }
    }
    for (let commute_id in $scope.commute_locations) {
      if ($scope.filters.hide_failed_commutes[commute_id] != "0") {
        if (!postdata.hasOwnProperty('hide_failed_commutes')) {
          postdata.hide_failed_commutes = [];
        }
        postdata.hide_failed_commutes.push(commute_id);
      }
    }
    console.log("Filters:", postdata);

    // Get the house data from the database
    $http.post("api/houses.php", postdata).then(function (response) {

      // Check that this worked
      if (response.data.status != 'success') {
        if (response.data.msg) {
          $scope.error_msg = response.data.msg;
        } else {
          $scope.error_msg = "Something went wrong loading houses! Please check the PHP logs.";
        }
        console.log(response.data);
        return;
      }

      // Assign results
      $scope.num_results = response.data.num_results;
      $scope.oldest_fetch = parseFloat(response.data.oldest_fetch + "000");
      $scope.needs_update = Date.now() - $scope.oldest_fetch > (1000 * 60 * 60 * 12);
      $scope.results = response.data.results;
      $scope.users = response.data.users;
      $scope.tags = response.data.tags;
      $scope.commute_locations = response.data.commute_locations;
      $scope.commute_time_max = response.data.commute_time_max;
      $scope.commute_time_min = response.data.commute_time_min;
      $scope.commute_time_avg = response.data.commute_time_avg;
      $scope.marker_colour_scale_commute = chroma.scale('RdYlGn').domain([$scope.commute_time_max, $scope.commute_time_min]);
      var stats_price = get_min_max('price', response.data.results);
      $scope.marker_colour_scale_price = chroma.scale('RdYlGn').domain([stats_price[1], stats_price[0]]);
      var stats_size_total = get_min_max('size_total', response.data.results);
      $scope.marker_colour_scale_size_total = chroma.scale('RdYlGn').domain([stats_size_total[0], stats_size_total[1]]);
      $scope.marker_colour_scale_size_tomt = chroma.scale('RdYlGn').domain([500, 4000]);
      $scope.marker_colour_scale_rooms = chroma.scale('RdYlGn').domain([1, 8]);
      var stats_days_on_hemnet = get_min_max('age', response.data.results);
      $scope.marker_colour_scale_age = chroma.scale('BuGn').domain([Math.min(30, stats_days_on_hemnet[1] + 2), 0]);
      for (let id in $scope.commute_locations) {
        var dateobj = new Date();
        dateobj.setHours(0, 0, 0, 0);
        dateobj.setSeconds(parseFloat($scope.commute_locations[id].max_time));
        $scope.commute_locations[id].max_time = dateobj;
      }

      function get_min_max(key, results) {
        var vals_arr = Object.values(results).map(a => parseFloat(a[key]));
        var vals_arr = vals_arr.filter(function (el) { return !isNaN(el); });
        return [Math.min.apply(Math, vals_arr), Math.max.apply(Math, vals_arr)];
      }

      ////////////////////
      // First time we have fetched results
      ////////////////////
      if (init_call === true) {

        // Don't allow more data calls whilst we're setting the filters
        $scope.initialising = true;

        $scope.num_total_results = response.data.num_results;
        $scope.all_results = response.data.results;

        // Get stats
        $scope.stats.price = get_min_max('price', response.data.results);
        $scope.stats.size_total = get_min_max('size_total', response.data.results);
        $scope.stats.size_tomt = get_min_max('land_area', response.data.results);
        $scope.stats.days_on_hemnet = get_min_max('age', response.data.results);

        // Build user-filters
        for (let user_id in $scope.users) {
          $scope.filters.hide_ratings[user_id] = { 'yes': false, 'maybe': false, 'no': false, 'not_set': false };
        };

        // Build commute-filters
        for (let commute_id in $scope.commute_locations) {
          $scope.filters.hide_failed_commutes[commute_id] = "0";
        }

        // Add extra map settings
        for (let user_id in $scope.users) {
          $scope.map_setting_selects.marker_colour_icon.Ratings['rating_' + user_id] = 'Rating: ' + $scope.users[user_id];
          $scope.map_setting_selects.marker_colour.Ratings['rating_' + user_id] = 'Rating: ' + $scope.users[user_id];
          $scope.map_setting_selects.marker_icon.Ratings['rating_' + user_id] = 'Rating: ' + $scope.users[user_id];
          $scope.set_marker_colour['rating_' + user_id] = function (house) {
            if (house.ratings[user_id] == 'yes') { return '#28a745'; }
            if (house.ratings[user_id] == 'maybe') { return '#17a2b8'; }
            if (house.ratings[user_id] == 'no') { return '#dc3545'; }
            return $scope.base_marker_colour;
          };
          $scope.set_marker_icon['rating_' + user_id] = function (house) {
            if (house.ratings[user_id] == 'yes') { return ['fa-thumbs-up']; }
            if (house.ratings[user_id] == 'maybe') { return ['fa-question']; }
            if (house.ratings[user_id] == 'no') { return ['fa-thumbs-down']; }
            return [$scope.base_marker_icon];
          };
        }
        for (let commute_id in $scope.commute_locations) {
          $scope.map_setting_selects.marker_colour_icon.Commutes['commute_threshold_' + commute_id] = 'Commute threshold: ' + $scope.commute_locations[commute_id].address;
          $scope.map_setting_selects.marker_colour.Commutes['commute_threshold_' + commute_id] = 'Commute threshold: ' + $scope.commute_locations[commute_id].address;
          $scope.map_setting_selects.marker_icon.Commutes['commute_threshold_' + commute_id] = 'Commute threshold: ' + $scope.commute_locations[commute_id].address;
          $scope.set_marker_colour['commute_threshold_' + commute_id] = function (house) {
            if (house.commute_times[commute_id].pass_threshold == true) { return '#28a745'; }
            else if (house.commute_times[commute_id].pass_threshold == false) { return '#dc3545'; }
            return $scope.base_marker_colour;
          };
          $scope.set_marker_icon['commute_threshold_' + commute_id] = function (house) {
            if (house.commute_times[commute_id].pass_threshold == true) { return ['fa-check']; }
            else if (house.commute_times[commute_id].pass_threshold == false) { return ['fa-times']; }
            return ['fa-question'];
          };

          $scope.map_setting_selects.marker_colour.Commutes['commute_' + commute_id] = 'Commute time: ' + $scope.commute_locations[commute_id].address;
          $scope.set_marker_colour['commute_' + commute_id] = function (house) {
            if (house.commute_times[commute_id].status == 'OK') {
              var duration = parseFloat(house.commute_times[commute_id].duration_value);
              return $scope.marker_colour_scale_commute(duration).hex();
            }
            return $scope.base_marker_colour;
          };
        }

        // Wait for the page to update, then allow more update calls
        // This is to stop the page reloading again when the filters are set
        $timeout(function () {
          $scope.initialising = false;
          // Fetch the commute time shape once the marker stuff is done
          $scope.plot_commute_map();
        }, 1000);
      }

      // Plot markers
      var markers = $scope.plot_markers();

      // Get new map bounds
      var l_bounds = L.latLngBounds(Object.values(markers));
      var bounds = {
        northEast: l_bounds._northEast,
        southWest: l_bounds._southWest,
      }

      // Update the map
      angular.extend($scope, {
        bounds: bounds,
        markers: markers
      });

      // Allow function to call again in 1 second
      $timeout(function () {
        $scope.update_results_call_active = false;
        if ($scope.update_results_call_requested) {
          $scope.update_results();
        }
      }, 1000);

    });
  };

  $scope.plot_markers = function () {
    // Make nice object of map markers
    var markers = {};
    angular.forEach($scope.results, function (house, key) {

      // Lat / Lng
      var lat = parseFloat(house.lat);
      var lng = parseFloat(house.lng);
      if (isNaN(lat) || isNaN(lng)) {
        console.error("NaN for lat/lng!", lat, lng, house);
        $scope.missing_geo.push(house.id);
      } else {

        // Get marker colour / icon
        var m_colour = $scope.set_marker_colour[$scope.map_settings.marker_colour](house, 1);
        var m_icon = $scope.set_marker_icon[$scope.map_settings.marker_icon](house, 1);

        markers[house.id] = {
          id: house.id,
          lat: lat,
          lng: lng,
          message: '<h6><a href="' + house.url + '" target="_blank">' + house.address + '</a></h6><p><img src="' + house.image_url + '" style="width:100%"></p>',
          icon: {
            type: 'extraMarker',
            markerColor: m_colour,
            icon: m_icon[0],
            number: m_icon[1],
            prefix: 'fa',
            shape: 'circle',
            svg: true
          }
        }

      }
    });

    // Markers for commute locations
    angular.forEach($scope.commute_locations, function (commute, commute_id) {
      // Lat / Lng
      var lat = parseFloat(commute.lat);
      var lng = parseFloat(commute.lng);
      if (isNaN(lat) || isNaN(lng)) {
        console.error("NaN for lat/lng!", lat, lng, commute);
      } else {
        markers['commute_' + commute_id] = {
          lat: lat,
          lng: lng,
          message: '<h6>Commute location</h6><p class="my-0">' + commute.address + '</p>',
          icon: {
            type: 'extraMarker',
            markerColor: 'blue-dark',
            icon: 'fa-building',
            prefix: 'fa',
            shape: 'square',
            svg: true
          }
        }
      }
    });

    return markers;
  }

  // Map marker settings updated
  $scope.update_marker_col_icon = function () {
    $scope.map_settings.marker_colour = $scope.map_settings.marker_colour_icon;
    $scope.map_settings.marker_icon = $scope.map_settings.marker_colour_icon;
    $scope.update_markers();
  }
  $scope.update_markers = function () {
    angular.extend($scope, { markers: $scope.plot_markers() });
  }

  // Get the map markers
  $scope.plot_commute_map = function () {
    $scope.commute_map_call_active = true;

    // Get the house data from the database
    $http.get("api/commute_map.php").then(function (response) {
      if (response.data.status !== 'success') {
        if (response.data.msg) {
          $scope.error_msg = response.data.msg;
        } else {
          $scope.error_msg = "Something went wrong fetching the commute map! Please check the PHP logs.";
        }
        console.error(response.data);
        return;
      }

      // Wipe any existing layers
      $scope.layers.overlays = {};

      // Plot each shape separately
      var colours = ['#3388FF', '#e7298a', '#66a61e', '#d95f02', '#7570b3'];
      var colour_idx = 0;
      for (let id in response.data.results) {
        // Convert TravelTime response data to geoJSON
        var geoJSON = $scope.toGeojson([response.data.results[id]]);
        var layer_name = response.data.layer_names[id];
        var is_visible = true;
        var style = {
          color: colours[0],
          fillColor: colours[0],
          weight: 2.0,
          opacity: 1.0,
          fillOpacity: 0.4
        };
        if (layer_name !== 'Intersection of commutes') {
          is_visible = false;
          colour_idx++;
          style = {
            color: colours[colour_idx],
            fillColor: colours[colour_idx],
            weight: 2.0,
            opacity: 0.8,
            fillOpacity: 0.2
          };
        }
        // Add to map as new layer
        angular.extend($scope.layers.overlays, {
          ["commute_map_" + id]: {
            name: layer_name,
            type: 'geoJSONShape',
            data: geoJSON,
            visible: is_visible,
            layerOptions: {
              style: style
            }
          }
        });
      }

      $scope.commute_map_call_active = false;
    });
  }

  // Convert TravelTime response to GeoJSON
  // https://gist.github.com/MockusT/4059e72becc7e2465b9458ccc11577e6#file-traveltime_timemap_json_to_geojson-js
  // https://traveltime.com/blog/how-to-create-a-geojson-isochrone
  $scope.remapLinearRing = function (linearRing) {
    return linearRing.map(c => [c['lng'], c['lat']]);
  }
  $scope.shapesToMultiPolygon = function (shapes) {
    var allRings = shapes.map(function (shape) {
      var shell = $scope.remapLinearRing(shape['shell']);
      var holes = shape['holes'].map(h => $scope.remapLinearRing(h));
      return [shell].concat(holes);
    });
    return {
      'type': 'MultiPolygon',
      'coordinates': allRings
    };
  }

  $scope.toGeojson = function (results) {
    var multiPolygons = results.map(r => $scope.shapesToMultiPolygon(r['shapes']));
    var features = multiPolygons.map(mp => {
      return {
        geometry: mp,
        type: "Feature",
        properties: {}
      }
    });
    return {
      'type': 'FeatureCollection',
      'features': features
    };
  }

  // Initialise the map with markers on load
  $scope.update_results(true);

  // Leaflet marker clicked
  $scope.$on('leafletDirectiveMarker.click', function (event, args) {
    // Get house details
    if (args.model.id !== undefined) {
      $scope.active_id = args.model.id;
      $scope.active_house = $scope.results[$scope.active_id];
      console.log("House clicked:", $scope.active_house);
    }
  });

  // Ratings button clicked
  $scope.save_rating = function (r_user_id, rating) {
    // Deselect ratings
    if (rating == $scope.active_house.ratings[r_user_id]) {
      rating = 'not_set';
    }

    // Build post data and send to API
    var post_data = {
      'house_id': $scope.active_id,
      'user_id': r_user_id,
      'rating': rating
    };
    $http.post("api/ratings.php", JSON.stringify(post_data)).then(function (response) {
      // Update the scope with the new rating
      $scope.active_house.ratings[r_user_id] = rating;
      $scope.update_markers();
    });
  }

  // Comment updated
  $scope.save_comment = function (r_user_id) {
    // Build post data and send to API
    var post_data = {
      'house_id': $scope.active_id,
      'user_id': r_user_id,
      'comment': $scope.active_house.comments[r_user_id]
    };
    $http.post("api/comments.php", JSON.stringify(post_data));
  }

  // Tag button clicked
  $scope.save_tag = function (tag_id) {
    var selected = !$scope.active_house.tags[tag_id];

    // Build post data and send to API
    var post_data = {
      'house_id': $scope.active_id,
      'tag_id': tag_id,
      'selected': selected
    };
    $http.post("api/tags.php", JSON.stringify(post_data)).then(function (response) {
      // Update the scope with the new tag status
      $scope.active_house.tags[tag_id] = selected;
    });
  }

  // Add tag button clicked
  $scope.add_tag = function () {
    var tag_name = prompt('New tag:');
    if (!tag_name || tag_name.trim().length == 0) {
      return;
    }
    // Build post data and send to API
    var post_data = { 'new_tag': tag_name };
    $http.post("api/tags.php", JSON.stringify(post_data)).then(function (response) {
      var new_tag_id = response.data.new_tag_id;
      // Update the scope with the new tag status
      $scope.tags[new_tag_id] = tag_name;
      $scope.active_house.tags[new_tag_id] = false;
      // Update all results to have this tag
      angular.forEach($scope.results, function (house, key) {
        house.tags[new_tag_id] = false;
      });
    });
  }

  // Update commute time
  $scope.update_commute_time = function (commute_id) {
    var dateobj = new Date();
    dateobj.setHours(0, 0, 0, 0);
    var max_time = ($scope.commute_locations[commute_id].max_time - dateobj) / 1000;
    // Build post data and send to API
    var post_data = {
      'id': commute_id,
      'max_time': max_time
    };
    $http.post("api/commute_locations.php", JSON.stringify(post_data)).then(function (response) {
      $scope.update_results();
      $scope.plot_commute_map();
    });
  };

  // Add commute location button clicked
  $scope.add_commute_location = function () {
    var address = prompt('Address:');
    if (!address || address.trim().length == 0) {
      return;
    }
    // Build post data and send to API
    var post_data = { 'add_address': address.trim() };
    $http.post("api/commute_locations.php", JSON.stringify(post_data)).then(function (response) {
      console.log(response.data);
      $scope.commute_locations[response.data.new_commute_id] = {
        address: address.trim(),
        max_time: 3600
      }
      $scope.update_results();
    });
  }

  // Delete commute location button clicked
  $scope.delete_commute_address = function (commute_id) {
    if (confirm('Delete ' + $scope.commute_locations[commute_id].address + '?')) {
      $http.post("api/commute_locations.php", JSON.stringify({ 'delete': commute_id })).then(function (response) {
        delete $scope.commute_locations[commute_id];
        $scope.update_results();
      });
    }
  }

  // Update fetched results from Hemnet
  $scope.update_hemnet_results = function () {
    $scope.hemnet_results_updating = true;
    $scope.hemnet_results_update_btn_text = 'Updating Hemnet results';
    // Update houses
    $http.get("api/update_houses.php").then(function (response) {
      if (response.data.status != 'success') {
        $scope.hemnet_results_updating = false;
        $scope.hemnet_results_update_btn_text = 'Update failed!';
        if (response.data.msg) {
          $scope.error_msg = response.data.msg;
        } else {
          $scope.error_msg = "Something went wrong updating the search results! Please check the PHP logs.";
        }
        console.error(response.data);
      } else {
        // Update commute times
        $scope.hemnet_results_update_btn_text = 'Fetching commute times';
        $http.get("api/commute_times.php").then(function (response) {
          $scope.hemnet_results_updating = false;
          if (response.data.status != 'success') {
            $scope.hemnet_results_update_btn_text = 'Update failed!';
            if (response.data.msg) {
              $scope.error_msg = response.data.msg;
            } else {
              $scope.error_msg = "Something went wrong fetching the commute times! Please check the PHP logs.";
            }
            console.error(response.data);
          } else {
            $scope.hemnet_results_update_btn_text = 'Update';
            $scope.needs_update = false;
            $scope.update_results(true);
          }
        });
      }
    });
  }

  // Show recent ratings
  $scope.update_recent_ratings = function () {
    // Fetch latest and display
    var post_data = {};
    if ($scope.recent_ratings_filter_user != '') { post_data.user_id = $scope.recent_ratings_filter_user; }
    if ($scope.recent_ratings_filter_type != '') { post_data.rating_type = $scope.recent_ratings_filter_type; }
    console.log("Getting ratings", post_data);
    $http.post("api/ratings.php?recent", JSON.stringify(post_data)).then(function (response) {
      console.log(response.data);
      $scope.recent_ratings = response.data.results;
    });
  }
  $scope.recent_ratings_click = function (house_id) {
    $scope.active_id = house_id;
    $scope.active_house = $scope.results[house_id];
    $scope.markers[house_id].focus = true;
  }

}]);
