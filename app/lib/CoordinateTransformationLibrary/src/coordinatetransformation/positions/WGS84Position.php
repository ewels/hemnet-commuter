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

require_once dirname(__FILE__) . '/../Position.php';
require_once dirname(__FILE__) . '/ParseException.php';

abstract class WGS84Format {
  const Degrees = 0;
  const DegreesMinutes = 1;
  const DegreesMinutesSeconds = 2;
}

class WGS84Position extends Position {

  //TODO: error handing
  public function __construct() {
    $args = func_get_args();
    if(empty($args)) {
      //Create a new WGS84 position with empty coordinates
      parent::__construct(Grid::WGS84);
    }
    else if(count($args) == 2) {
      if(is_numeric($args[0]) && is_numeric($args[1])) {
        //Create a new WGS84 position with latitude and longitude
        parent::__construct($args[0], $args[1], Grid::WGS84);
      }
      else if(is_string($args[0]) && is_int($args[1])) {
        parent::__construct(Grid::WGS84);
        $this->WGS84PositionString($args[0], $args[1]);
      }
    }
  }

  /**
   * Create a new WGS84 position from a String containing both latitude
   * and longitude. The string is parsed based on the supplied format.
   * @param positionString
   * @param format
   * @throws java.lang.Exception
   */
  private function WGS84PositionString($positionString, $format) {
    if($format == WGS84Format::Degrees) {
      $positionString = trim($positionString);
      $lat_lon = explode(' ', $positionString);
      if (strlen($lat_lon) == 2) {
        $this->latitude = floatval(str_replace(',', '.', $lat_lon[0]));
        $this->longitude = floatval(str_replace(',', '.', $lat_lon[1]));
      }
      else {
        throw new ParseException("The position string is invalid");
      }
    }
    else if ($format == WGS84Format::DegreesMinutes || $format == WGS84Format::DegreesMinutesSeconds) {
      $firstValueEndPos = 0;

      if($format == WGS84Format::DegreesMinutes) {
        $firstValueEndPos = mb_strpos($positionString, "'");
      }
      else if ($format == WGS84Format::DegreesMinutesSeconds) {
        $firstValueEndPos = mb_strpos($positionString, '"');
      }

      $lat = trim(mb_substr($positionString, 0, $firstValueEndPos + 1));
      $lon = trim(mb_substr($positionString, $firstValueEndPos + 1));

      $this->setLatitudeFromString($lat, $format);
      $this->setLongitudeFromString($lon, $format);
    }
  }

  /**
   * Set the latitude value from a string. The string is parsed based on given format.
   * @param value
   * @param format
   */
  public function setLatitudeFromString($value, $format) {
    $value = trim($value);
    if($format == WGS84Format::DegreesMinutes) {
      $this->latitude = $this->parseValueFromDmString($value, 'S');
    }
    else if ($format == WGS84Format::DegreesMinutesSeconds) {
      $this->latitude = $this->parseValueFromDmsString($value, 'S');
    }
    else if ($format == WGS84Format::Degrees) {
      $this->latitude = doubleval($value);
    }
  }

  /**
   * Set the longitude value from a string. The string is parsed based on given format.
   * @param value
   * @param format
   */
  public function setLongitudeFromString($value, $format) {
    $value = trim($value);

    if($format == WGS84Format::DegreesMinutes) {
      $this->longitude = $this->parseValueFromDmString($value, 'W');
    }
    else if ($format == WGS84Format::DegreesMinutesSeconds) {
      $this->longitude = $this->parseValueFromDmsString($value, 'W');
    }
    else if ($format == WGS84Format::Degrees) {
      $this->longitude = doubleval($value);
    }
  }
  /**
   * Returns a string representation in the given format
   * @param format
   * @return
   */
  public function latitudeToString($format) {
    if ($format == WGS84Format::DegreesMinutes) {
      return $this->convToDmString($this->latitude, 'N', 'S');
    }
    else if ($format == WGS84Format::DegreesMinutesSeconds) {
      return $this->convToDmsString($this->latitude, 'N', 'S');
    }
    else {
      return strval($this->latitude);
    }
  }
  /**
   * Returns a string represenation in the given format
   * @param format
   * @return
   */
  public function longitudeToString($format) {
    if ($format == WGS84Format::DegreesMinutes) {
      return $this->convToDmString($this->longitude, 'E', 'W');
    }
    else if ($format == WGS84Format::DegreesMinutesSeconds) {
      return $this->convToDmsString($this->longitude, 'E', 'W');
    }
    else {
      return strval($this->longitude);
    }
  }

  private function convToDmString($value, $positiveValue, $negativeValue) {
    if (is_nan($value)) {
      return "";
    }
    $degrees = floor(abs($value));
    $minutes = (abs($value) - $degrees) * 60;

    return str_replace('.', ',', sprintf("%s %.0Fº %.0F'", $value >= 0 ? $positiveValue : $negativeValue, $degrees, floor($minutes * 10000) / 10000));
  }

  private function convToDmsString($value, $positiveValue, $negativeValue) {
    if(is_nan($value)) {
      return "";
    }
    $degrees = floor(abs($value));
    $minutes = floor((abs($value) - $degrees) * 60);
    $seconds = (abs($value) - $degrees - $minutes / 60) * 3600;

    return str_replace('.', ',', sprintf("%s %.0Fº %.0F' %.5F\"", $value >= 0 ? $positiveValue : $negativeValue, $degrees, $minutes, round($seconds * 100000) / 100000));
  }

  private function parseValueFromDmString($value, $positiveChar) {
    $retVal = 0;
    if (isset($value)) {
      if(!empty($value)) {

        $orig_enc = mb_internal_encoding();
        mb_internal_encoding('UTF-8');
        
        $direction = mb_substr($value, 0, 1);
        $value = trim(mb_substr($value, 1));

        $degree = mb_substr($value, 0, mb_strpos($value, 'º'));
        $value = trim(mb_substr($value, mb_strpos($value, 'º') + 1));

        $minutes = mb_substr($value, 0, mb_strpos($value, "'"));
        $value = trim(mb_substr($value, mb_strpos($value, "'"), + 1));

        $retVal = doubleval($degree);
        $retVal += doubleval(str_replace(',', '.', $minutes)) / 60;
        
        mb_internal_encoding($orig_enc);

        if($retVal > 90) {
          $retVal = NAN;
        }
        if($direction == $positiveChar || $direction == '-') {
          $retVal *= -1;
        }

      }
    }
    else {
      $retVal = NAN;
    }
    return $retVal;
  }

  private function parseValueFromDmsString($value, $positiveChar) {
    $retVal = 0;
    if (isset($value)) {
      if(!empty($value)) {

        $orig_enc = mb_internal_encoding();
        mb_internal_encoding('UTF-8');

        $direction = mb_substr($value, 0, 1);

        $value = trim(mb_substr($value, 1));
        $degree = mb_substr($value, 0, mb_strpos($value, 'º'));

        $value = trim(mb_substr($value, mb_strpos($value, 'º') + 1));
        $minutes = mb_substr($value, 0, mb_strpos($value, "'"));

        $value = trim(mb_substr($value, mb_strpos($value, "'") + 1)); 
        $seconds = mb_substr($value, 0, mb_strpos($value, '"'));

        $retVal = doubleval($degree);
        $retVal += doubleval(str_replace(',', '.', $minutes)) / 60;
        $retVal += doubleval(str_replace(',', '.', $seconds)) / 3600;

        mb_internal_encoding($orig_enc);

        if ($retVal > 90) {
          $retVal = NAN;
          return $retVal;
        }
        if($direction == $positiveChar || $direction == '-') {
          $retVal *= -1;
        }

      }
    }
    else {
      $retVal = NAN;
    }
    return $retVal;

  }
  //@Override
  public function __toString() {
    return sprintf("Latitude: %s  Longitude: %s", $this->latitudeToString(WGS84Format::DegreesMinutesSeconds), $this->longitudeToString(WGS84Format::DegreesMinutesSeconds));
  }
}
