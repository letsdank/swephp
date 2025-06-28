<?php

class Sweph extends SweModule
{
    const float J2000 = 2451545.0;          // 2000 January 1.5
    const float B1950 = 2433282.42345905;   // 1059 January 0.923
    const float J1900 = 2415020.0;          // 1900 January 0.5
    const float B1850 = 2396758.2035810;    // 1850 January 16:53

    const int ENDMARK = -99;

    // TODO: Review these constants
    const int SEI_FILE_PLANET = 0;
    const int SEI_FILE_MOON = 1;
    const int SEI_FILE_MAIN_AST = 2;
    const int SEI_FILE_ANY_AST = 3;
    const int SEI_FILE_FIXSTAR = 4;
    const int SEI_FILE_PLMOON = 5;

    // we always use Astronomical Almanac constants, if available
    const float MOON_MEAN_DIST = 384400000.0;           // in m, AA 1996, F2
    const float MOON_MEAN_INCL = 5.1453964;             // AA 1996, D2
    const float MOON_MEAN_ECC = 0.054900489;            // AA 1996, F2
    // const float SUN_EARTH_MRAT = 328900.561400;      // Su/(Ea+Mo) AA 2006 K7
    const float SUN_EARTH_MRAT = 332946.050895;         // Su / (Ea only) AA 2006 K7
    const float EARTH_MOON_MRAT = (1 / 0.0123000383);   // AA 2006, K7
    // const float EARTH_MOON_MRAT = 81.30056907419062; // de431
    // const float EARTH_MOON_MRAT = 81.30056;          // de406
    // const float AUNIT = 1.49597870691e+11;           // au in meters, AA 2006 K6
    const float AUNIT = 1.49597870700e+11;              // au in meters, DE431
    const float CLIGHT = 299792458e+8;                  // m/s, AA 1996 K6 / DE431
    // const float HELGRAVCONST = 1.32712438e+20;       // G * M(sun), m^3/sec^2, AA 1996 K6
    const float HELGRAVCONST = 1.32712440017987e+20;    // G * M(sun), m^3/sec^2, AA 2006 K6
    const float GEOGCONST = 3.98600448e+14;             // G * M(earth) m^3/sec^2, AA 1996 K6
    const float KGAUSS = 0.01720209895;                 // Gaussian gravitational constant K6
    const float SUN_RADIUS = (959.63 / 3600 * SweConst::DEGTORAD); // Meeus germ. p 391
    const float EARTH_RADIUS = 6378136.6;               // AA 2006 K6
    // const float EARTH_OBLATENESS = (1.0 / 298.257223563); // AA 1998 K13
    const float EARTH_OBLATENESS = (1.0 / 298.25642);   // AA 2006 K6
    const float EARTH_ROT_SPEED = (7.2921151467e-5 * 86400); // in rad/day, expl. suppl., p. 162

    const float LIGHTTIME_AUNIT = (499.0047838362 / 3600.0 / 24.0); // 8.3167 minutes (days)
    const float PARSEC_TO_AUNIT = 206264.8062471;       // 648000/PI, according to IAU Resolution B2, 2016

    public function __construct(SwePhp $base)
    {
        parent::__construct($base);
    }

    public static function square_num(array $x): float
    {
        return $x[0] * $x[0] + $x[1] * $x[1] + $x[2] * $x[2];
    }

    public static function dot_prod(array $x, array $y): float
    {
        return $x[0] * $y[0] + $x[1] * $y[1] + $x[2] * $y[2];
    }

    public function swi_fopen(int $ifno, string $fname, string $ephepath, ?string &$serr = null)
    {
        // TODO:
        try {
            return fopen($ephepath . $fname, 'r');
        } catch (Exception $e) {
            if ($serr)
                $serr = $e->getMessage();
            return null;
        }
    }
}