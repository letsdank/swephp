<?php

use Enums\SweModel;
use Enums\SweModelDeltaT;
use Enums\SweTidalAccel;

class swephlib_deltat
{
    private SwephLib $parent;

    function __construct(SwephLib $parent)
    {
        $this->parent = $parent;
    }

    const int TABSTART = 1620;
    const int TABEND = 2028;
    const int TABSIZ = (self::TABEND - self::TABSTART + 1);
    const int TABSIZ_SPACE = (self::TABSIZ + 100);
    static array $dt = [
        /* 1620.0 - 1659.0 */
        124.00, 119.00, 115.00, 110.00, 106.00, 102.00, 98.00, 95.00, 91.00, 88.00,
        85.00, 82.00, 79.00, 77.00, 74.00, 72.00, 70.00, 67.00, 65.00, 63.00,
        62.00, 60.00, 58.00, 57.00, 55.00, 54.00, 53.00, 51.00, 50.00, 49.00,
        48.00, 47.00, 46.00, 45.00, 44.00, 43.00, 42.00, 41.00, 40.00, 38.00,
        /* 1660.0 - 1699.0 */
        37.00, 36.00, 35.00, 34.00, 33.00, 32.00, 31.00, 30.00, 28.00, 27.00,
        26.00, 25.00, 24.00, 23.00, 22.00, 21.00, 20.00, 19.00, 18.00, 17.00,
        16.00, 15.00, 14.00, 14.00, 13.00, 12.00, 12.00, 11.00, 11.00, 10.00,
        10.00, 10.00, 9.00, 9.00, 9.00, 9.00, 9.00, 9.00, 9.00, 9.00,
        /* 1700.0 - 1739.0 */
        9.00, 9.00, 9.00, 9.00, 9.00, 9.00, 9.00, 9.00, 10.00, 10.00,
        10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 10.00, 11.00, 11.00, 11.00,
        11.00, 11.00, 11.00, 11.00, 11.00, 11.00, 11.00, 11.00, 11.00, 11.00,
        11.00, 11.00, 11.00, 11.00, 12.00, 12.00, 12.00, 12.00, 12.00, 12.00,
        /* 1740.0 - 1779.0 */
        12.00, 12.00, 12.00, 12.00, 13.00, 13.00, 13.00, 13.00, 13.00, 13.00,
        13.00, 14.00, 14.00, 14.00, 14.00, 14.00, 14.00, 14.00, 15.00, 15.00,
        15.00, 15.00, 15.00, 15.00, 15.00, 16.00, 16.00, 16.00, 16.00, 16.00,
        16.00, 16.00, 16.00, 16.00, 16.00, 17.00, 17.00, 17.00, 17.00, 17.00,
        /* 1780.0 - 1799.0 */
        17.00, 17.00, 17.00, 17.00, 17.00, 17.00, 17.00, 17.00, 17.00, 17.00,
        17.00, 17.00, 16.00, 16.00, 16.00, 16.00, 15.00, 15.00, 14.00, 14.00,
        /* 1800.0 - 1819.0 */
        13.70, 13.40, 13.10, 12.90, 12.70, 12.60, 12.50, 12.50, 12.50, 12.50,
        12.50, 12.50, 12.50, 12.50, 12.50, 12.50, 12.50, 12.40, 12.30, 12.20,
        /* 1820.0 - 1859.0 */
        12.00, 11.70, 11.40, 11.10, 10.60, 10.20, 9.60, 9.10, 8.60, 8.00,
        7.50, 7.00, 6.60, 6.30, 6.00, 5.80, 5.70, 5.60, 5.60, 5.60,
        5.70, 5.80, 5.90, 6.10, 6.20, 6.30, 6.50, 6.60, 6.80, 6.90,
        7.10, 7.20, 7.30, 7.40, 7.50, 7.60, 7.70, 7.70, 7.80, 7.80,
        /* 1860.0 - 1899.0 */
        7.88, 7.82, 7.54, 6.97, 6.40, 6.02, 5.41, 4.10, 2.92, 1.82,
        1.61, .10, -1.02, -1.28, -2.69, -3.24, -3.64, -4.54, -4.71, -5.11,
        -5.40, -5.42, -5.20, -5.46, -5.46, -5.79, -5.63, -5.64, -5.80, -5.66,
        -5.87, -6.01, -6.19, -6.64, -6.44, -6.47, -6.09, -5.76, -4.66, -3.74,
        /* 1900.0 - 1939.0 */
        -2.72, -1.54, -.02, 1.24, 2.64, 3.86, 5.37, 6.14, 7.75, 9.13,
        10.46, 11.53, 13.36, 14.65, 16.01, 17.20, 18.24, 19.06, 20.25, 20.95,
        21.16, 22.25, 22.41, 23.03, 23.49, 23.62, 23.86, 24.49, 24.34, 24.08,
        24.02, 24.00, 23.87, 23.95, 23.86, 23.93, 23.73, 23.92, 23.96, 24.02,
        /* 1940.0 - 1949.0 */
        24.33, 24.83, 25.30, 25.70, 26.24, 26.77, 27.28, 27.78, 28.25, 28.71,
        /* 1950.0 - 1959.0 */
        29.15, 29.57, 29.97, 30.36, 30.72, 31.07, 31.35, 31.68, 32.18, 32.68,
        /* 1960.0 - 1969.0 */
        33.15, 33.59, 34.00, 34.47, 35.03, 35.73, 36.54, 37.43, 38.29, 39.20,
        /* 1970.0 - 1979.0 */
        /* from 1974 on values (with 4-digit precision) were calculated from IERS data */
        40.18, 41.17, 42.23, 43.37, 44.4841, 45.4761, 46.4567, 47.5214, 48.5344, 49.5862,
        /* 1980.0 - 1989.0 */
        50.5387, 51.3808, 52.1668, 52.9565, 53.7882, 54.3427, 54.8713, 55.3222, 55.8197, 56.3000,
        /* 1990.0 - 1999.0 */
        56.8553, 57.5653, 58.3092, 59.1218, 59.9845, 60.7854, 61.6287, 62.2951, 62.9659, 63.4673,
        /* 2000.0 - 2009.0 */
        63.8285, 64.0908, 64.2998, 64.4734, 64.5736, 64.6876, 64.8452, 65.1464, 65.4574, 65.7768,
        /* 2010.0 - 2018.0 */
        66.0699, 66.3246, 66.6030, 66.9069, 67.2810, 67.6439, 68.1024, 68.5927, 68.9676, 69.2202,
        /* 2020.0 - 2023.0        */
        69.3612, 69.3593, 69.2945, 69.1833,
        /* Extrapolated values:
         * 2024 - 2028 */
        69.10, 69.00, 68.90, 68.80, 68.80,
    ];
    const int TAB2_SIZ = 27;
    const int TAB2_START = -1000;
    const int TAB2_END = 1600;
    const int TAB2_STEP = 100;
    const int LTERM_EQUATION_YSTART = 1820;
    const int LTERM_EQUATION_COEFF = 32;

    // Table for -1000 through 1600, from Morrison & Stephenson (2004).
    const array dt2 = [
        /*-1000  -900  -800  -700  -600  -500  -400  -300  -200  -100*/
        25400, 23700, 22000, 21000, 19040, 17190, 15530, 14080, 12790, 11640,
        /*    0   100   200   300   400   500   600   700   800   900*/
        10580, 9600, 8640, 7680, 6700, 5710, 4740, 3810, 2960, 2200,
        /* 1000  1100  1200  1300  1400  1500  1600,                 */
        1570, 1090, 740, 490, 320, 200, 120,
    ];

    /* Table for -500 through 1600, from Stephenson & Morrison (1995).
     *
     * The first value for -550 has been added from Borkowski
     * in order to make this table fit with the Borkowski formula
     * for times before -550.
     */
    const int TAB97_SIZ = 43;
    const int TAB97_START = -500;
    const int TAB97_END = 1600;
    const int TAB97_STEP = 50;
    const array dt97 = [
        /* -500  -450  -400  -350  -300  -250  -200  -150  -100   -50*/
        16800, 16000, 15300, 14600, 14000, 13400, 12800, 12200, 11600, 11100,
        /*    0    50   100   150   200   250   300   350   400   450*/
        10600, 10100, 9600, 9100, 8600, 8200, 7700, 7200, 6700, 6200,
        /*  500   550   600   650   700   750   800   850   900   950*/
        5700, 5200, 4700, 4300, 3800, 3400, 3000, 2600, 2200, 1900,
        /* 1000  1050  1100  1150  1200  1250  1300  1350  1400  1450*/
        1600, 1350, 1100, 900, 750, 600, 470, 380, 300, 230,
        /* 1500  1550  1600 */
        180, 140, 110,
    ];

    function calc_deltat(float $tjd, int $iflag, float &$deltat, ?string &$serr): int
    {
        $tid_acc = 0.0;
        $denumret = 0;
        $ans = 0;
        $deltat_model = $this->parent->getSwePhp()->swed->astro_models[SweModel::MODEL_DELTAT->value];
        if ($deltat_model == 0) $deltat_model = SweModelDeltaT::default();
        $epheflag = $iflag & SwephLib::SEFLG_EPHMASK;
        $otherflag = $iflag & ~SwephLib::SEFLG_EPHMASK;
        // with iflag = -1, we use default tid_acc
        if ($iflag == -1) {
            $retc = $this->swi_get_tid_acc($tjd, 0, 9999, $denumret, $tid_acc, $serr); // for default tid_acc
        } else { // otherwise we use tid_acc consistent with epheflag
            $denum = $this->parent->getSwePhp()->swed->jpldenum;
            if ($epheflag & SweConst::SEFLG_SWIEPH)
                $denum = $this->parent->getSwePhp()->swed->fidat[SweConst::SEI_FILE_MOON]->sweph_denum ?? 0;
            if ($this->parent->getSwePhp()->sweph->swi_init_swed_if_start() == 1 && !($epheflag & SweConst::SEFLG_MOSEPH)) {
                if (isset($serr))
                    $serr = "Please call swe_set_ephe_path() or swe_set_jplfile() before calling swe_deltat_ex()";
                $retc = $this->swi_set_tid_acc($tjd, $epheflag, $denum); // _set_ saves tid_acc in swed
            } else {
                $retc = $this->swi_set_tid_acc($tjd, $epheflag, $denum, $serr); // _set_ saved tid_acc in swed
            }
            $tid_acc = $this->parent->getSwePhp()->swed->tid_acc;
        }
        $iflag = $otherflag | $retc;
        $Y = 2000.0 + ($tjd - Sweph::J2000) / 365.25;
        $Ygreg = 2000.0 + ($tjd - Sweph::J2000) / 365.2425;
        // Model for epochs before 1955, currently default in Swiss Ephemeris:
        // Stephenson/Morrison/Hohenkerk 2016
        // (we switch over to Astronomical Almanac K8-K9 and IERS at 1 Jan. 1955.
        // To make the curve continuous we apply some linear term over
        // 1000 days before this date.)
        // Delta T according to Stephenson/Morrison/Hohenkerk 2016 is based on
        // ancient, medieval, and modern observations of eclipses and occultations.
        // Values of Deltat T before 1955 depend on this kind of observations.
        // For more recent data we want to use the data provided by IERS
        // (or Astronomical Almanac K8-K9).
        //
        if ($deltat_model == SweModelDeltaT::MOD_DELTAT_STEPHENSON_ETC_2016 && $tjd < 2435108.5) { // tjd < 2432521.453645833
            $deltat = $this->deltat_stephenson_etc_2016($tjd, $tid_acc);
            if ($tjd >= 2434108.5) {
                $deltat += (1.0 - (2435108.5 - $tjd) / 1000.0) * 0.6610218 / 86400.0;
            }
            return $iflag;
        }
        // Model used SE 1.77 - 2.05.01, for epochs before 1633:
        // Polynomials by Espenak & Meeus 2006,
        // derived from Stephenson & Morrison 2004.
        // deltat_model == SEMOD_DELTAT_ESPENAK_MEEUS_2006:
        // This method is used only for epochs before 1633. (For more recent
        // epochs, we use the data provided by Astronomical Almanac K8-K9.)
        //
        if ($deltat_model == SweModelDeltaT::MOD_DELTAT_ESPENAK_MEEUS_2006 && $tjd < 2317746.13090277789) {
            $deltat = $this->deltat_espenak_meeus_1620($tjd, $tid_acc);
            return $iflag;
        }
        // delta t model used in SE 1.72 - 1.76:
        // Stephenson & Morrison 2004;
        // before 1620
        if ($deltat_model == SweModelDeltaT::MOD_DELTAT_STEPHENSON_MORRISON_2004 && $Y < self::TABSTART) {
            $deltat = $this->deltat_espenak_meeus_1620($tjd, $tid_acc);
            return $iflag;
        }
        // delta t model used in SE 1.72 - 1.76:
        // Stephenson & Morrison 2004;
        // before 1620
        if ($deltat_model == SweModelDeltaT::MOD_DELTAT_STEPHENSON_MORRISON_2004 && $Y < self::TABSTART) {
            // before 1600:
            if ($Y < self::TAB2_END) {
                $deltat = $this->deltat_stephenson_morrison_2004_1600($tjd, $tid_acc);
                return $iflag;
            } else {
                // between 1600 and 1620:
                // linear interpolation between
                // end of table dt2 and start of table dt
                if ($Y >= self::TAB2_END) {
                    $B = self::TABSTART - self::TAB2_END;
                    $iy = (self::TAB2_END - self::TAB2_START) / self::TAB2_STEP;
                    $dd = ($Y - self::TAB2_END) / $B;
                    $ans = self::dt2[$iy] + $dd * (self::$dt[0] - self::dt2[$iy]);
                    $ans = $this->adjust_for_tidacc($ans, $Ygreg, $tid_acc, SweTidalAccel::SE_TIDAL_26, false);
                    $deltat = $ans / 86400.0;
                    return $iflag;
                }
            }
        }
        // delta t model used in SE 1.64 - 1.71:
        // Stephenson 1997;
        // before 1620
        if ($deltat_model == SweModelDeltaT::MOD_DELTAT_STEPHENSON_1997 && $Y < self::TABSTART) {
            // before 1600:
            if ($Y < self::TAB97_END) {
                $deltat = $this->deltat_stephenson_morrison_1997_1600($tjd, $tid_acc);
                return $iflag;
            } else {
                // between 1600 and 1620:
                // linear interpolation between
                // end of table dt2 and start of table dt
                $B = self::TABSTART - self::TAB97_END;
                $iy = (self::TAB97_END - self::TAB97_START) / self::TAB97_STEP;
                $dd = ($Y - self::TAB97_END) / $B;
                $ans = self::dt97[$iy] + $dd * (self::$dt[0] - self::dt97[$iy]);
                $ans = $this->adjust_for_tidacc($ans, $Ygreg, $tid_acc, SweTidalAccel::SE_TIDAL_26, false);
                $deltat = $ans / 86400.0;
                return $iflag;
            }
        }
        // delta t model used before SE 1.64:
        // Stephenson/Morrison 1984 with Borkowski 1988;
        // before 1620
        if ($deltat_model == SweModelDeltaT::MOD_DELTAT_STEPHENSON_MORRISON_1984 && $Y < self::TABSTART) {
            if ($Y >= 948.0) {
                // Stephenson and Morrison, stated domain is 948 to 1600:
                // 25.5(centuries from 1800)^2 - 1.9159(centuries from 1955)^2
                $B = 0.01 * ($Y - 2000.0);
                $ans = (23.58 * $B + 100.3) * $B + 101.6;
            } else {
                // Borkowski, before 948 and between 1600 and 1620
                $B = 0.01 * ($Y - 2000.0) + 3.75;
                $ans = 35.0 * $B * $B + 40.;
            }
            $deltat = $ans / 86400.0;
            return $iflag;
        }
        // 1620 - today + a few years (tabend):
        // Tabulated values of deltaT from Astronomical Almanac
        // (AA 1997etc., pp. K8-K9) and from IERS
        // (http://maia.usno.navy.mil/ser7/deltat.data).
        //
        if ($Y >= self::TABSTART) {
            $deltat = $this->deltat_aa($tjd, $tid_acc);
            return $iflag;
        }

        if (SweInternalParams::TRACE) {
            // TODO: Do tracing as in C++ provided code
        }

        $deltat = $ans / 86400.0;
        return $iflag;
    }

    /* The tabulated values of deltaT, in hundredths of a second,
     * were taken from The Astronomical Almanac 1997etc., pp. K8-K9.
     * Some more recent values are taken from IERS
     * http://maia.usno.navy.mil/ser7/deltat.data .
     * Bessel's interpolation formula is implemented to obtain fourth
     * order interpolated values at intermediate times.
     * The values are adjusted depending on the ephemeris used
     * and its inherent value of secular tidal acceleration ndot.
     * Note by Dieter Jan. 2017:
     * Bessel interpolation assumes equidistant sampling points. However the
     * sampling points are not equidistant, because they are for first January of
     * every year and years can have either 365 or 366 days. The interpolation uses
     * a step width of 365.25 days. As a consequence, in three out of four years
     * the interpolation does not reproduce the exact values of the sampling points
     * on the days they refer to.  */
    private function deltat_aa(float $tjd, float $tid_acc): float
    {
        $ans = 0.0;
        $ans2 = 0.0;
        // read additional values from swedelta.txt
        $tabsiz = $this->init_dt();
        $tabend = self::TABSTART + $tabsiz - 1;
        $deltat_model = $this->parent->getSwePhp()->swed->astro_models[SweModel::MODEL_DELTAT->value];
        if ($deltat_model == 0) $deltat_model = SweModelDeltaT::default();
        $Y = 2000.0 + ($tjd - 2451544.5) / 365.25;
        if ($Y <= $tabend) {
            // Index into the table.
            //
            $p = floor($Y);
            $iy = (int)($p - self::TABSTART);
            // Zeroth order estimate is value at start of year
            $ans = self::$dt[$iy];
            $k = $iy + 1;
            if ($k >= $tabsiz)
                goto done; // No data, can't go on.
            // The fraction of tabulation interval
            $p = $Y - $p;
            // First order interpolated value
            $ans += $p * (self::$dt[$k] - self::$dt[$iy]);
            if (($iy - 1 < 0) || ($iy + 2 >= $tabsiz))
                goto done; // can't do second differences
            // Make table of first differences
            $k = $iy - 2;
            for ($i = 0; $i < 5; $i++) {
                if (($k < 0) || ($k + 1 >= $tabsiz))
                    $d[$i] = 0;
                else
                    $d[$i] = self::$dt[$k + 1] - self::$dt[$k];
                $k += 1;
            }
            // Compute second differences
            for ($i = 0; $i < 4; $i++)
                $d[$i] = $d[$i + 1] - $d[$i];
            $B = 0.25 * $p * ($p - 1.0);
            $ans += $B * ($d[1] + $d[2]);
            if (SweInternalParams::DEMO)
                printf("B %.4lf, ans %.4lf\n", $B, $ans);
            if ($iy + 2 >= $tabsiz)
                goto done;
            // Compute third differences
            for ($i = 0; $i < 3; $i++)
                $d[$i] = $d[$i + 1] - $d[$i];
            $B = 2.0 * $B / 3.0;
            $ans += ($p - 0.5) * $B * $d[1];
            if (SweInternalParams::DEMO)
                printf("B %.4lf, ans %.4lf\n", $B * ($p - 0.5), $ans);
            if (($iy - 2 < 0) || ($iy + 3 > $tabsiz))
                goto done;
            // Compute fourth differences
            for ($i = 0; $i < 2; $i++)
                $d[$i] = $d[$i + 1] - $d[$i];
            $B = 0.125 * $B * ($p + 1.0) * ($p - 2.0);
            $ans += $B * ($d[0] + $d[1]);
            if (SweInternalParams::DEMO)
                printf("B %.4lf, ans %.4lf\n", $B, $ans);
            done:
            $ans = $this->adjust_for_tidacc($ans, $Y, $tid_acc, SweTidalAccel::SE_TIDAL_26, false);
            return $ans / 86400.0;
        }
        // today - future:
        // 3rd degree polynomial based on data given by
        // Stephenson/Morrison/Hohenkerk 2016 here:
        // http://astro.ukho.gov.uk/nao/lvm/
        //
        if ($deltat_model == SweModelDeltaT::MOD_DELTAT_STEPHENSON_ETC_2016) {
            $B = ($Y - 2000);
            if ($Y < 2500) {
                $ans = $B * $B * $B * 121.0 / 30000000.0 + $B * $B / 1250.0 + $B * 521.0 / 3000.0 + 64.0;
                // for slow transition from tabulated data
                $B2 = ($tabend - 2000);
                $ans2 = $B2 * $B2 * $B2 * 121.0 / 30000000.0 + $B2 * $B2 / 1250.0 + $B2 * 521.0 / 3000.0 + 64.0;
            } else { // we use a parable after 2500
                $B = 0.01 * ($Y - 2000);
                $ans = $B * $B * 32.5 + 42.5;
            }
        } else {
            // Formula Stephenson (1997; p. 507),
            // with modification to avoid jump at end of AA table,
            // similar to what Meeus 1998 had suggested.
            // Slow transition within 100 years.
            //
            $B = 0.01 * ($Y - 1820);
            $ans = -20 + 31 * $B * $B;
            // for slow transition from tabulated data
            $B2 = 0.01 * ($tabend - 1820);
            $ans2 = -20 + 31 * $B2 * $B2;
        }
        // slow transition from tabulated values to Stephenson formula:
        if ($Y <= $tabend + 100) {
            $ans3 = self::$dt[$tabsiz - 1];
            $dd = ($ans2 - $ans3);
            $ans += $dd * ($Y - ($tabend + 100)) * 0.01;
        }
        return $ans / 86400.0;
    }

    private function deltat_longterm_morrison_stephenson(float $tjd): float
    {
        $Ygreg = 2000.0 + ($tjd - Sweph::J2000) / 365.2425;
        $u = ($Ygreg - 1820) / 100.0;
        return -20 + 32 * $u * $u;
    }

    private function deltat_stephenson_morrison_1997_1600(float $tjd, float $tid_acc): float
    {
        $ans = 0;
        $Y = 2000.0 + ($tjd - Sweph::J2000) / 365.25;
        // before -500:
        // formula by Stephenson (1997; p. 508) but adjusted to fit the starting
        // point of table dt97 (Stephenson 1997).
        if ($Y < self::TAB97_START) {
            $B = ($Y - 1735) * 0.01;
            $ans = -20 + 35 * $B * $B;
            $ans = $this->adjust_for_tidacc($ans, $Y, $tid_acc, SweTidalAccel::SE_TIDAL_26, false);
            // transition from formula to table over 100 years
            if ($Y >= self::TAB97_START - 100) {
                // starting value of table dt97:
                $ans2 = $this->adjust_for_tidacc(self::dt97[0], self::TAB97_START, $tid_acc, SweTidalAccel::SE_TIDAL_26, false);
                // value of formula at epoch TAB97_START
                $B = (self::TAB97_START - 1735) * 0.01;
                $ans3 = -20 + 35 * $B * $B;
                $ans3 = $this->adjust_for_tidacc($ans3, $Y, $tid_acc, SweTidalAccel::SE_TIDAL_26, false);
                $dd = $ans3 - $ans2;
                $B = ($Y - (self::TAB97_START - 100)) * 0.01;
                // fit to starting point of table dt97.
                $ans = $ans - $dd * $B;
            }
        }
        // between -500 and 1600:
        // linear interpolation between values of table dt97 (Stephenson 1997)
        if ($Y >= self::TAB97_START && $Y < self::TAB2_END) {
            $p = floor($Y);
            $iy = (int)(($p - self::TAB97_START) / 50.0);
            $dd = ($Y - (self::TAB97_START + 50 * $iy)) / 50.0;
            $ans = self::dt97[$iy] + (self::dt97[$iy + 1] - self::dt97[$iy]) * $dd;
            // correction for tidal acceleration used by our ephemeris
            $ans = $this->adjust_for_tidacc($ans, $Y, $tid_acc, SweTidalAccel::SE_TIDAL_26, false);
        }
        $ans /= 86400.0;
        return $ans;
    }

    // Stephenson & Morrison (2004)
    private function deltat_stephenson_morrison_2004_1600(float $tjd, float $tid_acc): float
    {
        $ans = 0;
        $Y = 2000.0 + ($tjd - Sweph::J2000) / 365.2425;
        // double Y = 2000.0 + (tjd - J2000) / 365.25
        // before -1000:
        // formula by Stephenson & Morrison (2004; p. 335) but adjusted to fit the
        // starting point of table dt2.
        if ($Y < self::TAB2_START) { // before -1000
            $ans = $this->deltat_longterm_morrison_stephenson($tjd);
            $ans = $this->adjust_for_tidacc($ans, $Y, $tid_acc, SweTidalAccel::SE_TIDAL_26, false);
            // transition from formula to table over 100 years
            if ($Y >= self::TAB2_START - 100) {
                // starting value of table dt2:
                $ans2 = $this->adjust_for_tidacc(self::dt2[0], self::TAB2_START, $tid_acc, SweTidalAccel::SE_TIDAL_26, false);
                // value of formula at epoch TAB2_START
                // B = (TAB2_START - LTERM_EQUATION_YSTART) * 0.01;
                // ans3 = -20 + LTERM_EQUATION_COEFF * B * B;
                $tjd0 = (self::TAB2_START - 2000) * 365.2425 + Sweph::J2000;
                $ans3 = $this->deltat_longterm_morrison_stephenson($tjd0);
                $ans3 = $this->adjust_for_tidacc($ans3, $Y, $tid_acc, SweTidalAccel::SE_TIDAL_26, false);
                $dd = $ans3 - $ans2;
                $B = ($Y - (self::TAB2_START - 100)) * 0.01;
                // fit to starting point of table dt2.
                $ans = $ans - $dd * $B;
            }
        }
        // between -1000 and 1600:
        // linear interpolation between values of table dt2 (Stephenson & Morrison 2004)
        if ($Y >= self::TAB2_START && $Y < self::TAB2_END) {
            $Yjul = 2000 + ($tjd - 2451557.5) / 365.25;
            $p = floor($Yjul);
            $iy = (int)(($p - self::TAB2_START) / self::TAB2_STEP);
            $dd = ($Yjul - (self::TAB2_START + self::TAB2_STEP * $iy)) / self::TAB2_STEP;
            $ans = self::dt2[$iy] + (self::dt2[$iy + 1] - self::dt2[$iy]) * $dd;
            // correction for tidal acceleration used by our ephemeris
            $ans = $this->adjust_for_tidacc($ans, $Y, $tid_acc, SweTidalAccel::SE_TIDAL_26, false);
        }
        $ans /= 86400.0;
        return $ans;
    }

    /*
     * These coefficients represent the spline approximation discussed in the
     * paper "Measurement of the Earth's Rotation: 720 BC to AD 2015",
     * Stephenson, F.R., Morrison, L.V., and Hohenkerk, C.Y., published by
     * Royal Society Proceedings A and available from their website at
     * http://rspa.royalsocietypublishing.org/lookup/doi/10.1098/rspa.2016.0404.
     * Year numbers have been replaced by Julian day numbers by D. Koch.
     */
    private const int NDTCF16 = 54;
    private const array dtcf16 = [
        [1458085.5, 1867156.5, 20550.593, -21268.478, 11863.418, -4541.129],    /*00*/ /* ybeg=-720, yend= 400 */
        [1867156.5, 2086302.5, 6604.404, -5981.266, -505.093, 1349.609],        /*01*/ /* ybeg= 400, yend=1000 */
        [2086302.5, 2268923.5, 1467.654, -2452.187, 2460.927, -1183.759],       /*02*/ /* ybeg=1000, yend=1500 */
        [2268923.5, 2305447.5, 292.635, -216.322, -43.614, 56.681],             /*03*/ /* ybeg=1500, yend=1600 */
        [2305447.5, 2323710.5, 89.380, -66.754, 31.607, -10.497],               /*04*/ /* ybeg=1600, yend=1650 */
        [2323710.5, 2349276.5, 43.736, -49.043, 0.227, 15.811],                 /*05*/ /* ybeg=1650, yend=1720 */
        [2349276.5, 2378496.5, 10.730, -1.321, 62.250, -52.946],                /*06*/ /* ybeg=1720, yend=1800 */
        [2378496.5, 2382148.5, 18.714, -4.457, -1.509, 2.507],                  /*07*/ /* ybeg=1800, yend=1810 */
        [2382148.5, 2385800.5, 15.255, 0.046, 6.012, -4.634],                   /*08*/ /* ybeg=1810, yend=1820 */
        [2385800.5, 2389453.5, 16.679, -1.831, -7.889, 3.799],                  /*09*/ /* ybeg=1820, yend=1830 */
        [2389453.5, 2393105.5, 10.758, -6.211, 3.509, -0.388],                  /*10*/ /* ybeg=1830, yend=1840 */
        [2393105.5, 2396758.5, 7.668, -0.357, 2.345, -0.338],                   /*11*/ /* ybeg=1840, yend=1850 */
        [2396758.5, 2398584.5, 9.317, 1.659, 0.332, -0.932],                    /*12*/ /* ybeg=1850, yend=1855 */
        [2398584.5, 2400410.5, 10.376, -0.472, -2.463, 1.596],                  /*13*/ /* ybeg=1855, yend=1860 */
        [2400410.5, 2402237.5, 9.038, -0.610, 2.325, -2.497],                   /*14*/ /* ybeg=1860, yend=1865 */
        [2402237.5, 2404063.5, 8.256, -3.450, -5.166, 2.729],                   /*15*/ /* ybeg=1865, yend=1870 */
        [2404063.5, 2405889.5, 2.369, -5.596, 3.020, -0.919],                   /*16*/ /* ybeg=1870, yend=1875 */
        [2405889.5, 2407715.5, -1.126, -2.312, 0.264, -0.037],                  /*17*/ /* ybeg=1875, yend=1880 */
        [2407715.5, 2409542.5, -3.211, -1.894, 0.154, 0.562],                   /*18*/ /* ybeg=1880, yend=1885 */
        [2409542.5, 2411368.5, -4.388, 0.101, 1.841, -1.438],                   /*19*/ /* ybeg=1885, yend=1890 */
        [2411368.5, 2413194.5, -3.884, -0.531, -2.473, 1.870],                  /*20*/ /* ybeg=1890, yend=1895 */
        [2413194.5, 2415020.5, -5.017, 0.134, 3.138, -0.232],                   /*21*/ /* ybeg=1895, yend=1900 */
        [2415020.5, 2416846.5, -1.977, 5.715, 2.443, -1.257],                   /*22*/ /* ybeg=1900, yend=1905 */
        [2416846.5, 2418672.5, 4.923, 6.828, -1.329, 0.720],                    /*23*/ /* ybeg=1905, yend=1910 */
        [2418672.5, 2420498.5, 11.142, 6.330, 0.831, -0.825],                   /*24*/ /* ybeg=1910, yend=1915 */
        [2420498.5, 2422324.5, 17.479, 5.518, -1.643, 0.262],                   /*25*/ /* ybeg=1915, yend=1920 */
        [2422324.5, 2424151.5, 21.617, 3.020, -0.856, 0.008],                   /*26*/ /* ybeg=1920, yend=1925 */
        [2424151.5, 2425977.5, 23.789, 1.333, -0.831, 0.127],                   /*27*/ /* ybeg=1925, yend=1930 */
        [2425977.5, 2427803.5, 24.418, 0.052, -0.449, 0.142],                   /*28*/ /* ybeg=1930, yend=1935 */
        [2427803.5, 2429629.5, 24.164, -0.419, -0.022, 0.702],                  /*29*/ /* ybeg=1935, yend=1940 */
        [2429629.5, 2431456.5, 24.426, 1.645, 2.086, -1.106],                   /*30*/ /* ybeg=1940, yend=1945 */
        [2431456.5, 2433282.5, 27.050, 2.499, -1.232, 0.614],                   /*31*/ /* ybeg=1945, yend=1950 */
        [2433282.5, 2434378.5, 28.932, 1.127, 0.220, -0.277],                   /*32*/ /* ybeg=1950, yend=1953 */
        [2434378.5, 2435473.5, 30.002, 0.737, -0.610, 0.631],                   /*33*/ /* ybeg=1953, yend=1956 */
        [2435473.5, 2436569.5, 30.760, 1.409, 1.282, -0.799],                   /*34*/ /* ybeg=1956, yend=1959 */
        [2436569.5, 2437665.5, 32.652, 1.577, -1.115, 0.507],                   /*35*/ /* ybeg=1959, yend=1962 */
        [2437665.5, 2438761.5, 33.621, 0.868, 0.406, 0.199],                    /*36*/ /* ybeg=1962, yend=1965 */
        [2438761.5, 2439856.5, 35.093, 2.275, 1.002, -0.414],                   /*37*/ /* ybeg=1965, yend=1968 */
        [2439856.5, 2440952.5, 37.956, 3.035, -0.242, 0.202],                   /*38*/ /* ybeg=1968, yend=1971 */
        [2440952.5, 2442048.5, 40.951, 3.157, 0.364, -0.229],                   /*39*/ /* ybeg=1971, yend=1974 */
        [2442048.5, 2443144.5, 44.244, 3.198, -0.323, 0.172],                   /*40*/ /* ybeg=1974, yend=1977 */
        [2443144.5, 2444239.5, 47.291, 3.069, 0.193, -0.192],                   /*41*/ /* ybeg=1977, yend=1980 */
        [2444239.5, 2445335.5, 50.361, 2.878, -0.384, 0.081],                   /*42*/ /* ybeg=1980, yend=1983 */
        [2445335.5, 2446431.5, 52.936, 2.354, -0.140, -0.166],                  /*43*/ /* ybeg=1983, yend=1986 */
        [2446431.5, 2447527.5, 54.984, 1.577, -0.637, 0.448],                   /*44*/ /* ybeg=1986, yend=1989 */
        [2447527.5, 2448622.5, 56.373, 1.649, 0.709, -0.277],                   /*45*/ /* ybeg=1989, yend=1992 */
        [2448622.5, 2449718.5, 58.453, 2.235, -0.122, 0.111],                   /*46*/ /* ybeg=1992, yend=1995 */
        [2449718.5, 2450814.5, 60.677, 2.324, 0.212, -0.315],                   /*47*/ /* ybeg=1995, yend=1998 */
        [2450814.5, 2451910.5, 62.899, 1.804, -0.732, 0.112],                   /*48*/ /* ybeg=1998, yend=2001 */
        [2451910.5, 2453005.5, 64.082, 0.675, -0.396, 0.193],                   /*49*/ /* ybeg=2001, yend=2004 */
        [2453005.5, 2454101.5, 64.555, 0.463, 0.184, -0.008],                   /*50*/ /* ybeg=2004, yend=2007 */
        [2454101.5, 2455197.5, 65.194, 0.809, 0.161, -0.101],                   /*51*/ /* ybeg=2007, yend=2010 */
        [2455197.5, 2456293.5, 66.063, 0.828, -0.142, 0.168],                   /*52*/ /* ybeg=2010, yend=2013 */
        [2456293.5, 2457388.5, 66.917, 1.046, 0.360, -0.282],                   /*53*/ /* ybeg=2013, yend=2016 */
    ];

    private function deltat_stephenson_etc_2016(float $tjd, float $tid_acc): float
    {
        $irec = -1;
        $Ygreg = 2000.0 + ($tjd - Sweph::J2000) / 365.2425;
        // after the year -720 get value from spline curve
        for ($i = 0; $i < self::NDTCF16; $i++) {
            if ($tjd < self::dtcf16[$i][0]) break;
            if ($tjd < self::dtcf16[$i][1]) {
                $irec = $i;
                break;
            }
        }
        if ($irec >= 0) {
            $t = ($tjd - self::dtcf16[$irec][0]) / (self::dtcf16[$irec][1] - self::dtcf16[$irec][0]);
            $dt = self::dtcf16[$irec][2] + self::dtcf16[$irec][3] * $t + self::dtcf16[$irec][4] * $t * $t + self::dtcf16[$irec][5] * $t * $t * $t;
        } else if ($Ygreg < -720) { // for earlier epochs, use long term parabola
            $t = ($Ygreg - 1825) / 100.0;
            $dt = -320 + 32.5 * $t * $t;
            $dt -= 179.7337208; // to make curve continuous on 1 Jan -720 (D. Koch)
        } else { // future
            $t = ($Ygreg - 1825) / 100.0;
            $dt = -320 + 32.5 * $t * $t;
            $dt += 269.4790417; // to make curve continuous on 1 Jan 2016 (D. Koch)
        }
        // The parameter adjust_after_1955 must be TRUE here, because the
        // Stephenson 2016 curve is based on occultation data alone,
        // not on IERS data.
        // Note, however, the current location deltat_stephenson_etc_2016()
        // is called only for dates before 1 Jan 1955.
        $dt = $this->adjust_for_tidacc($dt, $Ygreg, $tid_acc, SweTidalAccel::SE_TIDAL_STEPHENSON_2016, true);
        $dt /= 86400.0;
        return $dt;
    }

    private function deltat_espenak_meeus_1620(float $tjd, float $tid_acc): float
    {
        $ans = 0;
        // Y = 2000.0 + (tjd - J2000) / 365.25
        $Ygreg = 2000.0 + ($tjd - Sweph::J2000) / 365.2425;
        if ($Ygreg < -500) {
            $ans = $this->deltat_longterm_morrison_stephenson($tjd);
        } else if ($Ygreg < 500) {
            $u = $Ygreg / 100.0;
            $ans = (((((0.0090316521 * $u + 0.022174192) * $u - 0.1798452) * $u - 5.952053) * $u + 33.78311) * $u - 1014.41) * $u + 10583.6;
        } else if ($Ygreg < 1600) {
            $u = ($Ygreg - 1000) / 100.0;
            $ans = (((((0.0083572073 * $u - 0.005050998) * $u - 0.8503463) * $u + 0.319781) * $u + 71.23472) * $u - 556.01) * $u + 1574.2;
        } else if ($Ygreg < 1700) {
            $u = $Ygreg - 1600;
            $ans = 120 - 0.9808 * $u - 0.01532 * $u * $u * $u * $u * $u / 7129.0;
        } else if ($Ygreg < 1800) {
            $u = $Ygreg - 1700;
            $ans = (((-$u / 1174000.0 + 0.00013336) * $u - 0.0059285) * $u + 0.1603) * $u + 8.83;
        } else if ($Ygreg < 1860) {
            $u = $Ygreg - 1800;
            $ans = ((((((0.000000000875 * $u - 0.0000001699) * $u + 0.0000121272) * $u + 0.00037436) * $u + 0.0041116) * $u + 0.0068612) * $u - 0.332447) * $u + 13.72;
        } else if ($Ygreg < 1900) {
            $u = $Ygreg - 1860;
            $ans = (((($u / 233174.0 - 0.0004473624) * $u + 0.01680668) * $u - 0.251754) * $u + 0.5737) * $u + 7.62;
        } else if ($Ygreg < 1920) {
            $u = $Ygreg = 1900;
            $ans = (((-0.000197 * $u + 0.0061966) * $u - 0.0598939) * $u + 1.494119) * $u - 2.79;
        } else if ($Ygreg < 1941) {
            $u = $Ygreg - 1920;
            $ans = 21.20 + 0.84493 * $u - 0.076100 * $u * $u + 0.0020936 * $u * $u * $u;
        } else if ($Ygreg < 1961) {
            $u = $Ygreg - 1950;
            $ans = 29.07 + 0.407 * $u - $u * $u / 233.0 + $u * $u * $u / 2547.0;
        } else if ($Ygreg < 1986) {
            $u = $Ygreg - 1975;
            $ans = 45.45 + 1.067 * $u - $u * $u / 260.0 - $u * $u * $u / 718.0;
        } else if ($Ygreg < 2005) {
            $u = $Ygreg - 2000;
            $ans = ((((0.00002373599 * $u + 0.000651814) * $u + 0.0017275) * $u - 0.060374) * $u + 0.3345) * $u + 63.86;
        }
        $ans = $this->adjust_for_tidacc($ans, $Ygreg, $tid_acc, SweTidalAccel::SE_TIDAL_26, false);
        $ans /= 86400.0;
        return $ans;
    }

    // Read delta t values from external file.
    // record structure: year(whitespace)delta_t in 0.01 sec.
    //
    private function init_dt(): int
    {
        if (!$this->parent->getSwePhp()->swed->init_dt_done) {
            $this->parent->getSwePhp()->swed->init_dt_done = true;
            // no error message if file is missing
            if (($fp = $this->parent->getSwePhp()->sweph->swi_fopen(-1, "swe_deltat.txt",
                    $this->parent->getSwePhp()->swed->ephepath)) == null &&
                ($fp = $this->parent->getSwePhp()->sweph->swi_fopen(-1, "sedeltat.txt",
                    $this->parent->getSwePhp()->swed->ephepath)) == null)
                return self::TABSIZ;
            while (($s = fgets($fp, SweConst::AS_MAXCH)) != null) {
                $sp = $s;
                while (strpos(" \t", $sp[0]) != null && $sp[0] != "\0")
                    $sp++;
                if ($sp[0] == "#" || $sp[0] == "\n")
                    continue;
                $year = intval($s);
                $tab_index = $year - self::TABSTART;
                // table space is limited. no error msg, if exceeded
                if ($tab_index >= self::TABSIZ_SPACE) continue;
                $sp += 4;
                while (strpos(" \t", $sp[0]) != null && $sp[0] != "\0")
                    $sp++;
                self::$dt[$tab_index] = floatval($sp);
            }
            fclose($fp);
        }
        // find table size
        $tabsiz = 2001 - self::TABSTART + 1;
        for ($i = $tabsiz - 1; $i < self::TABSIZ_SPACE; $i++) {
            if ((self::$dt[$i] ?? 0) == 0) break;
            else $tabsiz++;
        }
        $tabsiz--;
        return $tabsiz;
    }

    /* Astronomical Almanac table is corrected by adding the expression
     *     -0.000091 (ndot + 26)(year-1955)^2  seconds
     * to entries prior to 1955 (AA page K8), where ndot is the secular
     * tidal term in the mean motion of the Moon.
     *
     * Entries after 1955 are referred to atomic time standards and
     * are not affected by errors in Lunar or planetary theory.
     */
    private function adjust_for_tidacc(float $ans, float $Y, float $tid_acc, float $tid_acc0, bool $adjust_after_1955): float
    {
        if ($Y < 1955.0 || $adjust_after_1955) {
            $B = ($Y - 1955.0);
            $ans += -0.000091 * ($tid_acc - $tid_acc0) * $B * $B;
        }
        return $ans;
    }



    function swi_guess_ephe_flag(): int
    {
        $iflag = SweConst::SEFLG_SWIEPH;
        // if jpl file is open, assume SEFLG_JPLEPH
        if ($this->parent->getSwePhp()->swed->jpl_file_is_open)
            $iflag = SweConst::SEFLG_JPLEPH;
        return $iflag;
    }

    private function swi_get_tid_acc(float $tjd_ut, int $iflag, int $denum, int &$denumret,
                                     float &$tid_acc, ?string &$serr = null): int
    {
        $iflag &= SwephLib::SEFLG_EPHMASK;
        if ($this->parent->getSwePhp()->swed->is_tid_acc_manual) {
            $tid_acc = $this->parent->getSwePhp()->swed->tid_acc;
            return $iflag;
        }
        if ($denum == 0) {
            if ($iflag & SweConst::SEFLG_MOSEPH) {
                $tid_acc = SweTidalAccel::SE_TIDAL_DE404;
                $denumret = 404;
                return $iflag;
            }
            if ($iflag & SweConst::SEFLG_JPLEPH) {
                if ($this->parent->getSwePhp()->swed->jpl_file_is_open)
                    $denum = $this->parent->getSwePhp()->swed->jpldenum;
            }
            // SEFLG_SWIEPH wanted or SEFLG_JPLEPH failed:
            if ($iflag & SweConst::SEFLG_SWIEPH) {
                // TODO: Should we remove "?? null" after fptr?
                if (($this->parent->getSwePhp()->swed->fidat[SweConst::SEI_FILE_MOON]->fptr ?? null) != null)
                    $denum = $this->parent->getSwePhp()->swed->fidat[SweConst::SEI_FILE_MOON]->sweph_denum;
            }
        }
        switch ($denum) {
            case 200:
                $tid_acc = SweTidalAccel::SE_TIDAL_DE200;
                break;
            case 403:
                $tid_acc = SweTidalAccel::SE_TIDAL_DE403;
                break;
            case 404:
                $tid_acc = SweTidalAccel::SE_TIDAL_DE404;
                break;
            case 405:
                $tid_acc = SweTidalAccel::SE_TIDAL_DE405;
                break;
            case 406:
                $tid_acc = SweTidalAccel::SE_TIDAL_DE406;
                break;
            case 421:
                $tid_acc = SweTidalAccel::SE_TIDAL_DE421;
                break;
            case 422:
                $tid_acc = SweTidalAccel::SE_TIDAL_DE422;
                break;
            case 430:
                $tid_acc = SweTidalAccel::SE_TIDAL_DE430;
                break;
            case 431:
                $tid_acc = SweTidalAccel::SE_TIDAL_DE431;
                break;
            case 441:
            case 440:
                $tid_acc = SweTidalAccel::SE_TIDAL_DE441;
                break;
            default:
                $denum = SweConst::SE_DE_NUMBER;
                $tid_acc = SweTidalAccel::SE_TIDAL_DEFAULT;
                break;
        }
        $denumret = $denum;
        $iflag &= SwephLib::SEFLG_EPHMASK;
        return $iflag;
    }

    private function swi_set_tid_acc(float $tjd_ut, int $iflag, int $denum, ?string &$serr = null): int
    {
        $retc = $iflag;
        $denumret = 0;
        // manual tid_acc overrides automatic tid_acc
        if ($this->parent->getSwePhp()->swed->is_tid_acc_manual)
            return $retc;
        $retc = $this->swi_get_tid_acc($tjd_ut, $iflag, $denum, $denumret,
            $this->parent->getSwePhp()->swed->tid_acc, $serr);

        if (SweInternalParams::TRACE) {
            // TODO: Do tracing as in C++ provided code
        }

        return $retc;
    }
}