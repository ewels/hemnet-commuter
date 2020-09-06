# Hemnet Commuter

### Visualisation, annotation and powerful filtering tools for houses discovered on Hemnet.

Hemnet ([https://www.hemnet.se](https://www.hemnet.se/)) is the largest listing website for the Swedish property market.
Almost all available apartments, houses and summer cottages for sale in Sweden are listed there.

The success of Hemnet is not a surprise - the website and app are brilliant.
However, there are a few features that I found myself looking for when searching for a new house.
_Hemnet Commuter_ is a pet-project developed in my spare time to address these.

## Key features:

* Interactive map showing details in a sidebar when clicking houses (a bit like the AirBnb website)
* Live filtering of metrics, map refreshes as you adjust limits
* Map overlay showing area covered by one or more commute times (from [TravelTime](https://traveltime.com/travel-time-maps))
* Tools for one or more people to rate houses _(Yes / No / Maybe)_, write comments and add custom tags
* Ability to filter on custom fields not available on Hemnet, such as:
  * Total house area (Bo + Bi)
  * Hide houses with active bidding
  * Show _only_ Kommande (upcoming) houses
* Customisable map marker display with colours and icons. For example:
  * Continuous colour scales showing graduation of price / commute times etc
  * Map marker icons showing rating, bidding status, and many more.

#### Example screenshot:

![screenshot](screenshot.png)

## How it works

You use the tool by creating one or more _Saved searches_ on Hemnet.
You enter these IDs into the `saved_searches` DB table and Hemnet Commuter pulls information about each house from the Hemnet API.

> This is the API that the main Hemnet website uses, and is not publicly documented. So if they change anything without warning, this could break.

If one or more workplace addresses are added, Hemnet Commuter does extra commute-time things:

* Uses the Google Maps API to calculate commute times for each house to each workplace
* Fetches a commute time _isochrone_ (map of maximum commute time) from [TravelTime](https://traveltime.com/travel-time-maps) for each place, including the intersection

This back-end data retrieval is done with PHP scripts, which save results to a MySQL database.
The front-end is built using AngularJS with Leaflet maps and Bootstrap CSS.

## How you can use it

### Requirements

Whilst it would be fairly easy to extend this so that a single installation could be used for multiple people,
I haven't done that. So you'll have to run the web server yourself I'm afraid.

Before you start, you'll need:

* A method of running an Apache / PHP / MySQL web server (eg. [MAMP](https://www.mamp.info/), Docker etc)
* An API key for Google Maps (costs money, but easy to get loads of free credits for a trial. You won't need loads.)
* A [developer API key for TravelTime](https://traveltime.com/travel-time-maps?openDialog=true)

### Initial setup

Get your server running and create a new MySQL database using the `hemnet_commuter.sql` file.
This can be imported into _phpMyAdmin_ / similar tools, or done on the command line and should create all of
the necessary database tables and structure.

Create a copy of `config_example.ini` called `config.ini` in the filename and save the
database details and API keys there.

### First run

In the database, add one or more rows in the `saved_searches` DB table with the numeric IDs for your saved searches.

> `#TODO`: Add a front-end method to do manage these.

Open Hemnet Commuter in your web browser. Hopefully it should load with a big yellow _"Update"_ button
which you can press to fetch all data.

## Licence and code use

The code for Hemnet Commuter is released with an open-source MIT licence.
You are free to use it, tinker, build on it or do whatever you like with it.

This was a pet project for personal use by [Phil Ewels](http://phil.ewels.co.uk), an
Englishman living in Stockholm. It's pretty fragile code and is likely to break
as soon as Hemnet changes anything. I can't promise to maintain it, but hopefully
it'll come in helpful for others!

Please consider contributing / reporting issues via GitHub and I'll do my best to help.

---

This tool is in no way endorsed by Hemnet. But it does use their publicly-visible data. Thanks!

<img src="hemnet.svg" width="200">
