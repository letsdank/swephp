<?php

namespace Enums;

class SweTidalAccel
{
    const float SE_TIDAL_DE200 = -23.8946;
    const float SE_TIDAL_DE403 = -25.580;       // was -25.8 until V. 1.76.2
    const float SE_TIDAL_DE404 = -25.580;       // was -25.8 until V. 1.76.2
    const float SE_TIDAL_DE405 = -25.826;       // was -25.7376 until V. 1.76.2
    const float SE_TIDAL_DE406 = -25.826;       // was -25.7376 until V. 1.76.2
    const float SE_TIDAL_DE421 = -25.85;        // JPL Interoffice Memorandum 14-mar-2008 on DE421 Lunar Orbit
    const float SE_TIDAL_DE422 = -25.85;        // JPL Interoffice Memorandum 14-mar-2008 on DE421 (sic!) Lunar Orbit
    const float SE_TIDAL_DE430 = -25.82;        // JPL Interoffice Memorandum 9-jul-2013 on DE430 Lunar Orbit
    const float SE_TIDAL_DE431 = -25.80;        // IPN Progress Report 42-196 • February 15, 2014, p. 15; was -25.82 in V. 2.00.00
    const float SE_TIDAL_DE441 = -25.936;       // unpublished value, from email by Jon Giorgini to DK on 11 Apr 2021
    const float SE_TIDAL_26 = -26.0;
    const float SE_TIDAL_STEPHENSON_2016 = -25.85;
    const float SE_TIDAL_DEFAULT = self::SE_TIDAL_DE431;
    const int SE_TIDAL_AUTOMATIC = 999999;
    const float SE_TIDAL_MOSEPH = self::SE_TIDAL_DE404;
    const float SE_TIDAL_SWIEPH = self::SE_TIDAL_DEFAULT;
    const float SE_TIDAL_JPLEPH = self::SE_TIDAL_DEFAULT;
}