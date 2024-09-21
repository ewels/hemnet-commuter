<?php

require_once dirname(__FILE__) . '/../lib/CoordinateTransformationLibrary/src/coordinatetransformation/positions/SWEREF99Position.php';
require_once dirname(__FILE__) . '/../lib/CoordinateTransformationLibrary/src/coordinatetransformation/positions/WGS84Position.php';

$lat = $_REQUEST['lat'];
$lng = $_REQUEST['lng'];
$zoom = $_REQUEST['z'];

$wgsPos = new WGS84Position($lat, $lng);
$rtPos = new SWEREF99Position($wgsPos, SWEREFProjection::sweref_99_tm);

$sweref99_lat = $rtPos->getLatitude();
$sweref99_lng = $rtPos->getLongitude();

header("Location: https://minkarta.lantmateriet.se/?e={$sweref99_lng}&n={$sweref99_lat}&z={$zoom}&mapprofile=flygbild&layers=%5B%5B%227%22%5D%2C%5B%225%22%2C1%2C%225o%22%5D%2C%5B%226%22%2C1%2C%226o%22%5D%5D");

exit;
