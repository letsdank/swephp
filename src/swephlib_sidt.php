<?php

use Utils\SwephCotransUtils;

class swephlib_sidt
{
    private SwephLib $parent;

    function __construct(SwephLib $parent)
    {
        $this->parent = $parent;
    }

    /*
     * The time range of DE431 requires a new calculation of sidereal time that
     * gives sensible results for the remote past and future.
     * The algorithm is based on the formula of the mean earth by Simon & alii,
     * "Precession formulae and mean elements for the Moon and the Planets",
     * A&A 282 (1994), p. 675/678.
     * The longitude of the mean earth relative to the mean equinox J2000
     * is calculated and then precessed to the equinox of date, using the
     * default precession model of the Swiss Ephmeris. Afte that,
     * sidereal time is derived.
     * The algoritm provides exact agreement for epoch 1 Jan. 2003 with the
     * definition of sidereal time as given in the IERS Convention 2010.
     */
    function sidtime_long_term(float $tjd_ut, float $eps, float $nut): float
    {
        $tsid = 0;
        $nutlo = [];
        $dlt = Sweph::AUNIT / Sweph::CLIGHT / 86400.0;
        $tjd_et = $tjd_ut + $this->parent->swe_deltat_ex($tjd_ut, -1);
        $t = ($tjd_et - Sweph::J2000) / 365250.0;
        $t2 = $t * $t;
        $t3 = $t * $t2;
        // mean longitude of earth J2000
        $dlon = 100.46645683 + (1295977422.83429 * $t - 2.04411 * $t2 - 0.00523 * $t3) / 3600.0;
        // light time sun-earth
        $dlon = SwephLib::swe_degnorm($dlon - $dlt * 360.0 / 365.2425);
        $xs[0] = $dlon * SweConst::DEGTORAD;
        $xs[1] = 0;
        $xs[2] = 1;
        // to mean equator J2000, cartesian
        $xobl[0] = 23.45;
        $xobl[1] = 23.45;
        $xobl[1] = $this->parent->swi_epsiln(
                Sweph::J2000 + $this->parent->swe_deltat_ex(Sweph::J2000, -1),
                0) * SweConst::RADTODEG;
        SwephCotransUtils::swi_polcart($xs, $xs);
        SwephCotransUtils::swi_coortrf($xs, $xs, -$xobl[1] * SweConst::DEGTORAD);
        // precess to mean equinox of date
        $this->parent->swi_precess($xs, $tjd_et, 0, -1);
        // to mean equinox of date
        $xobl[1] = $this->parent->swi_epsiln($tjd_et, 0) * SweConst::RADTODEG;
        $this->parent->swi_nutation($tjd_et, 0, $nutlo);
        $xobl[0] = $xobl[1] + $nutlo[1] * SweConst::RADTODEG;
        $xobl[2] = $nutlo[0] * SweConst::RADTODEG;
        SwephCotransUtils::swi_coortrf($xs, $xs, $xobl[1] * SweConst::DEGTORAD);
        SwephCotransUtils::swi_cartpol($xs, $xs);
        $xs[0] *= SweConst::RADTODEG;
        $dhour = fmod($tjd_ut - 0.5, 1) * 360;
        // mean to true (if nut != 0)
        if ($eps == 0)
            $xs[0] += $xobl[2] * cos($xobl[0] * SweConst::DEGTORAD);
        else
            $xs[0] += $nut * cos($eps * SweConst::DEGTORAD);
        // add hour
        $xs[0] = SwephLib::swe_degnorm($xs[0] + $dhour);
        $tsid = $xs[0] / 15;
        return $tsid;
    }

    /* Apparent Sidereal Time at Greenwich with equation of the equinoxes
     *  ERA-based expression for Greenwich Sidereal Time (GST) based
     *  on the IAU 2006 precession and IAU 2000A_R06 nutation
     *  ftp://maia.usno.navy.mil/conv2010/chapter5/tab5.2e.txt
     *
     * returns sidereal time in hours.
     *
     * program returns sidereal hours since sidereal midnight
     * tjd 		julian day UT
     * eps 		obliquity of ecliptic, degrees
     * nut 		nutation, degrees
     */
    // C'_{s,j})_i    C'_{c,j})_i
    const int SIDTNTERM = 33;
    const array stcf = [
        2640.96, -0.39,
        63.52, -0.02,
        11.75, 0.01,
        11.21, 0.01,
        -4.55, 0.00,
        2.02, 0.00,
        1.98, 0.00,
        -1.72, 0.00,
        -1.41, -0.01,
        -1.26, -0.01,
        -0.63, 0.00,
        -0.63, 0.00,
        0.46, 0.00,
        0.45, 0.00,
        0.36, 0.00,
        -0.24, -0.12,
        0.32, 0.00,
        0.28, 0.00,
        0.27, 0.00,
        0.26, 0.00,
        -0.21, 0.00,
        0.19, 0.00,
        0.18, 0.00,
        -0.10, 0.05,
        0.15, 0.00,
        -0.14, 0.00,
        0.14, 0.00,
        -0.14, 0.00,
        0.14, 0.00,
        0.13, 0.00,
        -0.11, 0.00,
        0.11, 0.00,
        0.11, 0.00,
    ];
    const int SIDTNARG = 14;
    // l l' F D Om L_Me L_Ve L_E L_Ma L_J L_Sa L_U L_Ne p_A
    const array stfarg = [
        0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 0, 0, 0, 2, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 0, 2, -2, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 0, 2, -2, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 0, 2, -2, 2, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 0, 2, 0, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 0, 2, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 0, 0, 0, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 1, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 1, 0, 0, -1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        1, 0, 0, 0, -1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        1, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 1, 2, -2, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 1, 2, -2, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 0, 4, -4, 4, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 0, 1, -1, 1, 0, -8, 12, 0, 0, 0, 0, 0, 0,
        0, 0, 2, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 0, 2, 0, 2, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        1, 0, 2, 0, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        1, 0, 2, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 0, 2, -2, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 1, -2, 2, -3, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 1, -2, 2, -1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 0, 0, 0, 0, 0, 8, -13, 0, 0, 0, 0, 0, -1,
        0, 0, 0, 2, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        2, 0, -2, 0, -1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        1, 0, 0, -2, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 1, 2, -2, 2, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        1, 0, 0, -2, -1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 0, 4, -2, 4, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        0, 0, 2, -2, 4, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        1, 0, -2, 0, -3, 0, 0, 0, 0, 0, 0, 0, 0, 0,
        1, 0, -2, 0, -1, 0, 0, 0, 0, 0, 0, 0, 0, 0,
    ];

    function sidtime_non_polynomial_part(float $tt): float
    {
        // L Mean anomaly of the Moon.
        $delm[0] = SwephLib::swe_radnorm(2.35555598 + 8328.6914269554 * $tt);
        // LSU Mean anomaly of the Sun.
        $delm[1] = SwephLib::swe_radnorm(6.24006013 + 628.301955 * $tt);
        // F Mean argument of the latitude of the Moon.
        $delm[2] = SwephLib::swe_radnorm(1.627905234 + 8433.466158131 * $tt);
        // D Mean elongation of the Moon from the Sun.
        $delm[3] = SwephLib::swe_radnorm(5.198466741 + 7771.3771468121 * $tt);
        // OM Mean longitude of the ascending node of the Moon.
        $delm[4] = SwephLib::swe_radnorm(2.18243920 - 33.757045 * $tt);
        // Planetary longitudes, Mercury through Neptune (Souchay et al. 1999).
        // LME, LVE, LEA, LMA, LJU, LSA, LUR, LNE
        $delm[5] = SwephLib::swe_radnorm(4.402608842 + 2608.7903141574 * $tt);
        $delm[6] = SwephLib::swe_radnorm(3.176146697 + 1021.3285546211 * $tt);
        $delm[7] = SwephLib::swe_radnorm(1.753470314 + 628.3075849991 * $tt);
        $delm[8] = SwephLib::swe_radnorm(6.203480913 + 334.0612426700 * $tt);
        $delm[9] = SwephLib::swe_radnorm(0.599546497 + 52.9690962641 * $tt);
        $delm[10] = SwephLib::swe_radnorm(0.874016757 + 21.3299104960 * $tt);
        $delm[11] = SwephLib::swe_radnorm(5.481293871 + 7.4781598567 * $tt);
        $delm[12] = SwephLib::swe_radnorm(5.321159000 + 3.8127774000 * $tt);
        // PA General accumulated precession in  longitude.
        $delm[13] = (0.02438175 + 0.00000538691 * $tt) * $tt;
        $dadd = -0.87 * sin($delm[4]) * $tt;
        for ($i = 0; $i < self::SIDTNTERM; $i++) {
            $darg = 0;
            for ($j = 0; $j < self::SIDTNARG; $j++) {
                $darg += self::stfarg[$i * self::SIDTNARG + $j] * $delm[$j];
            }
            $dadd += self::stcf[$i * 2] * sin($darg) + self::stcf[$i * 2 + 1] * cos($darg);
        }
        $dadd /= (3600.0 * 1000000.0);
        return $dadd;
    }

    /*
     * SEMOD_SIDT_IAU_2006
     * N. Capitaine, P.T. Wallace, and J. Chapront, "Expressions for IAU 2000
     * precession quantities", 2003, A&A 412, 567-586 (2003), p. 582.
     * This is a "short" term model, that can be combined with other models
     */

    // sidtime_long_term() is not used between the following two dates
    const float SIDT_LTERM_T0 = 2396758.5;      // 1 Jan 1850
    const float SIDT_LTERM_T1 = 2469807.5;      // 1 Jan 2050
    const float SIDT_LTERM_OFS0 = (0.000378172 / 15.0);
    const float SIDT_LTERM_OFS1 = (0.001385646 / 15.0);
}