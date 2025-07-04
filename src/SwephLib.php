<?php

use Enums\SweModel;
use Enums\SweModelPrecession;
use Enums\SweModelSidereal;
use Enums\SweSiderealMode;
use Enums\SweTidalAccel;
use Utils\SwephCotransUtils;
use Utils\SwephStringUtils;

class SwephLib extends SweModule
{
    const int SEFLG_EPHMASK = (SweConst::SEFLG_JPLEPH | SweConst::SEFLG_SWIEPH | SweConst::SEFLG_MOSEPH);
    const float SE_DELTAT_AUTOMATIC = -1E-10;

    const float NCTIES = 6.0;     // nubmer of centuries per eph. file

    private swephlib_deltat $deltat;
    private swephlib_precess $precess;
    private swephlib_nut $nut;
    private swephlib_sidt $sidt;

    public function __construct(SwePhp $base)
    {
        parent::__construct($base);
        $this->deltat = new swephlib_deltat($this);
        $this->precess = new swephlib_precess($this);
        $this->nut = new swephlib_nut($this);
        $this->sidt = new swephlib_sidt($this);
    }

    function getSwePhp(): SwePhp
    {
        return $this->swePhp;
    }

    /**
     * Normalization of any degree number to the range [0;360].
     *
     * @param float $x
     * @return float
     */
    public static function swe_degnorm(float $x): float
    {
        $y = fmod($x, 360.0);
        if (abs($y) < 1e-13) $y = 0;
        if ($y < 0.0) $y += 360.0;
        return $y;
    }

    /**
     * Normalization of any radian number to the range [0;2*pi].
     *
     * @param float $x
     * @return float
     */
    public static function swe_radnorm(float $x): float
    {
        $y = fmod($x, SweConst::TWOPI);
        if (abs($y) < 1e-13) $y = 0;
        if ($y < 0.0) $y += SweConst::TWOPI;
        return $y;
    }

    /**
     * Calculate midpoint (in degrees).
     *
     * @param float $x1
     * @param float $x0
     * @return float
     */
    public static function swe_deg_midp(float $x1, float $x0): float
    {
        $d = self::swe_difdeg2n($x1, $x0);     // arc from x0 to x1
        $y = self::swe_degnorm($x0 + $d / 2);
        return $y;
    }

    /**
     * Calculate midpoint (in radians).
     *
     * @param float $x1
     * @param float $x0
     * @return float
     */
    public static function swe_rad_midp(float $x1, float $x0): float
    {
        return SweConst::DEGTORAD * self::swe_deg_midp(
                $x1 * SweConst::RADTODEG, $x0 * SweConst::RADTODEG);
    }

    // Reduce x modulo 2*PI
    static function swi_mod2PI(float $x): float
    {
        $y = fmod($x, SweConst::TWOPI);
        if ($y < 0.0) $y += SweConst::TWOPI;
        return $y;
    }

    static function swi_angnorm(float $x): float
    {
        if ($x < 0.0) return $x + SweConst::TWOPI;
        else if ($x >= SweConst::TWOPI) return $x - SweConst::TWOPI;
        return $x;
    }

    static function swi_cross_prod(array $a, array $b, array &$x): void
    {
        $x[0] = $a[1] * $b[2] - $a[2] * $b[1];
        $x[1] = $a[2] * $b[0] - $a[0] * $b[2];
        $x[2] = $a[0] * $b[1] - $a[1] * $b[0];
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
        SwephCotransUtils::swe_cotrans($xpo, $xpn, $eps);
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
        SwephCotransUtils::swe_cotrans_sp($xpo, $xpn, $eps);
    }

    static function swi_dot_prod_unit(array $x, array $y): float
    {
        $dop = $x[0] * $y[0] + $x[1] * $y[1] + $x[2] * $y[2];
        $e1 = sqrt($x[0] * $x[0] + $x[1] * $x[1] + $x[2] * $x[2]);
        $e2 = sqrt($y[0] * $y[0] + $y[1] * $y[1] + $y[2] * $y[2]);
        $dop /= $e1;
        $dop /= $e2;
        if ($dop > 1) $dop = 1;
        if ($dop < -1) $dop = -1;
        return $dop;
    }

    public function swe_deltat_ex(float $tjd, int $iflag, ?string &$serr = null): float
    {
        $deltat = 0.0;
        if ($this->swePhp->swed->delta_t_userdef_is_set)
            return $this->swePhp->swed->delta_t_userdef;
        if (isset($serr))
            $serr = "";
        $this->deltat->calc_deltat($tjd, $iflag, $deltat, $serr);
        return $deltat;
    }

    public function swe_deltat(float $tjd): float
    {
        $iflag = $this->deltat->swi_guess_ephe_flag();
        return $this->swe_deltat_ex($tjd, $iflag); // with default tidal acceleration/default ephemeris
    }

    // returns tidal acceleration used in swe_deltat() and swe_deltat_ex()
    public function swe_get_tid_acc(): float
    {
        return $this->swePhp->swed->tid_acc;
    }

    /* function sets tidal acceleration of the Moon.
     * t_acc can be either
     * - the value of the tidal acceleration in arcsec/cty^2
     *   of the Moon will be set consistent with that ephemeris.
     * - SE_TIDAL_AUTOMATIC,
     */
    public function swe_set_tid_acc(float $t_acc): void
    {
        if ($t_acc == SweTidalAccel::SE_TIDAL_AUTOMATIC) {
            $this->swePhp->swed->tid_acc = SweTidalAccel::SE_TIDAL_DEFAULT;
            $this->swePhp->swed->is_tid_acc_manual = false;
            return;
        }
        $this->swePhp->swed->tid_acc = $t_acc;
        $this->swePhp->swed->is_tid_acc_manual = true;
    }

    public function swe_set_delta_t_userdef(float $dt): void
    {
        if ($dt == self::SE_DELTAT_AUTOMATIC) {
            $this->swePhp->swed->delta_t_userdef_is_set = false;
        } else {
            $this->swePhp->swed->delta_t_userdef_is_set = true;
            $this->swePhp->swed->delta_t_userdef = $dt;
        }
    }

    function swe_sidtime0(float $tjd, float $eps, float $nut): float
    {
        // jd0 - Julian day at midnight Universal Time
        // secs - Time of day, UT seconds since UT midnight
        $prec_model_short = $this->swePhp->swed->astro_models[SweModel::MODEL_PREC_SHORTTERM->value];
        $sidt_model = $this->swePhp->swed->astro_models[SweModel::MODEL_SIDT->value];
        if ($prec_model_short == 0) $prec_model_short = SweModelPrecession::defaultShort();
        if ($sidt_model == 0) $sidt_model = SweModelSidereal::default();
        $this->swePhp->sweph->swi_init_swed_if_start();
        if ($sidt_model == SweModelSidereal::MOD_SIDT_LONGTERM) {
            if ($tjd <= swephlib_sidt::SIDT_LTERM_T0 || $tjd >= swephlib_sidt::SIDT_LTERM_T1) {
                $gmst = $this->sidt->sidtime_long_term($tjd, $eps, $nut);
                if ($tjd <= swephlib_sidt::SIDT_LTERM_T0)
                    $gmst -= swephlib_sidt::SIDT_LTERM_OFS0;
                else if ($tjd >= swephlib_sidt::SIDT_LTERM_T1)
                    $gmst -= swephlib_sidt::SIDT_LTERM_OFS1;
                if ($gmst >= 24) $gmst -= 24;
                if ($gmst < 0) $gmst += 24;
                goto sidtime_done;
            }
        }
        // Julian day at given UT
        $jd = $tjd;
        $jd0 = floor($jd);
        $secs = $tjd - $jd0;
        if ($secs < 0.5) {
            $jd0 -= 0.5;
            $secs += 0.5;
        } else {
            $jd0 += 0.5;
            $secs -= 0.5;
        }
        $secs *= 86400.0;
        $tu = ($jd0 - Sweph::J2000) / 36525.0; // UT1 in centuries after J2000
        if ($sidt_model == SweModelSidereal::MOD_SIDT_IERS_CONV_2010 || $sidt_model == SweModelSidereal::MOD_SIDT_LONGTERM) {
            // Era-based expression for Greenwich Sidereal Time (GST) based
            // on the IAU 2006 precession
            $jdrel = $tjd - Sweph::J2000;
            $tt = ($tjd + $this->swe_deltat_ex($tjd, -1) - Sweph::J2000) / 36525.0;
            $gmst = self::swe_degnorm((0.7790572732640 + 1.00273781191135448 * $jdrel) * 360);
            $gmst += (0.014506 + $tt * (4612.156534 + $tt * (1.3915817 + $tt * (-0.00000044 + $tt * (-0.000029956 + $tt * -0.0000000368))))) / 3600.0;
            $dadd = $this->sidt->sidtime_non_polynomial_part($tt);
            $gmst = self::swe_degnorm($gmst + $dadd);
            $gmst = $gmst / 15.0 * 3600.0;
        } else if ($sidt_model == SweModelSidereal::MOD_SIDT_IAU_2006) {
            // sidt_model == SEMOD_SIDT_IAU_2006, older standards according to precession model
            $tt = ($jd0 + $this->swe_deltat_ex($jd0, -1) - Sweph::J2000) / 36525.0; // TT in centuries after J2000
            $gmst = (((-0.000000002454 * $tt - 0.00000199708) * $tt - 0.0000002926) * $tt + 0.092772110) * $tt * $tt + 307.4771013 * ($tt - $tu) + 8640184.79447825 * $tu + 24110.5493771;
            // mean solar days per sidereal day at date tu;
            // for the derivative of gmst, we can assume UT1 =~ TT
            $msday = 1 + ((((-0.000000012270 * $tt - 0.00000798832) * $tt - 0.0000008778) * $tt + 0.185544220) * $tt + 8640184.79447825) / (86400. * 36525.);
            $gmst += $msday * $secs;
        } else { // IAU 1976 formula
            // Greenwich Mean Sidereal Time at 0h UT of date
            $gmst = ((-6.2e-6 * $tu + 9.3104e-2) * $tu + 8640184.812666) * $tu + 24110.54841;
            // mean solar days per sidereal day at date tu, = 1.00273790934 in 1986
            $msday = 1.0 + ((-1.86e-5 * $tu + 0.186208) * $tu + 8640184.812866) / (86400. * 36525.);
            $gmst += $msday * $secs;
        }
        // Local apparent sidereal time at given UT at Greenwich
        $eqeq = 240.0 * $nut * cos($eps * SweConst::DEGTORAD);
        $gmst = $gmst + $eqeq; // + 240.0*tlong
        // Sidereal seconds modulo 1 sidereal day
        $gmst = $gmst - 86400.0 * floor($gmst / 86400.0);
        // return in hours
        $gmst /= 3600;
        goto sidtime_done;
        sidtime_done:
        if (SweInternalParams::TRACE) {
            // TODO: Make trace
        }
        return $gmst;
    }

    public function swe_set_interpolate_nut(bool $do_interpolate): void
    {
        if ($this->swePhp->swed->do_interpolate_nut == $do_interpolate)
            return;
        if ($do_interpolate)
            $this->swePhp->swed->do_interpolate_nut = true;
        else
            $this->swePhp->swed->do_interpolate_nut = false;
        $this->swePhp->swed->interpol->tjd_nut0 = 0;
        $this->swePhp->swed->interpol->tjd_nut2 = 0;
        $this->swePhp->swed->interpol->nut_dpsi0 = 0;
        $this->swePhp->swed->interpol->nut_dpsi1 = 0;
        $this->swePhp->swed->interpol->nut_dpsi2 = 0;
        $this->swePhp->swed->interpol->nut_deps0 = 0;
        $this->swePhp->swed->interpol->nut_deps1 = 0;
        $this->swePhp->swed->interpol->nut_deps2 = 0;
    }

    // sidereal time, without eps and nut as parameters.
    // tjd must be UT !!!
    // for more information, see comment with swe_sidtime0()
    //
    public function swe_sidtime(float $tjd_ut): float
    {
        $nutlo = [];
        // delta t adjusted to default tidal acceleration of the moon
        $tjde = $tjd_ut + $this->swe_deltat_ex($tjd_ut, -1);
        $this->swePhp->sweph->swi_init_swed_if_start();
        $eps = $this->precess->swi_epsiln($tjde, 0) * SweConst::RADTODEG;
        $this->nut->swi_nutation($tjde, 0, $nutlo);
        for ($i = 0; $i < 2; $i++)
            $nutlo[$i] *= SweConst::RADTODEG;
        $tsid = $this->swe_sidtime0($tjd_ut, $eps + $nutlo[1], $nutlo[0]);
        return $tsid;
    }

    /* SWISSEPH
     * generates name of ephemeris file
     * file name looks as follows:
     * swephpl.m30, where
     *
     * "sweph"              	"swiss ephemeris"
     * "pl","mo","as"               planet, moon, or asteroid
     * "m"  or "_"                  BC or AD
     *
     * "30"                         start century
     * tjd        	= ephemeris file for which julian day
     * ipli       	= number of planet
     * fname      	= ephemeris file name
     */
    function swi_gen_filename(float $tjd, int $ipli, string &$fname): void
    {
        $ncties = (int)self::NCTIES;
        $jmon = 0;
        $jday = 0;
        $jyear = 0;
        $jut = 0.;
        switch ($ipli) {
            case SweConst::SEI_MOON:
                $fname = "semo";
                break;
            case SweConst::SEI_EMB:
            case SweConst::SEI_MERCURY:
            case SweConst::SEI_VENUS:
            case SweConst::SEI_MARS:
            case SweConst::SEI_JUPITER:
            case SweConst::SEI_SATURN:
            case SweConst::SEI_URANUS:
            case SweConst::SEI_NEPTUNE:
            case SweConst::SEI_PLUTO:
            case SweConst::SEI_SUNBARY:
                $fname = "sepl";
                break;
            case SweConst::SEI_CERES:
            case SweConst::SEI_PALLAS:
            case SweConst::SEI_JUNO:
            case SweConst::SEI_VESTA:
            case SweConst::SEI_CHIRON:
            case SweConst::SEI_PHOLUS:
                $fname = "seas";
                break;
            default: // asteroid or planetary moon
                if ($ipli > Sweph::SE_PLMOON_OFFSET && $ipli < Sweph::SE_AST_OFFSET) {
                    $fname = sprintf("sat%ssepm%d.%s", DIRECTORY_SEPARATOR, $ipli, SweConst::SE_FILE_SUFFIX);
                } else {
                    $sform = "ast%d%sse%05d.%s";
                    if ($ipli - Sweph::SE_AST_OFFSET > 99999)
                        $sform = "ast%d%ss%06d.%s";
                    $fname = sprintf($sform, ($ipli - Sweph::SE_AST_OFFSET) / 1000, DIRECTORY_SEPARATOR,
                        $ipli - Sweph::SE_AST_OFFSET, SweConst::SE_FILE_SUFFIX);
                }
                return; // asteroids or planetary moons: only one file 3000 bc - 3000 ad
        }
        // century of tjd
        // if tjd > 1600 then gregorian calendar
        if ($tjd >= 2305447.5) {
            $gregflag = 1;
            $this->swePhp->sweDate->swe_revjul($tjd, $gregflag, $jyear, $jmon, $jday, $jut);
            // else julian calendar
        } else {
            $gregflag = 0;
            $this->swePhp->sweDate->swe_revjul($tjd, $gregflag, $jyear, $jmon, $jday, $jut);
        }
        // start century of file containing tjd
        if ($jyear < 0)
            $sgn = -1;
        else
            $sgn = 1;
        $icty = $jyear / 100;
        if ($sgn < 0 && $jyear % 100 != 0)
            $icty -= 1;
        while ($icty % $ncties != 0)
            $icty--;
        // B.C. or A.D.
        if ($icty < 0)
            $fname .= "m";
        else
            $fname .= "_";
        $icty = abs($icty);
        $fname .= sprintf("%02d.%s", $icty, SweConst::SE_FILE_SUFFIX);
    }

    /*******************************************************
     * other functions from swephlib.c;
     * they are not needed for Swiss Ephemeris,
     * but may be useful to former Placalc users.
     ********************************************************/

    // Normalize argument into interval [0..DEG360]
    public static function swe_csnorm(int $p): int
    {
        if ($p < 0)
            do {
                $p += SweConst::DEG360;
            } while ($p < 0);
        else if ($p >= SweConst::DEG360)
            do {
                $p -= SweConst::DEG360;
            } while ($p >= SweConst::DEG360);
        return $p;
    }

    // Distance in centisecs p1 - p2
    // normalized to [0..360[
    public static function swe_difcsn(int $p1, int $p2): int
    {
        return self::swe_csnorm($p1 - $p2);
    }

    public static function swe_difdegn(float $p1, float $p2): float
    {
        return self::swe_degnorm($p1 - $p2);
    }

    // Distance in centisecs p1 - p2
    // normalized to [-180..180[
    public static function swe_difcs2n(int $p1, int $p2): int
    {
        $dif = self::swe_csnorm($p1 - $p2);
        if ($dif >= SweConst::DEG180) return ($dif - SweConst::DEG360);
        return $dif;
    }

    public static function swe_difdeg2n(float $p1, float $p2): float
    {
        $dif = self::swe_degnorm($p1 - $p2);
        if ($dif >= 180.0) return ($dif - 360.0);
        return $dif;
    }

    public static function swe_difrad2n(float $p1, float $p2): float
    {
        $dif = self::swe_radnorm($p1 - $p2);
        // TODO: What's the point of having TWOPI / 2 (instead of M_PI)?
        if ($dif >= SweConst::TWOPI / 2) return ($dif - SweConst::TWOPI);
        return $dif;
    }

    // Round second, but at 29.5959 always down
    public static function swe_csroundsec(int $x): int
    {
        $t = (int)(($x + 50) / 100) * 100;          // round to seconds
        if ($t > $x && $t % SweConst::DEG30 == 0)   // was rounded up to next sign
            $t = (int)($x / 100) * 100;             // round last second of sign downwards
        return $t;
    }

    // double to int32 with rounding, no overflow check
    public static function swe_d2l(float $x): int
    {
        if ($x >= 0)
            return ((int)($x + 0.5));
        else
            return (-(int)(0.5 - $x));
    }

    // monday = 0, ... sunday = 6
    public static function swe_day_of_week(float $jd): int
    {
        return (((int)floor($jd - 2433282 - 1.5) % 7) + 7) % 7;
    }

    public static function swe_cs2timestr(int $t, string $sep, bool $suppressZero): string
    {
        return SwephStringUtils::swe_cs2timestr($t, $sep, $suppressZero);
    }

    public static function swe_cs2lonlatstr(int $t, string $pchar, string $mchar): string
    {
        return SwephStringUtils::swe_cs2lonlatstr($t, $pchar, $mchar);
    }

    public static function swe_cs2degstr(int $t): string
    {
        return SwephStringUtils::swe_cs2degstr($t);
    }

    const int SE_SPLIT_DEG_ROUND_SEC = 1;
    const int SE_SPLIT_DEG_ROUND_MIN = 2;
    const int SE_SPLIT_DEG_ROUND_DEG = 4;
    const int SE_SPLIT_DEG_ZODIACAL = 8;
    const int SE_SPLIT_DEG_NAKSHATRA = 1024;
    // don't round to next sign, e.g. 29.9999999 will be rounded to 29d59'59" (or 29d59' or 29d)
    const int SE_SPLIT_DEG_KEEP_SIGN = 16;
    // don't round to next degree, e.g. 13.9999999 will be rounded to 13d59'59" (or 13d59' or 13d)
    const int SE_SPLIT_DEG_KEEP_DEG = 32;

    /*****************************************************************
     * decimal degrees in zodiac to nakshatra position, deg, min, sec *
     * for definition of input see function swe_split_deg().
     * output:
     * ideg    degrees,
     * imin    minutes,
     * isec    seconds,
     * dsecfr    fraction of seconds (zero if rounding used)
     * inak    nakshatra number;
     ******************************************************************/
    function split_deg_nakshatra(float $ddeg, int $roundflag, int &$ideg, int &$imin, int &$isec,
                                 float &$dsecfr, int &$inak): void
    {
        $dadd = 0;
        $dnakshsize = 13.33333333333333;
        $ddeghelp = fmod($ddeg, $dnakshsize);
        $inak = 1;
        if ($ddeg < 0) {
            $inak = -1;
            $ddeg = 0;
        }
        // Sheoran "Vedic" ayanamsha: 0 Aries = 3°20 Ashvini
        if ($this->swePhp->swed->sidd->sid_mode == SweSiderealMode::SE_SIDM_TRUE_SHEORAN)
            $ddeg = self::swe_degnorm($ddeg + 3.33333333333333);
        if ($roundflag & self::SE_SPLIT_DEG_ROUND_DEG) {
            $dadd = 0.5;
        } else if ($roundflag & self::SE_SPLIT_DEG_ROUND_MIN) {
            $dadd = 0.5 / 60;
        } else if ($roundflag & self::SE_SPLIT_DEG_ROUND_SEC) {
            $dadd = 0.5 / 3600;
        }
        if ($roundflag & self::SE_SPLIT_DEG_KEEP_DEG) {
            if ((int)($ddeghelp + $dadd) - (int)$ddeghelp > 0)
                $dadd = 0;
        } else if ($roundflag & self::SE_SPLIT_DEG_KEEP_SIGN) {
            if ($ddeghelp + $dadd >= $dnakshsize)
                $dadd = 0;
        }
        $ddeg += $dadd;
        $inak = (int)($ddeg / $dnakshsize);
        if ($inak == 27) $inak = 0; // with rounding up from 359.9999
        $ddeg = fmod($ddeg, $dnakshsize);
        $ideg = (int)$ddeg;
        $ddeg -= $ideg;
        $imin = (int)($ddeg * 60);
        $ddeg -= $imin / 60.0;
        $isec = (int)($ddeg * 3600);
        if (!($roundflag & (self::SE_SPLIT_DEG_ROUND_DEG | self::SE_SPLIT_DEG_ROUND_MIN | self::SE_SPLIT_DEG_ROUND_SEC))) {
            $dsecfr = $ddeg * 3600 - $isec;
        } else {
            $dsecfr = 0;
        }
    }

    public function swe_split_deg(float $ddeg, int $roundflag, int &$ideg, int &$imin, int &$isec,
                                  float &$dsecfr, int &$isgn): void
    {
        $dadd = 0;
        $isgn = 1;
        if ($ddeg < 0) {
            $isgn = -1;
            $ddeg = -$ddeg;
        } else if ($roundflag & self::SE_SPLIT_DEG_NAKSHATRA) {
            $this->split_deg_nakshatra($ddeg, $roundflag, $ideg, $imin, $isec, $dsecfr, $isgn);
            return;
        }
        if ($roundflag & self::SE_SPLIT_DEG_ROUND_DEG) {
            $dadd = 0.5;
        } else if ($roundflag & self::SE_SPLIT_DEG_ROUND_MIN) {
            $dadd = 0.5 / 60.0;
        } else if ($roundflag & self::SE_SPLIT_DEG_ROUND_SEC) {
            $dadd = 0.5 / 3600.0;
        }
        if ($roundflag & self::SE_SPLIT_DEG_KEEP_DEG) {
            if ((int)($ddeg + $dadd) - (int)$ddeg > 0)
                $dadd = 0;
        } else if ($roundflag & self::SE_SPLIT_DEG_KEEP_SIGN) {
            if (fmod($ddeg, 30) + $dadd >= 30)
                $dadd = 0;
        }
        $ddeg += $dadd;
        if ($roundflag & self::SE_SPLIT_DEG_ZODIACAL) {
            $isgn = (int)($ddeg / 30);
            if ($isgn == 12) // 360° = 0°
                $isgn = 0;
            $ddeg = fmod($ddeg, 30);
        }
        $ideg = (int)$ddeg;
        $ddeg -= $ideg;
        $imin = (int)($ddeg * 60);
        $ddeg -= $imin / 60.0;
        $isec = (int)($ddeg * 3600);
        if (!($roundflag & (self::SE_SPLIT_DEG_ROUND_DEG | self::SE_SPLIT_DEG_ROUND_MIN | self::SE_SPLIT_DEG_ROUND_SEC))) {
            $dsecfr = $ddeg * 3600 - $isec;
        } else {
            $dsecfr = 0;
        }
    }

    function swi_kepler(float $E, float $M, float $ecce): float
    {
        $dE = 1;
        // simple formula for small eccentricities
        if ($ecce < 0.4) {
            while ($dE > 1e-12) {
                $E0 = $E;
                $E = $M + $ecce * sin($E0);
                $dE = abs($E - $E0);
            }
        } else {
            // complicated formula for high eccentricities
            while ($dE > 1e-12) {
                $E0 = $E;
                //
                // Alois 21-jul-2000: workaround an optimizer problem in gcc
                // swi_mod2PI sees very small negative argument e-322 and returns +2PI
                // we avoid swi_mod2PI for small x.
                //
                $x = ($M + $ecce * sin($E0) - $E0) / (1 - $ecce * cos($E0));
                $dE = abs($x);
                if ($dE < 1e-2) {
                    $E = $E0 + $x;
                } else {
                    $E = self::swi_mod2PI($E0 + $x);
                    $dE = abs($E - $E0);
                }
            }
        }
        return $E;
    }

    function swi_FK4_FK5(array &$xp, float $tjd): void
    {
        $correct_speed = true;
        if ($xp[0] == 0 && $xp[1] == 0 && $xp[2] == 0)
            return;
        // with zero speed, we assume that it should be really zero
        if ($xp[3] == 0)
            $correct_speed = false;
        SwephCotransUtils::swi_cartpol_sp($xp, $xp);
        // according to Expl.Suppl., p. 167f.
        $xp[0] += (0.035 + 0.085 * ($tjd - Sweph::B1950) / 36524.2198782) / 3600 * 15 * SweConst::DEGTORAD;
        if ($correct_speed)
            $xp[3] += (0.085 / 36524.2198782) / 3600 * 15 * SweConst::DEGTORAD;
        SwephCotransUtils::swi_polcart_sp($xp, $xp);
    }

    function swi_FK5_FK4(array &$xp, float $tjd): void
    {
        if ($xp[0] == 0 && $xp[1] == 0 && $xp[2] == 0)
            return;
        SwephCotransUtils::swi_cartpol_sp($xp, $xp);
        // according to Expl.Suppl., p. 167f.
        $xp[0] -= (0.035 + 0.085 * ($tjd - Sweph::B1950) / 36524.2198782) / 3600 * 15 * SweConst::DEGTORAD;
        $xp[3] -= (0.085 / 36524.2198782) / 3600 * 15 * SweConst::DEGTORAD;
        SwephCotransUtils::swi_polcart_sp($xp, $xp);
    }

    // TODO: Need to implement:
    //  * set_astro_models
    //  * swe_set_astro_models
    //  * get_precession_model
    //  * get_deltat_model
    //  * get_nutation_model
    //  * get_frame_bias_model
    //  * get_sidt_model
    //  * swe_get_astro_models
    //  * swi_strcpy?
    //  * swi_open_trace
    //

    //////////////////////////////////////////////////
    // Placeholders used for private classes
    //////////////////////////////////////////////////

    // chebyshev

    static function swi_echeb(float $x, array $coef, int $ncf): float
    {
        return swephlib_chebyshev::swi_echeb($x, $coef, $ncf);
    }

    static function swi_edcheb(float $x, array $coef, int $ncf): float
    {
        return swephlib_chebyshev::swi_edcheb($x, $coef, $ncf);
    }

    // precess

    function swi_epsiln(float $J, int $iflag): float
    {
        return $this->precess->swi_epsiln($J, $iflag);
    }

    function swi_precess(array &$R, float $J, int $iflag, int $direction): int
    {
        return $this->precess->swi_precess($R, $J, $iflag, $direction);
    }

    function swi_ldp_peps(float $tjd, ?float &$dpre = null, ?float &$deps = null): void
    {
        $this->precess->swi_ldp_peps($tjd, $dpre, $deps);
    }

    // nut

    function swi_nutation(float $tjd, int $iflag, array &$nutlo): int
    {
        return $this->nut->swi_nutation($tjd, $iflag, $nutlo);
    }

    function swi_bias(array &$x, float $tjd, int $iflag, bool $backward): void
    {
        $this->nut->swi_bias($x, $tjd, $iflag, $backward);
    }

    // deltat
    function swi_set_tid_acc(float $tjd_ut, int $iflag, int $denum, ?string &$serr = null): int
    {
        return $this->deltat->swi_set_tid_acc($tjd_ut, $iflag, $denum, $serr);
    }

    function swi_guess_ephe_flag(): int
    {
        return $this->deltat->swi_guess_ephe_flag();
    }
}