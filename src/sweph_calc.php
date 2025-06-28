<?php

use Enums\SwePlanet;
use Enums\SweSiderealMode;
use Structs\file_data;
use Structs\plan_data;
use Structs\save_positions;

class sweph_calc
{
    private Sweph $parent;

    const array pnoext2int = [
        SweConst::SEI_SUN, SweConst::SEI_MOON, SweConst::SEI_MERCURY, SweConst::SEI_VENUS,
        SweConst::SEI_MARS, SweConst::SEI_JUPITER, SweConst::SEI_SATURN, SweConst::SEI_URANUS,
        SweConst::SEI_NEPTUNE, SweConst::SEI_PLUTO, 0, 0, 0, 0,
        SweConst::SEI_EARTH, SweConst::SEI_CHIRON, SweConst::SEI_PHOLUS, SweConst::SEI_CERES,
        SweConst::SEI_PALLAS, SweConst::SEI_JUNO, SweConst::SEI_VESTA,
    ];

    function __construct(Sweph $parent)
    {
        $this->parent = $parent;
    }

    /* The routine called by the user.
     * It checks whether a position for the same planet, the same t, and the
     * same flag bits has already been computed.
     * If yes, this position is returned. Otherwise it is computed.
     * -> If the SEFLG_SPEED flag has been specified, the speed will be returned
     * at offset 3 of position array x[]. Its precision is probably better
     * than 0.002"/day.
     * -> If the SEFLG_SPEED3 flag has been specified, the speed will be computed
     * from three positions. This speed is less accurate than SEFLG_SPEED,
     * i.e. better than 0.1"/day. And it is much slower. It is used for
     * program tests only.
     * -> If no speed flag has been specified, no speed will be returned.
     */
    public function swe_calc(float $tjd, int $ipl, int $iflag, array &$xx, ?string &$serr = null): int
    {
        $x0 = [];
        $x2 = [];
        $iplmoon = 0;
        $iflgsave = $iflag;
        $use_speed3 = false;
        if (isset($serr))
            $serr = "";
        if (SweInternalParams::TRACE) {
            // TODO: Implement tracing
        }
        // function calls for Pluto with asteroid number 134340
        // are treated as calls for Pluto as main body SE_PLUTO.
        // Reason: Our numerical integrator takes into account Pluto
        // perturbation and therefore crashes  with body 134340 Pluto.
        if ($ipl == Sweph::SE_AST_OFFSET + 134340)
            $ipl = SwePlanet::PLUTO->value;
        // if ephemeris flag != ephemeris flag of last call,
        // we clear the save area, to prevent swecalc() using
        // previously computed data for current calculation.
        // except with ipl = SE_ECL_NUT which is not dependent
        // on ephemeris, and except if change is from
        // ephemeris = 0 to ephemeris = SEFLG_DEFAULTEPH
        // or vice-versa.
        //
        $epheflag = $iflag & Sweph::SEFLG_EPHMASK;
        if ($epheflag & SweConst::SEFLG_MOSEPH) {
            $epheflag = SweConst::SEFLG_MOSEPH;
        } else if ($epheflag & SweConst::SEFLG_JPLEPH) {
            $epheflag = SweConst::SEFLG_JPLEPH;
        } else {
            $epheflag = SweConst::SEFLG_SWIEPH;
        }
        if ($this->parent->swi_init_swed_if_start() == 1 && !($epheflag & SweConst::SEFLG_MOSEPH) && isset($serr)) {
            $serr = "Please call swe_set_ephe_path() or swe_set_jplfile() before calling swe_calc() or swe_calc_ut()";
        }
        if ($this->parent->getSwePhp()->swed->last_epheflag != $epheflag) {
            $this->free_planets();
            // close and free ephemeris files
            if ($ipl != SwePlanet::ECL_NUT->value) { // because file will not be reopened with this jpl
                if ($this->parent->getSwePhp()->swed->jpl_file_is_open) {
                    // TODO: Implement
                    // $this->swi_close_jpl_file();
                    $this->parent->getSwePhp()->swed->jpl_file_is_open = false;
                }
                for ($i = 0; $i < Sweph::SEI_NEPHFILES; $i++) {
                    if ($this->parent->getSwePhp()->swed->fidat[$i]->fptr != null)
                        fclose($this->parent->getSwePhp()->swed->fidat[$i]->fptr);
                    $this->parent->getSwePhp()->swed->fidat[$i] = new file_data();
                }
                $this->parent->getSwePhp()->swed->last_epheflag = $epheflag;
            }
        }
        // high precision speed prevails fast speed
        if (($iflag & SweConst::SEFLG_SPEED3) && ($iflag & SweConst::SEFLG_SPEED))
            $iflag = $iflag & ~SweConst::SEFLG_SPEED3;
        if ($iflag & SweConst::SEFLG_SPEED3)
            $use_speed3 = true;
        // topocentric with SEFLG_SPEED is not good if aberration is included.
        // in such cases we calculate speed from three positions
        if (($iflag & SweConst::SEFLG_SPEED) && ($iflag & SweConst::SEFLG_TOPOCTR) && !($iflag & SweConst::SEFLG_NOABERR))
            $use_speed3 = true;
        // cartesian flag excludes radians flag
        if (($iflag & SweConst::SEFLG_XYZ) && ($iflag & SweConst::SEFLG_RADIANS))
            $iflag = $iflag & ~SweConst::SEFLG_RADIANS;
        // planetary center of body or planetary moon: either planet is called
        // with SEFLG_CENTER_BODY or center of body wil ipl = 9n99 is called.
        // we want to handle both cases the same way.

        // planet is called with SE_PLUTO etc. and SEFLG_CENTER_BODY:
        // get number of center of body
        if (($iflag & SweConst::SEFLG_CENTER_BODY) &&
            $ipl <= SwePlanet::PLUTO->value &&
            ($iflag & SweConst::SEFLG_TEST_PLMOON) != SweConst::SEFLG_TEST_PLMOON) {
            $iplmoon = $ipl * 100 + 9099; // planetary center of body
        }
        // planet center of body or planetary moon is called using 9... number:
        // moon number and planet number
        if ($ipl >= Sweph::SE_PLMOON_OFFSET &&
            $ipl < Sweph::SE_AST_OFFSET &&
            ($iflag & SweConst::SEFLG_TEST_PLMOON) != SweConst::SEFLG_TEST_PLMOON) {
            $iplmoon = $ipl; // planetary center of body or planetary moon
            $ipl = (int)(($ipl - 9000) / 100);
            $iflag |= SweConst::SEFLG_CENTER_BODY;
        }
        // with Mercury to Mars, we do not have center of body different from barycenter
        if (($iflag & SweConst::SEFLG_CENTER_BODY) && $ipl <= SwePlanet::MARS->value && ($iplmoon % 100) == 99) {
            $iplmoon = 0;
            $iflag &= ~SweConst::SEFLG_CENTER_BODY;
        }
        if (($iflag & SweConst::SEFLG_CENTER_BODY) || $iplmoon > 0) {
            // TODO:
            //  $this->swi_force_app_pos_etc();
        }
        // pointer to save area
        if ($ipl < SwePlanet::count() && $ipl >= SwePlanet::SUN->value) {
            $sd = &$this->parent->getSwePhp()->swed->savedat[$ipl];
        } else {
            // other bodies, e.g. asteroids called with ipl = SE_AST_OFFSET + MPC
            $sd = &$this->parent->getSwePhp()->swed->savedat[SwePlanet::count()];
        }
        //
        // if position is available in save area, it is returned.
        // this is the case, if tjd = tsave and iflag = iflgsave.
        // coordinate flags can be neglected, because save area
        // provides all coordinate types.
        // if ipl > SE_AST(EROID)_OFFSET, ipl must be checked,
        // because all asteroids called by MPC number share the save
        // save area.
        //
        if ($sd->tsave == $tjd && $tjd != 0 && $ipl == $sd->ipl && $iplmoon == 0) {
            if (($sd->iflgsave & ~Sweph::SEFLG_COORDSYS) == ($iflag & ~Sweph::SEFLG_COORDSYS))
                goto end_swe_calc;
        }
        //
        // otherwise, new position must be computed
        //
        if (!$use_speed3) {
            //
            // with high precision speed from one call of swecalc()
            // (FAST speed)
            //
            $sd->tsave = $tjd;
            $sd->ipl = $ipl;
            if (($sd->iflgsave = $this->swecalc($tjd, $ipl, $iplmoon, $iflag, $sd->xsaves, $serr)) == SweConst::ERR)
                goto return_error;
        } else {
            //
            // with speed from three calls of swecalc(), slower and less accurate.
            // (SLOW speed, for test only)
            //
            $sd->tsave = $tjd;
            $sd->ipl = $ipl;
            switch ($ipl) {
                case SwePlanet::MOON->value:
                    $dt = Sweph::MOON_SPEED_INTV;
                    break;
                case SwePlanet::OSCU_APOG->value:
                case SwePlanet::TRUE_NODE:
                    // this is the optimum dt with Moshier ephemeris, but not with
                    // JPL ephemeris or SWISSEPH. To avoid completely false speed
                    // in case that JPL is wanted but the program returns Moshier,
                    // we use Moshier optimum.
                    // For precise speed, use JPL and FAST speed computation.
                    //
                    $dt = Sweph::NODE_CALC_INTV_MOSH;
                    break;
                default:
                    $dt = Sweph::PLAN_SPEED_INTV;
                    break;
            }
            if (($sd->iflgsave = $this->swecalc($tjd - $dt, $ipl, $iplmoon, $iflag, $x0, $serr)) == SweConst::ERR)
                goto return_error;
            if (($sd->iflgsave = $this->swecalc($tjd + $dt, $ipl, $iplmoon, $iflag, $x2, $serr)) == SweConst::ERR)
                goto return_error;
            if (($sd->iflgsave = $this->swecalc($tjd, $ipl, $iplmoon, $iflag, $sd->xsaves, $serr)) == SweConst::ERR)
                goto return_error;
            $this->denormalize_positions($x0, $sd->xsaves, $x2);
            $this->calc_speed($x0, $sd->xsaves, $x2, $dt);
        }
        end_swe_calc:
        if ($iflag & SweConst::SEFLG_EQUATORIAL) {
            $xs = array_slice($sd->xsaves, 12); // equatorial coordinates
        } else {
            $xs = array_slice($sd->xsaves, 0, 12); // ecliptic coordinates
        }
        if ($iflag & SweConst::SEFLG_XYZ)
            $xs = array_slice($xs, 6); // cartesian coordinates
        if ($ipl == SwePlanet::ECL_NUT->value)
            $i = 4;
        else
            $i = 3;
        for ($j = 0; $j < $i; $j++)
            $x[$j] = $xs[$j];
        for ($j = $i; $j < 6; $j++)
            $x[$j] = 0;
        if ($iflag & (SweConst::SEFLG_SPEED3 | SweConst::SEFLG_SPEED)) {
            for ($j = 3; $j < 6; $j++)
                $x[$j] = $xs[$j];
        }
        if ($iflag & SweConst::SEFLG_RADIANS) {
            if ($ipl == SwePlanet::ECL_NUT) {
                for ($j = 0; $j < 4; $j++)
                    $x[$j] *= SweConst::DEGTORAD;
            } else {
                for ($j = 0; $j < 2; $j++)
                    $x[$j] *= SweConst::DEGTORAD;
                if ($iflag & (SweConst::SEFLG_SPEED3 | SweConst::SEFLG_SPEED)) {
                    for ($j = 3; $j < 5; $j++)
                        $x[$j] *= SweConst::DEGTORAD;
                }
            }
        }
        for ($i = 0; $i <= 5; $i++)
            $xx[$i] = $x[$i];
        // iflag from previous call of swe_calc(), without coordinate system flags
        $iflag = $sd->iflgsave & ~Sweph::SEFLG_COORDSYS;
        // add correct coordinate system flags
        $iflag |= ($iflgsave & Sweph::SEFLG_COORDSYS);
        // if no ephemeris has been specified, do not return chosen ephemeris
        if (($iflgsave & Sweph::SEFLG_EPHMASK) == 0)
            $iflag = $iflag & ~SweConst::SEFLG_DEFAULTEPH;
        if (SweInternalParams::TRACE) {
            // TODO: Do tracing
        }
        return $iflag;
        return_error:
        for ($i = 0; $i <= 5; $i++)
            $xx[$i] = 0;
        if (SweInternalParams::TRACE) {
            // TODO: Do tracing
        }
        return SweConst::ERR;
    }

    public function swe_calc_ut(float $tjd_ut, int $ipl, int $iflag, array &$xx, ?string &$serr = null): int
    {
        $retval = SweConst::OK;
        $epheflag = 0;
        $iflag = $this->plaus_iflag($iflag, $ipl, $tjd_ut, $serr);
        $epheflag = $iflag & Sweph::SEFLG_EPHMASK;
        if ($epheflag == 0) {
            $epheflag = SweConst::SEFLG_SWIEPH;
            $iflag |= SweConst::SEFLG_SWIEPH;
        }
        $deltat = $this->parent->getSwePhp()->swephLib->swe_deltat_ex($tjd_ut, $iflag, $serr);
        $retval = $this->swe_calc($tjd_ut + $deltat, $ipl, $iflag, $xx, $serr);
        // if ephe required is not ephe returned, adjust delta t:
        if (($retval & Sweph::SEFLG_EPHMASK) != $epheflag) {
            $deltat = $this->parent->getSwePhp()->swephLib->swe_deltat_ex($tjd_ut, $retval);
            $retval = $this->swe_calc($tjd_ut + $deltat, $ipl, $iflag, $xx);
        }
        return $retval;
    }

    function swecalc(float $tjd, int $ipl, int $iplmoon, int $iflag, array &$x, ?string &$serr = null): int
    {
        $epheflag = SweConst::SEFLG_DEFAULTEPH;
        $pedp = &$this->parent->getSwePhp()->swed->pldat[SweConst::SEI_EARTH];
        $psdp = &$this->parent->getSwePhp()->swed->pldat[SweConst::SEI_SUNBARY];
        $ss = [];
        $serr2 = '';
        /////////////////////////////////////////////////////
        // iflag plausible?
        /////////////////////////////////////////////////////
        $iflag = $this->plaus_iflag($iflag, $ipl, $tjd, $serr);
        /////////////////////////////////////////////////////
        // which ephemeris is wanted, which is used?
        // Three ephemerides are possible: MOSEPH, SWIEPH, JPLEPH.
        // The availability of the various ephemerides depends on the installed
        // ephemeris files in the users ephemeris directory. This can change at
        // any time.
        // Swisseph should try to fulfill the wish of the user for a specific
        // ephemeris, but use a less precise one of the desired ephemeris is not
        // available for the given date and body.
        // If internal ephemeris errors are detected (data error, file length error)
        // an error is returned.
        // If the time range is bad but another ephemeris can deliver this range,
        // the other ephemeris is used.
        // If not ephemeris is specified, DEFAULTEPH is assumed as desired.
        // DEFAULTEPH is defined at compile time, usually as JPLEPH.
        // The caller learns from the return flag which ephemeris was used.
        // ephe_flag is extracted from iflag, but can change later if the
        // desired ephe is not available.
        /////////////////////////////////////////////////////
        if ($iflag & SweConst::SEFLG_MOSEPH)
            $epheflag = SweConst::SEFLG_MOSEPH;
        if ($iflag & SweConst::SEFLG_SWIEPH)
            $epheflag = SweConst::SEFLG_SWIEPH;
        if ($iflag & SweConst::SEFLG_JPLEPH)
            $epheflag = SweConst::SEFLG_JPLEPH;
        // no barycentric calculation with Moshier ephemeris
        if (($iflag & SweConst::SEFLG_BARYCTR) && ($iflag & SweConst::SEFLG_MOSEPH)) {
            if (isset($serr))
                $serr = "barycentric Moshier positions are not supported.";
            return SweConst::ERR;
        }
        if ($epheflag != SweConst::SEFLG_MOSEPH && !$this->parent->getSwePhp()->swed->ephe_path_is_set && !$this->parent->getSwePhp()->swed->jpl_file_is_open)
            $this->parent->swe_set_ephe_path(NULL); // TODO
        if (($iflag & SweConst::SEFLG_SIDEREAL) && !$this->parent->getSwePhp()->swed->ayana_is_set)
            $this->parent->swe_set_sid_mode(SweSiderealMode::SIDM_FAGAN_BRADLEY, 0, 0);
        /////////////////////////////////////////////////////
        // obliquity of ecliptic 2000 and of date
        /////////////////////////////////////////////////////
        $this->swi_check_ecliptic($tjd, $iflag);
        /////////////////////////////////////////////////////
        // nutation
        /////////////////////////////////////////////////////
        $this->swi_check_nutation($tjd, $iflag);
        /////////////////////////////////////////////////////
        // select planet and ephemeris
        //
        // ecliptic and nutation
        /////////////////////////////////////////////////////
        if ($ipl == SwePlanet::ECL_NUT->value) {
            $x[0] = $this->parent->getSwePhp()->swed->oec->eps + $this->parent->getSwePhp()->swed->nut->nutlo[1];   // true ecliptic
            $x[1] = $this->parent->getSwePhp()->swed->oec->eps;                                                     // mean ecliptic
            $x[2] = $this->parent->getSwePhp()->swed->nut->nutlo[0];                                    // nutation in longitude
            $x[3] = $this->parent->getSwePhp()->swed->nut->nutlo[1];                                    // nutation in obliquity
            for ($i = 0; $i <= 3; $i++)
                $x[$i] *= SweConst::RADTODEG;
            return $iflag;
            /////////////////////////////////////////////////////
            // moon
            /////////////////////////////////////////////////////
        } else if ($ipl == SwePlanet::MOON->value) {
            // internal planet number
            $ipli = SweConst::SEI_MOON;
            $pdp = &$this->parent->getSwePhp()->swed->pldat[$ipli];
            $xp = $pdp->xreturn;
            switch ($epheflag) {
                case SweConst::SEFLG_JPLEPH:
                    $retc = $this->jplplan($tjd, $ipli, $iflag, true, null, null, null, $serr);
                    // read error or corrupt file
                    if ($retc == SweConst::ERR)
                        goto return_error;
                    // jpl ephemeris not on disk or date beyond ephemeris range
                    // or file corrupt
                    if ($retc == SweConst::NOT_AVAILABLE) {
                        $iflag = ($iflag & ~SweConst::SEFLG_JPLEPH) | SweConst::SEFLG_SWIEPH;
                        if (isset($serr))
                            $serr .= " \ntrying Swiss Eph; ";
                        goto sweph_moon;
                    } else if ($retc == SweConst::BEYOND_EPH_LIMITS) {
                        if ($tjd > SweConst::MOSHLUEPH_START && $tjd < SweConst::MOSHLUEPH_END) {
                            $iflag = ($iflag & SweConst::SEFLG_JPLEPH) | SweConst::SEFLG_MOSEPH;
                            if (isset($serr))
                                $serr .= " \nusing Moshier Eph; ";
                            goto moshier_moon;
                        } else
                            goto return_error;
                    }
                    break;
                case SweConst::SEFLG_SWIEPH:
                    sweph_moon:
                    $retc = $this->sweplan($tjd, $ipli, SweConst::SEI_FILE_MOON, $iflag, true,
                        null, null, null, null, $serr);
                    if ($retc == SweConst::ERR)
                        goto return_error;
                    // if sweph file not found, switch to moshier
                    if ($retc == SweConst::NOT_AVAILABLE) {
                        if ($tjd >= SweConst::MOSHLUEPH_START && $tjd < SweConst::MOSHLUEPH_END) {
                            $iflag = ($iflag & ~SweConst::SEFLG_SWIEPH) | SweConst::SEFLG_MOSEPH;
                            if (isset($serr))
                                $serr .= " \nusing Moshier eph.; ";
                            goto moshier_moon;
                        } else
                            goto return_error;
                    }
                    break;
                case SweConst::SEFLG_MOSEPH:
                    moshier_moon:
                    $retc = $this->swi_moshmoon($tjd, true, null, $serr);
                    if ($retc == SweConst::ERR)
                        goto return_error;
                    // for hel. position, we need earth as well
                    $retc = $this->swi_moshplan($tjd, SweConst::SEI_EARTH, true, null, null, $serr);
                    if ($retc == SweConst::ERR)
                        goto return_error;
                    break;
                default:
                    break;
            }
            // heliocentric, lighttime etc.
            if (($retc == $this->app_pos_etc_moon($iflag, $serr)) != SweConst::OK)
                goto return_error; // retc may be wrong with sidereal calculation
            /////////////////////////////////////////////////////
            // barycentric sun
            // (only JPL and SWISSEPH ephemerises)
            /////////////////////////////////////////////////////
        } else if ($ipl == SwePlanet::SUN->value && ($iflag & SweConst::SEFLG_BARYCTR)) {
            // barycentric sun must be handled separately because of
            // the following reasons:
            // ordinary planetary computations use the function
            // main_planet() and its subfunction jplplan(),
            // se further below.
            // now, these functions need the swisseph internal
            // planetary indices, where SEI_EARTH = SEI_SUN = 0.
            // therefore they don't know the difference between
            // a barycentric sun and a barycentric earth and
            // always return barycentric earth.
            // to avoid this problem, many functions would have to
            // be changed. as an alternative, we choose a more
            // separate handling.
            $ipli = SweConst::SEI_SUN; // = SEI_EARTH !
            $xp = $pedp->xreturn;
            switch ($epheflag) {
                case SweConst::SEFLG_JPLEPH:
                    // open ephemeris, if still closed
                    if (!$this->parent->getSwePhp()->swed->jpl_file_is_open) {
                        $retc = $this->open_jpl_file($ss, $this->parent->getSwePhp()->swed->jplfnam,
                            $this->parent->getSwePhp()->swed->ephepath, $serr);
                        if ($retc != SweConst::OK)
                            goto sweph_sbar;
                    }
                    $retc = $this->swi_pleph($tjd, SweJPL::J_SUN, SweJPL::J_SBARY, $psdp->x, $serr);
                    if ($retc == SweConst::ERR || $retc == SweConst::BEYOND_EPH_LIMITS) {
                        $this->swi_close_jpl_file();
                        $this->parent->getSwePhp()->swed->jpl_file_is_open = false;
                        goto return_error;
                    }
                    // jpl ephemeris not on disk or date beyond ephemeris range
                    // or file corrupt
                    if ($retc == SweConst::NOT_AVAILABLE) {
                        $iflag = ($iflag & ~SweConst::SEFLG_JPLEPH) | SweConst::SEFLG_SWIEPH;
                        if (isset($serr))
                            $serr .= " \ntrying Swiss Eph; ";
                        goto sweph_sbar;
                    }
                    $psdp->teval = $tjd;
                    break;
                case SweConst::SEFLG_SWIEPH:
                    sweph_sbar:
                    // sweplan() provides barycentric sun as a by-product in save area;
                    // it is saved in swed.pldat[SEI_SUNBARY].x
                    $retc = $this->sweplan($tjd, SweConst::SEI_EARTH, SweConst::SEI_FILE_PLANET, $iflag, true,
                        null, null, null, null, $serr);
                    if ($retc == SweConst::ERR || $retc == SweConst::NOT_AVAILABLE)
                        goto return_error;
                    $psdp->teval = $tjd;
                    //pedp->teval=tjd;
                    break;
                default:
                    return SweConst::ERR;
            }
            // flags
            if (($retc = $this->app_pos_etc_sbar($iflag, $serr)) != SweConst::OK)
                goto return_error;
            // iflag has possibly changed
            $iflag = $pedp->xflgs;
            // barycentric sun is now in save area of barycentric earth.
            // (pedp->xreturn = swed.pldat[SEI_EARTH].xreturn).
            // in case a barycentric earth computation follows for the same
            // date, the planetary functions will return the barycentric
            // SUN unless we force a new computation of pedp->xreturn.
            // this can be done by initializing the save of iflag.
            //
            $pedp->xflgs = -1;
            /////////////////////////////////////////////////////
            // mercury - pluto
            /////////////////////////////////////////////////////
        } else if ($ipl == SwePlanet::SUN->value ||
            $ipl == SwePlanet::MERCURY->value ||
            $ipl == SwePlanet::VENUS->value ||
            $ipl == SwePlanet::MARS->value ||
            $ipl == SwePlanet::JUPITER->value ||
            $ipl == SwePlanet::SATURN->value ||
            $ipl == SwePlanet::URANUS->value ||
            $ipl == SwePlanet::NEPTUNE->value ||
            $ipl == SwePlanet::PLUTO->value ||
            $ipl == SwePlanet::EARTH->value) {
            if ($iflag & SweConst::SEFLG_HELCTR) {
                if ($ipl == SwePlanet::SUN->value) {
                    // heliocentric position of Sun does not exist
                    for ($i = 0; $i < 24; $i++)
                        $x[$i] = 0;
                    return $iflag;
                }
            } else if ($iflag & SweConst::SEFLG_BARYCTR) {
                ;
            } else { // geocentric
                if ($ipl == SwePlanet::EARTH->value) {
                    // geocentric position of Earth does not exist
                    for ($i = 0; $i < 24; $i++)
                        $x[$i] = 0;
                    return $iflag;
                }
            }
            // internal planet number
            $ipli = self::pnoext2int[$ipl];
            $pdp =& $this->parent->getSwePhp()->swed->pldat[$ipli];
            $xp = $pdp->xreturn;
            $retc = $this->main_planet($tjd, $ipli, $iplmoon, $epheflag, $iflag, $serr);
            if ($retc == SweConst::ERR)
                goto return_error;
            // iflag has possible changed in main_planet()
            $iflag = $pdp->xflgs;
            /////////////////////////////////////////////////////
            // mean lunar node
            // for comment s. moshmoon.c, swi_mean_node()
            /////////////////////////////////////////////////////
        } else if ($ipl == SwePlanet::MEAN_NODE->value) {
            if (($iflag & SweConst::SEFLG_HELCTR) || ($iflag & SweConst::SEFLG_BARYCTR)) {
                // heliocentric/barycentric lunar node not allwed
                for ($i = 0; $i < 24; $i++)
                    $x[$i] = 0;
                return $iflag;
            }
            $ndp =& $this->parent->getSwePhp()->swed->nddat[SweConst::SEI_MEAN_NODE];
            $xp = $ndp->xreturn;
            $xp2 = $ndp->x;
            $retc = $this->swi_mean_node($tjd, $xp2, $serr);
            if ($retc == SweConst::ERR)
                goto return_error;
            // speed (is almost constant; variation < 0.001 arcsec)
            $retc = $this->swi_mean_node($tjd - Sweph::MEAN_NODE_SPEED_INTV, array_slice($xp2, 3), $serr);
            if ($retc == SweConst::ERR)
                goto return_error;
            $xp2[3] = $this->parent->getSwePhp()->swephLib->swe_difrad2n($xp2[0], $xp2[3]) / Sweph::MEAN_NODE_SPEED_INTV;
            $xp2[4] = $xp2[5] = 0;
            $ndp->teval = $tjd;
            $ndp->xflgs = -1;
            // lighttime etc.
            if (($retc = $this->app_pos_etc_mean(SweConst::SEI_MEAN_NODE, $iflag, $serr)) != SweConst::OK)
                goto return_error;
            // to avoid infinitesimal deviations from latitude = 0
            // that result from conversions
            if (!($iflag & SweConst::SEFLG_SIDEREAL) && !($iflag & SweConst::SEFLG_J2000)) {
                $ndp->xreturn[1] = 0.0;     // ecl. latitude
                $ndp->xreturn[4] = 0.0;     // speed
                $ndp->xreturn[5] = 0.0;     // radial speed
                $ndp->xreturn[8] = 0.0;     // z coordinate
                $ndp->xreturn[11] = 0.0;    // speed
            }
            if ($retc == SweConst::ERR)
                goto return_error;
            /////////////////////////////////////////////////////
            // mean lunar apogee ('dark moon', 'lilith')
            // for comment s. moshmoon.c, swi_mean_apog()
            /////////////////////////////////////////////////////
        } else if ($ipl == SwePlanet::MEAN_APOG->value) {
            if (($iflag & SweConst::SEFLG_HELCTR) || ($iflag & SweConst::SEFLG_BARYCTR)) {
                // heliocentric/barycentric lunar apogee not allowed
                for ($i = 0; $i < 24; $i++)
                    $x[$i] = 0;
                return $iflag;
            }
            $ndp =& $this->parent->getSwePhp()->swed->nddat[SweConst::SEI_MEAN_APOG];
            $xp = $ndp->xreturn;
            $xp2 = $ndp->x;
            $retc = $this->swi_mean_apog($tjd, $xp2, $serr);
            if ($retc == SweConst::ERR)
                goto return_error;
            // speed (is not constant! variation ~= several arcsec)
            $retc = $this->swi_mean_apog($tjd - Sweph::MEAN_NODE_SPEED_INTV, array_slice($xp2, 3), $serr);
            if ($retc == SweConst::ERR)
                goto return_error;
            for ($i = 0; $i <= 1; $i++)
                $xp2[3 + $i] = $this->parent->getSwePhp()->swephLib->swe_difrad2n($xp2[$i], $xp2[3 + $i]) / Sweph::MEAN_NODE_SPEED_INTV;
            $xp2[5] = 0;
            $ndp->teval = $tjd;
            $ndp->xflgs = -1;
            // lighttime etc.
            if (($retc = $this->app_pos_etc_mean(SweConst::SEI_MEAN_APOG, $iflag, $serr)) != SweConst::OK)
                goto return_error;
            // to avoid infinitesimal deviation from r-speed = 0
            // that result from conversions
            $ndp->xreturn[5] = 0.0; // speed
            if ($retc == SweConst::ERR)
                goto return_error;
            /////////////////////////////////////////////////////
            // osculating lunar node ('true node')
            /////////////////////////////////////////////////////
        } else if ($ipl == SwePlanet::TRUE_NODE) {
            if (($iflag & SweConst::SEFLG_HELCTR) || ($iflag & SweConst::SEFLG_BARYCTR)) {
                // heliocentric/barycentric lunar node not allowed
                for ($i = 0; $i < 24; $i++)
                    $x[$i] = 0;
                return $iflag;
            }
            $ndp =& $this->parent->getSwePhp()->swed->nddat[SweConst::SEI_TRUE_NODE];
            $xp = $ndp->xreturn;
            $retc = $this->lunar_osc_elem($tjd, SweConst::SEI_TRUE_NODE, $iflag, $serr);
            $iflag = $ndp->xflgs;
            // to avoid infinitesimal deviations from latitude = 0
            // that result from conversions
            if (!($iflag & SweConst::SEFLG_SIDEREAL) && !($iflag & SweConst::SEFLG_J2000)) {
                $ndp->xreturn[1] = 0.0;     // ecl. latitude
                $ndp->xreturn[4] = 0.0;     // speed
                $ndp->xreturn[8] = 0.0;     // z coordinate
                $ndp->xreturn[11] = 0.0;    // speed
            }
            if ($retc == SweConst::ERR)
                goto return_error;
            /////////////////////////////////////////////////////
            // osculating lunar apogee
            /////////////////////////////////////////////////////
        } else if ($ipl == SwePlanet::OSCU_APOG) {
            if (($iflag & SweConst::SEFLG_HELCTR) || ($iflag & SweConst::SEFLG_BARYCTR)) {
                // heliocentric/barycentric lunar apogee not allowed
                for ($i = 0; $i < 24; $i++)
                    $x[$i] = 0;
                return $iflag;
            }
            $ndp =& $this->parent->getSwePhp()->swed->nddat[SweConst::SEI_OSCU_APOG];
            $xp = $ndp->xreturn;
            $retc = $this->lunar_osc_elem($tjd, SweConst::SEI_OSCU_APOG, $iflag, $serr);
            $iflag = $ndp->xflgs;
            if ($retc == SweConst::ERR)
                goto return_error;
            /////////////////////////////////////////////////////
            // interpolated lunar apogee
            /////////////////////////////////////////////////////
        } else if ($ipl == SwePlanet::INTP_APOG) {
            if (($iflag & SweConst::SEFLG_HELCTR) || ($iflag & SweConst::SEFLG_BARYCTR)) {
                // heliocentric/barycentric lunar apogee not allowed
                for ($i = 0; $i < 24; $i++)
                    $x[$i] = 0;
                return $iflag;
            }
            if ($tjd < SweConst::MOSHLUEPH_START || $tjd > SweConst::MOSHLUEPH_END) {
                for ($i = 0; $i < 24; $i++)
                    $x[$i] = 0;
                if (isset($serr))
                    $serr = sprintf("Interpolated apsides are restricted to JD %8.1f - JD %8.1f",
                        SweConst::MOSHLUEPH_START, SweConst::MOSHLUEPH_END);
                return SweConst::ERR;
            }
            $ndp = &$this->parent->getSwePhp()->swed->nddat[SweConst::SEI_INTP_APOG];
            $xp = $ndp->xreturn;
            $retc = $this->intp_apsides($tjd, SweConst::SEI_INTP_APOG, $iflag, $serr);
            $iflag = $ndp->xflgs;
            if ($retc == SweConst::ERR)
                goto return_error;
            /////////////////////////////////////////////////////
            // interpolated lunar perigee
            /////////////////////////////////////////////////////
        } else if ($ipl == SwePlanet::INTP_PERG) {
            if (($iflag & SweConst::SEFLG_HELCTR) || ($iflag & SweConst::SEFLG_BARYCTR)) {
                // heliocentric/barycentric lunar apogee not allowed
                for ($i = 0; $i < 24; $i++)
                    $x[$i] = 0;
                return $iflag;
            }
            if ($tjd < SweConst::MOSHLUEPH_START || $tjd > SweConst::MOSHLUEPH_END) {
                for ($i = 0; $i < 24; $i++)
                    $x[$i] = 0;
                if (isset($serr))
                    $serr = sprintf("Interpolated apsides are restricted to JD %8.1f - JD %8.1f",
                        SweConst::MOSHLUEPH_START, SweConst::MOSHLUEPH_END);
                return SweConst::ERR;
            }
            $ndp = &$this->parent->getSwePhp()->swed->nddat[SweConst::SEI_INTP_PERG];
            $xp = $ndp->xreturn;
            $retc = $this->intp_apsides($tjd, SweConst::SEI_INTP_PERG, $iflag, $serr);
            $iflag = $ndp->xflgs;
            if ($retc == SweConst::ERR)
                goto return_error;
            /////////////////////////////////////////////////////
            // minor planets
            /////////////////////////////////////////////////////
        } else if ($ipl == SwePlanet::CHIRON->value ||
            $ipl == SwePlanet::PHOLUS->value ||
            $ipl == SwePlanet::CERES->value ||
            $ipl == SwePlanet::PALLAS->value ||
            $ipl == SwePlanet::JUNO->value ||
            $ipl == SwePlanet::VESTA->value ||
            $ipl > Sweph::SE_PLMOON_OFFSET ||
            $ipl > Sweph::SE_AST_OFFSET // obsolete after previous condition
        ) {
            // internal planet number
            if ($ipl < SwePlanet::count()) {
                $ipli = self::pnoext2int[$ipl];
            } else if ($ipl <= Sweph::SE_AST_OFFSET + Sweph::MPC_VESTA && $ipl > Sweph::SE_AST_OFFSET) {
                $ipli = SweConst::SEI_CERES + $ipl - Sweph::SE_AST_OFFSET - 1;
                $ipli = SwePlanet::CERES->value + $ipl - Sweph::SE_AST_OFFSET - 1;
            } else { // any asteroid except
                $ipli = SweConst::SEI_ANYBODY;
            }
            if ($ipl == SweConst::SEI_ANYBODY) {
                $ipli_ast = $ipl;
            } else {
                $ipli_ast = $ipli;
            }
            $pdp =& $this->parent->getSwePhp()->swed->pldat[$ipli];
            $xp = $pdp->xreturn;
            if ($ipli_ast > Sweph::SE_AST_OFFSET) {
                $ifno = SweConst::SEI_FILE_ANY_AST;
            } else if ($ipli_ast > Sweph::SE_PLMOON_OFFSET) {
                $ifno = SweConst::SEI_FILE_ANY_AST;
            } else {
                $ifno = SweConst::SEI_FILE_MAIN_AST;
            }
            if ($ipli == SweConst::SEI_CHIRON && ($tjd < SweConst::CHIRON_START || $tjd > SweConst::CHIRON_END)) {
                if (isset($serr)) {
                    $serr = sprintf("Chiron's ephemeris is restricted to JD %8.1f - JD %8.1f",
                        SweConst::CHIRON_START, SweConst::CHIRON_END);
                }
                return SweConst::ERR;
            }
            if ($ipli == SweConst::SEI_PHOLUS && ($tjd < SweConst::PHOLUS_START || $tjd > SweConst::PHOLUS_END)) {
                if (isset($serr)) {
                    $serr = sprintf("Pholus's ephemeris is restricted to JD %8.1f - JD %8.1f",
                        SweConst::PHOLUS_START, SweConst::PHOLUS_END);
                }
                return SweConst::ERR;
            }
            do_asteroid:
            // earth and sun are also needed
            $retc = $this->main_planet($tjd, SweConst::SEI_EARTH, 0, $epheflag, $iflag, $serr);
            if ($retc == SweConst::ERR)
                goto return_error;
            // iflag (ephemeris bit) has possible changed in main_planet()
            $iflag = $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_EARTH]->xflgs;
            // asteroid
            if (isset($serr)) {
                $serr2 = $serr;
                $serr = '';
            }
            // asteroid
            $retc = $this->sweph($tjd, $ipli_ast, $ifno, $iflag, $psdp->x, true, null, $serr);
            if ($retc == SweConst::ERR || $retc == SweConst::NOT_AVAILABLE)
                goto return_error;
            $retc = $this->app_pos_etc_plan($ipli_ast, 0, $iflag, $serr);
            if ($retc == SweConst::ERR)
                goto return_error;
            // app_pos_etc_plan() might have failed, if t(light-time)
            // is beyond ephemeris range. in this case redo with Moshier
            //
            if ($retc == SweConst::NOT_AVAILABLE || $retc == SweConst::BEYOND_EPH_LIMITS) {
                if ($epheflag != SweConst::SEFLG_MOSEPH) {
                    $iflag = ($iflag & ~Sweph::SEFLG_EPHMASK) | SweConst::SEFLG_MOSEPH;
                    $epheflag = SweConst::SEFLG_MOSEPH;
                    if (isset($serr))
                        $serr .= "\nusing Moshier eph.; ";
                    goto do_asteroid;
                } else
                    goto return_error;
            }
            // add warnings from earth/sun computation
            if (isset($serr) && empty($serr) && empty($serr2)) {
                $serr = "sun :" . $serr2;
            }
            /////////////////////////////////////////////////////
            // fictitious planets
            // (Isis-Transpluto and Uranian planets)
            /////////////////////////////////////////////////////
        } else if ($ipl >= Sweph::SE_FICT_OFFSET && $ipl <= Sweph::SE_FICT_MAX) {
            // internal planet number
            $ipli = SweConst::SEI_ANYBODY;
            $pdp =& $this->parent->getSwePhp()->swed->pldat[$ipli];
            $xp = $pdp->xreturn;
            do_fict_plan:
            // the earth for geocentric position
            $retc = $this->main_planet($tjd, SweConst::SEI_EARTH, 0, $epheflag, $iflag, $serr);
            // iflag (ephemeris bit) has possibly changed in main_planet()
            $iflag = $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_EARTH]->xflgs;
            // planet from osculating elements
            if ($this->swi_osc_el_plan($tjd, $pdp->x, $ipl - Sweph::SE_FICT_OFFSET, $ipli, $pedp->x, $psdp->x, $serr) != SweConst::OK)
                goto return_error;
            if ($retc == SweConst::ERR)
                goto return_error;
            $retc = $this->app_pos_etc_plan_osc($ipl, $ipli, $iflag, $serr);
            if ($retc == SweConst::ERR)
                goto return_error;
            // app_pos_etc_plan_osc() might have failed, if t(light-time)
            // is beyond ephemeris range. in this case redo with Moshier
            //
            if ($retc == SweConst::NOT_AVAILABLE || $retc == SweConst::BEYOND_EPH_LIMITS) {
                if ($epheflag != SweConst::SEFLG_MOSEPH) {
                    $iflag = ($iflag & ~Sweph::SEFLG_EPHMASK) | SweConst::SEFLG_MOSEPH;
                    $epheflag = SweConst::SEFLG_MOSEPH;
                    if (isset($serr))
                        $serr .= "\nusing Moshier eph.; ";
                    goto do_fict_plan;
                } else
                    goto return_error;
            }
            /////////////////////////////////////////////////////
            // invalid body number
            /////////////////////////////////////////////////////
        } else {
            if (isset($serr))
                $serr = sprintf("illegal planet number %d.", $ipl);
            goto return_error;
        }
        for ($i = 0; $i < 24; $i++)
            $x[$i] = $xp[$i];
        return $iflag;
        /////////////////////////////////////////////////////
        // return error
        /////////////////////////////////////////////////////
        return_error:
        for ($i = 0; $i < 24; $i++)
            $x[$i] = 0;
        return SweConst::ERR;
    }

    function free_planets(): void
    {
        // free planets data space
        for ($i = 0; $i < SweConst::SEI_NPLANETS; $i++) {
            if ($this->parent->getSwePhp()->swed->pldat[$i]->segp != null)
                unset($this->parent->getSwePhp()->swed->pldat[$i]->segp);
            if ($this->parent->getSwePhp()->swed->pldat[$i]->refep != null)
                unset($this->parent->getSwePhp()->swed->pldat[$i]->refep);
            $this->parent->getSwePhp()->swed->pldat[$i] = new plan_data();
        }
        for ($i = 0; $i <= SweConst::SEI_NPLANETS; $i++) // "<=" is correct! see decl.
            $this->parent->getSwePhp()->swed->savedat[$i] = new save_positions();
        // clean node data space
        for ($i = 0; $i < SweConst::SEI_NNODE_ETC; $i++)
            $this->parent->getSwePhp()->swed->nddat[$i] = new plan_data();
    }
}