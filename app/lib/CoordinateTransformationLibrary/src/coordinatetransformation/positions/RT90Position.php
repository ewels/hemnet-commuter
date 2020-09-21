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

abstract class RT90Projection {
    const rt90_7_5_gon_v = 0;
    const rt90_5_0_gon_v = 1;
    const rt90_2_5_gon_v = 2;
    const rt90_0_0_gon_v = 3;
    const rt90_2_5_gon_o = 5;
    const rt90_5_0_gon_o = 6;
}

class RT90Position extends Position {

  private $projection;

  public function __construct() {
    $args = func_get_args();
    if(count($args) == 2) {
      if(is_numeric($args[0]) && is_numeric($args[1])) {
        $this->RT90Position($args[0], $args[1]);
      }
      else if($args[0] instanceof WGS84Position && is_int($args[1])) {
        $this->RT90PositionPositionProjection($args[0], $args[1]);
      }
    }
    else if(count($args) == 3) {
      $this->RT90PositionProjection($args[0], $args[1], $args[2]);
    }
  }



  /**
   * Create a new position using default projection (2.5 gon v)
   * @param x X value
   * @param y Y value
   */
  private function RT90Position($x, $y) {
    parent::__construct($x, $y, Grid::RT90);
    $this->projection = RT90Projection::rt90_2_5_gon_v;
  }

  /**
   * Create a new position
   * @param x X value
   * @param y Y value
   * @param projection Projection type
   */
  private function RT90PositionProjection($x, $y, $rt90projection) {
    parent::__construct($x, $y, Grid::RT90);
    $this->projection = $rt90projection;
  }

  /**
   * Create a RT90 position by converting a WGS84 position
   * @param position WGS84 position to convert
   * @param rt90projection Projection to convert to
   */
  private function RT90PositionPositionProjection(WGS84Position $position, $rt90projection) {
    parent::__construct(Grid::RT90);
    $gkProjection = new GaussKreuger();
    $gkProjection->swedish_params($this->getProjectionString($rt90projection));
    list($this->latitude, $this->longitude) =  $gkProjection->geodetic_to_grid($position->getLatitude(), $position->getLongitude());
    $this->projection = $rt90projection;
  }

  /**
   * Convert position to WGS84 format
   * @return
   */
  public function toWGS84() {
    $gkProjection = new GaussKreuger();
    $gkProjection->swedish_params($this->getProjectionString());

    list($lat, $lon) = $gkProjection->grid_to_geodetic($this->latitude, $this->longitude);
    $newPos = new WGS84Position($lat, $lon);
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
      case RT90Projection::rt90_7_5_gon_v:
        return "rt90_7.5_gon_v";
      case RT90Projection::rt90_5_0_gon_v:
        return "rt90_5.0_gon_v";
      case RT90Projection::rt90_2_5_gon_v:
        return "rt90_2.5_gon_v";
      case RT90Projection::rt90_0_0_gon_v:
        return "rt90_0.0_gon_v";
      case RT90Projection::rt90_2_5_gon_o:
        return "rt90_2.5_gon_o";
      case RT90Projection::rt90_5_0_gon_o:
        return "rt90_5.0_gon_o";
      default:
        return "rt90_2.5_gon_v";
    }
  }

  //Override
  public function __toString() {
    return sprintf("X: %F Y: %F, Projection %s", $this->latitude, $this->longitude, $this->getProjectionString());
  }
}
