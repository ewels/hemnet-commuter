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

### Using Docker

#### Initial setup

First, clone this repository and move to the local repository root directory.

Now create a new Docker container using the `mattrayner/lamp` image. This comes with a full _LAMP_ stack
(Linux, Apache, MySQL, PHP). See [https://github.com/mattrayner/docker-lamp](https://github.com/mattrayner/docker-lamp) for more details.

```console
$ docker create --name hemnet_commuter -t -p "80:80" -v ${PWD}/app:/app -v ${PWD}/mysql:/var/lib/mysql mattrayner/lamp:latest
$ docker start hemnet_commuter
```

This container will now start up in the background and create a new MySQL database for you in a new `mysql` directory.

If you want to use phpMyAdmin at a later date to view or manage the database, you'll need this to log in
with the username `admin` and password that is auto-generated on this first run. You can find this info (and note it down)
by running the `docker logs hemnet_commuter` command.
Look for the log that says something like: `=> Creating MySQL admin user with random password` followed by
`You can now connect to this MySQL Server with XXXXXXX`.

Next, create a new database and fill it with the required table structures in the supplied SQL file:

```console
$ docker exec -i hemnet_commuter mysql -uroot -e "CREATE DATABASE hemnet_commuter"
$ docker exec -i hemnet_commuter mysql -uroot hemnet_commuter < hemnet_commuter.sql
```

#### Continued use

Once the Docker container is set up, you can use the following commands to start and stop the server:

```console
$ docker start hemnet_commuter
$ docker stop hemnet_commuter
```

You can check the status of any running Docker containers with the `docker ps` command.

To use Hemnet Commuter, go to `http://localhost` in your browser.
You can manage the database using phpMyAdmin at `http://localhost/phpmyadmin`

### Database credentials

The default database credentials are public on GitHub, so anyone will be able to log in to your database if it's on the web.
They are used in the Dockerfile to create an empty skeleton database and user.

If you are running Hemnet Commuter anywhere where another (potentially malicious) user could get to it, you should change
the database credentials. Make a copy of `app/config_defaults.ini` called `app/config.ini` and save them there (this file will be ignored by git).

See the readme from the LAMP docker image for more details: [mattrayner/docker-lamp](https://github.com/mattrayner/docker-lamp#mysql-databases).

### Google Maps & TravelTime

Hemnet Commuter uses the Google Maps API to fetch travel times between houses and your commute locations.
It uses TravelTime to fetch a map of all potential commute locations within your criteria.

For these features to work, you will need to get API keys for the two services. Make a copy of `app/config_defaults.ini`
called `app/config.ini` and save them there (this file will be ignored by git).

### First run

In the database, add one or more rows in the `saved_searches` DB table with the numeric IDs for your saved searches.

> `#TODO`: Add a front-end method to do manage these (the error message even suggests this already exists).

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

<img src="app/hemnet.svg" width="200">
