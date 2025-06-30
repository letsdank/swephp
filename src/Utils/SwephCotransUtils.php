<?php

namespace Utils;

use SweConst;
use Sweph;

class SwephCotransUtils
{
    // conversion between ecliptical and equatorial cartesian coordinates
    // for ecl. to equ.  eps must be negative
    // for equ. to ecl.  eps must be positive
    public static function swi_coortrf(array $xpo, array &$xpn, float $eps): void
    {
        $sineps = sin($eps);
        $coseps = cos($eps);
        self::swi_coortrf2($xpo, $xpn, $sineps, $coseps);
    }

    // conversion between ecliptical and equatorial cartesian coordinates
    // sineps            sin(eps)
    // coseps            cos(eps)
    // for ecl. to equ.  sineps must be -sin(eps)
    public static function swi_coortrf2(array $xpo, array &$xpn, float $sineps, float $coseps): void
    {
        $x[0] = $xpo[0];
        $x[1] = $xpo[1] * $coseps + $xpo[2] * $sineps;
        $x[2] = -$xpo[1] * $sineps + $xpo[2] * $coseps;
        $xpn[0] = $x[0];
        $xpn[1] = $x[1];
        $xpn[2] = $x[2];
    }

    // conversion of cartesian (x[3]) to polar coordinates (l[3]).
    // x = l is allowed.
    // if |x| = 0, then lon, lat and rad := 0.
    public static function swi_cartpol(array $x, array &$l): void
    {
        if ($x[0] == 0 && $x[1] == 0 && $x[2] == 0) {
            $l[0] = $l[1] = $l[2] = 0;
            return;
        }
        $rxy = $x[0] * $x[0] + $x[1] * $x[1];
        $ll[2] = sqrt($rxy + $x[2] * $x[2]);
        $rxy = sqrt($rxy);
        $ll[0] = atan2($x[1], $x[0]);
        if ($ll[0] < 0.0) $ll[0] += SweConst::TWOPI;
        if ($rxy == 0) {
            if ($x[2] >= 0)
                $ll[1] = M_PI / 2;
            else
                $ll[1] = -(M_PI / 2);
        } else {
            $ll[1] = atan($x[2] / $rxy);
        }
        $l[0] = $ll[0];
        $l[1] = $ll[1];
        $l[2] = $ll[2];
    }

    // conversion from polar (l[3]) to cartesian coordinates (x[3]).
    // x = l is allowed.
    public static function swi_polcart(array $l, array &$x): void
    {
        $cosl1 = cos($l[1]);
        $xx[0] = $l[2] * $cosl1 * cos($l[0]);
        $xx[1] = $l[2] * $cosl1 * sin($l[0]);
        $xx[2] = $l[2] * sin($l[1]);
        $x[0] = $xx[0];
        $x[1] = $xx[1];
        $x[2] = $xx[2];
    }

    // conversion of position and speed.
    // from cartesian (x[6]) to polar coordinates (l[6]).
    // x = l is allowed.
    // if position is 0, function returns direction of
    // motion.
    public static function swi_cartpol_sp(array $x, array &$l): void
    {
        // zero position
        if ($x[0] == 0 && $x[1] == 0 && $x[2] == 0) {
            $ll[0] = $ll[1] = $ll[3] = $ll[4] = 0;
            $ll[5] = sqrt(Sweph::square_num([$x[3], $x[4], $x[5]]));
            self::swi_cartpol([$x[3], $x[4], $x[5]], $ll);
            $ll[2] = 0;
            for ($i = 0; $i <= 5; $i++)
                $l[$i] = $ll[$i];
            return;
        }
        // zero speed
        if ($x[3] == 0 && $x[4] == 0 && $x[5] == 0) {
            $l[3] = $l[4] = $l[5] = 0;
            self::swi_cartpol($x, $l);
            return;
        }
        // position
        $rxy = $x[0] * $x[0] + $x[1] * $x[1];
        $ll[2] = sqrt($rxy + $x[2] * $x[2]);
        $rxy = sqrt($rxy);
        $ll[0] = atan2($x[1], $x[0]);
        if ($ll[0] < 0.0) $ll[0] += SweConst::TWOPI;
        $ll[1] = atan($x[2] / $rxy);
        // speed:
        // 1. rotate coordinate system by longitude of position about z-axis,
        //    so that new x-axis = position radius projected onto x-y-plane.
        //    in the new coordinate system
        //    vy'/r = dlong/dt, where r = sqrt(x^2 +y^2).
        // 2. rotate coordinate system by latitude about new y-axis.
        //    vz"/r = dlat/dt, where r = position radius.
        //    vx" = dr/dt
        //
        $coslon = $x[0] / $rxy;         // cos(l[0]);
        $sinlon = $x[1] / $rxy;         // sin(l[0]);
        $coslat = $rxy / $ll[2];        // cos(l[1]);
        $sinlat = $x[2] / $ll[2];       // sin(ll[1]);
        $xx[3] = $x[3] * $coslon + $x[4] * $sinlon;
        $xx[4] = -$x[3] * $sinlon + $x[4] * $coslon;
        $l[3] = $xx[4] / $rxy;          // speed in longitude
        $xx[4] = -$sinlat * $xx[3] + $coslat * $x[5];
        $xx[5] = $coslat * $xx[3] + $sinlat * $x[5];
        $l[4] = $xx[4] / $ll[2];        // speed in latitude
        $l[5] = $xx[5];                 // speed in radius
        $l[0] = $ll[0];                 // return position
        $l[1] = $ll[1];
        $l[2] = $ll[2];
    }

    // conversion of position and speed
    // from polar (l[6]) to cartesian coordinates (x[6])
    // x = l is allowed
    // explanation s. swi_cartpol_sp()
    public static function swi_polcart_sp(array $l, array &$x): void
    {
        // zero speed
        if ($l[3] == 0 && $l[4] == 0 && $l[5] == 0) {
            $x[3] = $x[4] = $x[5] = 0;
            self::swi_polcart($l, $x);
            return;
        }
        // position
        $coslon = cos($l[0]);
        $sinlon = sin($l[0]);
        $coslat = cos($l[1]);
        $sinlat = sin($l[1]);
        $xx[0] = $l[2] * $coslat * $coslon;
        $xx[1] = $l[2] * $coslat * $sinlon;
        $xx[2] = $l[2] * $sinlat;
        // speed; explanation s. swi_cartpol_sp(), same method the other way round
        $rxyz = $l[2];
        $rxy = sqrt($xx[0] * $xx[0] + $xx[1] * $xx[1]);
        $xx[5] = $l[5];
        $xx[4] = $l[4] * $rxyz;
        $x[5] = $sinlat * $xx[5] + $coslat * $xx[4];        // speed z
        $xx[3] = $coslat * $xx[5] - $sinlat * $xx[4];
        $xx[4] = $l[3] * $rxy;
        $x[3] = $coslon * $xx[3] - $sinlon * $xx[4];        // speed x
        $x[4] = $sinlon * $xx[3] + $coslon * $xx[4];        // speed y
        $x[0] = $xx[0];
        $x[1] = $xx[1];
        $x[2] = $xx[2];
    }

    /**
     * Coordinate transformation from ecliptic to the equator or vice-versa.
     *
     * @param array $xpo Array of 3 float for coordinates:
     *  - 0: longitude
     *  - 1: latitude
     *  - 2: distance (unchanged, can be set to 1)
     * @param array $xpn Return array of 3 float with values:
     *  - 0: converted longitude
     *  - 1: converted latitude
     *  - 2: converted distance
     * @param float $eps Obliquity of ecliptic, in degrees
     * @return void
     *
     * @note For equatorial to ecliptical, obliquity must be positive. From ecliptical to
     * equatorial, obliquity must be negative. Longitude, latitude and obliquity
     * are in positive degrees.
     */
    public static function swe_cotrans(array $xpo, array &$xpn, float $eps): void
    {
        $e = $eps * SweConst::DEGTORAD;
        for ($i = 0; $i <= 1; $i++)
            $x[$i] = $xpo[$i];
        $x[0] *= SweConst::DEGTORAD;
        $x[1] *= SweConst::DEGTORAD;
        $x[2] = 1;
        for ($i = 3; $i <= 5; $i++)
            $x[$i] = 0;
        SwephCotransUtils::swi_polcart($x, $x);
        SwephCotransUtils::swi_coortrf($x, $x, $e);
        SwephCotransUtils::swi_cartpol($x, $x);
        $xpn[0] = $x[0] * SweConst::RADTODEG;
        $xpn[1] = $x[1] * SweConst::RADTODEG;
        $xpn[2] = $xpo[2];
    }

    /**
     * Coordinate transformation of position and speed, from ecliptic to the equator
     * or vice-versa.
     *
     * @param array $xpo Array of 6 float for coordinates:
     *  - 0: longitude
     *  - 1: latitude
     *  - 2: distance
     *  - 3: longitude speed
     *  - 4: latitude speed
     *  - 5: distance speed
     * @param array $xpn Return array of 6 float with values:
     *  - 0: converted longitude
     *  - 1: converted longitude speed
     *  - 2: converted latitude
     *  - 3: converted latitude speed
     *  - 4: converted distance
     *  - 5: converted distance speed
     * @param float $eps Obliquity of ecliptic, in degrees
     * @return void
     *
     * @note For equatorial to ecliptical, obliquity must be positive. From ecliptical to
     * equatorial, obliquity must be negative. Longitude, latitude, their speeds
     * and obliquity are in positive degrees.
     */
    public static function swe_cotrans_sp(array $xpo, array &$xpn, float $eps): void
    {
        $e = $eps * SweConst::DEGTORAD;
        for ($i = 0; $i <= 5; $i++)
            $x[$i] = $xpo[$i];
        $x[0] *= SweConst::DEGTORAD;
        $x[1] *= SweConst::DEGTORAD;
        $x[2] = 1;          // avoids problems with polcart(), if x[2] = 0
        $x[3] *= SweConst::DEGTORAD;
        $x[4] *= SweConst::DEGTORAD;
        SwephCotransUtils::swi_polcart_sp($x, $x);
        SwephCotransUtils::swi_coortrf($x, $x, $e);
        PointerUtils::pointerFn($x, 3,
            fn(&$arr) => SwephCotransUtils::swi_coortrf($arr, $arr, $e));
        SwephCotransUtils::swi_cartpol_sp($x, $xpn);
        $xpn[0] *= SweConst::RADTODEG;
        $xpn[1] *= SweConst::RADTODEG;
        $xpn[2] = $xpo[2];
        $xpn[3] *= SweConst::RADTODEG;
        $xpn[4] *= SweConst::RADTODEG;
        $xpn[5] = $xpo[5];
    }
}