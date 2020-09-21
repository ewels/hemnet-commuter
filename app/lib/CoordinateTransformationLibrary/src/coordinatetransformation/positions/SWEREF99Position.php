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


require_once dirname(__FILE__) . '/../GaussKreuger.php';
require_once dirname(__FILE__) . '/../Position.php';

abstract class SWEREFProjection {
  const sweref_99_tm = 0;
  const sweref_99_12_00 = 1;
  const sweref_99_13_30 = 2;
  const sweref_99_15_00 = 3;
  const sweref_99_16_30 = 4;
  const sweref_99_18_00 = 5;
  const sweref_99_14_15 = 6;
  const sweref_99_15_45 = 7;
  const sweref_99_17_15 = 8;
  const sweref_99_18_45 = 9;
  const sweref_99_20_15 = 10;
  const sweref_99_21_45 = 11;
  const sweref_99_23_1 = 12;
}

class SWEREF99Position extends Position {

  private $projection;

  /**
   * Create a SWEREF99 position from double values with SWEEREF 99 TM as default projection
   * @param n North value
   * @param e East value
   */

  public function __construct() {
    $args = func_get_args();
    if(count($args) == 2) {
      if(is_numeric($args[0]) && is_numeric($args[1])) {
        $this->SWEREF99Position($args[0], $args[1]);
      }
      else if($args[0] instanceof WGS84Position && is_int($args[1]))  {
        $this->SWEREF99PositionPositionProjection($args[0], $args[1]);
      }
    }
    else if(count($args) == 3) {
      $this->SWEREF99PositionProjection($args[0], $args[1], $args[2]);
    }
  }

  private function SWEREF99Position($n, $e) {
    parent::__construct($n, $e, Grid::SWEREF99);
    $this->projection = SWEREFProjection::sweref_99_tm;
  }

  /**
   * Create a SWEREF99 position from double values. Suplly the projection for values
   * other than SWEREF 99 TM
   * @param n North value
   * @param e East value
   * @param projection Projection type
   */
  private function SWEREF99PositionProjection($n, $e, $projection) {
    parent::__construct($n, $e, Grid::SWEREF99);
    $this->projection = $projection;
  }

  /**
   * Create a SWEREF99 position by converting a WGS84 position
   * @param position WGS84 position to convert
   * @param projection Projection to convert to
   */
  private function SWEREF99PositionPositionProjection(WGS84Position $position, $projection) {
    parent::__construct(Grid::SWEREF99);
    $gkProjection = new GaussKreuger();
    $gkProjection->swedish_params($this->getProjectionString($projection));
    list($this->latitude, $this->longitude) = $gkProjection->geodetic_to_grid($position->getLatitude(), $position->getLongitude());
    $this->projection = $projection;
  }

  /**
   * Convert the position to WGS84 format
   * @return
   */
  public function toWGS84() {
    $gkProjection = new GaussKreuger();
    $gkProjection->swedish_params($this->getProjectionString($this->projection));
    $lat_lon = $gkProjection->grid_to_geodetic($this->latitude, $this->longitude);

    $newPos = new WGS84Position($lat_lon[0], $lat_lon[1]);

    return $newPos;
  }

  /**
   * Get projection type as String
   * @return
   */
  private function getProjectionString($projection = NULL) {
    if(!isset($projection)) {
      $projection = $this->projection;
    }

    switch ($projection) {
      case SWEREFProjection::sweref_99_tm:
        return "sweref_99_tm";
      case SWEREFProjection::sweref_99_12_00:
        return "sweref_99_1200";
      case SWEREFProjection::sweref_99_13_30:
        return "sweref_99_1330";
      case SWEREFProjection::sweref_99_14_15:
        return "sweref_99_1415";
      case SWEREFProjection::sweref_99_15_00:
        return "sweref_99_1500";
      case SWEREFProjection::sweref_99_15_45:
        return "sweref_99_1545";
      case SWEREFProjection::sweref_99_16_30:
        return "sweref_99_1630";
      case SWEREFProjection::sweref_99_17_15:
        return "sweref_99_1715";
      case SWEREFProjection::sweref_99_18_00:
        return "sweref_99_1800";
      case SWEREFProjection::sweref_99_18_45:
        return "sweref_99_1845";
      case SWEREFProjection::sweref_99_20_15:
        return "sweref_99_2015";
      case SWEREFProjection::sweref_99_21_45:
        return "sweref_99_2145";
      case SWEREFProjection::sweref_99_23_15:
        return "sweref_99_2315";
      default:
        return "sweref_99_tm";
    }
  }

  //@Override
  public function __toString() {
    return sprintf("N: %F E: %F Projection: %s", $this->latitude, $this->longitude, $this->getProjectionString());
  }
}
