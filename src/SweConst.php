<?php

class SweConst
{
    const int OK = 0;
    const int ERR = -1;
    const int AS_MAXCH = 256;

    // Mathematical constants
    const float TWOPI = 2.0 * M_PI;
    const float DEGTORAD = M_PI / 180.0;
    const float RADTODEG = 180.0 / M_PI;

    //
    // flag bits for parameter iflag in function swe_calc()
    // The flag bits are defined in such a way that iflag = 0 delivers what one
    // usually wants:
    //    - the default ephemeris (SWISS EPHEMERIS) is used,
    //    - apparent geocentric positions referring to the true equinox of date
    //      are returned.
    // If not only coordinates, but also speed values are required, use
    // flag = SEFLG_SPEED.
    //
    // The 'L' behind the number indicates that 32-bit integers (Long) are used.
    //
    const int SEFLG_JPLEPH = 0x00000001;            // use JPL ephemeris
    const int SEFLG_SWIEPH = 0x00000002;            // use SWISSEPH ephemeris
    const int SEFLG_MOSEPH = 0x00000004;            // use Moshier ephemeris

    const int SEFLG_HELCTR = 0x00000008;            // heliocentric position
    const int SEFLG_TRUEPOS = 0x00000010;           // true/geometric position, not apparent position
    const int SEFLG_J2000 = 0x00000020;             // no precession, i.e. give J2000 equinox
    const int SEFLG_NONUT = 0x00000040;             // no nutation, i.e. mean equinox of date
    // speed from 3 positions (do not use it, SEFLG_SPEED is faster and more precise.)
    const int SEFLG_SPEED3 = 0x00000080;
    const int SEFLG_SPEED = 0x00000100;             // high precision speed
    const int SEFLG_NOGDEFL = 0x00000200;           // turn off gravitational deflection
    const int SEFLG_NOABERR = 0x00000400;           // turn off 'annual' aberration of light
    // astrometric position, i.e. with light-time, but without aberration and light deflection
    const int SEFLG_ASTROMETRIC = (self::SEFLG_NOABERR | self::SEFLG_NOGDEFL);
    const int SEFLG_EQUATORIAL = 0x00000800;        // equatorial positions are wanted
    const int SEFLG_XYZ = 0x00001000;               // cartesian, not polar, coordinates
    const int SEFLG_RADIANS = 0x00002000;           // coordinates in radians, not degrees
    const int SEFLG_BARYCTR = 0x00004000;           // barycentric position
    const int SEFLG_TOPOCTR = 0x00008000;           // topocentric position
    // used for Astronomical Almanac mode in calculation of Kepler ellipses
    const int SEFLG_ORBEL_AA = self::SEFLG_TOPOCTR;
    const int SEFLG_TROPICAL = 0;                   // tropical position (default)
    const int SEFLG_SIDEREAL = 0x00010000;          // sidereal position
    const int SEFLG_ICRS = 0x00020000;              // ICRS (DE406 reference frame)
    const int SEFLG_DPSIDEPS_1980 = 0x00040000;     // reproduce JPL horizons 1962 - today to 0.002 arcsec.
    const int SEFLG_JPLHOR = self::SEFLG_DPSIDEPS_1980;
    const int SEFLG_JPLHOR_APPROX = 0x00080000;     // approximate JPL horizons 1962 - today
    // calculate position of center of body (COB) of planet, not barycenter of its system
    const int SEFLG_CENTER_BODY = 0x00100000;
    // test raw data in files sepm9*
    const int SEFLG_TEST_PLMOON = 0x00200000 | self::SEFLG_J2000 | self::SEFLG_ICRS | self::SEFLG_HELCTR | self::SEFLG_TRUEPOS;

    //
    // only used for experimenting with various JPL ephemeris files
    // which are available at Astrodienst's internal network
    //
    const int SE_DE_NUMBER = 431;
}