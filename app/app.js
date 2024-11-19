//////////////////////////////////////////////
// hemnet-commuter                          //
// https://github.com/ewels/hemnet-commuter //
//////////////////////////////////////////////

/**
 * Hemnet Commuter AngularJS code
 */

var app = angular.module("hemnetCommuterApp", ['ui-leaflet', 'ngCookies', 'ngAnimate', 'ngTouch', 'ui.bootstrap']);
app.controller("hemnetCommuterController", ['$scope', '$location', '$compile', '$http', '$timeout', '$cookies', 'leafletData', function ($scope, $location, $compile, $http, $timeout, $cookies, leafletData) {

  // Cookie expiration date - 200 days
  var cookieExpireDate = new Date();
  cookieExpireDate.setDate(cookieExpireDate.getDate() + 200);

  // First - check if we have an auth token cookie
  $scope.hasAuth = false;
  $scope.hc_auth_token = '';
  $http.get("api/auth.php").then(function (response) {
    if (response.data.has_auth === true) {
      $scope.hasAuth = true;
    }
  });
  // Login form submitted
  $scope.login = function () {
    // Save hc_auth_token cookie
    $cookies.put('hc_auth_token', $scope.hc_auth_token, {'expires': cookieExpireDate});
    // reload page
    location.reload();
  }

  // Put some common functions onto the $scope
  $scope.isArray = angular.isArray;
  $scope.console = console;

  // Saved search credentials
  $scope.upcoming_viewings_houses = [];
  $scope.saved_searches = [];
  $scope.update_saved_searches = false;
  $scope.hemnet_api_key = false;
  $scope.hemnet_email = '';
  $scope.hemnet_password = '';
  $scope.hemnet_saved_searches = [];
  $scope.saved_search_selected = [];
  if ($cookies.get('hc_hemnet_api_key')) {
    $scope.hemnet_api_key = $cookies.get('hc_hemnet_api_key');
  }
  $scope.lantmateriet_api_key = $cookies.get('hc_lantmateriet_api_key');

  // Filters
  $scope.filters = {
    min_combined_rating_score: 0,
    min_num_ratings: "0",
    hide_ratings: {},
    kommande: "0",
    bidding: "0",
    price_min: 0,
    price_max: 10000000,
    days_on_hemnet_max: 999999999,
    days_on_hemnet_min: 0,
    size_total_min: 0,
    size_total_max: 999999999,
    size_tomt_min: 0,
    size_tomt_max: 999999999,
    has_upcoming_open_house: "0",
    open_house_before: '',
    open_house_after: '',
    hide_failed_commutes: [],
    tags: []
  }

  $scope.stats = {
    price: [0, 10000000],
    size_total: [0, 10000],
    size_tomt: [0, 3000],
    days_on_hemnet: [0, 999999999],
    next_visning_timestamps: [0, 999999999],
  }
  $scope.initialising = false;
  $scope.show_detail = false; // mobile only
  $scope.sidebar = false;
  $scope.show_footer = true;
  if ($cookies.get('hc_hide_footer')) {
    $scope.show_footer = false;
  }
  $scope.photos_modal = false;
  $scope.photos_modal_thumbs = true;
  $scope.photos_modal_planritning = true;
  $scope.photos_modal_thumbs_width = 100;
  $scope.photos_modal_planritning_width = 400;

  // Settings
  $scope.map_settings = {
    marker_colour_icon: 'rating_combined',
    marker_colour: 'rating_combined',
    marker_icon: 'rating_combined'
  }
  // Build the dropdowns
  var base_setting_select = {
    "None": { none: "None" },
    "Ratings": { rating_combined: 'Rating: Combined' },
    "Commutes": { commute_threshold_combined: 'Commute threshold: Combined' },
    "Stats": {
      days_until_next_visning: 'Days until next visning',
      status: 'Kommande/Bidding',
      price: 'Price',
      size_total: 'Total size',
      size_tomt: 'Land area',
      rooms: 'Rooms',
      days_on_hemnet: 'Days on Hemnet'
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
  $scope.get_rating_colour = function (score, num_ratings) {
    if (score >= 2) { return '#28a745'; }
    if (score == 1) { return '#87d699'; }
    if (score == 0 && num_ratings > 1) { return '#17a2b8'; }
    if (score == 0 && num_ratings == 1) { return '#8bccd6'; }
    if (score == -1) { return '#f5c6cb'; }
    if (score <= -2) { return '#dc3545'; }
    return $scope.base_marker_colour;
  }
  $scope.set_marker_colour = {
    'none': function (house) { return $scope.base_marker_colour; },
    'rating_combined': function (house) {
      var score = null;
      var num_ratings = 0;
      for (let user_id in $scope.users) {
        if (house.ratings[user_id] == 'yes') { score += 1; num_ratings++; }
        if (house.ratings[user_id] == 'maybe') { score += 0; num_ratings++; }
        if (house.ratings[user_id] == 'no') { score += -1; num_ratings++; }
      }
      return $scope.get_rating_colour (score, num_ratings);
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
      if (house.isUpcoming == '1') { return '#28a745'; }
      if (house.isBiddingOngoing == '1') { return '#67458c'; }
      return $scope.base_marker_colour;
    },
    'price': function (house) { return $scope.marker_colour_scale_price(parseFloat(house.askingPrice)).hex(); },
    'size_total': function (house) { return $scope.marker_colour_scale_size_total(house.size_total).hex(); },
    'size_tomt': function (house) { return $scope.marker_colour_scale_size_tomt(house.landArea).hex(); },
    'rooms': function (house) { return $scope.marker_colour_scale_rooms(house.numberOfRooms).hex(); },
    'days_on_hemnet': function (house) { return $scope.marker_colour_scale_age(house.daysOnHemnet).hex(); },
    'days_until_next_visning': function (house) {
      if (house.nextOpenHouse != null) {
        return $scope.marker_colour_scale_next_visning(house.nextOpenHouse).hex();
      }
      return $scope.base_marker_colour;
    },
  }

  // Functions to assign icons to markers
  $scope.base_marker_icon = 'fa-circle';
  $scope.set_marker_icon = {
    'none': function (house) { return [$scope.base_marker_icon]; },
    'status': function (house) {
      if (house.isUpcoming == '1') { return ['fa-bolt']; }
      if (house.isBiddingOngoing == '1') { return ['fa-gavel']; }
      return [$scope.base_marker_icon];
    },
    'rating_combined': function (house) {
      var col = $scope.set_marker_colour['rating_combined'](house);
      if (col == '#28a745') { return ['fa-star']; }
      if (col == '#87d699') { return ['fa-thumbs-up']; }
      if (col == '#17a2b8') { return ['fa-question']; }
      if (col == '#8bccd6') { return ['fa-question']; }
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
    'price': function (house) { return ['fa-number', (house.askingPrice / 1000000).toFixed(1)] },
    'size_total': function (house) { return ['fa-number', house.size_total]; },
    'size_tomt': function (house) { return ['fa-number', house.landArea]; },
    'rooms': function (house) { return ['fa-number', house.numberOfRooms] },
    'days_on_hemnet': function (house) { return ['fa-number', house.daysOnHemnet] },
    'days_until_next_visning': function (house) {
      if (house.nextOpenHouse != null) {
        var days_until_next_visning = Math.round((house.nextOpenHouse * 1000.0 - Date.now()) / (1000.0 * 60 * 60 * 24));
        return ['fa-number', days_until_next_visning]
      } else {
        return ['fa-cross'];
      }
    },
  }

  // House results
  $scope.error_msg = false;
  $scope.update_results_call_active = false;
  $scope.update_results_call_requested = false;
  $scope.active_id = false;
  $scope.active_house = false;
  $scope.carousel_idx = 0;
  $scope.num_total_results = 0;
  $scope.num_results = 0;
  $scope.oldest_fetch = 0;
  $scope.needs_update = false;
  $scope.results = [];
  $scope.recent_ratings = [];
  $scope.recent_ratings_filter_user = '';
  $scope.recent_ratings_filter_type = '';
  $scope.missing_geo = [];
  $scope.users = {};
  $scope.num_users = 0;
  $scope.tags = {};
  $scope.commute_locations = {};
  $scope.commute_map_call_active = false;
  $scope.hemnet_results_updating = false;
  $scope.hemnet_results_update_btn_text = 'Update';
  $scope.translate_target_language = '';
  $scope.translate_description = false;

  // School results
  $scope.base_overlays = {
    houses: { name: 'Houses', type: 'group', visible: true },
    commute_locations: { name: 'Commute centres', type: 'group', visible: true },
    schools: { name: 'Schools', type: 'group', visible: false },
  };
  $scope.active_schools = [];
  $scope.active_school_leaflet_ids = [];
  $scope.school_ratings = [];
  $scope.school_comments = [];
  $scope.schools_data = {};
  $scope.school_national_averages = {};
  $scope.school_names = {};
  $scope.favourite_schools = [];
  var favourite_schools_cookie = $cookies.get('hc_favourite_schools');
  if (favourite_schools_cookie) {
    $scope.favourite_schools = JSON.parse(favourite_schools_cookie);
  }
  $scope.favourite_schools.forEach(function(school_id){
    // Fetch detailed info about favourite schoole
    if(!(school_id in $scope.schools_data)){
      $http.get("api/school_details.php?school_id="+school_id).then(function (response) {
        if (response.status != 200) {
          console.error("Could not fetch school details: ", school_id);
          console.log(response);
        } else {
          $scope.schools_data[school_id] = response.data;
          console.log(response);
        }
      });
    }
  });


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
        <button ng-click="${opts.ngclick}" ng-class="${opts.ngclass}" class="btn px-2" title="${opts.title}" uib-tooltip="${opts.title}" tooltip-placement="left">
          <i class="fa fa-fw ${opts.icon_class}"></i>
        </button>
      </div>`;
      var linkFn = $compile(btn_link);
      var content = linkFn($scope);
      return content[0];
    }
  });


  // Set up the map
  $scope.map = {
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
        },
        lmtTopowebb: {
          name: 'Lantmäteriet',
          url: 'https://api.lantmateriet.se/open/topowebb-ccby/v1/wmts/token/' + $scope.lantmateriet_api_key + '/?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=topowebb&STYLE=default&TILEMATRIXSET=3857&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}&FORMAT=image%2Fpng',
          layerOptions: {
            maxZoom: 15,
            minZoom: 0
          },
          type: 'xyz'
        },
        lmtTopowebbNedtonad: {
          name: 'Lantmäteriet - muted',
          url: 'https://api.lantmateriet.se/open/topowebb-ccby/v1/wmts/token/' + $scope.lantmateriet_api_key + '/?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=topowebb_nedtonad&STYLE=default&TILEMATRIXSET=3857&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}&FORMAT=image%2Fpng',
          layerOptions: {
            maxZoom: 15,
            minZoom: 0
          },
          type: 'xyz'
        },
      },
      overlays: $scope.base_overlays,
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
          'ngclass': `sidebar == 'filters' ? 'btn-secondary' : 'btn-outline-info'`,
          'icon_class': 'fa-filter'
        }),
        new L.Control.HncBtn({
          'title': 'Settings',
          'ngclick': `sidebar = sidebar == 'map' ? false : 'map'`,
          'ngclass': `sidebar == 'map' ? 'btn-secondary' : 'btn-outline-secondary'`,
          'icon_class': 'fa-cog'
        }),
        new L.Control.HncBtn({
          'title': 'Viewings',
          'ngclick': `sidebar = sidebar == 'viewing' ? false : 'viewing'`,
          'ngclass': `sidebar == 'viewing' ? 'btn-secondary' : 'btn-outline-secondary'`,
          'icon_class': 'fa-solid fa-street-view'
        })
      ]
    }
  }

  // Make sure that the map has the right height on the 100% height div
  // https://stackoverflow.com/a/44132780/713980
  function updateMapSize(timeout=0){
    leafletData.getMap().then(function (map) {
      setTimeout(function () {
        map.invalidateSize();
        map._resetView(map.getCenter(), map.getZoom(), true);
      }, timeout);
    });
  }
  updateMapSize(200);
  // Force LeafletJS to updat map when sidebar is toggled
  $scope.$watch('sidebar', function () {
    updateMapSize(100);
  });
  $scope.$watch('show_detail', function () {
    updateMapSize(100);
  });

  // Get the initial stats and setup
  $scope.init_vars = function () {

    // Get the house data from the database
    $http.get("api/init_stats.php").then(function (response) {

      // Check that this worked
      if (response.data.status != 'success') {
        if (response.data.msg) {
          $scope.error_msg = response.data.msg;
        } else {
          $scope.error_msg = "Something went wrong loading the initial setup! Please check the PHP logs.";
        }
        console.log(response.data);
        return;
      }

      // Init stats and website setup
      $scope.num_total_results = response.data.stats.num_houses;
      $scope.tags = response.data.tags;
      $scope.users = response.data.users;
      $scope.commute_locations = response.data.commute_locations;
      $scope.school_locations = response.data.school_locations;
      $scope.school_ratings = response.data.school_ratings;
      $scope.school_comments = response.data.school_comments;
      $scope.translate_target_language = response.data.translate_target_language;
      $scope.stats = response.data.stats;

      // Update filter values
      $scope.filters.price_min = $scope.stats.price_min;
      $scope.filters.price_max = $scope.stats.price_max;
      $scope.filters.size_total_min = $scope.stats.size_total_min;
      $scope.filters.size_total_max = $scope.stats.size_total_max;
      $scope.filters.size_tomt_min = $scope.stats.size_tomt_min;
      $scope.filters.size_tomt_max = $scope.stats.size_tomt_max;
      $scope.filters.days_on_hemnet_min = $scope.stats.days_on_hemnet_min;
      $scope.filters.days_on_hemnet_max = $scope.stats.days_on_hemnet_max;

      // Build user-filters
      $scope.num_users = Object.keys($scope.users).length;
      $scope.filters.min_combined_rating_score = ($scope.num_users * -1).toString();
      for (let user_id in $scope.users) {
        $scope.filters.hide_ratings[user_id] = { 'yes': false, 'maybe': false, 'no': false, 'not_set': false };
      };

      // Build commute-filters and commute settings
      for (let commute_id in $scope.commute_locations) {
        // Filters
        $scope.filters.hide_failed_commutes[commute_id] = "0";

        // Vars for Commute settings sidebar
        $scope.commute_locations[commute_id].max_time = $scope.commute_locations[commute_id].max_time / 60;
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
        $scope.map_setting_selects.marker_colour_icon.Commutes['commute_threshold_' + commute_id] = 'Commute threshold: ' + $scope.commute_locations[commute_id].nickname;
        $scope.map_setting_selects.marker_colour.Commutes['commute_threshold_' + commute_id] = 'Commute threshold: ' + $scope.commute_locations[commute_id].nickname;
        $scope.map_setting_selects.marker_icon.Commutes['commute_threshold_' + commute_id] = 'Commute threshold: ' + $scope.commute_locations[commute_id].nickname;
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

        $scope.map_setting_selects.marker_colour.Commutes['commute_' + commute_id] = 'Commute time: ' + $scope.commute_locations[commute_id].nickname;
        $scope.set_marker_colour['commute_' + commute_id] = function (house) {
          if (house.commute_times[commute_id].status == 'OK') {
            var duration = parseFloat(house.commute_times[commute_id].duration_value);
            return $scope.marker_colour_scale_commute(duration).hex();
          }
          return $scope.base_marker_colour;
        };
      }

      // Check for filters cookie and set if found
      var house_filters = $cookies.getObject('hc_house_filters');
      if (house_filters) {
        Object.assign($scope.filters, house_filters);
      }
      console.log("House filters found in a cookie: ", house_filters);

      // Fetch houses for the map
      $scope.update_results(true);
      // Fetch the commute time shape
      $scope.plot_commute_map();
    });
  }

  // Get the map markers
  $scope.update_results = function (set_bounds = false) {

    if ($scope.initialising) {
      console.log("Ignoring update_results - still initialising");
      return;
    }

    // Fill in empty filters
    if ($scope.filters.price_min == '') { $scope.filters.price_min = $scope.stats.price_min; }
    if ($scope.filters.price_max == '') { $scope.filters.price_max = $scope.stats.price_max; }
    if ($scope.filters.size_total_min == '') { $scope.filters.size_total_min = $scope.stats.size_total_min; }
    if ($scope.filters.size_total_max == '') { $scope.filters.size_total_max = $scope.stats.size_total_max; }
    if ($scope.filters.size_tomt_min == '') { $scope.filters.size_tomt_min = $scope.stats.size_tomt_min; }
    if ($scope.filters.size_tomt_max == '') { $scope.filters.size_tomt_max = $scope.stats.size_tomt_max; }
    if ($scope.filters.days_on_hemnet_min == '') { $scope.filters.days_on_hemnet_min = $scope.stats.days_on_hemnet_min; }
    if ($scope.filters.days_on_hemnet_max == '') { $scope.filters.days_on_hemnet_max = $scope.stats.days_on_hemnet_max; }

    // Don't fire too frequently
    if ($scope.update_results_call_active) {
      $scope.update_results_call_requested = true;
      return;
    }
    $scope.update_results_call_active = true;
    $scope.update_results_call_requested = false;

    // Build filters POST data
    var postdata = {};
    if($scope.filters.tags.length > 0){
      postdata.tags = $scope.filters.tags;
    }
    if ($scope.filters.min_combined_rating_score != ($scope.num_users * -1).toString()) {
      postdata.min_combined_rating_score = $scope.filters.min_combined_rating_score;
    }
    if ($scope.filters.min_num_ratings != "0") {
      postdata.min_num_ratings = $scope.filters.min_num_ratings;
    }
    if ($scope.filters.kommande != "0") {
      postdata.kommande = $scope.filters.kommande;
    }
    if ($scope.filters.bidding != "0") {
      postdata.bidding = $scope.filters.bidding;
    }
    if ($scope.filters.price_min != $scope.stats.price_min) {
      postdata.price_min = $scope.filters.price_min;
    }
    if ($scope.filters.price_max != $scope.stats.price_max) {
      postdata.price_max = $scope.filters.price_max;
    }
    if ($scope.filters.days_on_hemnet_min != $scope.stats.days_on_hemnet_min) {
      postdata.days_on_hemnet_min = $scope.filters.days_on_hemnet_min;
    }
    if ($scope.filters.days_on_hemnet_max != $scope.stats.days_on_hemnet_max) {
      postdata.days_on_hemnet_max = $scope.filters.days_on_hemnet_max;
    }
    if ($scope.filters.size_total_min != $scope.stats.size_total_min) {
      postdata.size_total_min = $scope.filters.size_total_min;
    }
    if ($scope.filters.size_total_max != $scope.stats.size_total_max) {
      postdata.size_total_max = $scope.filters.size_total_max;
    }
    if ($scope.filters.size_tomt_min != $scope.stats.size_tomt_min) {
      postdata.size_tomt_min = $scope.filters.size_tomt_min;
    }
    if ($scope.filters.size_tomt_max != $scope.stats.size_tomt_max) {
      postdata.size_tomt_max = $scope.filters.size_tomt_max;
    }
    if ($scope.filters.has_upcoming_open_house != "0") {
      postdata.has_upcoming_open_house = $scope.filters.has_upcoming_open_house;
    }
    if ($scope.filters.open_house_before.trim().length > 0) {
      postdata.open_house_before = $scope.filters.open_house_before;
    }
    if ($scope.filters.open_house_after.trim().length > 0) {
      postdata.open_house_after = $scope.filters.open_house_after;
    }
    for (var user_id in $scope.users) {
      var ratings = [];
      if ($scope.filters.hide_ratings.hasOwnProperty(user_id)) {
        if ($scope.filters.hide_ratings[user_id]['yes']) { ratings.push('yes'); }
        if ($scope.filters.hide_ratings[user_id]['maybe']) { ratings.push('maybe'); }
        if ($scope.filters.hide_ratings[user_id]['no']) { ratings.push('no'); }
        if ($scope.filters.hide_ratings[user_id]['not_set']) { ratings.push('not_set'); }
        if (ratings.length > 0) {
          if (!postdata.hasOwnProperty('hide_ratings')) {
            postdata.hide_ratings = {};
          }
          postdata.hide_ratings[user_id] = ratings;
        }
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

    // Save filters for next time - user hide_ratings has different structure but rest is the same.
    var cookie_filters = JSON.parse(JSON.stringify(postdata));
    cookie_filters.hide_ratings = $scope.filters.hide_ratings;
    $cookies.putObject('hc_house_filters', cookie_filters, {'expires': cookieExpireDate});

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
      // Stats of *returned* results for marker colour ranges
      // Commute times
      $scope.commute_time_max = response.data.commute_time_max;
      $scope.commute_time_min = response.data.commute_time_min;
      $scope.commute_time_avg = response.data.commute_time_avg;
      $scope.marker_colour_scale_commute = chroma.scale('RdYlGn').domain([$scope.commute_time_max, $scope.commute_time_min]);
      // Price
      var stats_price = get_min_max('askingPrice', response.data.results);
      $scope.marker_colour_scale_price = chroma.scale('RdYlGn').domain([stats_price[1], stats_price[0]]);
      // Size
      var stats_size_total = get_min_max('size_total', response.data.results);
      $scope.marker_colour_scale_size_total = chroma.scale('RdYlGn').domain([stats_size_total[0], stats_size_total[1]]);
      // Tomt
      $scope.marker_colour_scale_size_tomt = chroma.scale('RdYlGn').domain([500, 4000]);
      // Rooms
      $scope.marker_colour_scale_rooms = chroma.scale('RdYlGn').domain([1, 8]);
      // Days on hemnet
      var stats_days_on_hemnet = get_min_max('daysOnHemnet', response.data.results);
      $scope.marker_colour_scale_age = chroma.scale('BuGn').domain([Math.min(30, stats_days_on_hemnet[1] + 2), 0]);
      // Visning
      var stats_next_visning_timestamps = get_min_max('nextOpenHouse', response.data.results);
      $scope.marker_colour_scale_next_visning = chroma.scale('PRGn').domain([stats_next_visning_timestamps[0], stats_next_visning_timestamps[1]]);
      // Helper function
      function get_min_max(key, results) {
        var vals_arr = Object.values(results).map(a => parseFloat(a[key]));
        var vals_arr = vals_arr.filter(function (el) { return !isNaN(el); });
        return [Math.min.apply(Math, vals_arr), Math.max.apply(Math, vals_arr)];
      }

      // Plot markers
      var markers = $scope.plot_markers();
      // Filter markers for just those with layer 'houses'
      var house_markers = Object.values(markers).filter(marker => marker.layer == 'houses');
      // Get new map bounds
      var l_bounds = L.latLngBounds(house_markers);
      var bounds = {
        northEast: l_bounds._northEast,
        southWest: l_bounds._southWest,
      }

      // Update the map
      $scope.map.markers = markers;

      // Allow function to call again in 1 second
      $timeout(function () {
        if(set_bounds){
          $scope.map.bounds = bounds;
        }
        $scope.update_results_call_active = false;
        if ($scope.update_results_call_requested) {
          $scope.update_results();
        }
      }, 1000);


      // Check for `active_house_id` in URL query parameters on page load
      const active_house_id = $location.search().active_house_id;

      if (active_house_id) {
        // If `active_house_id` exists, simulate a marker click
        // Assuming we have a method to programmatically trigger the event
        $scope.simulateClickOnMarker(active_house_id)
      }

    });
  };

  $scope.$watch(
    'map.markers',
    (newMarkers, oldMarkers)  => {
      const markers = Object.values(newMarkers)
      $scope.update_viewings(markers)
    },
    true // Set to true for deep watching
  );

  $scope.getIconClasses = function (icon) {
    let classes = [];
    if (icon.prefix) {
      classes.push(icon.prefix); // e.g., 'fa'
    }
    if (icon.icon) {
      classes.push(icon.icon); // e.g., 'fa-circle'
    }
    classes.push('fa-3x');
    return classes;
  }

  $scope.getIconStyles = function (icon) {
    // Apply color or any other styles dynamically
    return {
      color: icon.markerColor || '#000'
    };
  };

  $scope.update_viewings = function (markers) {

    if(markers.length == 0){
      $scope.upcoming_viewings_houses = []
      return
    }
    const viewings = markers.filter((marker) => {
      const house = $scope.results[marker.id]
      return house?.nextOpenHouse != null
    })
    .map(marker => ({...$scope.results[marker.id], marker}))
    .sort((houseA, houseB) => {
      // Extract the first upcomingOpenHouse epoch time for each house
      const firstOpenHouseA = houseA.nextOpenHouse;
      const firstOpenHouseB = houseB.nextOpenHouse;

      // Sort by the earliest upcomingOpenHouses epoch number
      return firstOpenHouseA - firstOpenHouseB;
    }).map(house => ({...house, upcomingOpenHouses: house.upcomingOpenHouses.split(",")}));
    $scope.upcoming_viewings_houses = viewings
    console.log('upcoming biewings', $scope.upcoming_viewings_houses);
  };

  $scope.simulateClickOnMarker = function (houseId) {
    $timeout(function () {
      $scope.$broadcast('leafletDirectiveMarker.click', { model: { id: houseId } });
      leafletData.getMap().then(function (map) {
        // Fly to the marker's location
        map.flyTo(new L.LatLng($scope.active_house.lat, $scope.active_house.lng), 13);
        // Locate the marker by its houseId (model id)
        leafletData.getMarkers().then(function (markers) {
          let targetMarker = markers[houseId]; // Assuming markers are keyed by houseId
          if (!targetMarker) return;
          // Open the marker's popup
          targetMarker.openPopup();
        });
      });
    }, 200);
  }

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
          layer: 'houses',
          lat: lat,
          lng: lng,
          message: house.title,
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
          layer: 'commute_locations',
          lat: lat,
          lng: lng,
          message: '<h6>'+commute.nickname+'</h6><p class="my-0">' + commute.address + '</p>',
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

    // Markers for schools
    angular.forEach($scope.school_locations, function (school) {
      $scope.school_names[school['id']] = school['name'];

      // Rating
      var rating_score = null;
      var num_ratings = 0;
      for (let user_id in $scope.users) {
        if(school['id'] in $scope.school_ratings[user_id]){
          var user_rating = $scope.school_ratings[user_id][school['id']];
          if (user_rating == 'yes') { rating_score += 1; num_ratings++; }
          if (user_rating == 'maybe') { rating_score += 0; num_ratings++; }
          if (user_rating == 'no') { rating_score += -1; num_ratings++; }
        }
      }
      var marker_colour = $scope.get_rating_colour (rating_score, num_ratings);

      // Lat / Lng
      var lat = parseFloat(school['lat']);
      var lng = parseFloat(school['lng']);
      if (isNaN(lat) || isNaN(lng)) {
        console.error("NaN for lat/lng!", lat, lng, school);
      } else {
        markers['school_' + school['id']] = {
          school_id: school['id'],
          layer: 'schools',
          lat: lat,
          lng: lng,
          message: school['description'],
          icon: {
            type: 'extraMarker',
            markerColor: marker_colour,
            icon: 'fa-graduation-cap',
            prefix: 'fa',
            shape: 'square',
            svg: true
          },
          popupOptions: {
            autoClose: false,
            closeOnClick: false,
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
    $scope.map.markers = $scope.plot_markers();
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
      $scope.map.layers.overlays = $scope.base_overlays;

      // Plot each shape separately
      var colours = ['#3388FF', '#e7298a', '#7570b3'];
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
          colour_idx = (colour_idx + 1) % colours.length;
          style = {
            color: colours[colour_idx],
            fillColor: colours[colour_idx],
            weight: 2.0,
            opacity: 0.8,
            fillOpacity: 0.2
          };
        }
        // Add to map as new layer
        angular.extend($scope.map.layers.overlays, {
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

  // START LOAD
  // Initialise the website, load the houses!
  $scope.init_vars();

  // Leaflet marker clicked
  $scope.$on('leafletDirectiveMarker.click', function (event, args) {

    // Get house details
    if (args.model.id !== undefined) {
      // Clear any active error message
      $scope.error_msg = false;

      // Clear any schools
      $scope.clear_active_schools();

      $scope.active_id = args.model.id;
      $scope.active_house = $scope.results[$scope.active_id];
      $scope.active_house.description_translatedText = '';
      $scope.active_house.carousel = [];
      $scope.carousel_idx = 0;
      $scope.show_detail = true;
      console.log("House clicked:", $scope.active_house);
      // Fetch the images for the carousel and the mäklare URL
      var graphQL_query = `
      query iOSListingQuery($propertyListingId: ID!) {
        listing(id: $propertyListingId) {
          ...FullPropertyListingFragment
        }
      }

      fragment FullListingImageFragment on ListingImage {
        original: url(format: ORIGINAL)
        labels
      }

      fragment FullPropertyListingFragment on PropertyListing {
        title
        id
        area
        legacyPrimaryLocation
        streetAddress
        listingHemnetUrl
        yearlyArrendeFee {
          ...MoneyFragment
        }
        yearlyLeaseholdFee {
          ...MoneyFragment
        }
        ... on ActivePropertyListing {
          fee {
            ...MoneyFragment
          }
          squareMeterPrice {
            ...MoneyFragment
          }
          description
          formattedLandArea
          formattedFloor
          storeys
          housingCooperative {
            name
          }
          listingBrokerUrl
          isNewConstructionProject
          timesViewed
          publishedAt
          legacyConstructionYear
          tenure {
            name
          }

          allImages {
            ...FullListingImageFragment
          }
        }
      }

      fragment MoneyFragment on Money {
        amount
      }
      `;
      var req = {
        method: 'POST',
        url: 'https://www.hemnet.se/graphql',
        headers: {
          'Accept': '*/*',
          'Content-Type': 'application/json'
        },
        data: {
          "query": graphQL_query,
          "variables": { "propertyListingId": $scope.active_id }
        }
      }
      $http(req).then(function (response) {
        if (response.status != 200) {
          console.error("Could not fetch house listing images with Hemnet GraphQL query!");
          console.log(response);
          return;
        }
        // Merge this data with what we have already
        $scope.active_house = Object.assign($scope.active_house, response.data.data.listing);

        // Split the upcoming active houses string into an array
        if ($scope.active_house.upcomingOpenHouses !== null && $scope.active_house.upcomingOpenHouses !== "") {
          if (typeof $scope.active_house.upcomingOpenHouses == "string") {
            $scope.active_house.upcomingOpenHouses = $scope.active_house.upcomingOpenHouses.split(',');
          } else {
            $scope.active_house.upcomingOpenHouses = $scope.active_house.upcomingOpenHouses;
          }
        }

        // Build the carousel
        var idx = 0;
        angular.forEach(response.data.data.listing.allImages, function (img) {
          $scope.active_house.carousel.push({ image: img.original, id: idx });
          idx++;
        });

        // Get the translated description text
        if (response.data.data.listing.description != undefined) {
          $http.post('api/translate.php', { query: response.data.data.listing.description }).then(function (response) {
            // Check it worked
            if (response.data.status != 'success') {
              if (response.data.msg) {
                $scope.error_msg = response.data.msg;
              } else {
                $scope.error_msg = "Something went wrong fetching the description translation! Please check the PHP logs.";
              }
              console.log(response.data);
              return;
            }
            $scope.active_house.description_translatedText = response.data.translatedText;
          });
        }
      });
    }

    // Get school details
    if (args.model.school_id !== undefined) {
      console.log("School clicked:", args);
      $id = args.model.school_id;

      // Clear any active error message
      $scope.error_msg = false;
      // Clear any active houses
      $scope.active_id = false;
      $scope.active_house = false;

      // Add to active schools and update side panel
      $scope.active_schools.push($id);
      $scope.active_school_leaflet_ids.push(args.leafletObject._leaflet_id);

      // Fetch the national averages if we don't already have them
      if(Object.keys($scope.school_national_averages).length == 0){
        console.log("Fetching national averages for schools");
        $http.get("api/school_details.php?school_id=national").then(function (response) {
          if (response.status != 200) {
            console.error("Could not fetch national averages for schools");
            console.log(response);
          } else {
            $scope.school_national_averages = response.data;
          }
        });
      }

      // Fetch detailed info about the schoole
      if(!($id in $scope.schools_data)){
        $http.get("api/school_details.php?school_id="+$id).then(function (response) {
          if (response.status != 200) {
            console.error("Could not fetch school details: ", $id);
            console.log(response);
          } else {
            $scope.schools_data[$id] = response.data;
            console.log(response);
          }
          // Update plot
          $scope.school_plots();
        });
      } else {
        // Update plot
        $scope.school_plots();
      }

    }
  });

  $scope.school_plots = function(){
    // Update all school survey plots
    $scope.school_survey_plot();
    // Update the school results plot
    $scope.school_results_plot();
  };
  $scope.school_survey_plot_data = function(survey_type){
    var d3colors = Plotly.d3.scale.category10();
    var questions = [
      // 'recommend',
      'satisfaction',
      'security',
      'workingEnvironment',
      'support',
      'inspiration',
    ];
    var data = [];
    // First - plot favourite schools (so that they're on the bottom)
    $scope.favourite_schools.forEach(function(school_id){
      // Skip if also selected
      if(school_id in $scope.active_schools){
        return;
      }
      var trace = {
        r: [],
        theta: [],
        name: $scope.school_names[school_id],
        type: 'scatterpolar',
        line: {
          color: 'rgb(150, 150, 150)',
          dash: 'dashdot',
        }
      };
      if(survey_type == 'custodians'){
        trace.subplot = "polar2";
        trace.showlegend = false;
      }
      questions.forEach(function(question){
        var survey_data = $scope.schools_data[school_id].survey_custodians;
        if(survey_type == 'pupils'){
          survey_data = $scope.schools_data[school_id].survey_pupils;
        }
        if(!(question+'Average' in survey_data)){
          return;
        }
        var result = parseFloat(survey_data[question+'Average'].replace('..', '').replace(',', '.'));
        trace.r.push(result);
        trace.theta.push(question);
      });
      trace.r.push(trace.r[0]);
      trace.theta.push(trace.theta[0]);
      data.push(trace);
    });
    // Now plot selected schools
    $scope.active_schools.forEach(function(school_id, idx){
      var trace = {
        r: [],
        theta: [],
        name: $scope.school_names[school_id],
        type: 'scatterpolar',
        line: {
          color: d3colors(idx),
        }
      };
      if(survey_type == 'custodians'){
        trace.subplot = "polar2";
        trace.showlegend = false;
      }
      questions.forEach(function(question){
        var survey_data = $scope.schools_data[school_id].survey_custodians;
        if(survey_type == 'pupils'){
          survey_data = $scope.schools_data[school_id].survey_pupils;
        }
        if(survey_data == null || !(question+'Average' in survey_data)){
          return;
        }
        var result = parseFloat(survey_data[question+'Average'].replace('..', '').replace(',', '.'));
        trace.r.push(result);
        trace.theta.push(question);
      });
      trace.r.push(trace.r[0]);
      trace.theta.push(trace.theta[0]);
      data.push(trace);
    });
    return data;
  }
  $scope.school_survey_plot = function(){
    var data = $scope.school_survey_plot_data('pupils');
    data = data.concat($scope.school_survey_plot_data('custodians'));
    // Config for plot
    const layout = {
      title: 'Survey results (pupils, custodians)',
      polar: {
        domain: {
          x: [0, 1],
          y: [0, 0.46]
        },
        angularaxis: {
          linecolor: '#dedede',
        },
        radialaxis: {
          visible: true,
          title: 'Average score',
          rangemode: 'normal',
          gridcolor: '#ededed',
          showline: false
        }
      },
      polar2: {
        domain: {
          x: [0, 1],
          y: [0.54, 1]
        },
        angularaxis: {
          linecolor: '#dedede',
        },
        radialaxis: {
          visible: true,
          title: 'Average score',
          rangemode: 'normal',
          gridcolor: '#ededed',
          showline: false
        }
      },
      legend: {"orientation": "h"}
    };
    // Render plots - wait a second for the DOM to update
    setTimeout(function () {
      console.log(data, layout);
      Plotly.newPlot(document.getElementById("school_survey_plotly"), data, layout);
    }, 100);
  }

  $scope.school_results_plot = function(){
    var subjects = ['SVE', 'ENG', 'MA', 'SVA'];
    var data = [];
    // First - plot favourite schools (so that they're on the bottom)
    $scope.favourite_schools.forEach(function(school_id){
      // Skip if also selected
      if(school_id in $scope.active_schools){
        return;
      }
      // Random value between 80 and 180
      const random = Math.floor(Math.random() * 100) + 80;
      var trace = {
        y: subjects,
        x: [],
        name: $scope.school_names[school_id],
        marker: { color: `rgb(${random}, ${random}, ${random})` },
        type: 'bar',
        orientation: 'h',
      };
      subjects.forEach(function(subject){
        const field = `averageResultNationalTestsSubject${subject}6thGrade`;
        var result = null;
        try {
          // TODO: Show whiskers for range of other available years
          result = $scope.schools_data[school_id].full_statistics[field][0].value;
          result = parseFloat(result?.replace('..', '')?.replace(',', '.'));
        } catch (e) {
          console.log("Error fetching school results for: ", school_id, subject, e);
        }
        trace.x.push(result);
      });
      data.push(trace);
    });
    // Now plot selected schools
    var d3colors = Plotly.d3.scale.category10();
    $scope.active_schools.forEach(function(school_id, idx){
      var trace = {
        y: subjects,
        x: [],
        name: $scope.school_names[school_id],
        type: 'bar',
        orientation: 'h',
        marker: { color: d3colors(idx) }
      };
      subjects.forEach(function(subject){
        const field = `averageResultNationalTestsSubject${subject}6thGrade`;
        var result = null;
        try {
          // TODO: Show whiskers for range of other available years
          result = $scope.schools_data[school_id].full_statistics[field][0]?.value;
          result = parseFloat(result?.replace('..', '')?.replace(',', '.'));
        } catch (e) {
          console.log("Error fetching school results for: ", school_id, subject, e);
        }
        trace.x.push(result);
      });
      data.push(trace);
    });

    // Render plots - wait a second for the DOM to update
    setTimeout(function () {
      var layout = {
        title: '6th Grade Results',
        barmode: 'group',
        yaxis: { autorange: "reversed" },
        legend: {"orientation": "h"},
      };
      Plotly.newPlot(document.getElementById("school_results_plotly"), data, layout);
    }, 100);
  }



  $scope.star_school = function(school_id){
    if ($scope.favourite_schools.indexOf(school_id) == -1) {
      $scope.favourite_schools.push(school_id);
      $cookies.put('hc_favourite_schools', JSON.stringify($scope.favourite_schools), {'expires': cookieExpireDate});
    }
    console.log("Starred school: " + school_id, $scope.favourite_schools.indexOf(school_id), $scope.favourite_schools);
  }
  $scope.unstar_school = function(school_id){
    var index = $scope.favourite_schools.indexOf(school_id);
    if (index !== -1) {
      $scope.favourite_schools.splice(index, 1);
      $cookies.put('hc_favourite_schools', JSON.stringify($scope.favourite_schools), {'expires': cookieExpireDate});
    }
    console.log("Unstarred school: " + school_id, $scope.favourite_schools.indexOf(school_id), $scope.favourite_schools);
  }

  // Leaflet marker popup closed
  $scope.$on('leafletDirectiveMarker.popupclose', function (event, args) {
    // Remove from active schools and update side panel
    $scope.active_schools = $scope.active_schools.filter(function(e) { return e !== args.model.school_id });
    $scope.school_survey_plots();
  });

  // Clear active schools
  $scope.clear_active_schools = function(){
    // Close all school marker popups
    leafletData.getMap().then(function (map) {
      map.eachLayer(function(layer){
        if($scope.active_school_leaflet_ids.includes(layer._leaflet_id)){
          layer.closePopup();
        }
      });
    });
  }



  // House Ratings button clicked
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

  // House Comment updated
  $scope.save_comment = function (r_user_id) {
    // Build post data and send to API
    var post_data = {
      'house_id': $scope.active_id,
      'user_id': r_user_id,
      'comment': $scope.active_house.comments[r_user_id]
    };
    $http.post("api/comments.php", JSON.stringify(post_data));
  }

  // School Ratings button clicked
  $scope.save_school_rating = function (school_id, r_user_id, rating) {
    // Deselect school ratings
    if (rating == $scope.school_ratings[r_user_id][school_id]) {
      rating = 'not_set';
    }

    // Build post data and send to API
    var post_data = {
      'school_id': school_id,
      'user_id': r_user_id,
      'rating': rating
    };
    $http.post("api/school_ratings.php", JSON.stringify(post_data)).then(function (response) {
      // Update the scope with the new rating
      $scope.school_ratings[r_user_id][school_id] = rating;
      // Update the map marker colour
      var rating_score = null;
      var num_ratings = 0;
      for (let user_id in $scope.users) {
        if(user_id in response.data){
          var user_rating = response.data[user_id][school_id];
          if (user_rating == 'yes') { rating_score += 1; num_ratings++; }
          if (user_rating == 'maybe') { rating_score += 0; num_ratings++; }
          if (user_rating == 'no') { rating_score += -1; num_ratings++; }
        }
      }
      $scope.map.markers['school_' + school_id].icon.markerColor = $scope.get_rating_colour(rating_score, num_ratings);
      console.log("School rating saved:", post_data, response);
    });
  }

  // School Comment updated
  $scope.save_school_comment = function (school_id, r_user_id) {
    // Build post data and send to API
    var post_data = {
      'school_id': school_id,
      'user_id': r_user_id,
      'comment': $scope.school_comments[r_user_id][school_id]
    };
    $http.post("api/school_comments.php", JSON.stringify(post_data)).then(function (response) {
      console.log("School comment saved:", post_data, response);
    });
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
  // add tag on filter
  $scope.toggle_tag_filter = function (tag_id) {
    // Check if the tag_id exists in the filters.tags array
    if ($scope.filters.tags.includes(tag_id)) {
      // If it exists, remove it
      const index = $scope.filters.tags.indexOf(tag_id);
      if (index > -1) {
        $scope.filters.tags.splice(index, 1);
      }
    } else {
      // If it does not exist, add it
      $scope.filters.tags.push(tag_id);
    }

    $scope.update_results();
  };

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

  // Hide footer button clicked
  $scope.hide_footer = function () {
    $scope.show_footer = false;
    $cookies.put('hc_hide_footer', true, {'expires': cookieExpireDate});
  }

  // Update commute time
  $scope.update_commute_time = function (commute_id) {
    var max_time = $scope.commute_locations[commute_id].max_time * 60;
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
    var nickname = prompt('Nickname:');
    if (!nickname || nickname.trim().length == 0) {
      return;
    }
    var address = prompt('Address:');
    if (!address || address.trim().length == 0) {
      return;
    }
    // Build post data and send to API
    var post_data = { 'add_address': address.trim(), 'nickname': nickname.trim() };
    $http.post("api/commute_locations.php", JSON.stringify(post_data)).then(function (response) {
      console.log(response.data);
      $scope.commute_locations[response.data.new_commute_id] = {
        nickname: nickname.trim(),
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

  // Add user button clicked
  $scope.add_user = function () {
    var name = prompt('Name:');
    if (!name || name.trim().length == 0) {
      return;
    }
    // Build post data and send to API
    var post_data = { 'add_user_name': name.trim() };
    $http.post("api/users.php", JSON.stringify(post_data)).then(function (response) {
      console.log(response.data);
      $scope.update_results();
    });
  }

  // Delete user button clicked
  $scope.delete_user = function (user_id) {
    if (confirm('Delete ' + $scope.users[user_id].address + '?')) {
      $http.post("api/users.php", JSON.stringify({ 'delete_id': user_id })).then(function (response) {
        delete $scope.users[user_id];
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
            $scope.init_vars();
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
    $scope.map.markers[house_id].focus = true;
  }

  // Sign in to Hemnet
  $scope.hemnet_signin = function () {
    if ($scope.hemnet_email.length == 0 || $scope.hemnet_password.length == 0) {
      return;
    }
    var req = {
      method: 'POST',
      url: 'https://www.hemnet.se/graphql',
      headers: {
        'Accept': '*/*',
        'Content-Type': 'application/json'
      },
      data: {
        "query": `mutation iOSAuthenticateUser($email: String!, $password: String!) {
                    authenticateUser(email: $email, password: $password) {
                      apiToken
                      errors {
                        type
                        message
                      }
                    }
                  }`,
        "variables": {
          "email": $scope.hemnet_email,
          "password": $scope.hemnet_password
        }
      }
    }
    $http(req).then(function (response) {
      $scope.hemnet_password = '';
      if (response.status != 200) {
        alert("Could not log in to Hemnet with Hemnet GraphQL query!");
        console.error(response);
        return;
      }
      if (response.data.data.authenticateUser.errors.length > 0) {
        alert("Could not log in to Hemnet: " + response.data.data.authenticateUser.errors[0].message);
        console.error(response.data.data.authenticateUser);
        return;
      }
      $scope.hemnet_api_key = response.data.data.authenticateUser.apiToken;
      $cookies.put('hc_hemnet_api_key', $scope.hemnet_api_key, {'expires': cookieExpireDate});
      $scope.hemnet_fetch_saved_searches();
    });
  }

  // Fetch Hemnet saved search details
  $scope.hemnet_fetch_saved_searches = function () {
    if (!$scope.hemnet_api_key) {
      return;
    }
    var req = {
      method: 'POST',
      url: 'https://www.hemnet.se/graphql',
      headers: {
        'Accept': '*/*',
        'Content-Type': 'application/json',
        'hemnet-token': $scope.hemnet_api_key
      },
      data: {
        "query": `query iOSSavedSearchesQuery($limit: Int!, $offset: Int!) {
                    me {
                    legacySavedSearches(limit: $limit, offset: $offset) {
                      total
                      limit
                      offset
                      savedSearches {
                          totalListings
                          name
                          id
                          search {
                            ...ListingSearchFragment
                          }
                        }
                      }
                    }
                  }

                  fragment BoundingBoxFragment on BoundingBox {
                    northEast {
                      ...GeometryPointFragment
                    }
                    southWest {
                      ...GeometryPointFragment
                    }
                  }

                  fragment GeometryPointFragment on GeometryPoint {
                    lat
                    long
                  }

                  fragment ListingSearchFragment on ListingSearch {
                    boundingBox {
                      ...BoundingBoxFragment
                    }
                    openHouseWithin
                    housingFormGroups
                    locations {
                      fullName
                      id
                      type
                    }
                    biddingStarted
                    keywords
                    geometries
                    coastlineDistanceMax
                    coastlineDistanceMin
                    constructionYearMax
                    constructionYearMin
                    feeMax
                    feeMin
                    landAreaMax
                    landAreaMin
                    livingAreaMax
                    livingAreaMin
                    priceMax
                    priceMin
                    roomsMax
                    roomsMin
                    squareMeterPriceMax
                    squareMeterPriceMin
                    waterDistanceMax
                    waterDistanceMin
                    upcoming
                    newConstruction
                    deactivatedBeforeOpenHouse
                    publishedSince
                  }`,
        "variables": {
          "limit": 40,
          "offset": 0
        }
      }
    }
    $http(req).then(function (response) {
      if (response.status != 200) {
        alert("Could not fetch Hemnet saved searches!");
        console.error(response);
        return;
      }
      $scope.hemnet_saved_searches = response.data.data.me.legacySavedSearches.savedSearches;
      $scope.saved_search_selected = [];
      angular.forEach($scope.hemnet_saved_searches, function (saved_search) {
        $scope.saved_search_selected['search_' + saved_search.id] = true;
      });
    });
  }

  // Save the search data from Hemnet to our database
  $scope.save_saved_searches = function () {

    $scope.saved_searches = [];
    angular.forEach($scope.hemnet_saved_searches, function (saved_search) {
      if ($scope.saved_search_selected['search_' + saved_search.id]) {
        $scope.saved_searches.push(saved_search);
      }
    });

    $http.post("api/saved_searches.php", { 'savedSearches': $scope.saved_searches }).then(function (response) {
      // Check it worked
      if (response.data.status != 'success') {
        if (response.data.msg) {
          $scope.error_msg = response.data.msg;
        } else {
          $scope.error_msg = "Something went wrong saving the searches! Please check the PHP logs.";
        }
        console.log(response.data);
        return;
      }

      // Trigger an update for the hemnet results
      $scope.needs_update = true;
      $scope.update_hemnet_results();

      $scope.update_saved_searches = false;
    });
  }

  // Fetch search data from our database
  $scope.fetch_saved_searches = function () {
    $scope.saved_searches = [];
    $http.get("api/saved_searches.php").then(function (response) {
      // Check it worked
      if (response.data.status != 'success') {
        if (response.data.msg) {
          $scope.error_msg = response.data.msg;
        } else {
          $scope.error_msg = "Something went wrong fetchin the saved searches! Please check the PHP logs.";
        }
        console.log(response.data);
        return;
      }

      $scope.saved_searches = response.data.saved_searches;

    });
  }
  $scope.fetch_saved_searches();

}]);
app.filter('stripZeros', function () {
  return function(text) {
      return text.replace(', 00:00', '');
  }
});
