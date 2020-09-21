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

/**
 * Signals that an error has been reached unexpectedly while parsing. 
 * 
 */
class ParseException extends Exception {
}
