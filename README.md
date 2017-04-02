# Hemnet Commuter

> Given RSS feeds of Hemnet searches, filter properties for those that match commuting preferences.


### Tool available at: http://beta.tallphil.co.uk/hemnet-commuter/

## What does this tool do?
Whilst looking for new houses on hemnet, it can be difficult to assess which
properties have acceptable commute times for multiple people.

This tool takes saved searches from hemnet and filters all of the properties
for any combination of commute times - enter up to 25 different addresses with
maximum times, and it will show you which houses match your criteria.

## Who built it?
This was a weekend project by [Phil Ewels](http://phil.ewels.co.uk), an
Englishman living in Stockholm. It's pretty fragile code and is likely to break
as soon as Hemnet changes anything. I can't promise to maintain it, but hopefully
it'll come in helpful for others!

## How does it work?
There are multiple steps to this tool, which roughly work as follows:

1. You enter everything into the form and press submit
2. RSS feeds are fetched from Hemnet and parsed
    * They have to be funnelled through a PHP script, as they have annoying
  `Access-Control-Allow-Origin` headers which prevents the browser from being able
  to load them. Hemnet techs - if you're reading this, it'd be great if this could
  be removed!
3. As the RSS feeds don't contain much information, we scrape the Hemnet HTML page
for every property.
    * This gives us information such as location, price, size and so on.
4. The commute locations are geocoded using the Google Maps API
    * This gives us proper latitudes and longitudes and so on, which we can work with.
5. Requests are sent to the [Google Maps Distance Matrix API](https://developers.google.com/maps/documentation/distance-matrix/)
service. This works out public transport commute times for every combination of property
and commute location.
    * We search for travel times that arrive at work at 8:00am, next Monday.
    * Bizarrely, this API also has `Access-Control-Allow-Origin` headers, so we
  pipe through PHP again.
6. Results with commute times are printed to a table in the web page
7. A Google Map is loaded with each property, colour-coded by whether it passed
the filters or not. Clicking reveals more information about that house.

---

This tool is in no way endorsed by Hemnet. But it does use their nice RSS feeds. Thanks!

<img src="http://beta.tallphil.co.uk/hemnet-commuter/hemnet.svg" width="200">
