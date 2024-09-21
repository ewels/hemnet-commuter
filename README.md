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
* Auto-translation of house description texts to your language of choice

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

* [Docker](https://www.docker.com/) installed to run the web server locally on your computer _(recommended)_ or an alternative method of running an Apache / PHP / MySQL web server (eg. [MAMP](https://www.mamp.info/)).
* An API key for Google Maps / Google Translate (costs money, but easy to get loads of free credits for a trial. You won't need loads.)
* A [developer API key for TravelTime](https://traveltime.com/travel-time-maps?openDialog=true)

For Google you will need to register and set up a cloud computing account, then make a service account for Hemnet Commuter
and set up some API keys which are allowed to use the `Geocoding API`, `Distance Matrix API` and `Cloud Translation API`.
There are plenty of docs on how to do this, for example [here](https://cloud.google.com/translate/docs/setup?hl=en_GB).

### Using Docker

#### Initial setup

First, clone this repository and move to the local repository root directory.

Now create a new Docker container using the `mattrayner/lamp` image. This comes with a full _LAMP_ stack
(Linux, Apache, MySQL, PHP). See [https://github.com/mattrayner/docker-lamp](https://github.com/mattrayner/docker-lamp) for more details.

```bash
docker create --name hemnet_commuter -t -p "80:80" -v ${PWD}/app:/app -v ${PWD}/mysql:/var/lib/mysql mattrayner/lamp:latest
```

```bash
docker start hemnet_commuter
```

This container will now start up in the background and create a new MySQL database for you in a new `mysql` directory.

Give the server a few seconds to start, you can tail the logs with the following command:
```bash
docker logs hemnet_commuter -f
```

Next, create a new database and fill it with the required table structures
in the supplied SQL file:

```bash
docker exec -i hemnet_commuter mysql -uroot -e "CREATE DATABASE hemnet_commuter"
```

```bash
docker exec -i hemnet_commuter mysql -uroot hemnet_commuter < hemnet_commuter.sql
```

You can read the server logs by going into the container and tailing the apache log (which gets the PHP logs):

```bash
docker exec -it hemnet_commuter
```

```bash
tail -f /var/log/apache2/error.log
```

Go to [http://localhost](http://localhost) in your web browser. Hopefully Hemnet Commuter should load.

On the right hand side of the map there is a toolbar with several buttons. Use the map-marker
icon to open the _Map Settings_ sidebar. Click _Update Saved Searches_ and enter the
e-mail address and password that you use to log in to https://www.hemnet.se and click _Sign in to Hemnet_.

If the login works successfully, you should see a list of the names of your saved searches,
each with a toggle button. Select the searches that you would like to use for Hemnet Commuter
and click _Update_. Hemnet Commuter will now pull in all houses from Hemnet and hopefully plot
them on the map. If your saved searches have a lot of houses (> 1000) this can take a long time.
A maximum of ~500 houses seems to work well.

> Your hemnet.se email and password is not saved to the database and will be forgotten as soon
> as you close or refresh the web page. Only the saved search IDs and details are logged in the DB.
> You need to sign and refresh your saved searches each time you update their settings on Hemnet.

#### Continued use

Once the Docker container is set up, you can use the following commands to start and stop the server:

```bash
docker start hemnet_commuter
```

```bash
docker stop hemnet_commuter
```

You can check the status of any running Docker containers with the `docker ps` command.

To use Hemnet Commuter, go to [http://localhost](http://localhost) in your browser.

### Password / auth token

Hemnet Commuter comes with a _very_ basic auth system. You can set a password in `config.ini` and it'll validate it.
It's the `auth_token` value - please change it so that it's not `EXAMPLE` 🙄
Note that I've really not spent any time making this secure. So don't trust it. I'd recommend running offline (eg. with docker - see below), or using an additional layer of protection such as `.htpasswd` or something.

### Schools

Hemnet Commuter will fetch location and information about schools, from the Skolverket API.
To avoid hitting the API too hard (there are over 10k schools at time of writing), you have to specify the Kommuns that you're interested in using the `config.ini`. Set `school_kommuns` - the example config file has `Stockholm` and `Ekerö`.
You can find the complete list of available Kommun names [here](https://api.skolverket.se/skolenhetsregistret/v1/kommun).

You can also limit the schools shown by setting `school_types`. Leave unset to show all schools.

### Database credentials

The default database credentials are public on GitHub, so anyone will be able to log in to your database if it's on the web.
They are used in the Dockerfile to create an empty skeleton database and user.

If you are running Hemnet Commuter anywhere where another (potentially malicious) user could get to it, you should change
the database credentials. Make a copy of `app/config_defaults.ini` called `app/config.ini` and save them there (this file will be ignored by git).

If you want to use phpMyAdmin to view or manage the database, you'll need the login credentials created
when you first set up the docker container. The username is always `admin` and password is auto-generated
on this first run. You can find this info by running the `docker logs hemnet_commuter | less` command.
Look for the log that says something like: `=> Creating MySQL admin user with random password` followed by
`You can now connect to this MySQL Server with XXXXXXX`.
Once you have the çredentials, you can use phpMyAdmin at [http://localhost/phpmyadmin](http://localhost/phpmyadmin).

See the readme from the LAMP docker image for more details: [mattrayner/docker-lamp](https://github.com/mattrayner/docker-lamp#mysql-databases).

### Google Maps, Google Translate & TravelTime

Hemnet Commuter uses the Google Maps API to fetch travel times between houses and your commute locations.
The Google Translate API is used to be able to translate the description text from Hemnet to your chosen language.
It uses TravelTime to fetch a map of all potential commute locations within your criteria.

For these features to work, you will need to get API keys for the two services. Make a copy of `app/config_defaults.ini`
called `app/config.ini` and save them there (this file will be ignored by git).

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
