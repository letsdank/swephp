<?php

/* SWISSEPH
   Moshier planet routines

   modified for SWISSEPH by Dieter Koch

**************************************************************/

use Utils\SwephCotransUtils;

class SweMPlan extends SweModule
{
    public function __construct(SwePhp $base)
    {
        parent::__construct($base);
    }

    const float TIMESCALE = 3652500.0;

    static function mods3600(float $x): float
    {
        return $x - 1.296e6 * floor($x / 1.296e6);
    }

    const int FICT_GEO = 1;
    const float KGAUSS_GEO = 0.0000298122353216; /* Earth only */

    const array pnoint2msh = [2, 2, 0, 1, 3, 4, 5, 6, 7, 8,];

    /* From Simon et al (1994)  */
    const array freqs = [
        /* Arc sec per 10000 Julian years.  */
        53810162868.8982,
        21066413643.3548,
        12959774228.3429,
        6890507749.3988,
        1092566037.7991,
        439960985.5372,
        154248119.3933,
        78655032.0744,
        52272245.1795
    ];

    const array phases = [
        /* Arc sec.  */
        252.25090552 * 3600.,
        181.97980085 * 3600.,
        100.46645683 * 3600.,
        355.43299958 * 3600.,
        34.35151874 * 3600.,
        50.07744430 * 3600.,
        314.05500511 * 3600.,
        304.34866548 * 3600.,
        860492.1546,
    ];

    static array $planets = [];

    static function initialize(): void
    {
        self::$planets = [
            SweMPTab::$mer404,
            SweMPTab::$ven404,
            SweMPTab::$ear404,
            SweMPTab::$mar404,
            SweMPTab::$jup404,
            SweMPTab::$sat404,
            SweMPTab::$ura404,
            SweMPTab::$nep404,
            SweMPTab::$plu404,
        ];
    }

    static array $ss;
    static array $cc;

    function swi_moshplan2(float $J, int $iplm, array &$pobj): int
    {
        $plan = self::$planets[$iplm];

        $T = ($J - Sweph::J2000) / self::TIMESCALE;
        // Calculate sin(i*MM), etc. for needed multiple angles.
        for ($i = 0; $i < 9; $i++) {
            if (($j = $plan->max_harmonic[$i]) > 0) {
                $sr = (self::mods3600(self::freqs[$i] * $T) + self::phases[$i]) * SweMoshierConst::STR;
                self::sscc($i, $sr, $j);
            }
        }

        // Point to start of table of arguments.
        $p = $plan->arg_tbl;
        $pIndex = 0;
        // Point to tabulated cosine and sine amplitudes.
        $pl = $plan->lon_tbl;
        $plIndex = 0;
        $pb = $plan->lat_tbl;
        $pbIndex = 0;
        $pr = $plan->rad_tbl;
        $prIndex = 0;
        $sl = 0.0;
        $sb = 0.0;
        $sr = 0.0;

        for (; ;) {
            // argument of sine and cosine
            // Number of periodic arguments.
            $np = $p[$pIndex++];
            if ($np < 0) break;
            if ($np == 0) { // It is a polynomial term.
                $nt = $p[$pIndex++];
                // Longitude polynomial.
                $cu = $pl[$plIndex++];
                for ($ip = 0; $ip < $nt; $ip++) {
                    $cu = $cu * $T + $pl[$plIndex++];
                }
                $sl += self::mods3600($cu);
                // Latitude polynomial.
                $cu = $pb[$pbIndex++];
                for ($ip = 0; $ip < $nt; $ip++) {
                    $cu = $cu * $T + $pb[$pbIndex++];
                }
                $sb += $cu;
                // Radius polynomial.
                $cu = $pr[$prIndex++];
                for ($ip = 0; $ip < $nt; $ip++) {
                    $cu = $cu * $T + $pr[$prIndex++];
                }
                $sr += $cu;
                continue;
            }
            $k1 = 0;
            $cv = 0.0;
            $sv = 0.0;
            for ($ip = 0; $ip < $np; $ip++) {
                // What harmonic.
                $j = $p[$pIndex++];
                // Which planet.
                $m = $p[$pIndex++] - 1;
                if ($j) {
                    $k = $j;
                    if ($j < 0) $k = -$k;
                    $k -= 1;
                    $su = self::$ss[$m][$k];      // sin(k*angle)
                    if ($j < 0)
                        $su = -$su;
                    $cu = self::$cc[$m][$k];
                    if ($k1 == 0) {
                        // set first angle
                        $sv = $su;
                        $cv = $cu;
                        $k1 = 1;
                    } else {
                        // combine angles
                        $t = $su * $cv + $cu * $sv;
                        $cv = $cu * $cv - $su * $sv;
                        $sv = $t;
                    }
                }
            }
            // Highest power of T.
            $nt = $p[$pIndex++];
            // Longitude.
            $cu = $pl[$plIndex++];
            $su = $pl[$plIndex++];
            for ($ip = 0; $ip < $nt; $ip++) {
                $cu = $cu * $T + $pl[$plIndex++];
                $su = $su * $T + $pl[$plIndex++];
            }
            $sl += $cu * $cv + $su * $sv;
            // Latitude.
            $cu = $pb[$pbIndex++];
            $su = $pb[$pbIndex++];
            for ($ip = 0; $ip < $nt; $ip++) {
                $cu = $cu * $T + $pb[$pbIndex++];
                $su = $su * $T + $pb[$pbIndex++];
            }
            $sb += $cu * $cv + $su * $sv;
            // Radius.
            $cu = $pr[$prIndex++];
            $su = $pr[$prIndex++];
            for ($ip = 0; $ip < $nt; $ip++) {
                $cu = $cu * $T + $pr[$prIndex++];
                $su = $su * $T + $pr[$prIndex++];
            }
            $sr += $cu * $cv + $su * $sv;
        }
        $pobj[0] = SweMoshierConst::STR * $sl;
        $pobj[1] = SweMoshierConst::STR * $sb;
        $pobj[2] = SweMoshierConst::STR * $plan->distance * $sr + $plan->distance;
        return SweConst::OK;
    }

    /* Moshier ephemeris.
     * computes heliocentric cartesian equatorial coordinates of
     * equinox 2000
     * for earth and a planet
     * tjd		julian day
     * ipli		internal SWEPH planet number
     * xp		array of 6 doubles for planet's position and speed
     * xe		                       earth's
     * serr		error string
     */
    function swi_moshplan(float $tjd, int $ipli, bool $do_save, ?array &$xpret = null, ?array &$xeret = null, ?string $serr = null): int
    {
        $x2 = [];
        $xxe = [];
        $xxp = [];
        $do_earth = false;
        $iplm = self::pnoint2msh[$ipli];
        $pdp =& $this->swePhp->swed->pldat[$ipli];
        $pedp =& $this->swePhp->swed->pldat[SweConst::SEI_EARTH];
        $seps2000 = $this->swePhp->swed->oec2000->seps;
        $ceps2000 = $this->swePhp->swed->oec2000->ceps;
        if ($do_save) {
            $xp = $pdp->x;
            $xe = $pedp->x;
        } else {
            $xp = $xxp;
            $xe = $xxe;
        }
        if ($do_save || $ipli == SweConst::SEI_EARTH || isset($xeret))
            $do_earth = true;
        // tjd beyond ephemeris limits, give some margin for speed at edge
        if ($tjd < SweConst::MOSHPLEPH_START - 0.3 || $tjd > SweConst::MOSHPLEPH_END + 0.3) {
            if (isset($serr))
                $serr .= sprintf("jd %f outside Moshier planet range %.2f .. %.2f ",
                    $tjd, SweConst::MOSHPLEPH_START, SweConst::MOSHPLEPH_END);
            return SweConst::ERR;
        }
        // earth, for geocentric position
        if ($do_earth) {
            if ($tjd == $pedp->teval && $pedp->iephe == SweConst::SEFLG_MOSEPH)
                $xe = $pedp->x;
            else {
                // emb
                $this->swi_moshplan2($tjd, self::pnoint2msh[SweConst::SEI_EMB], $xe); // emb hel. ecl. 2000 polar
                SwephCotransUtils::swi_polcart($xe, $xe); // to cartesian
                SwephCotransUtils::swi_coortrf2($xe, $xe, -$seps2000, $ceps2000); // and equator 2000
                $this->embofs_mosh($tjd, $xe); // emb -> earth
                if ($do_save) {
                    $pedp->teval = $tjd;
                    $pedp->xflgs = -1;
                    $pedp->iephe = SweConst::SEFLG_MOSEPH;
                }
                // one more position for speed.
                $this->swi_moshplan2($tjd - Sweph::PLAN_SPEED_INTV, self::pnoint2msh[SweConst::SEI_EMB], $x2);
                SwephCotransUtils::swi_polcart($x2, $x2);
                SwephCotransUtils::swi_coortrf2($x2, $x2, -$seps2000, $ceps2000);
                $this->embofs_mosh($tjd - Sweph::PLAN_SPEED_INTV, $x2);
                for ($i = 0; $i <= 2; $i++)
                    $dx[$i] = ($xe[$i] - $x2[$i]) / Sweph::PLAN_SPEED_INTV;
                // store speed
                for ($i = 0; $i <= 2; $i++)
                    $xe[$i + 3] = $dx[$i];
            }
            if (isset($xeret))
                for ($i = 0; $i <= 5; $i++)
                    $xeret[$i] = $xe[$i];
        }
        // earth is the planet wanted
        if ($ipli == SweConst::SEI_EARTH)
            $xp = $xe;
        else {
            // other planet
            // if planet has already been computed, return
            if ($tjd == $pdp->teval && $pdp->iephe == SweConst::SEFLG_MOSEPH) {
                $xp = $pdp->x;
            } else {
                $this->swi_moshplan2($tjd, $iplm, $xp);
                SwephCotransUtils::swi_polcart($xp, $xp);
                SwephCotransUtils::swi_coortrf2($xp, $xp, -$seps2000, $ceps2000);
                if ($do_save) {
                    $pdp->teval = $tjd;
                    $pdp->xflgs = -1;
                    $pdp->iephe = SweConst::SEFLG_MOSEPH;
                }
                // one more position for speed.
                // the following dt gives good speed for light-time correction
                //
                $dt = Sweph::PLAN_SPEED_INTV;
                $this->swi_moshplan2($tjd - $dt, $iplm, $x2);
                SwephCotransUtils::swi_polcart($x2, $x2);
                SwephCotransUtils::swi_coortrf2($x2, $x2, -$seps2000, $ceps2000);
                for ($i = 0; $i <= 2; $i++)
                    $dx[$i] = ($xp[$i] - $x2[$i]) / $dt;
                // store speed
                for ($i = 0; $i <= 2; $i++)
                    $xp[$i + 3] = $dx[$i];
            }
            if (isset($xpret))
                for ($i = 0; $i <= 5; $i++)
                    $xpret[$i] = $xp[$i];
        }
        return SweConst::OK;
    }

    /* Prepare lookup table of sin and cos ( i*Lj )
     * for required multiple angles
     */
    static function sscc(int $k, float $arg, int $n): void
    {
        $su = sin($arg);
        $cu = cos($arg);
        self::$ss[$k][0] = $su;     // sin(L)
        self::$cc[$k][0] = $cu;     // cos(L)
        $sv = 2.0 * $su * $cu;
        $cv = $cu * $cu - $su * $su;
        self::$ss[$k][1] = $sv;     // sin(2L)
        self::$cc[$k][1] = $cv;
        for ($i = 2; $i < $n; $i++) {
            $s = $su * $cv + $cu * $sv;
            $cv = $cu * $cv - $su * $sv;
            $sv = $s;
            self::$ss[$k][$i] = $sv;// sin(i+1 L)
            self::$cc[$k][$i] = $cv;
        }
    }

    /* Adjust position from Earth-Moon barycenter to Earth
     *
     * J = Julian day number
     * xemb = rectangular equatorial coordinates of Earth
     */
    function embofs_mosh(float $tjd, array &$xemb): void
    {
        $seps = $this->swePhp->swed->oec->seps;
        $ceps = $this->swePhp->swed->oec->ceps;
        // Short series for position of the Moon
        $T = ($tjd - Sweph::J1900) / 36525.0;
        // Mean anomaly of moon (MP)
        $a = SwephLib::swe_degnorm(((1.44e-5 * $T + 0.009192) * $T + 477198.8491) * $T + 296.104608);
        $a *= SweConst::DEGTORAD;
        $smp = sin($a);
        $cmp = cos($a);
        $s2mp = 2.0 * $smp * $cmp;          // sin(2MP)
        $c2mp = $cmp * $cmp - $smp * $smp;  // cos(2MP)
        // Mean elongation of moon (D)
        $a = SwephLib::swe_degnorm(((1.9e-6 * $T - 0.001436) * $T + 445267.1142) * $T + 350.737486);
        $a = 2.0 * SweConst::DEGTORAD * $a;
        $s2d = sin($a);
        $c2d = cos($a);
        // Mean distance of moon from its ascending node (F)
        $a = SwephLib::swe_degnorm(((-3.e-7 * $T - 0.003211) * $T + 483202.0251) * $T + 11.250889);
        $a *= SweConst::DEGTORAD;
        $sf = sin($a);
        $cf = cos($a);
        $s2f = 2.0 * $sf * $cf;             // sin(2F)
        $sx = $s2d * $cmp - $c2d * $smp;    // sin(2D - MP)
        $cx = $c2d * $cmp + $s2d * $smp;    // cos(2D - MP)
        // Mean longitude of moon (LP)
        $L = ((1.9e-6 * $T - 0.001133) * $T + 481267.8831) * $T + 270.434164;
        // Mean anomaly of sun (M)
        $M = SwephLib::swe_degnorm(((-3.3e-6 * $T - 1.50e-4) * $T + 35999.0498) * $T + 358.475833);
        // Ecliptic longitude of the moon
        $L = $L
            + 6.288750 * $smp
            + 1.274018 * $sx
            + 0.658309 * $s2d
            + 0.213616 * $s2mp
            - 0.185596 * sin(SweConst::DEGTORAD * $M)
            - 0.114336 * $s2f;
        // Ecliptic latitude of the moon
        $a = $smp * $cf;
        $sx = $cmp * $sf;
        $B = 5.128139 * $sf
            + 0.280606 * ($a + $sx)                 // sin(MP+F)
            + 0.277693 * ($a - $sx)                 // sin(MP-F)
            + 0.173238 * ($s2d * $cf - $c2d * $sf); // sin(2D-F)
        $B *= SweConst::DEGTORAD;
        // Parallax of the moon
        $p = 0.950724
            + 0.521818 * $cmp
            + 0.009531 * $cx
            + 0.007843 * $c2d
            + 0.002824 * $c2mp;
        $p *= SweConst::DEGTORAD;
        // Elongation of Moon from Sun
        //
        $L = SwephLib::swe_degnorm($L);
        $L *= SweConst::DEGTORAD;
        // Distance in au
        $a = 4.263523e-5 / sin($p);
        // Convert to rectangular ecliptic coordinates
        $xyz[0] = $L;
        $xyz[1] = $B;
        $xyz[2] = $a;
        SwephCotransUtils::swi_polcart($xyz, $xyz);
        // Convert to equatorial
        SwephCotransUtils::swi_coortrf2($xyz, $xyz, -$seps, $ceps);
        // Precess to equinox of J2000.0
        $this->swePhp->swephLib->swi_precess($xyz, $tjd, 0, SweConst::J_TO_J2000);
        // now emb -> earth
        for ($i = 0; $i <= 2; $i++)
            $xemb[$i] -= $xyz[$i] / (Sweph::EARTH_MOON_MRAT + 1.0);
    }
}

SweMPlan::initialize();