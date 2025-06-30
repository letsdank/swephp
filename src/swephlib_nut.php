<?php

use Enums\SweModel;
use Enums\SweModelBias;
use Enums\SweModelJPLHorizonApprox;
use Enums\SweModelNutation;
use Utils\ArrayUtils;
use Utils\SwephCotransUtils;

class swephlib_nut
{
    private SwephLib $parent;

    function __construct(SwephLib $parent)
    {
        $this->parent = $parent;
    }

    /* Nutation in longitude and obliquity
     * computed at Julian date J.
     *
     * References:
     * "Summary of 1980 IAU Theory of Nutation (Final Report of the
     * IAU Working Group on Nutation)", P. K. Seidelmann et al., in
     * Transactions of the IAU Vol. XVIII A, Reports on Astronomy,
     * P. A. Wayman, ed.; D. Reidel Pub. Co., 1982.
     *
     * "Nutation and the Earth's Rotation",
     * I.A.U. Symposium No. 78, May, 1977, page 256.
     * I.A.U., 1980.
     *
     * Woolard, E.W., "A redevelopment of the theory of nutation",
     * The Astronomical Journal, 58, 1-3 (1953).
     *
     * This program implements all of the 1980 IAU nutation series.
     * Results checked at 100 points against the 1986 AA; all agreed.
     *
     *
     * - S. L. Moshier, November 1987
     *   October, 1992 - typo fixed in nutation matrix
     *
     * - D. Koch, November 1995: small changes in structure,
     *   Corrections to IAU 1980 Series added from Expl. Suppl. p. 116
     *
     * Each term in the expansion has a trigonometric
     * argument given by
     *   W = i*MM + j*MS + k*FF + l*DD + m*OM
     * where the variables are defined below.
     * The nutation in longitude is a sum of terms of the
     * form (a + bT) * sin(W). The terms for nutation in obliquity
     * are of the form (c + dT) * cos(W).  The coefficients
     * are arranged in the tabulation as follows:
     *
     * Coefficient:
     * i  j  k  l  m      a      b      c     d
     * 0, 0, 0, 0, 1, -171996, -1742, 92025, 89,
     * The first line of the table, above, is done separately
     * since two of the values do not fit into 16 bit integers.
     * The values a and c are arc seconds times 10000.  b and d
     * are arc seconds per Julian century times 100000.  i through m
     * are integers.  See the program for interpretation of MM, MS,
     * etc., which are mean orbital elements of the Sun and Moon.
     *
     * If terms with coefficient less than X are omitted, the peak
     * errors will be:
     *
     *   omit	error,		  omit	error,
     *   a <	longitude	  c <	obliquity
     * .0005"	.0100"		.0008"	.0094"
     * .0046	.0492		.0095	.0481
     * .0123	.0880		.0224	.0905
     * .0386	.1808		.0895	.1129
     */

    const array nt = [
        /* LS and OC are units of 0.0001"
 *LS2 and OC2 are units of 0.00001"
 *MM,MS,FF,DD,OM, LS, LS2,OC, OC2 */
        0, 0, 0, 0, 2, 2062, 2, -895, 5,
        -2, 0, 2, 0, 1, 46, 0, -24, 0,
        2, 0, -2, 0, 0, 11, 0, 0, 0,
        -2, 0, 2, 0, 2, -3, 0, 1, 0,
        1, -1, 0, -1, 0, -3, 0, 0, 0,
        0, -2, 2, -2, 1, -2, 0, 1, 0,
        2, 0, -2, 0, 1, 1, 0, 0, 0,
        0, 0, 2, -2, 2, -13187, -16, 5736, -31,
        0, 1, 0, 0, 0, 1426, -34, 54, -1,
        0, 1, 2, -2, 2, -517, 12, 224, -6,
        0, -1, 2, -2, 2, 217, -5, -95, 3,
        0, 0, 2, -2, 1, 129, 1, -70, 0,
        2, 0, 0, -2, 0, 48, 0, 1, 0,
        0, 0, 2, -2, 0, -22, 0, 0, 0,
        0, 2, 0, 0, 0, 17, -1, 0, 0,
        0, 1, 0, 0, 1, -15, 0, 9, 0,
        0, 2, 2, -2, 2, -16, 1, 7, 0,
        0, -1, 0, 0, 1, -12, 0, 6, 0,
        -2, 0, 0, 2, 1, -6, 0, 3, 0,
        0, -1, 2, -2, 1, -5, 0, 3, 0,
        2, 0, 0, -2, 1, 4, 0, -2, 0,
        0, 1, 2, -2, 1, 4, 0, -2, 0,
        1, 0, 0, -1, 0, -4, 0, 0, 0,
        2, 1, 0, -2, 0, 1, 0, 0, 0,
        0, 0, -2, 2, 1, 1, 0, 0, 0,
        0, 1, -2, 2, 0, -1, 0, 0, 0,
        0, 1, 0, 0, 2, 1, 0, 0, 0,
        -1, 0, 0, 1, 1, 1, 0, 0, 0,
        0, 1, 2, -2, 0, -1, 0, 0, 0,
        0, 0, 2, 0, 2, -2274, -2, 977, -5,
        1, 0, 0, 0, 0, 712, 1, -7, 0,
        0, 0, 2, 0, 1, -386, -4, 200, 0,
        1, 0, 2, 0, 2, -301, 0, 129, -1,
        1, 0, 0, -2, 0, -158, 0, -1, 0,
        -1, 0, 2, 0, 2, 123, 0, -53, 0,
        0, 0, 0, 2, 0, 63, 0, -2, 0,
        1, 0, 0, 0, 1, 63, 1, -33, 0,
        -1, 0, 0, 0, 1, -58, -1, 32, 0,
        -1, 0, 2, 2, 2, -59, 0, 26, 0,
        1, 0, 2, 0, 1, -51, 0, 27, 0,
        0, 0, 2, 2, 2, -38, 0, 16, 0,
        2, 0, 0, 0, 0, 29, 0, -1, 0,
        1, 0, 2, -2, 2, 29, 0, -12, 0,
        2, 0, 2, 0, 2, -31, 0, 13, 0,
        0, 0, 2, 0, 0, 26, 0, -1, 0,
        -1, 0, 2, 0, 1, 21, 0, -10, 0,
        -1, 0, 0, 2, 1, 16, 0, -8, 0,
        1, 0, 0, -2, 1, -13, 0, 7, 0,
        -1, 0, 2, 2, 1, -10, 0, 5, 0,
        1, 1, 0, -2, 0, -7, 0, 0, 0,
        0, 1, 2, 0, 2, 7, 0, -3, 0,
        0, -1, 2, 0, 2, -7, 0, 3, 0,
        1, 0, 2, 2, 2, -8, 0, 3, 0,
        1, 0, 0, 2, 0, 6, 0, 0, 0,
        2, 0, 2, -2, 2, 6, 0, -3, 0,
        0, 0, 0, 2, 1, -6, 0, 3, 0,
        0, 0, 2, 2, 1, -7, 0, 3, 0,
        1, 0, 2, -2, 1, 6, 0, -3, 0,
        0, 0, 0, -2, 1, -5, 0, 3, 0,
        1, -1, 0, 0, 0, 5, 0, 0, 0,
        2, 0, 2, 0, 1, -5, 0, 3, 0,
        0, 1, 0, -2, 0, -4, 0, 0, 0,
        1, 0, -2, 0, 0, 4, 0, 0, 0,
        0, 0, 0, 1, 0, -4, 0, 0, 0,
        1, 1, 0, 0, 0, -3, 0, 0, 0,
        1, 0, 2, 0, 0, 3, 0, 0, 0,
        1, -1, 2, 0, 2, -3, 0, 1, 0,
        -1, -1, 2, 2, 2, -3, 0, 1, 0,
        -2, 0, 0, 0, 1, -2, 0, 1, 0,
        3, 0, 2, 0, 2, -3, 0, 1, 0,
        0, -1, 2, 2, 2, -3, 0, 1, 0,
        1, 1, 2, 0, 2, 2, 0, -1, 0,
        -1, 0, 2, -2, 1, -2, 0, 1, 0,
        2, 0, 0, 0, 1, 2, 0, -1, 0,
        1, 0, 0, 0, 2, -2, 0, 1, 0,
        3, 0, 0, 0, 0, 2, 0, 0, 0,
        0, 0, 2, 1, 2, 2, 0, -1, 0,
        -1, 0, 0, 0, 2, 1, 0, -1, 0,

        1, 0, 0, -4, 0, -1, 0, 0, 0,
        -2, 0, 2, 2, 2, 1, 0, -1, 0,
        -1, 0, 2, 4, 2, -2, 0, 1, 0,
        2, 0, 0, -4, 0, -1, 0, 0, 0,
        1, 1, 2, -2, 2, 1, 0, -1, 0,
        1, 0, 2, 2, 1, -1, 0, 1, 0,
        -2, 0, 2, 4, 2, -1, 0, 1, 0,
        -1, 0, 4, 0, 2, 1, 0, 0, 0,
        1, -1, 0, -2, 0, 1, 0, 0, 0,
        2, 0, 2, -2, 1, 1, 0, -1, 0,
        2, 0, 2, 2, 2, -1, 0, 0, 0,
        1, 0, 0, 2, 1, -1, 0, 0, 0,
        0, 0, 4, -2, 2, 1, 0, 0, 0,
        3, 0, 2, -2, 2, 1, 0, 0, 0,
        1, 0, 2, -2, 0, -1, 0, 0, 0,
        0, 1, 2, 0, 1, 1, 0, 0, 0,
        -1, -1, 0, 2, 1, 1, 0, 0, 0,
        0, 0, -2, 0, 1, -1, 0, 0, 0,
        0, 0, 2, -1, 2, -1, 0, 0, 0,
        0, 1, 0, 2, 0, -1, 0, 0, 0,
        1, 0, -2, -2, 0, -1, 0, 0, 0,
        0, -1, 2, 0, 1, -1, 0, 0, 0,
        1, 1, 0, -2, 1, -1, 0, 0, 0,
        1, 0, -2, 2, 0, -1, 0, 0, 0,
        2, 0, 0, 2, 0, 1, 0, 0, 0,
        0, 0, 2, 4, 2, -1, 0, 0, 0,
        0, 1, 0, 1, 0, 1, 0, 0, 0,
        /*#if NUT_CORR_1987  switch is handled in function calc_nutation_iau1980() */
        /* corrections to IAU 1980 nutation series by Herring 1987
         *             in 0.00001" !!!
         *              LS      OC      */
        101, 0, 0, 0, 1, -725, 0, 213, 0,
        101, 1, 0, 0, 0, 523, 0, 208, 0,
        101, 0, 2, -2, 2, 102, 0, -41, 0,
        101, 0, 2, 0, 2, -81, 0, 32, 0,
        /*              LC      OS !!!  */
        102, 0, 0, 0, 1, 417, 0, 224, 0,
        102, 1, 0, 0, 0, 61, 0, -24, 0,
        102, 0, 2, -2, 2, -118, 0, -47, 0,
        /*#endif*/
        SweConst::ENDMARK,
    ];

    function calc_nutation_iau1980(float $J, array &$nutlo): int
    {
        // arrays to hold sines ans cosines of multiple angles
        $ss = ArrayUtils::createArray2D(5, 8);
        $cc = ArrayUtils::createArray2D(5, 8);
        $nut_model = $this->parent->getSwePhp()->swed->astro_models[SweModel::MODEL_NUT->value];
        if ($nut_model == 0) $nut_model = SweModelNutation::default();
        // Julian centuries from 2000 January 1.5,
        // barycentric dynamical time
        //
        $T = ($J - 2451545.0) / 36525.0;
        $T2 = $T * $T;
        // Fundamental arguments in the FK5 reference system.
        // The coefficients, originally given to 0.001",
        // are converted here to degrees.
        //

        // longitude of the mean ascending node of the lunar orbit
        // on the ecliptic, measured from the mean equinox of date
        //
        $OM = -6962890.539 * $T + 450160.280 + (0.008 * $T + 7.455) * $T2;
        $OM = SwephLib::swe_degnorm($OM / 3600) * SweConst::DEGTORAD;
        // mean longitude of the Sun minus the
        // mean longitude of the Sun's perigee
        //
        $MS = 129596581.224 * $T + 1287099.804 - (0.012 * $T + 0.577) * $T2;
        $MS = SwephLib::swe_degnorm($MS / 3600) * SweConst::DEGTORAD;
        // mean longitude of the Moon minus the
        // mean longitude of the Moon's perigee
        //
        $MM = 1717915922.633 * $T + 485866.733 + (0.064 * $T + 31.310) * $T2;
        $MM = SwephLib::swe_degnorm($MM / 3600) * SweConst::DEGTORAD;
        // mean longitude of the Moon minus the
        // mean longitude of the Moon's node
        $FF = 1739527263.137 * $T + 335778.877 + (0.011 * $T - 13.257) * $T2;
        $FF = SwephLib::swe_degnorm($FF / 3600) * SweConst::DEGTORAD;
        // mean elongation of the Moon from the Sun.
        //
        $DD = 1602961601.328 * $T + 1072261.307 + (0.019 * $T - 6.891) * $T2;
        $DD = SwephLib::swe_degnorm($DD / 3600) * SweConst::DEGTORAD;
        $args[0] = $MM;
        $ns[0] = 3;
        $args[1] = $MS;
        $ns[1] = 2;
        $args[2] = $FF;
        $ns[2] = 4;
        $args[3] = $DD;
        $ns[3] = 4;
        $args[4] = $OM;
        $ns[4] = 2;
        // Calculate sin(i*MM), etc. for needed multiple angles
        //
        for ($k = 0; $k <= 4; $k++) {
            $arg = $args[$k];
            $n = $ns[$k];
            $su = sin($arg);
            $cu = cos($arg);
            $ss[$k][0] = $su;               // sin(L)
            $cc[$k][0] = $cu;               // cos(L)
            $sv = 2.0 * $su * $cu;
            $cv = $cu * $cu - $su * $su;
            $ss[$k][1] = $sv;               // sin(2L)
            $cc[$k][1] = $cv;
            for ($i = 2; $i < $n; $i++) {
                $s = $su * $cv + $cu * $sv;
                $cv = $cu * $cv - $su * $sv;
                $sv = $s;
                $ss[$k][$i] = $sv;          // sin( i+1 L )
                $cc[$k][$i] = $cv;
            }
        }
        // first terms, not in table:
        $C = (-0.01742 * $T - 17.1996) * $ss[4][0]; // sin(OM)
        $D = (0.00089 * $T + 9.2025) * $cc[4][0];   // cos(OM)
        for ($i = 0; $i != SweConst::ENDMARK; $i += 9) {
            if ($nut_model != SweModelNutation::MOD_NUT_IAU_CORR_1987 && (self::nt[$i] == 101 || self::nt[$i] == 102))
                continue;
            // argument of sine and cosine
            $k1 = 0;
            $cv = 0.0;
            $sv = 0.0;
            for ($m = 0; $m < 5; $m++) {
                $j = self::nt[$i + $m];
                if ($j > 100)
                    $j = 0; // p[0] is a flag
                if ($j) {
                    $k = $j;
                    if ($j < 0)
                        $k = -$k;
                    $su = $ss[$m][$k - 1]; // sin(k*angle)
                    if ($j < 0)
                        $su = -$su;
                    $cu = $cc[$m][$k - 1];
                    if ($k1 == 0) { // set first angle
                        $sv = $su;
                        $cv = $cu;
                        $k1 = 1;
                    } else {        // combine angles
                        $sw = $su * $cv + $cu * $sv;
                        $cv = $cu * $cv - $su * $sv;
                        $sv = $sw;
                    }
                }
            }
            // longitude coefficient, in 0.0001"
            $f = self::nt[$i + 5] * 0.0001;
            if (self::nt[$i + 6] != 0)
                $f += 0.00001 * $T * self::nt[$i + 6];
            // obliquity coefficient, in 0.0001"
            $g = self::nt[$i + 7] * 0.0001;
            if (self::nt[$i + 8] != 0)
                $g += 0.00001 * $T * self::nt[$i + 8];
            if (self::nt[$i] >= 100) {      // coefficients in 0.00001"
                $f *= 0.1;
                $g *= 0.1;
            }
            // accumulate the terms
            if (self::nt[$i] != 102) {
                $C += $f * $sv;
                $D += $g * $cv;
            } else {                        // cos for nutl and sin for nuto
                $C += $f * $cv;
                $D += $g * $sv;
            }
        }
        // Save answers, expressed in radians
        $nutlo[0] = SweConst::DEGTORAD * $C / 3600.0;
        $nutlo[1] = SweConst::DEGTORAD * $D / 3600.0;
        return 0;
    }

    /* Nutation IAU 2000A model
     * (MHB2000 luni-solar and planetary nutation, without free core nutation)
     *
     * Function returns nutation in longitude and obliquity in radians with
     * respect to the equinox of date. For the obliquity of the ecliptic
     * the calculation of Lieske & al. (1977) must be used.
     *
     * The precision in recent years is about 0.001 arc seconds.
     *
     * The calculation includes luni-solar and planetary nutation.
     * Free core nutation, which cannot be predicted, is omitted,
     * the error being of the order of a few 0.0001 arc seconds.
     *
     * References:
     *
     * Capitaine, N., Wallace, P.T., Chapront, J., A & A 432, 366 (2005).
     *
     * Chapront, J., Chapront-Touze, M. & Francou, G., A & A 387, 700 (2002).
     *
     * Lieske, J.H., Lederle, T., Fricke, W. & Morando, B., "Expressions
     * for the precession quantities based upon the IAU (1976) System of
     * Astronomical Constants", A & A 58, 1-16 (1977).
     *
     * Mathews, P.M., Herring, T.A., Buffet, B.A., "Modeling of nutation
     * and precession   New nutation series for nonrigid Earth and
     * insights into the Earth's interior", J.Geophys.Res., 107, B4,
     * 2002.
     *
     * Simon, J.-L., Bretagnon, P., Chapront, J., Chapront-Touze, M.,
     * Francou, G., Laskar, J., A & A 282, 663-683 (1994).
     *
     * Souchay, J., Loysel, B., Kinoshita, H., Folgueira, M., A & A Supp.
     * Ser. 135, 111 (1999).
     *
     * Wallace, P.T., "Software for Implementing the IAU 2000
     * Resolutions", in IERS Workshop 5.1 (2002).
     *
     * Nutation IAU 2000A series in:
     * Kaplan, G.H., United States Naval Observatory Circular No. 179 (Oct. 2005)
     * aa.usno.navy.mil/publications/docs/Circular_179.html
     *
     * MHB2000 code at
     * - ftp://maia.usno.navy.mil/conv2000/chapter5/IAU2000A.
     * - http://www.iau-sofa.rl.ac.uk/2005_0901/Downloads.html
     */
    function calc_nutation_iau2000ab(float $J, array &$nutlo): int
    {
        $dpsi = 0;
        $deps = 0;
        $T = ($J - Sweph::J2000) / 36525.0;
        $nut_model = $this->parent->getSwePhp()->swed->astro_models[SweModel::MODEL_NUT->value];
        if ($nut_model == 0) $nut_model = SweModelNutation::default();
        // luni-solar nutation
        // Fundamental arguments, Simon & al. (1994)
        // Mean anomaly of the Moon.
        $M = SwephLib::swe_degnorm((485868.249036 +
                    $T * (1717915923.2178 +
                        $T * (31.8792 +
                            $T * (0.051635 +
                                $T * (-0.00024470))))) / 3600.0) * SweConst::DEGTORAD;
        // Mean anomaly of the Sun
        $SM = SwephLib::swe_degnorm((1287104.79305 +
                    $T * (129596581.0481 +
                        $T * (-0.5532 +
                            $T * (0.000136 +
                                $T * (-0.00001149))))) / 3600.0) * SweConst::DEGTORAD;
        // Mean argument of the latitude of the Moon.
        $F = SwephLib::swe_degnorm((335779.526232 +
                    $T * (1739527262.8478 +
                        $T * (-12.7512 +
                            $T * (-0.001037 +
                                $T * (0.00000417))))) / 3600.0) * SweConst::DEGTORAD;
        // Mean elongation of the Moon from the Sun.
        $D = SwephLib::swe_degnorm((1072260.70369 +
                    $T * (1602961601.2090 +
                        $T * (-6.3706 +
                            $T * (0.006593 +
                                $T * (-0.00003169))))) / 3600.0) * SweConst::DEGTORAD;
        // Mean longitude of the ascending node of the Moon.
        $OM = SwephLib::swe_degnorm((450160.398036 +
                    $T * (-6962890.5431 +
                        $T * (7.4722 +
                            $T * (0.007702 +
                                $T * (-0.00005939))))) / 3600.0) * SweConst::DEGTORAD;
        // luni-solar nutation series, in reverse order, starting with small terms
        if ($nut_model == SweModelNutation::MOD_NUT_IAU_2000B)
            $inls = swenut2000a::NLS_2000B;
        else
            $inls = swenut2000a::NLS;
        for ($i = $inls - 1; $i >= 0; $i--) {
            $j = $i * 5;
            $darg = SwephLib::swe_degnorm((float)swenut2000a::nls[$j] * $M +
                (float)swenut2000a::nls[$j + 1] * $SM +
                (float)swenut2000a::nls[$j + 2] * $F +
                (float)swenut2000a::nls[$j + 3] * $D +
                (float)swenut2000a::nls[$j + 4] * $OM);
            $sinarg = sin($darg);
            $cosarg = cos($darg);
            $k = $i * 6;
            $dpsi += (swenut2000a::cls[$k] + swenut2000a::cls[$k + 1] * $T) * $sinarg + swenut2000a::cls[$k + 2] * $cosarg;
            $deps += (swenut2000a::cls[$k + 3] + swenut2000a::cls[$k + 4] * $T) * $cosarg + swenut2000a::cls[$k + 5] * $sinarg;
        }
        $nutlo[0] = $dpsi * swenut2000a::O1MAS2DEG;
        $nutlo[1] = $deps * swenut2000a::O1MAS2DEG;
        if ($nut_model == SweModelNutation::MOD_NUT_IAU_2000A) {
            // planetary nutation
            // note: The MHB2000 code computes the luni-solar and planetary nutation
            // in different routines, using slightly different Delaunay
            // arguments in the two cases.  This behaviour is faithfully
            // reproduces here.  Use of the Simon et al. expressions for both
            // cases leads to negligible changes, well below 0.1 microarcsecond.

            // Mean anomaly of the Moon.
            $AL = SwephLib::swe_radnorm(2.35555598 + 8328.6914269554 * $T);
            // Mean anomaly of the Sun.
            $ALSU = SwephLib::swe_radnorm(6.24006013 + 628.301955 * $T);
            // Mean argument of the latitude of the Moon.
            $AF = SwephLib::swe_radnorm(1.627905234 + 8433.466158131 * $T);
            // Mean elongation of the Moon from the Sun.
            $AD = SwephLib::swe_radnorm(5.198466741 + 7771.3771468121 * $T);
            // Mean longitude of the ascending node of the Moon.
            $AOM = SwephLib::swe_radnorm(2.18243920 - 33.757045 * $T);
            // Planetary longitudes, Mercury through Neptune (Souchay et al. 1999).
            $ALME = SwephLib::swe_radnorm(4.402608842 + 2608.7903141574 * $T);
            $ALVE = SwephLib::swe_radnorm(3.176146697 + 1021.3285546211 * $T);
            $ALEA = SwephLib::swe_radnorm(1.753470314 + 628.3075849991 * $T);
            $ALMA = SwephLib::swe_radnorm(6.203480913 + 334.0612426700 * $T);
            $ALJU = SwephLib::swe_radnorm(0.599546497 + 52.9690962641 * $T);
            $ALSA = SwephLib::swe_radnorm(0.874016757 + 21.3299104960 * $T);
            $ALUR = SwephLib::swe_radnorm(5.481293871 + 7.4781598567 * $T);
            $ALNE = SwephLib::swe_radnorm(5.321159000 + 3.8127774000 * $T);
            // General accumulated precession in longitude.
            $APA = (0.02438175 + 0.00000538691 * $T) * $T;
            // planetary nutation series (in reverse order).
            $dpsi = 0;
            $deps = 0;
            for ($i = swenut2000a::NPL - 1; $i >= 0; $i--) {
                $j = $i * 14;
                $darg = SwephLib::swe_radnorm((float)swenut2000a::npl[$j] * $AL +
                    (float)swenut2000a::npl[$j + 1] * $ALSU +
                    (float)swenut2000a::npl[$j + 2] * $AF +
                    (float)swenut2000a::npl[$j + 3] * $AD +
                    (float)swenut2000a::npl[$j + 4] * $AOM +
                    (float)swenut2000a::npl[$j + 5] * $ALME +
                    (float)swenut2000a::npl[$j + 6] * $ALVE +
                    (float)swenut2000a::npl[$j + 7] * $ALEA +
                    (float)swenut2000a::npl[$j + 8] * $ALMA +
                    (float)swenut2000a::npl[$j + 9] * $ALJU +
                    (float)swenut2000a::npl[$j + 10] * $ALSA +
                    (float)swenut2000a::npl[$j + 11] * $ALUR +
                    (float)swenut2000a::npl[$j + 12] * $ALNE +
                    (float)swenut2000a::npl[$j + 13] * $APA);
                $k = $i * 4;
                $sinarg = sin($darg);
                $cosarg = cos($darg);
                $dpsi += (float)swenut2000a::icpl[$k] * $sinarg + (float)swenut2000a::icpl[$k + 1] * $cosarg;
                $deps += (float)swenut2000a::icpl[$k + 2] * $sinarg + (float)swenut2000a::icpl[$k + 3] * $cosarg;
            }
            $nutlo[0] += $dpsi * swenut2000a::O1MAS2DEG;
            $nutlo[1] += $deps * swenut2000a::O1MAS2DEG;
            // changes required by adoption of P03 precession
            // according to Capitaine et al. A & A 412, 366 (2005) = IAU 2006
            $dpsi = -8.1 * sin($OM) - 0.6 * sin(2 * $F - 2 * $D + 2 * $OM);
            $dpsi += $T * (47.8 * sin($OM) + 3.7 * sin(2 * $F - 2 * $D + 2 * $OM) + 0.6 * sin(2 * $F + 2 * $OM) - 0.6 * sin(2 * $OM));
            $deps = $T * (-25.6 * cos($OM) - 1.6 * cos(2 * $F - 2 * $D + 2 * $OM));
            $nutlo[0] += $dpsi / (3600.0 * 1000000.0);
            $nutlo[1] += $deps / (3600.0 * 1000000.0);
        }
        $nutlo[0] *= SweConst::DEGTORAD;
        $nutlo[1] *= SweConst::DEGTORAD;
        return 0;
    }

    // an incomplete implementation of nutation Woolard 1953
    function calc_nutation_woolard(float $J, array &$nutlo): int
    {
        // ls - sun's mean longitude, ld - moon's mean longitude
        // ms - sun's mean anomaly, moon's mean anomaly
        // nm - longitude of moon's ascending node
        // t, t2 - number of Julian centuries of 36525 days since Jan 0.5 1900.
        $mjd = $J - Sweph::J1900;
        $t = $mjd / 36525.;
        $t2 = $t * $t;
        $a = 100.0021358 * $t;
        $b = 360. * ($a - (int)$a);
        $ls = 279.697 * .000303 * $t2 + $b;
        $a = 1336.855231 * $t;
        $b = 360. * ($a - (int)$a);
        $ld = 270.434 - .001133 * $t2 + $b;
        $a = 99.99736056000026 * $t;
        $b = 360. * ($a - (int)$a);
        $ms = 358.476 - .00015 * $t2 + $b;
        $a = 13255523.59 * $t;
        $b = 360. * ($a - (int)$a);
        $md = 296.105 + .009192 * $t2 + $b;
        $a = 5.372616667 * $t;
        $b = 360. * ($a - (int)$a);
        $nm = 259.183 + .002078 * $t2 - $b;
        // convert to radian forms for use with trig functions.
        //
        $tls = 2 * $ls * SweConst::DEGTORAD;
        $nm = $nm * SweConst::DEGTORAD;
        $tnm = 2 * $nm;
        $ms = $ms * SweConst::DEGTORAD;
        $tld = 2 * $ld * SweConst::DEGTORAD;
        $md = $md * SweConst::DEGTORAD;
        // find data psi and eps, in arcseconds.
        //
        $dpsi = (-17.2327 - .01737 * $t) * sin($nm) + (-1.2729 - .00013 * $t) * sin($tls)
            + .2088 * sin($tnm) - .2037 * sin($tld) + (.1261 - .00031 * $t) * sin($ms)
            + .0675 * sin($md) - (.0497 - .00012 * $t) * sin($tls + $ms)
            - .0342 * sin($tld - $nm) - .0261 * sin($tld + $md) + .0214 * sin($tls - $ms)
            - .0149 * sin($tls - $tld + $md) + .0124 * sin($tls - $nm) + .0114 * sin($tld - $md);
        $deps = (9.21 + .00091 * $t) * cos($nm) + (.5522 - .00029 * $t) * cos($tls)
            - .0904 * cos($tnm) + .0884 * cos($tld) + .0216 * cos($tls + $ms)
            + .0183 * cos($tld - $nm) + .0113 * cos($tld + $md) - .0093 * cos($tls - $ms)
            - .0066 * cos($tls - $nm);
        // convert to radians.
        //
        $dpsi = $dpsi / 3600.0 * SweConst::DEGTORAD;
        $deps = $deps / 3600.0 * SweConst::DEGTORAD;
        $nutlo[1] = $deps;
        $nutlo[0] = $dpsi;
        return SweConst::OK;
    }

    function bessel(array $v, int $n, float $t): float
    {
        if ($t <= 0) {
            $ans = $v[0];
            goto done;
        }
        if ($t >= $n - 1) {
            $ans = $v[$n - 1];
            goto done;
        }
        $p = floor($t);
        $iy = (int)$t;
        // Zeroth order estimate is value at start of year
        $ans = $v[$iy];
        $k = $iy + 1;
        if ($k >= $n)
            goto done;
        // The fraction of tabulation interval
        $p = $t - $p;
        $ans += $p * ($v[$k] - $v[$iy]);
        if (($iy - 1 < 0) || ($iy + 2 >= $n))
            goto done; // can't do second differences
        // Make table of first differences
        $k = $iy - 2;
        for ($i = 0; $i < 5; $i++) {
            if (($k < 0) || ($k + 1 >= $n))
                $d[$i] = 0;
            else
                $d[$i] = $v[$k + 1] - $v[$k];
            $k += 1;
        }
        // Compute second differences
        for ($i = 0; $i < 4; $i++)
            $d[$i] = $d[$i + 1] - $d[$i];
        $B = .025 * $p * ($p - 1.0);
        $ans += $B * ($d[1] + $d[2]);
        if (SweInternalParams::DEMO)
            printf("B %.4lf, ans %.4lf\n", $B, $ans);
        if ($iy + 2 >= $n)
            goto done;
        // Compute third differences
        for ($i = 0; $i < 3; $i++)
            $d[$i] = $d[$i + 1] - $d[$i];
        $B = 2.0 * $B / 3.0;
        $ans += ($p - 0.5) * $B * $d[1];
        if (SweInternalParams::DEMO)
            printf("B %.4lf, ans %.4lf\n", $B * ($p - 0.5), $ans);
        if (($iy - 2 < 0) || ($iy + 3 > $n))
            goto done;
        // Compute fourth differences
        for ($i = 0; $i < 2; $i++)
            $d[$i] = $d[$i + 1] - $d[$i];
        $B = 0.125 * $B * ($p + 1.0) * ($p - 2.0);
        $ans += $B * ($d[0] + $d[1]);
        if (SweInternalParams::DEMO)
            printf("B %.4lf, ans %.4lf\n", $B, $ans);
        done:
        return $ans;
    }

    function calc_nutation(float $J, int $iflag, array &$nutlo): int
    {
        $nut_model = $this->parent->getSwePhp()->swed->astro_models[SweModel::MODEL_NUT->value];
        $jplhora_model = $this->parent->getSwePhp()->swed->astro_models[SweModel::MODEL_JPLHORA_MODE->value];
        $is_jplhor = false;
        if ($nut_model == 0) $nut_model = SweModelNutation::default();
        if ($jplhora_model == 0) $jplhora_model = SweModelJPLHorizonApprox::default();
        if ($iflag & SweConst::SEFLG_JPLHOR)
            $is_jplhor = true;
        if (($iflag & SweConst::SEFLG_JPLHOR_APPROX) &&
            $jplhora_model == SweModelJPLHorizonApprox::MOD_JPLHORA_3 &&
            $J <= swephlib_precess::HORIZONS_TJD0_DPSI_DEPS_IAU1980)
            $is_jplhor = true;
        if ($is_jplhor) {
            $this->calc_nutation_iau1980($J, $nutlo);
            if ($iflag & SweConst::SEFLG_JPLHOR) {
                $n = (int)($this->parent->getSwePhp()->swed->eop_tjd_end -
                    $this->parent->getSwePhp()->swed->eop_tjd_beg + 0.000001);
                $J2 = $J;
                if ($J < $this->parent->getSwePhp()->swed->eop_tjd_beg_horizons)
                    $J2 = $this->parent->getSwePhp()->swed->eop_tjd_beg_horizons;
                $dpsi = $this->bessel($this->parent->getSwePhp()->swed->dpsi, $n + 1,
                    $J2 - $this->parent->getSwePhp()->swed->eop_tjd_beg);
                $deps = $this->bessel($this->parent->getSwePhp()->swed->deps, $n + 1,
                    $J2 - $this->parent->getSwePhp()->swed->eop_tjd_beg);
                $nutlo[0] += $dpsi / 3600.0 * SweConst::DEGTORAD;
                $nutlo[1] += $deps / 3600.0 * SweConst::DEGTORAD;
                if (SweInternalParams::DEMO)
                    printf("tjd=%f, dpsi=%f, deps=%f\n", $J, $dpsi * 1000, $deps * 1000);
            } else {
                $nutlo[0] += swephlib_precess::DPSI_IAU1980_TJD0 / 3600.0 * SweConst::DEGTORAD;
                $nutlo[1] += swephlib_precess::DEPS_IAU1980_TJD0 / 3600.0 * SweConst::DEGTORAD;
            }
        } else if ($nut_model == SweModelNutation::MOD_NUT_IAU_1980 || $nut_model == SweModelNutation::MOD_NUT_IAU_CORR_1987) {
            $this->calc_nutation_iau1980($J, $nutlo);
        } else if ($nut_model == SweModelNutation::MOD_NUT_IAU_2000A || $nut_model == SweModelNutation::MOD_NUT_IAU_2000B) {
            $this->calc_nutation_iau2000ab($J, $nutlo);
            if (($iflag & SweConst::SEFLG_JPLHOR_APPROX) && $jplhora_model == SweModelJPLHorizonApprox::MOD_JPLHORA_2) {
                $nutlo[0] += -41.7750 / 3600.0 / 1000.0 * SweConst::DEGTORAD;
                $nutlo[1] += -6.8192 / 3600.0 / 1000.0 * SweConst::DEGTORAD;
            }
        } else if ($nut_model == SweModelNutation::MOD_NUT_WOOLARD) {
            $this->calc_nutation_woolard($J, $nutlo);
        }
        return SweConst::OK;
    }

    function quadratic_intp(float $ym, float $y0, float $yp, float $x): float
    {
        $c = $y0;
        $b = ($yp - $ym) / 2.0;
        $a = ($yp + $ym) / 2.0 - $c;
        return $a * $x * $x + $b * $x + $c;
    }

    function swi_nutation(float $tjd, int $iflag, array &$nutlo): int
    {
        $retc = SweConst::OK;
        $dnut = [];
        if (!$this->parent->getSwePhp()->swed->do_interpolate_nut) {
            $retc = $this->calc_nutation($tjd, $iflag, $nutlo);
            // from interpolation, with three data points in 1-day steps;
            // maximum error is about 3 mas
        } else {
            // precalculated data points available
            if ($tjd < $this->parent->getSwePhp()->swed->interpol->tjd_nut2 &&
                $tjd > $this->parent->getSwePhp()->swed->interpol->tjd_nut0) {
                $dx = ($tjd - $this->parent->getSwePhp()->swed->interpol->tjd_nut0) - 1.0;
                $nutlo[0] = $this->quadratic_intp($this->parent->getSwePhp()->swed->interpol->nut_dpsi0,
                    $this->parent->getSwePhp()->swed->interpol->nut_dpsi1,
                    $this->parent->getSwePhp()->swed->interpol->nut_dpsi2,
                    $dx);
                $nutlo[1] = $this->quadratic_intp($this->parent->getSwePhp()->swed->interpol->nut_deps0,
                    $this->parent->getSwePhp()->swed->interpol->nut_deps1,
                    $this->parent->getSwePhp()->swed->interpol->nut_deps2,
                    $dx);
            } else {
                $this->parent->getSwePhp()->swed->interpol->tjd_nut0 = $tjd - 1.0; // one day earlier
                $this->parent->getSwePhp()->swed->interpol->tjd_nut2 = $tjd + 1.0; // one day later
                $retc = $this->calc_nutation($this->parent->getSwePhp()->swed->interpol->tjd_nut0,
                    $iflag, $dnut);
                if ($retc == SweConst::ERR) return SweConst::ERR;
                $this->parent->getSwePhp()->swed->interpol->nut_dpsi0 = $dnut[0];
                $this->parent->getSwePhp()->swed->interpol->nut_deps0 = $dnut[1];
                $retc = $this->calc_nutation($this->parent->getSwePhp()->swed->interpol->tjd_nut2,
                    $iflag, $dnut);
                if ($retc == SweConst::ERR) return SweConst::ERR;
                $this->parent->getSwePhp()->swed->interpol->nut_dpsi2 = $dnut[0];
                $this->parent->getSwePhp()->swed->interpol->nut_deps2 = $dnut[1];
                $retc = $this->calc_nutation($tjd, $iflag, $nutlo);
                if ($retc == SweConst::ERR) return SweConst::ERR;
                $this->parent->getSwePhp()->swed->interpol->nut_dpsi1 = $nutlo[0];
                $this->parent->getSwePhp()->swed->interpol->nut_deps1 = $nutlo[1];
            }
        }
        return $retc;
    }

    const float OFFSET_JPLHORIZONS = -52.3;
    const float DCOR_RA_JPL_TJD0 = 2437846.5;
    const int NDCOR_RA_JPL = 51;
    private array $dcor_ra_jpl = [
        -51.257, -51.103, -51.065, -51.503, -51.224, -50.796, -51.161, -51.181,
        -50.932, -51.064, -51.182, -51.386, -51.416, -51.428, -51.586, -51.766, -52.038, -52.370,
        -52.553, -52.397, -52.340, -52.676, -52.348, -51.964, -52.444, -52.364, -51.988, -52.212,
        -52.370, -52.523, -52.541, -52.496, -52.590, -52.629, -52.788, -53.014, -53.053, -52.902,
        -52.850, -53.087, -52.635, -52.185, -52.588, -52.292, -51.796, -51.961, -52.055, -52.134,
        -52.165, -52.141, -52.255,
    ];

    function swi_approx_jplhor(array &$x, float $tjd, int $iflag, bool $backward): void
    {
        $t = ($tjd - self::DCOR_RA_JPL_TJD0) / 365.25;
        $dofs = self::OFFSET_JPLHORIZONS;
        $jplhora_model = $this->parent->getSwePhp()->swed->astro_models[SweModel::MODEL_JPLHORA_MODE->value];
        if ($jplhora_model == 0) $jplhora_model = SweModelJPLHorizonApprox::default();
        if (!($iflag & SweConst::SEFLG_JPLHOR_APPROX))
            return;
        if ($jplhora_model == SweModelJPLHorizonApprox::MOD_JPLHORA_2)
            return;
        if ($t < 0) {
            $t = 0;
            $dofs = $this->dcor_ra_jpl[0];
        } else if ($t >= self::NDCOR_RA_JPL - 1) {
            $t = self::NDCOR_RA_JPL;
            $dofs = $this->dcor_ra_jpl[self::NDCOR_RA_JPL - 1];
        } else {
            $t0 = (int)$t;
            $t1 = $t0 + 1;
            $dofs = $this->dcor_ra_jpl[$t0];
            $dofs = ($t - $t0) * ($this->dcor_ra_jpl[$t0] - $this->dcor_ra_jpl[$t1]) + $this->dcor_ra_jpl[$t0];
        }
        $dofs /= (1000.0 * 3600.0);
        SwephCotransUtils::swi_cartpol($x, $x);
        if ($backward)
            $x[0] -= $dofs * SweConst::DEGTORAD;
        else
            $x[0] += $dofs * SweConst::DEGTORAD;
        SwephCotransUtils::swi_polcart($x, $x);
    }

    // GCRS to J2000
    function swi_bias(array &$x, float $tjd, int $iflag, bool $backward): void
    {
        $xx = [];
        $rb = ArrayUtils::createArray2D(3, 3);
        $bias_model = $this->parent->getSwePhp()->swed->astro_models[SweModel::MODEL_BIAS->value];
        $jplhora_model = $this->parent->getSwePhp()->swed->astro_models[SweModel::MODEL_JPLHORA_MODE->value];
        if ($bias_model == 0) $bias_model = SweModelBias::default();
        if ($jplhora_model == 0) $jplhora_model = SweModelJPLHorizonApprox::default();
        if ($bias_model == SweModelBias::MOD_BIAS_NONE)
            return;
        if ($iflag & SweConst::SEFLG_JPLHOR_APPROX) {
            if ($jplhora_model == SweModelJPLHorizonApprox::MOD_JPLHORA_2)
                return;
            if ($jplhora_model == SweModelJPLHorizonApprox::MOD_JPLHORA_3 && $tjd < swephlib_precess::DPSI_DEPS_IAU1980_TJD0_HORIZONS)
                return;
        }
        if ($bias_model == SweModelBias::MOD_BIAS_IAU2006) {
            $rb[0][0] = +0.99999999999999412;
            $rb[1][0] = -0.00000007078368961;
            $rb[2][0] = +0.00000008056213978;
            $rb[0][1] = +0.00000007078368695;
            $rb[1][1] = +0.99999999999999700;
            $rb[2][1] = +0.00000003306428553;
            $rb[0][2] = -0.00000008056214212;
            $rb[1][2] = -0.00000003306427981;
            $rb[2][2] = +0.99999999999999634;
        } else {
            $rb[0][0] = +0.9999999999999942;
            $rb[1][0] = -0.0000000707827974;
            $rb[2][0] = +0.0000000805621715;
            $rb[0][1] = +0.0000000707827948;
            $rb[1][1] = +0.9999999999999969;
            $rb[2][1] = +0.0000000330604145;
            $rb[0][2] = -0.0000000805621738;
            $rb[1][2] = -0.0000000330604088;
            $rb[2][2] = +0.9999999999999962;
        }
        if ($backward) {
            $this->swi_approx_jplhor($x, $tjd, $iflag, true);
            for ($i = 0; $i <= 2; $i++) {
                $xx[$i] = $x[0] * $rb[$i][0] +
                    $x[1] * $rb[$i][1] +
                    $x[2] * $rb[$i][2];
                if ($iflag & SweConst::SEFLG_SPEED)
                    $xx[$i + 3] = $x[3] * $rb[$i][0] +
                        $x[4] * $rb[$i][1] +
                        $x[5] * $rb[$i][2];
            }
        } else {
            for ($i = 0; $i <= 2; $i++) {
                $xx[$i] = $x[0] * $rb[0][$i] +
                    $x[1] * $rb[1][$i] +
                    $x[2] * $rb[2][$i];
                if ($iflag & SweConst::SEFLG_SPEED)
                    $xx[$i + 3] = $x[3] * $rb[0][$i] +
                        $x[4] * $rb[1][$i] +
                        $x[5] * $rb[2][$i];
            }
            $this->swi_approx_jplhor($xx, $tjd, $iflag, false);
        }
        for ($i = 0; $i <= 2; $i++) $x[$i] = $xx[$i];
        if ($iflag & SweConst::SEFLG_SPEED)
            for ($i = 3; $i <= 5; $i++) $x[$i] = $xx[$i];
    }

    // GCRS to FK5
    function swi_icrs2fk5(array &$x, int $iflag, bool $backward): void
    {
        $xx = [];
        $rb = ArrayUtils::createArray2D(3, 3);
        $rb[0][0] = +0.9999999999999928;
        $rb[0][1] = +0.0000001110223287;
        $rb[0][2] = +0.0000000441180557;
        $rb[1][0] = -0.0000001110223330;
        $rb[1][1] = +0.9999999999999891;
        $rb[1][2] = +0.0000000964779176;
        $rb[2][0] = -0.0000000441180450;
        $rb[2][1] = -0.0000000964779225;
        $rb[2][2] = +0.9999999999999943;
        if ($backward) {
            for ($i = 0; $i <= 2; $i++) {
                $xx[$i] = $x[0] * $rb[$i][0] +
                    $x[1] * $rb[$i][1] +
                    $x[2] * $rb[$i][2];
                if ($iflag & SweConst::SEFLG_SPEED)
                    $xx[$i + 3] = $x[3] * $rb[$i][0] +
                        $x[4] * $rb[$i][1] +
                        $x[5] * $rb[$i][2];
            }
        } else {
            for ($i = 0; $i <= 2; $i++) {
                $xx[$i] = $x[0] * $rb[0][$i] +
                    $x[1] * $rb[1][$i] +
                    $x[2] * $rb[2][$i];
                if ($iflag & SweConst::SEFLG_SPEED)
                    $xx[$i + 3] = $x[3] * $rb[0][$i] +
                        $x[4] * $rb[1][$i] +
                        $x[5] * $rb[2][$i];
            }
        }
        for ($i = 0; $i <= 5; $i++) $x[$i] = $xx[$i];
    }
}