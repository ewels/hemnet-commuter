# Hemnet Commuter

> Visualisation, annotation and powerful filtering tools for houses discovered on Hemnet.

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

You use the tool by creating one or more _Saved searches_ on Hemnet. You enter this ID and Hemnet Commuter does the rest:

* Fetches house IDs and URLs from the saved search(es)
* Scrapes the main Hemnet web page for each house, fetching more detailed information
* Uses the Google Maps API to _geocode_ the location (find the latitude / longitude from the address)
  * The Hemnet knows the exact lat/lng but I haven't figured out how to scrape this. Geocoding is the next-best thing. Please help if you can!

If one or more workplace addresses are saved, Hemnet Commuter does extra commute-time things:

* Uses the Google Maps API to calculate commute times for each house to each workplace
* Fetches a commute time _isochrone_ (map of maximum commute time) from [TravelTime](https://traveltime.com/travel-time-maps) for each place, including the intersection

This back-end data retrieval is done with PHP scripts, which save results to a MySQL database.
The front-end is built using AngularJS with Leaflet maps and Bootstrap CSS.

> Note that a previous version did basically the same thing, but with a lot less database and a lot more custom JQuery code
> I haven't deleted this from the repository yet, but will soon. You may see relics from this kicking around. Please ignore / let me know.

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

Create a copy of `hemnet_commuter_config_example.ini` without the `_example` in the filename and save the
database details and API keys there.

### First run

Open Hemnet Commuter in your web browser. Before anything else, go to the URL `/update.php`.
Enter one or more comma-separated search IDs and look them up. Hopefully you'll get a message that it has found
some houses - the number should match what you see on Hemnet.

Once that's done, work your way down that _Update_ page clicking the relevant buttons to fetch all information
and save to the database. Be aware that some scripts take a long time on the first run, but when you're just
updating with new houses later they are fairly quick.

Once your database is full of data, you're ready to navigate to the homepage at the root URL and start using the tool.
Hopefully the rest of it you can figure out by exploring.

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
