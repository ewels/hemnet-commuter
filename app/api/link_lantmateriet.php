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

header("Location: https://kso.etjanster.lantmateriet.se/?e={$sweref99_lng}&n={$sweref99_lat}&z={$zoom}&profile=default_orto_granser");

exit;