<?php
/**
 *  CoordinateTransformationLibrary - David Gustafsson 2012
 *
 *  RT90, SWEREF99 and WGS84 coordinate transformation library
 *
 * This library is a PHP port of the .NET library by Björn Sållarp.
 *  calculations are based entirely on the excellent
 *  javscript library by Arnold Andreassons.
 *
 * Source: http://www.lantmateriet.se/geodesi/
 * Source: Arnold Andreasson, 2007. http://mellifica.se/konsult
 * Source: Björn Sållarp. 2009. http://blog.sallarp.com
 * Source: Mathias Åhsberg, 2009. http://github.com/goober/
 * Author: David Gustafsson, 2012. http://github.com/david-xelera/
 *
 * License: http://creativecommons.org/licenses/by-nc-sa/3.0/
 */

abstract class Grid {
  const RT90 = 0;
  const WGS84 = 1;
  const SWEREF99 = 3;
}

abstract class Position {

    //public enum Grid {RT90,WGS84,SWEREF99}

    protected $latitude;
    protected $longitude;
    protected $gridFormat;

    public function __construct() {
      $args = func_get_args();
      if(count($args) == 3) {
        call_user_func_array(array($this, 'setPositionLatLonFormat'), $args);
      }
      else if(count($args) == 1) {
        call_user_func_array(array($this, 'setPositionFormat'), $args);
      }
    }

    private function setPositionLatLonFormat($lat, $lon, $format) {
      $this->latitude = $lat;
      $this->longitude = $lon;
      //@todo: validation?
      $this->gridFormat = $format;
    }

    private function setPositionFormat($format) {
      //@todo: validation?
      $this->gridFormat = $format;
    }

    public function getLatitude() {
        return $this->latitude;
    }
    public function getLongitude() {
        return $this->longitude;
    }
}
