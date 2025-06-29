<?php

use Enums\SweModel;
use Enums\SwePlanet;
use Enums\SweTidalAccel;
use Structs\epsilon;
use Structs\file_data;
use Structs\nut;
use Structs\sid_data;
use Structs\swe_data;
use Structs\topo_data;

class Sweph extends SweModule
{
    private sweph_calc $calc;

    public function __construct(SwePhp $base)
    {
        parent::__construct($base);
        $this->calc = new sweph_calc($this);
    }

    function getSwePhp(): SwePhp
    {
        return $this->swePhp;
    }

    const int SEFLG_EPHMASK = (SweConst::SEFLG_JPLEPH | SweConst::SEFLG_SWIEPH | SweConst::SEFLG_MOSEPH);
    const int SEFLG_COORDSYS = (SweConst::SEFLG_EQUATORIAL | SweConst::SEFLG_XYZ | SweConst::SEFLG_RADIANS);

    const float J2000 = 2451545.0;          // 2000 January 1.5
    const float B1950 = 2433282.42345905;   // 1059 January 0.923
    const float J1900 = 2415020.0;          // 1900 January 0.5
    const float B1850 = 2396758.2035810;    // 1850 January 16:53

    const int MPC_CERES = 1;
    const int MPC_PALLAS = 2;
    const int MPC_JUNO = 3;
    const int MPC_VESTA = 4;
    const int MPC_CHIRON = 2060;
    const int MPC_PHOLUS = 5145;

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

    const float KM_S_TO_AU_CTY = 21.095;                // km/s to AU/century
    const float MOON_SPEED_INTV = 0.00005;              // 4.32 seconds (in days)
    const float PLAN_SPEED_INTV = 0.0001;               // 8.64 seconds (in days)
    const float MEAN_NODE_SPEED_INTV = 0.001;
    const float NODE_CALC_INTV = 0.0001;
    const float NODE_CALC_INTV_MOSH = 0.1;
    const float NUT_SPEED_INTV = 0.0001;
    const float DEFL_SPEED_INTV = 0.0000005;

    const float SE_LAPSE_RATE = 0.0065;                 // deg K / m, for refraction

    public static function square_num(array $x): float
    {
        return $x[0] * $x[0] + $x[1] * $x[1] + $x[2] * $x[2];
    }

    public static function dot_prod(array $x, array $y): float
    {
        return $x[0] * $y[0] + $x[1] * $y[1] + $x[2] * $y[2];
    }

    const int SEI_NEPHFILES = 7;

    const int SE_PLMOON_OFFSET = 9000;
    const int SE_AST_OFFSET = 10000;
    const int SE_VARUNA = self::SE_AST_OFFSET + 20000;

    const int SE_FICT_OFFSET = 40;
    const int SE_FICT_OFFSET_1 = 39;
    const int SE_FICT_MAX = 999;
    const int SE_NFICT_ELEM = 15;

    public function swe_calc(float $tjd, int $ipl, int $iflag, array &$xx, ?string &$serr = null): int
    {
        return $this->calc->swe_calc($tjd, $ipl, $iflag, $xx, $serr);
    }

    public function swe_calc_ut(float $tjd_ut, int $ipl, int $iflag, array &$xx, ?string &$serr = null): int
    {
        return $this->calc->swe_calc_ut($tjd_ut, $ipl, $iflag, $xx, $serr);
    }

    // Function initialises swed structure.
    // Returns 1 if initialisation is done, otherwise 0
    function swi_init_swed_if_start(): int
    {
        // initialisation of swed, when called first time from
        if (!$this->swePhp->swed->swed_is_initialized) {
            $this->swePhp->swed = new swe_data();
            $this->swePhp->swed->ephepath = "sweph/ephe/";
            $this->swePhp->swed->jplfnam = SweConst::SE_FNAME_DFT;
            $this->swePhp->swephLib->swe_set_tid_acc(SweTidalAccel::SE_TIDAL_AUTOMATIC);
            $this->swePhp->swed->swed_is_initialized = true;
            return 1;
        }
        return 0;
    }

    // closes all open files, frees space of planetary data,
    // deletes memory of all computed positions
    //
    function swi_close_keep_topo_etc(): void
    {
        $swed =& $this->swePhp->swed;
        // closs SWISSEPH files
        for ($i = 0; $i < Sweph::SEI_NEPHFILES; $i++) {
            if (($swed->fidat[$i]?->fptr ?? null) != null)
                fclose($swed->fidat[$i]->fptr);
            $swed->fidat[$i] = new file_data();
        }
        $this->calc->free_planets();
        $swed->oec = new epsilon();
        $swed->oec2000 = new epsilon();
        $swed->nut = new nut();
        $swed->nut2000 = new nut();
        $swed->nutv = new nut();
        $swed->astro_models = array_fill(0, SweModel::count(), 0);
        // close JPL file
        // TODO
        // $this->swi_close_jpl_file();
        $swed->jpl_file_is_open = false;
        $swed->jpldenum = 0;
        // close fixed stars
        if ($swed->fixfp != null) {
            fclose($swed->fixfp);
            $swed->fixfp = null;
        }
        $this->swePhp->swephLib->swe_set_tid_acc(SweTidalAccel::SE_TIDAL_AUTOMATIC);
        $swed->is_old_starfile = false;
        $swed->i_saved_planet_name = 0;
        $swed->saved_planet_name = "";
        $swed->timeout = 0;
    }

    // closes all open files, frees space of planetary data,
    // deletes memory of all computed positions
    public function swe_close(): void
    {
        $swed =& $this->swePhp->swed;
        // close SWISSEPH files
        for ($i = 0; $i < Sweph::SEI_NEPHFILES; $i++) {
            if ($swed->fidat[$i]->fptr != null)
                fclose($swed->fidat[$i]->fptr);
            $swed->fidat[$i] = new file_data();
        }
        $this->calc->free_planets();
        $swed->oec = new epsilon();
        $swed->oec2000 = new epsilon();
        $swed->nut = new nut();
        $swed->nut2000 = new nut();
        $swed->nutv = new nut();
        $swed->astro_models = array_fill(0, SweModel::count(), 0);
        // closes JPL file
        $swed->jpl_file_is_open = false;
        $swed->jpldenum = 0;
        // close fixed stars
        if ($swed->fixfp != null) {
            fclose($swed->fixfp);
            $swed->fixfp = null;
        }
        $this->swePhp->swephLib->swe_set_tid_acc(SweTidalAccel::SE_TIDAL_AUTOMATIC);
        $swed->geopos_is_set = false;
        $swed->ayana_is_set = false;
        $swed->is_old_starfile = false;
        $swed->i_saved_planet_name = 0;
        $swed->saved_planet_name = '';
        $swed->topd = new topo_data();
        $swed->sidd = new sid_data();
        $swed->timeout = 0;
        $swed->last_epheflag = 0;
        if ($swed->dpsi != null) {
            unset($swed->dpsi);
            $swed->dpsi = null;
        }
        if ($swed->deps != null) {
            unset($swed->deps);
            $swed->deps = null;
        }
        if ($swed->n_fixstars_records > 0) {
            unset($swed->fixed_stars);
            $swed->fixed_stars = null;
            $swed->n_fixstars_real = 0;
            $swed->n_fixstars_named = 0;
            $swed->n_fixstars_records = 0;
        }
        if (SweInternalParams::TRACE) {
            // TODO: Trace
        }
    }

    // sets ephemeris file path.
    // also calls swe_close(). this makes sure that swe_calc()
    // won't return planet positions previously computed from other
    // ephemerides
    //
    public function swe_set_ephe_path(?string $path): void
    {
        $xx = [];
        $swed = &$this->swePhp->swed;
        // close all open files and delete all planetary data
        $this->swi_close_keep_topo_etc();
        $this->swi_init_swed_if_start();
        $swed->ephe_path_is_set = true;
        // environment variable SE_EPHE_PATH has priority
        if (($sp = getenv("SE_EPHE_PATH")) != null
            && strlen($sp) != 0) {
            $s = $sp;
        } else if (empty($path)) {
            $s = "sweph/ephe";
        } else {
            $s = $path;
        }
        $i = strlen($s);
        if ($s[$i - 1] != DIRECTORY_SEPARATOR && !empty($s))
            $s .= DIRECTORY_SEPARATOR;
        $swed->ephepath = $s;
        // try to open lunar ephemeris, in order to get DE number and set
        // tidal acceleration of the Moon
        $iflag = SweConst::SEFLG_SWIEPH | SweConst::SEFLG_J2000 | SweConst::SEFLG_TRUEPOS | SweConst::SEFLG_ICRS;
        $swed->last_epheflag = 2;
        $this->swe_calc(Sweph::J2000, SwePlanet::MOON->value, $iflag, $xx);
        if ($swed->fidat[SweConst::SEI_FILE_MOON]->fptr != null) {
            $this->swePhp->swephLib->swi_set_tid_acc(0, 0, $swed->fidat[SweConst::SEI_FILE_MOON]->sweph_denum);
        }
        if (SweInternalParams::TRACE) {
            // TODO: Trace
        }
    }

    function load_dpsi_deps(): void
    {
        $swed =& $this->swePhp->swed;
        $n = 0;
        $mjd = 0;
        $mjdsv = 0;
        $TJDOFS = 2400000.5;
        if ($swed->eop_dpsi_loaded > 0)
            return;
        $fp = $this->swi_fopen(-1, swephlib_precess::DPSI_DEPS_IAU1980_FILE_EOPC04, $swed->ephepath);
        if ($fp == null) {
            $swed->eop_dpsi_loaded = SweConst::ERR;
            return;
        }
        // No need to alloc arrays, PHP can deal with it.
        $swed->dpsi = [];
        $swed->deps = [];
        $swed->eop_tjd_beg_horizons = swephlib_precess::DPSI_DEPS_IAU1980_TJD0_HORIZONS;
        while (($s = fgets($fp, SweConst::AS_MAXCH)) != null) {
            // According to swi_cutstr() description, there is the one-line analogue:
            $cpos = array_filter(explode(" ", $s));
            if (($iyear = intval($cpos[0])) == 0)
                continue;
            $mjd = intval($cpos[3]);
            // if file in one-day steps?
            if ($mjdsv > 0 && $mjd - $mjdsv != 1) {
                // we cannot return error but we not it as follows:
                $swed->eop_dpsi_loaded = -2;
                fclose($fp);
                return;
            }
            if ($n == 0)
                $swed->eop_tjd_beg_horizons = $mjd + $TJDOFS;
            $swed->dpsi[$n] = floatval($cpos[8]);
            $swed->deps[$n] = floatval($cpos[9]);
            $n++;
            $mjdsv = $mjd;
        }
        $swed->eop_tjd_end = $mjd + $TJDOFS;
        $swed->eop_dpsi_loaded = 1;
        fclose($fp);
        // file finals.all may have some more data, and especially estimations
        // for the near future
        $fp = $this->swi_fopen(-1, swephlib_precess::DPSI_DEPS_IAU1980_FILE_FINALS, $swed->ephepath);
        if ($fp == null)
            return; // return without error as existence of file is not mandatory

        while (($s = fgets($fp, SweConst::AS_MAXCH)) != null) {
            $mjd = intval(substr($s, 7));
            if ($mjd + $TJDOFS <= $swed->eop_tjd_end)
                continue;
            if ($n >= SweConst::SWE_DATA_DPSI_DEPS)
                return;
            // are data in one-day steps?
            if ($mjdsv > 0 && $mjd - $mjdsv != 1) {
                // no error, as we do have data; however, if this file is useful,
                // then swed.eop_dpsi_loaded will be set to 2
                $swed->eop_dpsi_loaded = -3;
                fclose($fp);
                return;
            }
            // dpsi, deps Bulletin B
            $dpsi = floatval(substr($s, 168));
            $deps = floatval(substr($s, 178));
            if ($dpsi == 0) {
                // try dpsi, deps Bulletin A
                $dpsi = floatval(substr($s, 99));
                $deps = floatval(substr($s, 118));
            }
            if ($dpsi == 0) {
                $swed->eop_dpsi_loaded = 2;
                fclose($fp);
                return;
            }
            $swed->eop_tjd_end = $mjd + $TJDOFS;
            $swed->dpsi[$n] = $dpsi / 1000.0;
            $swed->deps[$n] = $deps / 1000.0;
            $n++;
            $mjdsv = $mjd;
        }
        $swed->eop_dpsi_loaded = 2;
        fclose($fp);
    }

    // sets jpl file name.
    // also calls swe_close(). this makes sure that swe_calc()
    // won't return planet positions previously computed from other
    // ephemerides
    //
    public function swe_set_jpl_file(string $fname): void
    {
        $ss = [];
        // close all open files and delete all planetary data
        $this->swi_close_keep_topo_etc();
        $this->swi_init_swed_if_start();
        // if path is contained in fname, it is filled into the path variable
        $s = $fname;
        $sp = strrchr($s, DIRECTORY_SEPARATOR);
        if ($sp == null) $sp = $s; else $sp = substr($s, 1);
        $this->swePhp->swed->jplfnam = $sp;
        // open ephemeris
        $retc = $this->open_jpl_file($ss, $this->swePhp->swed->jplfnam, $this->swePhp->swed->ephepath);
        if ($retc == SweConst::OK) {
            if ($this->swePhp->swed->jpldenum >= 403) {
                $this->load_dpsi_deps();
            }
        }
        if (SweInternalParams::TRACE) {
            // TODO: Trace
        }
    }

    public function swi_fopen(int $ifno, string $fname, string $ephepath, ?string &$serr = null)
    {
        // TODO:
        try {
            return fopen($ephepath . $fname, 'r');
        } catch (Exception $e) {
            if (isset($serr))
                $serr = $e->getMessage();
            return null;
        }
    }
}