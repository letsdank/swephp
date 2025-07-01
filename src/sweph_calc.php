<?php

use Enums\SweModel;
use Enums\SweModelJPLHorizon;
use Enums\SweModelJPLHorizonApprox;
use Enums\SweModelPrecession;
use Enums\SwePlanet;
use Enums\SweSiderealMode;
use Structs\epsilon;
use Structs\file_data;
use Structs\meff_ele;
use Structs\nut;
use Structs\plan_data;
use Structs\save_positions;
use Utils\ArrayUtils;
use Utils\PointerUtils;
use Utils\SwephCotransUtils;

class sweph_calc
{
    private Sweph $parent;

    const array pnoint2jpl = [
        SweJPL::J_EARTH, SweJPL::J_MOON, SweJPL::J_MERCURY, SweJPL::J_VENUS,
        SweJPL::J_MARS, SweJPL::J_JUPITER, SweJPL::J_SATURN, SweJPL::J_URANUS,
        SweJPL::J_NEPTUNE, SweJPL::J_PLUTO, SweJPL::J_SUN,
    ];
    const array pnoext2int = [
        SweConst::SEI_SUN, SweConst::SEI_MOON, SweConst::SEI_MERCURY, SweConst::SEI_VENUS,
        SweConst::SEI_MARS, SweConst::SEI_JUPITER, SweConst::SEI_SATURN, SweConst::SEI_URANUS,
        SweConst::SEI_NEPTUNE, SweConst::SEI_PLUTO, 0, 0, 0, 0,
        SweConst::SEI_EARTH, SweConst::SEI_CHIRON, SweConst::SEI_PHOLUS, SweConst::SEI_CERES,
        SweConst::SEI_PALLAS, SweConst::SEI_JUNO, SweConst::SEI_VESTA,
    ];

    const int IS_PLANET = 0;
    const int IS_MOON = 1;
    const int IS_ANY_BODY = 2;
    const int IS_MAIN_ASTEROID = 3;

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
                    $this->parent->getSwePhp()->sweJPL->swi_close_jpl_file();
                    $this->parent->getSwePhp()->swed->jpl_file_is_open = false;
                }
                for ($i = 0; $i < Sweph::SEI_NEPHFILES; $i++) {
                    if (($this->parent->getSwePhp()->swed->fidat[$i]->fptr ?? null) != null)
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
            $this->swi_force_app_pos_etc();
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
            $this->parent->swe_set_ephe_path(NULL);
        if (($iflag & SweConst::SEFLG_SIDEREAL) && !$this->parent->getSwePhp()->swed->ayana_is_set)
            $this->parent->swe_set_sid_mode(SweSiderealMode::SIDM_FAGAN_BRADLEY->value, 0, 0);
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
                    $retc = $this->jplplan($tjd, $ipli, $iflag, true, serr: $serr);
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
                    $retc = $this->sweplan($tjd, $ipli, SweConst::SEI_FILE_MOON, $iflag, true, serr: $serr);
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
                    $retc = $this->parent->getSwePhp()->sweMMoon->swi_moshmoon($tjd, true, serr: $serr);
                    if ($retc == SweConst::ERR)
                        goto return_error;
                    // for hel. position, we need earth as well
                    $retc = $this->parent->getSwePhp()->sweMPlan->swi_moshplan($tjd, SweConst::SEI_EARTH,
                        true, serr: $serr);
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
                    $retc = $this->parent->getSwePhp()->sweJPL->swi_pleph($tjd, SweJPL::J_SUN, SweJPL::J_SBARY, $psdp->x, $serr);
                    if ($retc == SweConst::ERR || $retc == SweConst::BEYOND_EPH_LIMITS) {
                        $this->parent->getSwePhp()->sweJPL->swi_close_jpl_file();
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
                    $retc = $this->sweplan($tjd, SweConst::SEI_EARTH, SweConst::SEI_FILE_PLANET,
                        $iflag, true, serr: $serr);
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
            $retc = $this->parent->getSwePhp()->sweMMoon->swi_mean_node($tjd, $xp2, $serr);
            if ($retc == SweConst::ERR)
                goto return_error;
            // speed (is almost constant; variation < 0.001 arcsec)
            $retc = PointerUtils::pointerFn($xp2, 3,
                fn(&$xp2p) => $this->parent->getSwePhp()->sweMMoon->swi_mean_node($tjd - Sweph::MEAN_NODE_SPEED_INTV, $xp2p, $serr));
            if ($retc == SweConst::ERR)
                goto return_error;
            $xp2[3] = SwephLib::swe_difrad2n($xp2[0], $xp2[3]) / Sweph::MEAN_NODE_SPEED_INTV;
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
            $retc = $this->parent->getSwePhp()->sweMMoon->swi_mean_apog($tjd, $xp2, $serr);
            if ($retc == SweConst::ERR)
                goto return_error;
            // speed (is not constant! variation ~= several arcsec)
            $retc = PointerUtils::pointerFn($xp2, 3,
                fn(&$xp2p) => $this->parent->getSwePhp()->sweMMoon->swi_mean_apog($tjd - Sweph::MEAN_NODE_SPEED_INTV, $xp2p, $serr));
            if ($retc == SweConst::ERR)
                goto return_error;
            for ($i = 0; $i <= 1; $i++)
                $xp2[3 + $i] = SwephLib::swe_difrad2n($xp2[$i], $xp2[3 + $i]) / Sweph::MEAN_NODE_SPEED_INTV;
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
            $retc = $this->sweph($tjd, $ipli_ast, $ifno, $iflag, $psdp->x, true, serr: $serr);
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
        $swed =& $this->parent->getSwePhp()->swed;
        // free planets data space
        for ($i = 0; $i < SweConst::SEI_NPLANETS; $i++) {
            if (($swed->pldat[$i]->segp ?? null) != null)
                unset($swed->pldat[$i]->segp);
            if (($swed->pldat[$i]->refep ?? null) != null)
                unset($swed->pldat[$i]->refep);
            $swed->pldat[$i] = new plan_data();
        }
        for ($i = 0; $i <= SweConst::SEI_NPLANETS; $i++) // "<=" is correct! see decl.
            $swed->savedat[$i] = new save_positions();
        // clean node data space
        for ($i = 0; $i < SweConst::SEI_NNODE_ETC; $i++)
            $swed->nddat[$i] = new plan_data();
    }

    // calculates obliquity of ecliptic and stores it together
    // with its date, sine, and cosine
    //
    function calc_epsilon(float $tjd, int $iflag, epsilon &$e): void
    {
        $e->teps = $tjd;
        $e->eps = $this->parent->getSwePhp()->swephLib->swi_epsiln($tjd, $iflag);
        $e->seps = sin($e->eps);
        $e->ceps = cos($e->eps);
    }

    /* computes a main planet from any ephemeris, if it
     * has not yet been computed for this date.
     * since a geocentric position requires the earth, the
     * earth's position will be computed as well. With SWISSEPH
     * files the barycentric sun will be done as well.
     * With Moshier, the moon will be done as well.
     *
     * tjd 		= julian day
     * ipli		= body number
     * epheflag	= which ephemeris? JPL, SWISSEPH, Moshier?
     * iflag	= other flags
     *
     * the geocentric apparent position of ipli (or whatever has
     * been specified in iflag) will be saved in
     * &swed.pldat[ipli].xreturn[];
     *
     * the barycentric (heliocentric with Moshier) position J2000
     * will be kept in
     * &swed.pldat[ipli].x[];
     */
    function main_planet(float $tjd, int $ipli, int $iplmoon, int $epheflag, int $iflag, ?string &$serr = null): int
    {
        if (($iflag & SweConst::SEFLG_CENTER_BODY)
            && $ipli >= SwePlanet::MARS->value && $ipli <= SwePlanet::PLUTO) {
            //ipli_com = ipli * 100 + 9099;
            // jupiter center of body, relative to jupiter barycenter
            $retc = $this->sweph($tjd, $iplmoon, SweConst::SEI_FILE_ANY_AST, $iflag, null, true, serr: $serr);
            if ($retc == SweConst::ERR || $retc == SweConst::NOT_AVAILABLE)
                return SweConst::ERR;
        }
        switch ($epheflag) {
            case SweConst::SEFLG_JPLEPH:
                $retc = $this->jplplan($tjd, $ipli, $iflag, true, serr: $serr);
                // read error or corrupt file
                if ($retc == SweConst::ERR)
                    return SweConst::ERR;
                // jpl ephemeris not on disk or date beyond ephemeris range
                if ($retc == SweConst::NOT_AVAILABLE) {
                    $iflag = ($iflag & ~SweConst::SEFLG_JPLEPH) | SweConst::SEFLG_SWIEPH;
                    if (isset($serr))
                        $serr .= " \ntrying Swiss Eph; ";
                    goto sweph_planet;
                } else if ($retc == SweConst::BEYOND_EPH_LIMITS) {
                    if ($tjd > SweConst::MOSHPLEPH_START && $tjd < SweConst::MOSHPLEPH_END) {
                        $iflag = ($iflag & ~SweConst::SEFLG_JPLEPH) | SweConst::SEFLG_MOSEPH;
                        if (isset($serr))
                            $serr .= " \nusing Moshier Eph; ";
                        goto moshier_planet;
                    } else {
                        return SweConst::ERR;
                    }
                }
                // geocentric, lighttime etc.
                if ($ipli == SwePlanet::SUN->value) {
                    $retc = $this->app_pos_etc_sun($iflag, $serr);
                } else {
                    $retc = $this->app_pos_etc_plan($ipli, $iplmoon, $iflag, $serr);
                }
                if ($retc == SweConst::ERR)
                    return SweConst::ERR;
                // t for light-time beyond ephemeris range
                if ($retc == SweConst::NOT_AVAILABLE) {
                    $iflag = ($iflag & ~SweConst::SEFLG_JPLEPH) | SweConst::SEFLG_SWIEPH;
                    if (isset($serr))
                        $serr .= " \ntrying Swiss Eph; ";
                    goto sweph_planet;
                } else if ($retc == SweConst::BEYOND_EPH_LIMITS) {
                    if ($tjd > SweConst::MOSHPLEPH_START && $tjd < SweConst::MOSHPLEPH_END) {
                        $iflag = ($iflag & ~SweConst::SEFLG_JPLEPH) | SweConst::SEFLG_MOSEPH;
                        if (isset($serr))
                            $serr .= " \nusing Moshier Eph; ";
                        goto moshier_planet;
                    } else
                        return SweConst::ERR;
                }
                break;
            case SweConst::SEFLG_SWIEPH:
                sweph_planet:
                // compute barycentric planet (+ earth, sun, moon)
                $retc = $this->sweplan($tjd, $ipli, SweConst::SEI_FILE_PLANET, $iflag, true, serr: $serr);
                if ($retc == SweConst::ERR)
                    return SweConst::ERR;
                // if sweph file not found, switch to moshier
                if ($retc == SweConst::NOT_AVAILABLE) {
                    if ($tjd > SweConst::MOSHPLEPH_START && $tjd < SweConst::MOSHPLEPH_END) {
                        $iflag = ($iflag & ~SweConst::SEFLG_SWIEPH) | SweConst::SEFLG_MOSEPH;
                        if (isset($serr))
                            $serr .= " \nusing Moshier eph.; ";
                        goto moshier_planet;
                    } else
                        return SweConst::ERR;
                }
                // geocentric, lighttime etc.
                if ($ipli == SwePlanet::SUN->value) {
                    $retc = $this->app_pos_etc_sun($iflag, $serr);
                } else {
                    $retc = $this->app_pos_etc_plan($ipli, $iplmoon, $iflag, $serr);
                }
                if ($retc == SweConst::ERR)
                    return SweConst::ERR;
                // if sweph file for t(lighttime) not found, switch to moshier
                if ($retc == SweConst::NOT_AVAILABLE) {
                    if ($tjd > SweConst::MOSHPLEPH_START && $tjd < SweConst::MOSHPLEPH_END) {
                        $iflag = ($iflag & ~SweConst::SEFLG_SWIEPH) | SweConst::SEFLG_MOSEPH;
                        if (isset($serr))
                            $serr .= " \nusing Moshier eph.; ";
                        goto moshier_planet;
                    } else
                        return SweConst::ERR;
                }
                break;
            case SweConst::SEFLG_MOSEPH:
                moshier_planet:
                $retc = $this->parent->getSwePhp()->sweMPlan->swi_moshplan($tjd, $ipli, true, serr: $serr);
                if ($retc == SweConst::ERR)
                    return SweConst::ERR;
                // geocentric, lighttime etc.
                if ($ipli == SwePlanet::SUN->value) {
                    $retc = $this->app_pos_etc_sun($iflag, $serr);
                } else {
                    $retc = $this->app_pos_etc_plan($ipli, $iplmoon, $iflag, $serr);
                }
                if ($retc == SweConst::ERR)
                    return SweConst::ERR;
                break;
            default:
                break;
        }
        return SweConst::OK;
    }

    /* Computes a main planet from any ephemeris or returns
     * it again, if it has been computed before.
     * In barycentric equatorial position of the J2000 equinox.
     * The earth's position is computed as well. With SWISSEPH
     * and JPL ephemeris the barycentric sun is computed, too.
     * With Moshier, the moon is returned, as well.
     *
     * tjd 		= julian day
     * ipli		= body number
     * epheflag	= which ephemeris? JPL, SWISSEPH, Moshier?
     * iflag	= other flags
     * xp, xe, xs, and xm are the pointers, where the program
     * either finds or stores (if not found) the barycentric
     * (heliocentric with Moshier) positions of the following
     * bodies:
     * xp		planet
     * xe		earth
     * xs		sun
     * xm		moon
     *
     * xm is used with Moshier only
     */
    function main_planet_bary(float $tjd, int $ipli, int $epheflag, int $iflag, bool $do_save,
                              array &$xp, array &$xe, array &$xs, array &$xm, ?string &$serr = null): int
    {
        switch ($epheflag) {
            case SweConst::SEFLG_JPLEPH:
                $retc = $this->jplplan($tjd, $ipli, $iflag, $do_save, $xp, $xe, $xs, $serr);
                // read error or corrupt file
                if ($retc == SweConst::ERR || $retc == SweConst::BEYOND_EPH_LIMITS)
                    return $retc;
                // jpl ephemeris not on disk or date beyond ephemeris range
                if ($retc == SweConst::NOT_AVAILABLE) {
                    $iflag = ($iflag & ~SweConst::SEFLG_JPLEPH) | SweConst::SEFLG_SWIEPH;
                    if (isset($serr))
                        $serr .= " \ntrying Swiss Eph; ";
                    goto sweph_planet;
                }
                break;
            case SweConst::SEFLG_SWIEPH:
                sweph_planet:
                // compute barycentric planet (+ earth, sun, moon)
                $retc = $this->sweplan($tjd, $ipli, SweConst::SEI_FILE_PLANET, $iflag, $do_save,
                    $xp, $xe, $xs, $xm, $serr);
                // if barycentric moshier calculation were implemented
                if ($retc == SweConst::ERR)
                    return SweConst::ERR;
                // if sweph file not found, switch to moshier
                if ($retc == SweConst::NOT_AVAILABLE) {
                    if ($tjd > SweConst::MOSHPLEPH_START && $tjd < SweConst::MOSHPLEPH_END) {
                        $iflag = ($iflag & ~SweConst::SEFLG_SWIEPH) | SweConst::SEFLG_MOSEPH;
                        if (isset($serr))
                            $serr .= " \nusing Moshier eph.; ";
                        goto moshier_planet;
                    } else {
                        return SweConst::ERR;
                    }
                }
                break;
            case SweConst::SEFLG_MOSEPH:
                moshier_planet:
                $retc = $this->parent->getSwePhp()->sweMPlan->swi_moshplan($tjd, $ipli, $do_save,
                    $xp, $xe, $serr);
                if ($retc == SweConst::ERR)
                    return SweConst::ERR;
                for ($i = 0; $i <= 5; $i++)
                    $xs[$i] = 0;
                break;
            default:
                break;
        }
        return SweConst::OK;
    }

    /* SWISSEPH
     * this routine computes heliocentric cartesian equatorial coordinates
     * of equinox 2000 of
     * geocentric moon
     *
     * tjd 		julian date
     * iflag	flag
     * do_save	save J2000 position in save area pdp->x ?
     * xp		array of 6 doubles for lunar position and speed
     * serr		error string
     */
    function swemoon(float $tjd, int $iflag, bool $do_save, array &$xpret, ?string &$serr = null): int
    {
        $xx = [];
        $pdp =& $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_MOON];
        if ($do_save) {
            $xp = $pdp->x;
        } else {
            $xp = $xx;
        }
        // if planet has already been computed for this date, return
        // if speed flag has been turned on, recompute planet
        $speedf1 = $pdp->xflgs & SweConst::SEFLG_SPEED;
        $speedf2 = $iflag & SweConst::SEFLG_SPEED;
        if ($tjd == $pdp->teval && $pdp->iephe == SweConst::SEFLG_SWIEPH && (!$speedf2 || $speedf1)) {
            $xp = $pdp->x;
        } else {
            // call sweph for moon
            $retc = $this->sweph($tjd, SweConst::SEI_MOON, SweConst::SEI_FILE_MOON, $iflag, null, $do_save, $xp, $serr);
            if ($retc != SweConst::OK)
                return $retc;
            if ($do_save) {
                $pdp->teval = $tjd;
                $pdp->xflgs = -1;
                $pdp->iephe = SweConst::SEFLG_SWIEPH;
            }
        }
        if ($xpret != null)
            for ($i = 0; $i <= 5; $i++)
                $xpret[$i] = $xp[$i];
        return SweConst::OK;
    }

    /* SWISSEPH
     * this function computes
     * 1. a barycentric planet
     * plus, under certain conditions,
     * 2. the barycentric sun,
     * 3. the barycentric earth, and
     * 4. the geocentric moon,
     * in barycentric cartesian equatorial coordinates J2000.
     *
     * these are the data needed for calculation of light-time etc.
     *
     * tjd 		julian date
     * ipli		SEI_ planet number
     * ifno		ephemeris file number
     * do_save	write new positions in save area
     * xp		array of 6 doubles for planet's position and velocity
     * xpe                                 earth's
     * xps                                 sun's
     * xpm                                 moon's
     * serr		error string
     *
     * xp - xpm can be NULL. if do_save is TRUE, all of them can be NULL.
     * the positions will be written into the save area (swed.pldat[ipli].x)
     */
    function sweplan(float  $tjd, int $ipli, int $ifno, int $iflag, bool $do_save,
                     ?array &$xpret = null, ?array &$xperet = null, ?array &$xpsret = null,
                     ?array &$xpmret = null, ?string &$serr = null): int
    {
        $do_earth = false;
        $do_moon = false;
        $do_sunbary = false;
        $pdp =& $this->parent->getSwePhp()->swed->pldat[$ipli];
        $pebdp =& $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_EMB];
        $psbdp =& $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_SUNBARY];
        $pmdp =& $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_MOON];
        $xxp = [];
        $xxm = [];
        $xxs = [];
        $xxe = [];
        // xps (barycentric sun) may be necessary because some planets on sweph
        // file are heliocentric, other ones are barycentric. without xps,
        // the heliocentric ones cannot be returned barycentrically.
        //
        if ($do_save || $ipli == SweConst::SEI_SUNBARY || ($pdp->iflg & SweConst::SEI_FLG_HELIO) ||
            isset($xpsret) || ($iflag & SweConst::SEFLG_HELCTR))
            $do_sunbary = true;
        if ($do_save || $ipli == SweConst::SEI_EARTH || isset($xperet))
            $do_earth = true;
        if ($ipli == SweConst::SEI_MOON) {
            $do_earth = true;
            $do_sunbary = true;
        }
        if ($do_save || $ipli == SweConst::SEI_MOON || $ipli == SweConst::SEI_EARTH || isset($xperet) || isset($xpmret))
            $do_moon = true;
        if ($do_save) {
            $xp = $pdp->x;
            $xpe = $pebdp->x;
            $xps = $psbdp->x;
            $xpm = $pmdp->x;
        } else {
            $xp = $xxp;
            $xpe = $xxe;
            $xps = $xxs;
            $xpm = $xxm;
        }
        $speedf2 = $iflag & SweConst::SEFLG_SPEED;
        // barycentric sun
        if ($do_sunbary) {
            $speedf1 = $psbdp->xflgs & SweConst::SEFLG_SPEED;
            // if planet has already been computed for this date, return
            // if speed flag has been turned on, recompute planet
            if ($tjd == $psbdp->teval && $psbdp->iephe == SweConst::SEFLG_SWIEPH && (!$speedf2 || $speedf1)) {
                for ($i = 0; $i <= 5; $i++)
                    $xps[$i] = $psbdp->x[$i];
            } else {
                $retc = $this->sweph($tjd, SweConst::SEI_SUNBARY, SweConst::SEI_FILE_PLANET, $iflag, null, $do_save, $xps, $serr);
                if ($retc != SweConst::OK)
                    return $retc;
            }
            if ($xpsret != null)
                for ($i = 0; $i <= 5; $i++)
                    $xpret[$i] = $xps[$i];
        }
        // moon
        if ($do_moon) {
            $speedf1 = $pmdp->xflgs & SweConst::SEFLG_SPEED;
            if ($tjd == $pmdp->teval && $pmdp->iephe == SweConst::SEFLG_SWIEPH && (!$speedf2 || $speedf1)) {
                for ($i = 0; $i <= 5; $i++)
                    $xpm[$i] = $pmdp->x[$i];
            } else {
                $retc = $this->sweph($tjd, SweConst::SEI_MOON, SweConst::SEI_FILE_MOON, $iflag, null, $do_save, $xpm, $serr);
                if ($retc == SweConst::ERR)
                    return $retc;
                // if moon file doesn't exist, take moshier moon
                if ($this->parent->getSwePhp()->swed->fidat[SweConst::SEI_FILE_MOON]->fptr != null) {
                    if (isset($serr))
                        $serr .= " \nusing Moshier eph. for moon; ";
                    $retc = $this->parent->getSwePhp()->sweMMoon->swi_moshmoon($tjd, $do_save, $xpm, $serr);
                    if ($retc != SweConst::OK)
                        return $retc;
                }
            }
            if ($xpmret != null)
                for ($i = 0; $i <= 5; $i++)
                    $xpmret[$i] = $xpm[$i];
        }
        // barycentric earth
        if ($do_earth) {
            $speedf1 = $pebdp->xflgs & SweConst::SEFLG_SPEED;
            if ($tjd == $pebdp->teval && $pebdp->iephe == SweConst::SEFLG_SWIEPH && (!$speedf2 || $speedf1)) {
                for ($i = 0; $i <= 5; $i++)
                    $xpe[$i] = $pebdp->x[$i];
            } else {
                $retc = $this->sweph($tjd, SweConst::SEI_EMB, SweConst::SEI_FILE_PLANET, $iflag, null, $do_save, $xpe, $serr);
                if ($retc != SweConst::OK)
                    return $retc;
                // earth from emb and moon
                $this->embofs($xpe, $xpm);
                // speed is needed, if
                // 1. true position is being computed before applying light-time etc.
                //    this is the position saved in pdp->x.
                //    in this case, speed is needed for light-time correction.
                // 2. the speed flag has been specified.
                //
                if ($xpe == $pebdp->x || ($iflag & SweConst::SEFLG_SPEED))
                    PointerUtils::pointer2Fn($xpe, $xpm, 3, 3,
                        fn(&$xpeo, $xpmo) => $this->embofs($xpeo, $xpmo));
            }
            if (isset($xperet))
                for ($i = 0; $i <= 5; $i++)
                    $xperet[$i] = $xpe[$i];
        }
        if ($ipli == SweConst::SEI_MOON) {
            for ($i = 0; $i <= 5; $i++)
                $xp[$i] = $xpm[$i];
        } else if ($ipli == SweConst::SEI_EARTH) {
            for ($i = 0; $i <= 5; $i++)
                $xp[$i] = $xpe[$i];
        } else if ($ipli == SweConst::SEI_SUN) {
            for ($i = 0; $i <= 5; $i++)
                $xp[$i] = $xps[$i];
        } else {
            // planet
            $speedf1 = $pdp->xflgs & SweConst::SEFLG_SPEED;
            if ($tjd == $pdp->teval && $pdp->iephe == SweConst::SEFLG_SWIEPH && (!$speedf2 || $speedf1)) {
                for ($i = 0; $i <= 5; $i++)
                    $xp[$i] = $pdp->x[$i];
                return SweConst::OK;
            } else {
                $retc = $this->sweph($tjd, $ipli, $ifno, $iflag, null, $do_save, $xp, $serr);
                if ($retc != SweConst::OK)
                    return $retc;
                // if planet is heliocentric, it must be transformed to barycentric
                if ($pdp->iflg & SweConst::SEI_FLG_HELIO) {
                    // now barycentric planet
                    for ($i = 0; $i <= 2; $i++)
                        $xp[$i] += $xps[$i];
                    if ($do_save || ($iflag & SweConst::SEFLG_SPEED))
                        for ($i = 3; $i <= 5; $i++)
                            $xp[$i] += $xps[$i];
                }
            }
        }
        if (isset($xpret))
            for ($i = 0; $i <= 5; $i++)
                $xpret[$i] = $xp[$i];
        return SweConst::OK;
    }

    /* jpl ephemeris.
     * this function computes
     * 1. a barycentric planet position
     * plus, under certain conditions,
     * 2. the barycentric sun,
     * 3. the barycentric earth,
     * in barycentric cartesian equatorial coordinates J2000.

     * tjd		julian day
     * ipli		sweph internal planet number
     * do_save	write new positions in save area
     * xp		array of 6 doubles for planet's position and speed vectors
     * xpe		                       earth's
     * xps		                       sun's
     * serr		pointer to error string
     *
     * xp - xps can be NULL. if do_save is TRUE, all of them can be NULL.
     * the positions will be written into the save area (swed.pldat[ipli].x)
     */
    function jplplan(float  $tjd, int $ipli, int $iflag, bool $do_save,
                     ?array &$xpret = null, ?array &$xperet = null,
                     ?array &$xpsret = null, ?string &$serr = null): int
    {
        $do_earth = false;
        $do_sunbary = false;
        $ss = [];
        $xxp = [];
        $xxe = [];
        $xxs = [];
        $ictr = SweJPL::J_SBARY;
        $pdp =& $this->parent->getSwePhp()->swed->pldat[$ipli];
        $pedp =& $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_EARTH];
        $psdp =& $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_SUNBARY];
        $iflag = SweConst::SEFLG_JPLEPH; // currently not used, but this stops compiler warning
        // we assume Teph ~= TDB ~= TT. The maximum error is < 0.002 sec,
        // corresponding to an ephemeris error < 0.001 arcsec for the moon
        if ($do_save) {
            $xp = $pdp->x;
            $xpe = $pedp->x;
            $xps = $psdp->x;
        } else {
            $xp = $xxp;
            $xpe = $xxe;
            $xps = $xxs;
        }
        if ($do_save || $ipli == SweConst::SEI_EARTH || isset($xperet) || ($ipli == SweConst::SEI_MOON))
            $do_earth = true;
        if ($do_save || $ipli == SweConst::SEI_SUNBARY || isset($xpsret) || ($ipli == SweConst::SEI_MOON))
            $do_sunbary = true;
        if ($ipli == SweConst::SEI_MOON)
            $ictr = SweJPL::J_EARTH;
        // open ephemeris, if still closed
        if (!$this->parent->getSwePhp()->swed->jpl_file_is_open) {
            $retc = $this->open_jpl_file($ss, $this->parent->getSwePhp()->swed->jplfnam,
                $this->parent->getSwePhp()->swed->ephepath, $serr);
            if ($retc != SweConst::OK)
                return $retc;
        }
        if ($do_earth) {
            // barycentric earth
            if ($tjd != $pedp->teval || $tjd == 0) {
                $retc = $this->parent->getSwePhp()->sweJPL->swi_pleph($tjd, SweJPL::J_EARTH, SweJPL::J_SBARY, $xpe, $serr);
                if ($do_save) {
                    $pedp->teval = $tjd;
                    $pedp->xflgs = -1;      // new light-time etc. required
                    $pedp->iephe = SweConst::SEFLG_JPLEPH;
                }
                if ($retc != SweConst::OK) {
                    $this->parent->getSwePhp()->sweJPL->swi_close_jpl_file();
                    $this->parent->getSwePhp()->swed->jpl_file_is_open = false;
                    return $retc;
                }
            } else {
                $xpe = $pedp->x;
            }
            if (isset($xperet))
                for ($i = 0; $i <= 5; $i++)
                    $xperet[$i] = $xpe[$i];
        }
        if ($do_sunbary) {
            // barycentric sun
            if ($tjd != $psdp->teval || $tjd == 0) {
                $retc = $this->parent->getSwePhp()->sweJPL->swi_pleph($tjd, SweJPL::J_SUN, SweJPL::J_SBARY, $xps, $serr);
                if ($do_save) {
                    $psdp->teval = $tjd;
                    $psdp->xflgs = -1;
                    $psdp->iephe = SweConst::SEFLG_JPLEPH;
                }
                if ($retc != SweConst::OK) {
                    $this->parent->getSwePhp()->sweJPL->swi_close_jpl_file();
                    $this->parent->getSwePhp()->swed->jpl_file_is_open = false;
                    return $retc;
                }
            } else {
                $xps = $psdp->x;
            }
            if (isset($xpsret))
                for ($i = 0; $i <= 5; $i++)
                    $xpret[$i] = $xps[$i];
        }
        // earth is wanted
        if ($ipli == SweConst::SEI_EARTH) {
            for ($i = 0; $i <= 5; $i++)
                $xp[$i] = $xpe[$i];
        }
        // sunbary is wanted
        if ($ipli == SweConst::SEI_SUNBARY) {
            for ($i = 0; $i <= 5; $i++)
                $xp[$i] = $xps[$i];
        } else {
            // other planet
            // if planet already computed
            if ($tjd == $pdp->teval && $pdp->iephe == SweConst::SEFLG_JPLEPH) {
                $xp = $pdp->x;
            } else {
                $retc = $this->parent->getSwePhp()->sweJPL->swi_pleph($tjd, self::pnoint2jpl[$ipli], $ictr, $xp, $serr);
                if ($do_save) {
                    $pdp->teval = $tjd;
                    $pdp->xflgs = -1;
                    $pdp->iephe = SweConst::SEFLG_JPLEPH;
                }
                if ($retc != SweConst::OK) {
                    $this->parent->getSwePhp()->sweJPL->swi_close_jpl_file();
                    $this->parent->getSwePhp()->swed->jpl_file_is_open = false;
                    return $retc;
                }
            }
        }
        if (isset($xpret))
            for ($i = 0; $i <= 5; $i++)
                $xpret[$i] = $xp[$i];
        return SweConst::OK;
    }

    /*
     * this function looks for an ephemeris file,
     * opens it, if not yet open,
     * reads constants, if not yet read,
     * computes a planet, if not yet computed
     * attention: asteroids are heliocentric
     *            other planets barycentric
     *
     * tjd 		julian date
     * ipli		SEI_ planet number
     * ifno		ephemeris file number
     * xsunb	INPUT (!) array of 6 doubles containing barycentric sun
     *              (must be given with asteroids)
     * do_save	boolean: save result in save area
     * xp		return array of 6 doubles for planet's position
     * serr		error string
     */
    function sweph(float $tjd, int $ipli, int $ifno, int $iflag, ?array $xsunb,
                   bool  $do_save, ?array &$xpret = null, ?string &$serr = null): int
    {
        $fname = '';
        $xx = [];
        $pedp =& $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_EARTH];
        $psdp =& $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_SUNBARY];
        $fdp =& $this->parent->getSwePhp()->swed->fidat[$ifno];
        $ipl = $ipli;
        if ($ipli > Sweph::SE_AST_OFFSET)
            $ipl = SweConst::SEI_ANYBODY;
        if ($ipli > Sweph::SE_PLMOON_OFFSET)
            $ipl = SweConst::SEI_ANYBODY;
        $pdp =& $this->parent->getSwePhp()->swed->pldat[$ipl];
        if ($do_save) {
            $xp = $pdp->x;
        } else {
            $xp = $xx;
        }
        // if planet has already been computed for this date, return.
        // if speed flag has been turned on, recompute planet
        $speedf1 = $pdp->xflgs & SweConst::SEFLG_SPEED;
        $speedf2 = $iflag & SweConst::SEFLG_SPEED;
        if ($tjd == $pdp->teval &&
            $pdp->iephe == SweConst::SEFLG_SWIEPH &&
            (!$speedf2 || $speedf1) &&
            $ipl < SweConst::SEI_ANYBODY) {
            if (isset($xpret))
                for ($i = 0; $i <= 5; $i++)
                    $xpret[$i] = $pdp->x[$i];
            return SweConst::OK;
        }
        /******************************
         * get correct ephemeris file *
         ******************************/
        if ($fdp->fptr != null) {
            // if tjd is beyond file range, close old file.
            // if new asteroid, close old file.
            if ($tjd < $fdp->tfstart || $tjd > $fdp->tfend ||
                ($ipl == SweConst::SEI_ANYBODY && $ipli != $pdp->ibdy)) {
                fclose($fdp->fptr);
                $fdp->fptr = null;
                if ($pdp->refep != null)
                    unset($pdp->refep);
                $pdp->refep = null;
                if ($pdp->segp != null)
                    unset($pdp->segp);
                $pdp->segp = [];
            }
        }
        // if sweph file not open, find and open it
        if ($fdp->fptr == null) {
            $this->parent->getSwePhp()->swephLib->swi_gen_filename($tjd, $ipli, $fname);
            $subdirnam = $fname;
            $sp = strrchr($subdirnam, DIRECTORY_SEPARATOR);
            if ($sp != null) {
                $sp = '';
                $subdirlen = strlen($subdirnam);
            } else {
                $subdirlen = 0;
            }
            $s = $fname;
            again:
            $fdp->fptr = $this->parent->swi_fopen($ifno, $s, $this->parent->getSwePhp()->swed->ephepath, $serr);
            if ($fdp->fptr != null) {
                // if it is a planetary moon, also try without the direction "sat/"
                if ($ipli > Sweph::SE_PLMOON_OFFSET && $ipli < Sweph::SE_AST_OFFSET) {
                    if ($subdirlen > 0 && strncmp($s, $subdirnam, $subdirlen) == 0) {
                        $s = substr($s, $subdirlen + 1);
                        goto again;
                    }
                    //
                    // if it is a numbered asteroid file, try also for short files (..s.se1)
                    // On the second try, the inserted 's' will be seen and not tried again.
                    //
                } else if ($ipli > Sweph::SE_AST_OFFSET) {
                    $spp = strchr($s, '.');
                    if ($spp > $s && $spp[strlen($spp) - 1] != 's') { // no 's' before '.' ?
                        $spp = sprintf("s.%s", SweConst::SE_FILE_SUFFIX);
                        goto again;
                    }
                    //
                    // if we still have 'ast0' etc. in front of the filename,
                    // we remove it now, remove the 's' also,
                    // and try in the main ephemeris directory instead of the
                    // asteroid subdirectory.
                    //
                    $spp = substr($spp, 0, strlen($spp) - 1);
                    $spp = substr($spp, 1);
                    if ($subdirlen > 0 && strncmp($s, $subdirnam, $subdirlen) == 0) {
                        $s = substr($s, $subdirlen + 1); // remove "ast0/" etc.
                        goto again;
                    }
                }
                return SweConst::NOT_AVAILABLE;
            }
            // during the search error messages may have been built, delete them
            if (isset($serr)) $serr = "";
            $retc = $this->read_const($ifno, $serr);
            if ($retc != SweConst::OK)
                return $retc;
        }
        // if first ephemeris file (J-3000), it might start a mars period
        // after -3000. if last ephemeris file (J3000), it might end a
        // 4000-day-period before 3000.
        if ($tjd < $fdp->tfstart || $tjd > $fdp->tfend) {
            if (isset($serr)) {
                $sp = strrchr($fname, DIRECTORY_SEPARATOR);
                if ($sp != null) {
                    $sp = substr($s, 1);
                } else {
                    $sp = $fname;
                }
                if ($ipli > Sweph::SE_AST_OFFSET) {
                    $s = sprintf("asteroid No. %d (%s): ", $ipl - Sweph::SE_AST_OFFSET, $sp);
                } else if ($ipli > Sweph::SE_PLMOON_OFFSET) {
                    if (strstr($fname, "99.") != null)
                        $s = sprintf("plan. COB No. %d (%s): ", $ipli, $sp);
                    else
                        $s = sprintf("plan. moon No. %d (%s): ", $ipli, $sp);
                } else if ($ipli > SweConst::SEI_PLUTO) {
                    $s = sprintf("asteroid eph. file (%s): ", $sp);
                } else if ($ipli != SweConst::SEI_MOON) {
                    $s = sprintf("planets eph. file (%s): ", $sp);
                } else {
                    $s = sprintf("moon eph. file (%s): ", $sp);
                }
                if ($tjd < $fdp->tfstart) {
                    $s .= sprintf("jd %f < lower limit %f;", $tjd, $fdp->tfstart);
                } else {
                    $s .= sprintf("jd %f > upper limit %f;", $tjd, $fdp->tfend);
                }
            }
            return SweConst::NOT_AVAILABLE;
        }
        /******************************
         * get planet's position
         ******************************/
        // get new segment, if necessary
        if ($pdp->segp == null || $tjd < $pdp->tseg0 || $tjd > $pdp->tseg1) {
            $retc = $this->get_new_segment($tjd, $ipl, $ifno, $serr);
            if ($retc != SweConst::OK)
                return $retc;
            // rotate cheby coeffs back to equatorial system.
            // if necessary, add reference orbit.
            if ($pdp->iflg & SweConst::SEI_FLG_ROTATE) {
                $this->rot_back($ipl);
            } else {
                $pdp->neval = $pdp->ncoe;
            }
        }
        // evaluate chebyshev polynomial for tjd
        $t = ($tjd - $pdp->tseg0) / $pdp->dseg;
        $t = $t * 2 - 1;
        // speed is needed, if
        // 1. true position is being computed before applying light-time etc.
        //    this is the position saved in pdp->x.
        //    in this case, speed is needed for light-time correction.
        // 2. the speed flag has been specified.
        //
        $need_speed = ($do_save || ($ifno & SweConst::SEFLG_SPEED));
        for ($i = 0; $i <= 2; $i++) {
            $xp[$i] = $this->parent->getSwePhp()->swephLib->swi_echeb($t,
                array_slice($pdp->segp, $i * $pdp->ncoe, $pdp->neval), $pdp->neval);
            if ($need_speed) {
                $xp[$i + 3] = $this->parent->getSwePhp()->swephLib->swi_edcheb($t,
                        array_slice($pdp->segp, $i * $pdp->ncoe, $pdp->neval),
                        $pdp->neval) / $pdp->dseg * 2;
            } else {
                $xp[$i + 3] = 0;        // vol Alois als billiger fix, evtl. illegal
            }
        }
        // if planet wanted in barycentric sun:
        // current sepl* files have do not have barycentric sun,
        // but have heliocentric earth and barycentric earth.
        // So barycentric sun and must be computed
        // from heliocentric earth and barycentric earth: the
        // computation above gives heliocentric earth, therefore we
        // have to compute barycentric earth and subtract heliocentric
        // earth from it. this may be necessary with calls from
        // sweplan() and from app_pos_etc_sun() (light-time).
        if ($ipl == SweConst::SEI_SUNBARY && ($pdp->iflg & SweConst::SEI_FLG_EMBHEL)) {
            // sweph() calls sweph() !!! for EMB.
            // Attention: a new calculation must be forced in any case.
            // Otherwise EARTH (instead of EMB) will possibly takes from
            // save area.
            // to force new computation, set pedp->teval = 0 and restore it
            // after call of sweph(EMB).
            $tsv = $pedp->teval;
            $pedp->teval = 0;
            $retc = $this->sweph($tjd, SweConst::SEI_EMB, $ifno, $iflag | SweConst::SEFLG_SPEED, null, false, $xemb, $serr);
            if ($retc != SweConst::OK)
                return $retc;
            $pedp->teval = $tsv;
            for ($i = 0; $i <= 2; $i++)
                $xp[$i] = $xemb[$i] - $xp[$i];
            if ($need_speed)
                for ($i = 3; $i <= 5; $i++)
                    $xp[$i] = $xemb[$i] - $xp[$i];
        }
        // asteroids are heliocentric.
        // if JPL or SWISSEPH, convert to barycentric
        if (isset($xsunb) != null && (($iflag & SweConst::SEFLG_JPLEPH) || ($iflag & SweConst::SEFLG_SWIEPH))) {
            if ($ipl >= SweConst::SEI_ANYBODY) {
                for ($i = 0; $i <= 2; $i++)
                    $xp[$i] += $xsunb[$i];
                if ($need_speed)
                    for ($i = 3; $i <= 5; $i++)
                        $xp[$i] += $xsunb[$i];
            }
        }
        if ($do_save) {
            $pdp->teval = $tjd;
            $pdp->xflgs = -1;       // do new computation of light-time etc.
            if ($ifno == SweConst::SEI_FILE_PLANET || $ifno == SweConst::SEI_FILE_MOON) {
                $pdp->iephe = SweConst::SEFLG_SWIEPH;
            } else {
                $pdp->iephe = $psdp->iephe;
            }
        }
        if (isset($xpret))
            for ($i = 0; $i <= 5; $i++)
                $xpret[$i] = $xp[$i];
        return SweConst::OK;
    }

    function calc_center_body(int $ipli, int $iflag, array &$xx, array $xcom, ?string &$serr = null): int
    {
        if (!($iflag & SweConst::SEFLG_CENTER_BODY))
            return SweConst::OK;
        if ($ipli < SweConst::SEI_MARS || $ipli > SweConst::SEI_PLUTO)
            return SweConst::OK;
        for ($i = 0; $i <= 5; $i++)
            $xx[$i] += $xcom[$i];
        return SweConst::OK;
    }

    /* converts planets from barycentric to geocentric,
     * apparent positions
     * precession and nutation
     * according to flags
     * ipli		planet number
     * iflag	flags
     * serr         error string
     */
    function app_pos_etc_plan(int $ipli, int $iplmoon, int $iflag, ?string &$serr = null): int
    {
        $retc = SweConst::OK;
        $xobs = [];
        $xobs2 = [];
        $xearth = [];
        $xsun = [];
        $xcom = [];
        $xxsp = [];
        $xxsv = [];
        $pedp =& $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_EARTH];
        $oe =& $this->parent->getSwePhp()->swed->oec2000;
        $epheflag = $iflag & Sweph::SEFLG_EPHMASK;
        $dtsave_for_defl = 0.;
        // ephemeris file
        if ($ipli > Sweph::SE_PLMOON_OFFSET || $ipli > Sweph::SE_AST_OFFSET) { // 2nd condition obsolete
            $ifno = SweConst::SEI_FILE_ANY_AST;
            $ibody = self::IS_ANY_BODY;
            $pdp =& $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_ANYBODY];
        } else if ($ipli == SweConst::SEI_CHIRON ||
            $ipli == SweConst::SEI_PHOLUS ||
            $ipli == SweConst::SEI_CERES ||
            $ipli == SweConst::SEI_PALLAS ||
            $ipli == SweConst::SEI_JUNO ||
            $ipli == SweConst::SEI_VESTA) {
            $ifno = SweConst::SEI_FILE_MAIN_AST;
            $ibody = self::IS_MAIN_ASTEROID;
            $pdp =& $this->parent->getSwePhp()->swed->pldat[$ipli];
        } else {
            $ifno = SweConst::SEI_FILE_PLANET;
            $ibody = self::IS_PLANET;
            $pdp =& $this->parent->getSwePhp()->swed->pldat[$ipli];
        }
        $t = $pdp->teval;
        // if the same conversions have already been done for the same
        // date, then return
        $flg1 = $iflag & ~SweConst::SEFLG_EQUATORIAL & ~SweConst::SEFLG_XYZ;
        $flg2 = $pdp->xflgs & ~SweConst::SEFLG_EQUATORIAL & ~SweConst::SEFLG_XYZ;
        if ($flg1 == $flg2) {
            $pdp->xflgs = $iflag;
            $pdp->xflgs = $iflag & Sweph::SEFLG_EPHMASK;
            return SweConst::OK;
        }
        // the conversions will be done with xx[].
        for ($i = 0; $i <= 5; $i++)
            $xx[$i] = $pdp->x[$i];
        // center body of planet, if SEFLG_CENTER_BODY (which is checked inside function)
        $this->calc_center_body($ipli, $iflag, $xx,
            $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_ANYBODY]->x, $serr);
        for ($i = 0; $i <= 5; $i++)
            $xx0[$i] = $xx[$i];
        // if heliocentric position is wanted
        if ($iflag & SweConst::SEFLG_HELCTR) {
            if ($pdp->iephe == SweConst::SEFLG_JPLEPH || $pdp->iephe == SweConst::SEFLG_SWIEPH)
                for ($i = 0; $i <= 5; $i++)
                    $xx[$i] -= $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_SUNBARY]->x[$i];
        }
        /************************************
         * observer: geocenter or topocenter
         ************************************/
        // if topocentric position is wanted
        if ($iflag & SweConst::SEFLG_TOPOCTR) {
            if ($this->parent->getSwePhp()->swed->topd->teval != $pedp->teval ||
                $this->parent->getSwePhp()->swed->topd->teval == 0) {
                if ($this->swi_get_observer($pedp->teval, $iflag | SweConst::SEFLG_NONUT, true, $xobs, $serr) != SweConst::OK)
                    return SweConst::ERR;
            } else {
                for ($i = 0; $i <= 5; $i++)
                    $xobs[$i] = $this->parent->getSwePhp()->swed->topd->xobs[$i];
            }
            // barycentric position of observer
            for ($i = 0; $i <= 5; $i++)
                $xobs[$i] = $xobs[$i] + $pedp->x[$i];
        } else {
            // barycentric position of geocenter
            for ($i = 0; $i <= 5; $i++)
                $xobs[$i] = $pedp->x[$i];
        }
        /*******************************
         * light-time geocentric       *
         *******************************/
        if (!($iflag & SweConst::SEFLG_TRUEPOS)) {
            // number of iterations - 1
            if ($pdp->iephe == SweConst::SEFLG_JPLEPH || $pdp->iephe == SweConst::SEFLG_SWIEPH) {
                $niter = 1;     // SEFLG_MOSEPH or planet from osculating elements
            } else {
                $niter = 0;
            }
            if ($iflag & SweConst::SEFLG_SPEED) {
                //
                // Apparent speed if influenced by the fact that dt changes with
                // time. This makes a difference of several hundreds of an
                // arc second / day. To take this into account, we compute
                // 1. true position - apparent position at time t - 1.
                // 2. true position - apparent position at time t.
                // 3. the different between the two is the part of the daily motion
                // that results from the change of dt.
                //
                for ($i = 0; $i <= 2; $i++)
                    $xxsv[$i] = $xxsp[$i] = $xx[$i] - $xx[$i + 3];
                for ($j = 0; $j <= $niter; $j++) {
                    for ($i = 0; $i <= 2; $i++) {
                        $dx[$i] = $xxsp[$i];
                        if (!($iflag & SweConst::SEFLG_HELCTR) && !($iflag && SweConst::SEFLG_BARYCTR))
                            $dx[$i] -= ($xobs[$i] - $xobs[$i + 3]);
                    }
                    // new dt
                    $dt = sqrt(Sweph::square_sum($dx)) * Sweph::AUNIT / Sweph::CLIGHT / 86400.0;
                    for ($i = 0; $i <= 2; $i++) {       // rough apparent position at t-1
                        $xxsp[$i] = $xxsv[$i] - $dt * $xx0[$i + 3];
                    }
                }
                // true position - apparent position at time t-1
                for ($i = 0; $i <= 2; $i++)
                    $xxsp[$i] = $xxsv[$i] - $xxsp[$i];
            }
            // dt and t(apparent)
            for ($j = 0; $j <= $niter; $j++) {
                for ($i = 0; $i <= 2; $i++) {
                    $dx[$i] = $xx[$i];
                    if (!($iflag & SweConst::SEFLG_HELCTR) && !($iflag & SweConst::SEFLG_BARYCTR))
                        $dx[$i] -= $xobs[$i];
                }
                $dt = sqrt(Sweph::square_sum($dx)) * Sweph::AUNIT / Sweph::CLIGHT / 86400.0;
                // new t
                $t = $pdp->teval - $dt;
                $dtsave_for_defl = $dt;
                for ($i = 0; $i <= 2; $i++) {           // rough apparent position at t
                    $xx[$i] = $xx0[$i] - $dt * $xx0[$i + 3];
                }
            }
            // part of daily motion resulting from change of dt
            if ($iflag & SweConst::SEFLG_SPEED) {
                for ($i = 0; $i <= 2; $i++) {
                    $xxsp[$i] = $xx0[$i] - $xx[$i] - $xxsp[$i];
                }
            }
            // new position, accounting for light-time (accurate)
            if (($iflag & SweConst::SEFLG_CENTER_BODY) &&
                $ipli >= SwePlanet::MARS->value && $ipli <= SwePlanet::PLUTO->value) {
                // jupiter center of body, relative to jupiter barycenter
                $retc = $this->sweph($t, $iplmoon, SweConst::SEI_FILE_ANY_AST, $iflag, null,
                    false, $xcom, $serr);
                if ($retc == SweConst::ERR || $retc == SweConst::NOT_AVAILABLE)
                    return SweConst::ERR;
            }
            switch ($epheflag) {
                case SweConst::SEFLG_JPLEPH:
                    if ($ibody >= self::IS_ANY_BODY)
                        $ipl = -1;  // will not be used
                    else
                        $ipl = self::pnoint2jpl[$ipli];
                    if ($ibody == self::IS_PLANET) {
                        $retc = $this->parent->getSwePhp()->sweJPL->swi_pleph($t, $ipl, SweJPL::J_SBARY, $xx, $serr);
                        if ($retc != SweConst::OK) {
                            $this->parent->getSwePhp()->sweJPL->swi_close_jpl_file();
                            $this->parent->getSwePhp()->swed->jpl_file_is_open = false;
                        }
                    } else {        // asteroid
                        // first sun
                        $retc = $this->parent->getSwePhp()->sweJPL->swi_pleph($t, SweJPL::J_SUN, SweJPL::J_SBARY, $xsun, $serr);
                        if ($retc != SweConst::OK) {
                            $this->parent->getSwePhp()->sweJPL->swi_close_jpl_file();
                            $this->parent->getSwePhp()->swed->jpl_file_is_open = false;
                        }
                        // asteroid
                        $retc = $this->sweph($t, $ipli, $ifno, $iflag, $xsun, false, $xx, $serr);
                    }
                    if ($retc != SweConst::OK)
                        return $retc;
                    // for accuracy in speed, we need earth as well
                    if (($iflag & SweConst::SEFLG_SPEED) &&
                        !($iflag & SweConst::SEFLG_HELCTR) && !($iflag & SweConst::SEFLG_BARYCTR)) {
                        $retc = $this->parent->getSwePhp()->sweJPL->swi_pleph($t, SweJPL::J_EARTH, SweJPL::J_SBARY, $xearth, $serr);
                        if ($retc != SweConst::OK) {
                            $this->parent->getSwePhp()->sweJPL->swi_close_jpl_file();
                            $this->parent->getSwePhp()->swed->jpl_file_is_open = false;
                            return $retc;
                        }
                    }
                    break;
                case SweConst::SEFLG_SWIEPH:
                    if ($ibody == self::IS_PLANET) {
                        $retc = $this->sweplan($t, $ipli, $ifno, $iflag, false, $xx, $xearth, $xsun, serr: $serr);
                    } else {        // asteroid
                        $retc = $this->sweplan($t, SweConst::SEI_EARTH, SweConst::SEI_FILE_PLANET, $iflag, false, $xearth, xpsret: $xsun, serr: $serr);
                        if ($retc == SweConst::OK)
                            $retc = $this->sweph($t, $ipli, $ifno, $iflag, $xsun, false, $xx, $serr);
                    }
                    if ($retc != SweConst::OK)
                        return $retc;
                    break;
                case SweConst::SEFLG_MOSEPH:
                default:
                    //
                    // With Moshier or other ephemerides, subtraction of dt * speed
                    // is sufficient (has been done in light-time iteration above)
                    //
                    // if speed flag is true, we call swi_moshplan() for new t.
                    // this does not increase position precision,
                    // but speed precision, which becomes betters than 0.01"/day.
                    // for precise speed, we need earth as well.
                    //
                    if ($iflag & SweConst::SEFLG_SPEED &&
                        !($iflag & (SweConst::SEFLG_HELCTR | SweConst::SEFLG_BARYCTR))) {
                        if ($ibody == self::IS_PLANET) {
                            $retc = $this->parent->getSwePhp()->sweMPlan->swi_moshplan($t, $ipli, false, $xxsv, $xearth, $serr);
                        } else {
                            $retc = $this->sweph($t, $ipli, $ifno, $iflag, null, false, $xxsv, $serr);
                            if ($retc == SweConst::OK)
                                $retc = $this->parent->getSwePhp()->sweMPlan->swi_moshplan($t, SweConst::SEI_EARTH, false, $xearth, $xearth, $serr);
                        }
                        if ($retc != SweConst::OK)
                            return $retc;
                        // only speed is taken from this computation, otherwise position
                        // calculations with and without speed would not agree. The difference
                        // would be about 0.01", which is far below the intrinsic error of the
                        // moshier ephemeris.
                        //
                        for ($i = 3; $i <= 5; $i++)
                            $xx[$i] = $xxsv[$i];
                    }
                    break;
            }
            $this->calc_center_body($ipli, $iflag, $xx, $xcom, $serr);
            if ($iflag & SweConst::SEFLG_HELCTR) {
                if ($pdp->iephe == SweConst::SEFLG_JPLEPH || $pdp->iephe == SweConst::SEFLG_SWIEPH)
                    for ($i = 0; $i <= 5; $i++)
                        $xx[$i] -= $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_SUNBARY]->x[$i];
            }
            if ($iflag & SweConst::SEFLG_SPEED) {
                // observer position for t(light-time)
                if ($iflag & SweConst::SEFLG_TOPOCTR) {
                    if ($this->swi_get_observer($t, $iflag | SweConst::SEFLG_NONUT, false, $xobs2, $serr) != SweConst::OK)
                        return SweConst::ERR;
                    for ($i = 0; $i <= 5; $i++)
                        $xobs2[$i] += $xearth[$i];
                } else {
                    for ($i = 0; $i <= 5; $i++)
                        $xobs2[$i] = $xearth[$i];
                }
            }
        }
        /*******************************
         * conversion to geocenter     *
         *******************************/
        if (!($iflag & SweConst::SEFLG_HELCTR) && !($iflag & SweConst::SEFLG_BARYCTR)) {
            // subtract earth
            for ($i = 0; $i <= 5; $i++)
                $xx[$i] -= $xobs[$i];
            if (($iflag & SweConst::SEFLG_TRUEPOS) == 0) {
                //
                // Apparent speed is also influenced by
                // the change of dt during motion.
                // Neglect of this would result in an error of several 0.01"
                //
                if ($iflag & SweConst::SEFLG_SPEED)
                    for ($i = 3; $i <= 5; $i++)
                        $xx[$i] -= $xxsp[$i - 3];
            }
        }
        if (!($iflag & SweConst::SEFLG_SPEED))
            for ($i = 3; $i <= 5; $i++)
                $xx[$i] = 0;
        /************************************
         * relativistic deflection of light *
         ************************************/
        if (!($iflag & SweConst::SEFLG_TRUEPOS) && !($iflag & SweConst::SEFLG_NOGDEFL))
            $this->swi_deflect_light($xx, $dtsave_for_defl, $iflag);
        /**********************************
         * 'annual' aberration of light   *
         **********************************/
        if (!($iflag & SweConst::SEFLG_TRUEPOS) && !($iflag & SweConst::SEFLG_NOABERR)) {
            $this->swi_aberr_light($xx, $xobs, $iflag);
            //
            // Apparent speed is also influenced by
            // the different of speed of the earth between t and t-dt.
            // Neglecting this would involve an error of several 0.1"
            //
            if ($iflag & SweConst::SEFLG_SPEED) {
                for ($i = 3; $i <= 5; $i++)
                    $xx[$i] += $xobs[$i] - $xobs2[$i];
            }
        }
        if (!($iflag & SweConst::SEFLG_SPEED))
            for ($i = 3; $i <= 5; $i++)
                $xx[$i] = 0;
        // ICRS to J2000
        if (!($iflag & SweConst::SEFLG_ICRS) && $this->parent->swi_get_denum($ipli, $epheflag) >= 403)
            $this->parent->getSwePhp()->swephLib->swi_bias($xx, $t, $iflag, false);
        // save J2000 coordinates; required for sidereal positions
        for ($i = 0; $i <= 5; $i++)
            $xxsv[$i] = $xx[$i];
        /************************************************
         * precession, equator 2000 -> equator of date *
         ************************************************/
        if (!($iflag & SweConst::SEFLG_J2000)) {
            $this->parent->getSwePhp()->swephLib->swi_precess($xx, $pdp->teval, $iflag, SweConst::J2000_TO_J);
            if ($iflag & SweConst::SEFLG_SPEED)
                $this->swi_precess_speed($xx, $pdp->teval, $iflag, SweConst::J2000_TO_J);
            $oe =& $this->parent->getSwePhp()->swed->oec;
        } else {
            $oe =& $this->parent->getSwePhp()->swed->oec2000;
        }
        return $this->app_pos_rest($pdp, $iflag, $xx, $xxsv, $oe, $serr);
    }

    function app_pos_rest(plan_data $pdp, int $iflag, array &$xx, array &$x2000, epsilon $oe, ?string &$serr = null): int
    {
        $daya = [];
        /************************************************
         * nutation                                     *
         ************************************************/
        if (!($iflag & SweConst::SEFLG_NONUT))
            $this->swi_nutate($xx, $iflag, false);
        // now we have equatorial cartesian coordinates; save them
        for ($i = 0; $i <= 5; $i++)
            $pdp->xreturn[18 + $i] = $xx[$i];
        /************************************************
         * transformation to ecliptic.                  *
         * with sidereal calc. this will be overwritten *
         * afterwards.                                  *
         ************************************************/
        SwephCotransUtils::swi_coortrf2($xx, $xx, $oe->seps, $oe->ceps);
        if ($iflag & SweConst::SEFLG_SPEED)
            SwephCotransUtils::swi_coortrf2_ptr($xx, 3, $xx, 3, $oe->seps, $oe->ceps);
        if (!($iflag & SweConst::SEFLG_NONUT)) {
            SwephCotransUtils::swi_coortrf2($xx, $xx,
                $this->parent->getSwePhp()->swed->nut->snut,
                $this->parent->getSwePhp()->swed->nut->cnut);
            if ($iflag & SweConst::SEFLG_SPEED)
                SwephCotransUtils::swi_coortrf2_ptr($xx, 3, $xx, 3,
                    $this->parent->getSwePhp()->swed->nut->snut,
                    $this->parent->getSwePhp()->swed->nut->cnut);
        }
        // now we have ecliptic cartesian coordinates
        for ($i = 0; $i <= 5; $i++)
            $pdp->xreturn[6 + $i] = $xx[$i];
        /************************************
         * sidereal positions               *
         ************************************/
        if ($iflag & SweConst::SEFLG_SIDEREAL) {
            // project onto ecliptic t0
            if ($this->parent->getSwePhp()->swed->sidd->sid_mode & SweConst::SE_SIDBIT_ECL_T0) {
                if (PointerUtils::pointer2Fn($pdp->xreturn, $pdp->xreturn, 6, 18,
                        fn(&$lonx1, &$lonx2) => $this->swi_trop_ra2sid_lon($x2000, $lonx1, $lonx2, $iflag)) != SweConst::OK)
                    return SweConst::ERR;
                // project onto solar system equator
            } else if ($this->parent->getSwePhp()->swed->sidd->sid_mode & SweConst::SE_SIDBIT_SSY_PLANE) {
                if (PointerUtils::pointerFn($pdp->xreturn, 6,
                        fn(&$lonx1) => $this->swi_trop_ra2sid_lon_sosy($x2000, $lonx1, $iflag)) != SweConst::OK)
                    return SweConst::ERR;
            } else {
                // traditional algorithm
                SwephCotransUtils::swi_cartpol_sp_ptr($pdp->xreturn, 6, $pdp->xreturn, 0);
                // note, swi_get_ayanamsa_ex() disturbs present calculation, if sun is calculated with
                // TRUE_CHITRA ayanamsha, because the ayanamsha also calculates the sun.
                // Therefore current values are saved...
                for ($i = 0; $i < 24; $i++)
                    $xxsv[$i] = $pdp->xreturn[$i];
                if ($this->swi_get_ayanamsa_with_speed($pdp->teval, $iflag, $daya, $serr) == SweConst::ERR)
                    return SweConst::ERR;
                // ... and restored
                for ($i = 0; $i < 24; $i++)
                    $pdp->xreturn[$i] = $xxsv[$i];
                $pdp->xreturn[0] -= $daya[0] * SweConst::DEGTORAD;
                $pdp->xreturn[3] -= $daya[1] * SweConst::DEGTORAD;
                SwephCotransUtils::swi_polcart_sp_ptr($pdp->xreturn, 0, $pdp->xreturn, 6);
            }
        }
        /************************************************
         * transformation to polar coordinates          *
         ************************************************/
        SwephCotransUtils::swi_cartpol_sp_ptr($pdp->xreturn, 18, $pdp->xreturn, 12);
        SwephCotransUtils::swi_cartpol_sp_ptr($pdp->xreturn, 6, $pdp->xreturn, 0);
        /**********************
         * radians to degrees *
         **********************/
        for ($i = 0; $i < 2; $i++) {
            $pdp->xreturn[$i] *= SweConst::RADTODEG;        // ecliptic
            $pdp->xreturn[$i + 3] *= SweConst::RADTODEG;
            $pdp->xreturn[$i + 12] *= SweConst::RADTODEG;   // equator
            $pdp->xreturn[$i + 15] *= SweConst::RADTODEG;
        }
        // save, what has been done
        $pdp->xflgs = $iflag;
        $pdp->iephe = $iflag & Sweph::SEFLG_EPHMASK;
        return SweConst::OK;
    }

    function swi_get_ayanamsa_ex(float $tjd_et, int $iflag, float &$daya, ?string &$serr = null): int
    {
        $x = [];
        $corr = 0.;
        $sip =& $this->parent->getSwePhp()->swed->sidd;
        $sid_mode = $sip->sid_mode;
        $iflag = $this->plaus_iflag($iflag, -1, $tjd_et, $serr);
        $epheflag = $iflag & Sweph::SEFLG_EPHMASK;
        $otherflag = $iflag & ~Sweph::SEFLG_EPHMASK;
        $daya = 0.0;
        $iflag &= Sweph::SEFLG_EPHMASK;
        $iflag |= SweConst::SEFLG_NONUT;
        $sid_mode %= SweConst::SE_SIDBITS;
        // ayanamshas based on the intersection point of galactic equator and
        // ecliptic always need SEFLG_TRUEPOS, because position of galactic
        // pole is required without aberration or light deflection
        $iflag_galequ = $iflag | SweConst::SEFLG_TRUEPOS;
        // _TRUE_ ayanamshas can have the following SEFLG_s;
        // The star will have the intended fixed position even if these flags are
        // provided
        $iflag_true = $iflag;
        if ($otherflag & SweConst::SEFLG_TRUEPOS) $iflag_true |= SweConst::SEFLG_TRUEPOS;
        if ($otherflag & SweConst::SEFLG_NOABERR) $iflag_true |= SweConst::SEFLG_NOABERR;
        if ($otherflag & SweConst::SEFLG_NOGDEFL) $iflag_true |= SweConst::SEFLG_NOGDEFL;
        // warning, if swe_set_ephe_path() or swe_set_jplfile() was not called yet,
        // although ephemeris files are required
        if ($this->parent->swi_init_swed_if_start() == 1 && !($epheflag & SweConst::SEFLG_MOSEPH) &&
            ($sid_mode == SweSiderealMode::SE_SIDM_TRUE_CITRA->value ||
                $sid_mode == SweSiderealMode::SE_SIDM_TRUE_REVATI->value ||
                $sid_mode == SweSiderealMode::SE_SIDM_TRUE_PUSHYA->value ||
                $sip->sid_mode == SweSiderealMode::SE_SIDM_TRUE_SHEORAN->value ||
                $sid_mode == SweSiderealMode::SE_SIDM_TRUE_MULA->value ||
                $sid_mode == SweSiderealMode::SE_SIDM_GALCENT_0SAG->value ||
                $sid_mode == SweSiderealMode::SE_SIDM_GALCENT_COCHRANE->value ||
                $sid_mode == SweSiderealMode::SE_SIDM_GALCENT_RGILBRAND->value ||
                $sid_mode == SweSiderealMode::SE_SIDM_GALCENT_MULA_WILHELM->value ||
                $sid_mode == SweSiderealMode::SE_SIDM_GALEQU_IAU1958->value ||
                $sid_mode == SweSiderealMode::SE_SIDM_GALEQU_TRUE->value ||
                $sid_mode == SweSiderealMode::SE_SIDM_GALEQU_MULA->value) && isset($serr)) {
            $serr = "Please call swe_set_ephe_path() or swe_set_jplfile() before calling swe_get_ayanamsa_ex()";
        }
        if (!$this->parent->getSwePhp()->swed->ayana_is_set)
            $this->parent->swe_set_sid_mode(SweSiderealMode::SIDM_FAGAN_BRADLEY->value, 0, 0);
        if ($sid_mode == SweSiderealMode::SE_SIDM_TRUE_CITRA->value) {
            $star = "Spica"; // Citra
            if (($retflag = $this->swe_fixstar($star, $tjd_et, $iflag_true, $x, $serr)) == SweConst::ERR) {
                return SweConst::ERR;
            }
            $daya = SwephLib::swe_degnorm($x[0] - 180);
            return ($retflag & Sweph::SEFLG_EPHMASK);
        }
        if ($sid_mode == SweSiderealMode::SE_SIDM_TRUE_REVATI->value) {
            $star = ",zePsc"; // Revati
            if (($retflag = $this->swe_fixstar($star, $tjd_et, $iflag_true, $x, $serr)) == SweConst::ERR)
                return SweConst::ERR;
            $daya = SwephLib::swe_degnorm($x[0] - 359.8333333333);
            return ($retflag & Sweph::SEFLG_EPHMASK);
        }
        if ($sid_mode == SweSiderealMode::SE_SIDM_TRUE_PUSHYA->value) {
            $star = ",deCnc"; // Pushya = Asellus Australis
            if (($retflag = $this->swe_fixstar($star, $tjd_et, $iflag_true, $x, $serr)) == SweConst::ERR)
                return SweConst::ERR;
            $daya = SwephLib::swe_degnorm($x[0] - 106);
            return ($retflag & Sweph::SEFLG_EPHMASK);
        }
        if ($sid_mode == SweSiderealMode::SE_SIDM_TRUE_SHEORAN->value) {
            $star = ",deCnc"; // Asellus Australis
            if (($retflag = $this->swe_fixstar($star, $tjd_et, $iflag_true, $x, $serr)) == SweConst::ERR)
                return SweConst::ERR;
            $daya = SwephLib::swe_degnorm($x[0] - 103.49264221625);
            return ($retflag & Sweph::SEFLG_EPHMASK);
        }
        if ($sid_mode == SweSiderealMode::SE_SIDM_TRUE_MULA->value) {
            $star = ",laSco"; // Mula = lambda Scorpionis
            if (($retflag = $this->swe_fixstar($star, $tjd_et, $iflag_true, $x, $serr)) == SweConst::ERR)
                return SweConst::ERR;
            $daya = SwephLib::swe_degnorm($x[0] - 240);
            return ($retflag & Sweph::SEFLG_EPHMASK);
        }
        if ($sid_mode == SweSiderealMode::SE_SIDM_GALCENT_0SAG->value) {
            $star = ",SgrA*"; // Galactic Centre
            if (($retflag = $this->swe_fixstar($star, $tjd_et, $iflag_true, $x, $serr)) == SweConst::ERR)
                return SweConst::ERR;
            $daya = SwephLib::swe_degnorm($x[0] - 240.0);
            return ($retflag & Sweph::SEFLG_EPHMASK);
        }
        if ($sid_mode == SweSiderealMode::SE_SIDM_GALCENT_COCHRANE->value) {
            $star = ",ShrA*"; // Galactic Centre
            if (($retflag = $this->swe_fixstar($star, $tjd_et, $iflag_true, $x, $serr)) == SweConst::ERR)
                return SweConst::ERR;
            $daya = SwephLib::swe_degnorm($x[0] - 270.0);
            return ($retflag & Sweph::SEFLG_EPHMASK);
        }
        if ($sid_mode == SweSiderealMode::SE_SIDM_GALCENT_RGILBRAND->value) {
            $star = ",SgrA*"; // Galactic Centre
            if (($retflag = $this->swe_fixstar($star, $tjd_et, $iflag_true, $x, $serr)) == SweConst::ERR)
                return SweConst::ERR;
            $daya = SwephLib::swe_degnorm($x[0] - 210.0 - 90.0 * 0.3819660113);
            return ($retflag & Sweph::SEFLG_EPHMASK);
        }
        if ($sid_mode == SweSiderealMode::SE_SIDM_GALCENT_MULA_WILHELM->value) {
            $star = ",SgrA*"; // Galactic Centre
            // right ascension in polar projection onto the ecliptic,
            // and that point is put in the middle of Mula
            if (($retflag = $this->swe_fixstar($star, $tjd_et, $iflag_true | SweConst::SEFLG_EQUATORIAL, $x, $serr)) == SweConst::ERR)
                return SweConst::ERR;
            $eps = $this->parent->getSwePhp()->swephLib->swi_epsiln($tjd_et, $iflag) * SweConst::RADTODEG;
            $daya = $this->swi_armc_to_mc($x[0], $eps);
            $daya = SwephLib::swe_degnorm($daya - 246.6666666667);
            return ($retflag & Sweph::SEFLG_EPHMASK);
        }
        if ($sid_mode == SweSiderealMode::SE_SIDM_GALEQU_IAU1958->value) {
            $star = ",GP1958"; // Galactic Pole IAU 1958
            if (($retflag = $this->swe_fixstar($star, $tjd_et, $iflag_galequ, $x, $serr)) == SweConst::ERR)
                return SweConst::ERR;
            $daya = SwephLib::swe_degnorm($x[0] - 150);
            return ($retflag & Sweph::SEFLG_EPHMASK);
        }
        if ($sid_mode == SweSiderealMode::SE_SIDM_GALEQU_TRUE->value) {
            $star = ",GPol"; // Galactic Pole modern, true
            if (($retflag = $this->swe_fixstar($star, $tjd_et, $iflag_galequ, $x, $serr)) == SweConst::ERR)
                return SweConst::ERR;
            $daya = SwephLib::swe_degnorm($x[0] - 150);
            return ($retflag & Sweph::SEFLG_EPHMASK);
        }
        if ($sid_mode == SweSiderealMode::SE_SIDM_GALEQU_MULA->value) {
            $star = ",GPol"; // Galactic Pole modern, true
            if (($retflag = $this->swe_fixstar($star, $tjd_et, $iflag_galequ, $x, $serr)) == SweConst::ERR)
                return SweConst::ERR;
            $daya = SwephLib::swe_degnorm($x[0] - 150 - 6.6666666667);
            return ($retflag & Sweph::SEFLG_EPHMASK);
        }
        if (!($sip->sid_mode & SweConst::SE_SIDBIT_ECL_DATE)) {
            // Now calculate precession for ayanamsha.
            // The following is the original method implemented in 1999 and
            // still used as our default method, although it is not logical.
            // Precession is measured on the ecliptic of the start epoch t0 (ayan_t0),
            // then the initial value of ayanamsha is added.
            // The procedure is as follows: The vernal point of the end epoch tjd_et
            // is precessed to t0. Ayanamsha is the resulting longitude of that
            // point at t0 plus the initial value.
            // This method is not really consistent because later this ayanamsha,
            // which is based on the ecliptic t0, will be applied to planetary
            // positions relative to the ecliptic of date.
            //

            // vernal point (tjd), cartesian
            $x[0] = 1;
            $x[1] = $x[2] = $x[3] = $x[4] = $x[5] = 0;
            // to J2000
            if ($tjd_et != Sweph::J2000)
                $this->parent->getSwePhp()->swephLib->swi_precess($x, $tjd_et, 0, SweConst::J_TO_J2000);
            // to t0
            $t0 = $sip->t0;
            if ($sip->t0_is_UT)
                $t0 += $this->parent->getSwePhp()->swephLib->swe_deltat_ex($t0, $iflag, $serr);
            $this->parent->getSwePhp()->swephLib->swi_precess($x, $t0, 0, SweConst::J2000_TO_J);
            // to ecliptic t0
            $eps = $this->parent->getSwePhp()->swephLib->swi_epsiln($t0, 0);
            SwephCotransUtils::swi_coortrf($x, $x, $eps);
            // to polar
            SwephCotransUtils::swi_cartpol($x, $x);
            // subtract initial value of ayanamsa
            $x[0] = -$x[0] * SweConst::RADTODEG + $sip->ayan_t0;
        } else {
            // Alternative method, more consistent, programmed on 15 may 2020.
            // The ayanamsha is measured on the ecliptic of date. This is more
            // correct because the ayamansha will be applied to planetary positions
            // relative to the ecliptic of date.
            //
            // at t0, we have ayanamsha sip->ayan_t0
            $x[0] = SwephLib::swe_degnorm($sip->ayan_t0) * SweConst::DEGTORAD;
            $x[1] = 0;
            $x[2] = 1;
            // get position for t0
            $t0 = $sip->t0;
            if ($sip->t0_is_UT)
                $t0 += $this->parent->getSwePhp()->swephLib->swe_deltat_ex($t0, $iflag, $serr);
            $eps = $this->parent->getSwePhp()->swephLib->swi_epsiln($t0, 0);
            // to polar equatorial relative to equinox t0
            SwephCotransUtils::swi_polcart($x, $x);
            SwephCotransUtils::swi_coortrf($x, $x, -$eps);
            // precess to J2000
            if ($t0 != Sweph::J2000)
                $this->parent->getSwePhp()->swephLib->swi_precess($x, $t0, 0, SweConst::J_TO_J2000);
            // precess to date
            $this->parent->getSwePhp()->swephLib->swi_precess($x, $tjd_et, 0, SweConst::J2000_TO_J);
            // epsilon of date
            $eps = $this->parent->getSwePhp()->swephLib->swi_epsiln($tjd_et, 0);
            // to polar
            SwephCotransUtils::swi_coortrf($x, $x, $eps);
            SwephCotransUtils::swi_cartpol($x, $x);
            $x[0] = SwephLib::swe_degnorm($x[0] * SweConst::RADTODEG);
        }
        $this->get_aya_correction($iflag, $corr, $serr);
        // get ayanamsa
        $daya = SwephLib::swe_degnorm($x[0] - $corr);
        return $iflag;
    }

    function swi_get_ayanamsa_with_speed(float $tjd_et, int $iflag, array &$daya, ?string &$serr = null): int
    {
        $daya_t2 = 0.;
        $tintv = 0.001;
        $t2 = $tjd_et - $tintv;
        $retflag = $this->swi_get_ayanamsa_ex($t2, $iflag, $daya_t2, $serr);
        if ($retflag == SweConst::ERR)
            return SweConst::ERR;
        $retflag = $this->swi_get_ayanamsa_ex($tjd_et, $iflag, $daya[0], $serr);
        if ($retflag == SweConst::ERR)
            return SweConst::ERR;
        $daya[1] = ($daya[0] - $daya_t2) / $tintv;
        return $retflag;
    }

    public function swe_get_ayanamsa_ex_ut(float $tjd_ut, int $iflag, float &$daya, ?string &$serr = null): int
    {
        $retflag = SweConst::OK;
        $epheflag = $iflag & Sweph::SEFLG_EPHMASK;
        if ($epheflag == 0) {
            $epheflag = SweConst::SEFLG_SWIEPH;
            $iflag |= SweConst::SEFLG_SWIEPH;
        }
        $deltat = $this->parent->getSwePhp()->swephLib->swe_deltat_ex($tjd_ut, $iflag, $serr);
        // swe... includes nutation, unless SEFLG_NONUT
        $retflag = $this->swi_get_ayanamsa_ex($tjd_ut + $deltat, $iflag, $daya, $serr);
        // if ephe required is not ephe returned, adjust delta t:
        if (($retflag & Sweph::SEFLG_EPHMASK) != $epheflag) {
            $deltat = $this->parent->getSwePhp()->swephLib->swe_deltat_ex($tjd_ut, $retflag, $serr);
            $retflag = $this->swi_get_ayanamsa_ex($tjd_ut + $deltat, $iflag, $daya, $serr);
        }
        return $retflag;
    }

    /*
     * input coordinates are J2000, cartesian.
     * xout 	ecliptical sidereal position (relative to ecliptic t0)
     * xoutr 	equatorial sidereal position (relative to equator t0)
     */
    function swi_trop_ra2sid_lon(array $xin, array &$xout, array &$xoutr, int $iflag): int
    {
        $corr = 0.;
        $sip =& $this->parent->getSwePhp()->swed->sidd;
        $oectmp = new epsilon();
        for ($i = 0; $i <= 5; $i++)
            $x[$i] = $xin[$i];
        if ($sip->t0 != Sweph::J2000) {
            // iflag must not contain SEFLG_JPLHOR here
            $this->parent->getSwePhp()->swephLib->swi_precess($x, $sip->t0, 0, SweConst::J2000_TO_J);
            PointerUtils::pointerFn($x, 3,
                fn(&$xo) => $this->parent->getSwePhp()->swephLib->swi_precess($xo, $sip->t0, 0, SweConst::J2000_TO_J));
        }
        for ($i = 0; $i <= 5; $i++)
            $xoutr[$i] = $x[$i];
        $this->calc_epsilon($this->parent->getSwePhp()->swed->sidd->t0, $iflag, $oectmp);
        SwephCotransUtils::swi_coortrf2($x, $x, $oectmp->seps, $oectmp->ceps);
        if ($iflag & SweConst::SEFLG_SPEED)
            SwephCotransUtils::swi_coortrf2_ptr($x, 3, $x, 3,
                $oectmp->seps, $oectmp->ceps);
        // to polar coordinates
        SwephCotransUtils::swi_cartpol_sp($x, $x);
        // subtract ayan_t0
        $this->get_aya_correction($iflag, $corr, null);
        $x[0] -= $sip->ayan_t0 * SweConst::DEGTORAD;
        $x[0] = SwephLib::swe_radnorm($x[0] + $corr * SweConst::DEGTORAD);
        // back to cartesian
        SwephCotransUtils::swi_polcart_sp($x, $xout);
        return SweConst::OK;
    }

    /*
     * input coordinates are J2000, cartesian.
     * xout 	ecliptical sidereal position
     * xoutr 	equatorial sidereal position
     */
    function swi_trop_ra2sid_lon_sosy(array $xin, array &$xout, int $iflag): int
    {
        $corr = 0.;
        $sip =& $this->parent->getSwePhp()->swed->sidd;
        $oe = $this->parent->getSwePhp()->swed->oec2000;
        $plane_node = Sweph::SSY_PLANE_NODE_E2000;
        $plane_incl = Sweph::SSY_PLANE_INCL;
        for ($i = 0; $i <= 5; $i++)
            $x[$i] = $xin[$i];
        // planet to ecliptic 2000
        SwephCotransUtils::swi_coortrf2($x, $x, $oe->seps, $oe->ceps);
        if ($iflag & SweConst::SEFLG_SPEED)
            SwephCotransUtils::swi_coortrf2_ptr($x, 3, $x, 3, $oe->seps, $oe->ceps);
        // to polar coordinates
        SwephCotransUtils::swi_cartpol_sp($x, $x);
        // to solar system equator
        $x[0] -= $plane_node;
        SwephCotransUtils::swi_polcart_sp($x, $x);
        SwephCotransUtils::swi_coortrf($x, $x, $plane_incl);
        SwephCotransUtils::swi_coortrf_ptr($x, 3, $x, 3, $plane_incl);
        SwephCotransUtils::swi_cartpol_sp($x, $x);
        // zero point of t0 in J2000 system
        $x0[0] = 1;
        $x0[1] = $x0[2] = 0;
        if ($sip->t0 != Sweph::J2000) {
            // iflag must not contain SEFLG_JPLHOR here
            $this->parent->getSwePhp()->swephLib->swi_precess($x0, $sip->t0, 0, SweConst::J_TO_J2000);
        }
        // zero point to ecliptic 2000
        SwephCotransUtils::swi_coortrf2($x0, $x0, $oe->seps, $oe->ceps);
        // to polar coordinates
        SwephCotransUtils::swi_cartpol($x0, $x0);
        // to solar system equator
        $x0[0] -= $plane_node;
        SwephCotransUtils::swi_polcart($x0, $x0);
        SwephCotransUtils::swi_coortrf($x0, $x0, $plane_incl);
        SwephCotransUtils::swi_cartpol($x0, $x0);
        // measure planet from zero point
        $x[0] -= $x0[0];
        $x[0] *= SweConst::RADTODEG;
        // subtract ayan_t0
        $this->get_aya_correction($iflag, $corr, null);
        $x[0] -= $sip->ayan_t0;
        $x[0] = SwephLib::swe_degnorm($x[0] + $corr) * SweConst::DEGTORAD;
        // back to cartesian
        SwephCotransUtils::swi_polcart_sp($x, $xout);
        return SweConst::OK;
    }

    /* converts planets from barycentric to geocentric,
     * apparent positions
     * precession and nutation
     * according to flags
     * ipli		planet number
     * iflag	flags
     */
    function app_pos_etc_plan_osc(int $ipl, int $ipli, int $iflag, ?string &$serr = null): int
    {
        $xearth = [];
        $xsun = [];
        $xmoon = [];
        $xobs = [];
        $xobs2 = [];
        $pdp =& $this->parent->getSwePhp()->swed->pldat[$ipli];
        $pedp =& $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_EARTH];
        $psdp =& $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_SUNBARY];
        $oe =& $this->parent->getSwePhp()->swed->oec2000;
        $epheflag = SweConst::SEFLG_DEFAULTEPH;
        $dt = $dtsave_for_defl = 0;         // dummy assign to silence gcc
        if ($iflag & SweConst::SEFLG_MOSEPH) {
            $epheflag = SweConst::SEFLG_MOSEPH;
        } else if ($iflag & SweConst::SEFLG_SWIEPH) {
            $epheflag = SweConst::SEFLG_SWIEPH;
        } else if ($iflag & SweConst::SEFLG_JPLEPH) {
            $epheflag = SweConst::SEFLG_JPLEPH;
        }
        // the conversions will be done with xx[].
        for ($i = 0; $i <= 5; $i++)
            $xx[$i] = $pdp->x[$i];
        /************************************
         * barycentric position is required *
         ************************************/
        // = heliocentric position with Moshier ephemeris
        /************************************
         * observer: geocenter or topocenter
         ************************************/
        // if topocentic position is wanted
        if ($iflag & SweConst::SEFLG_TOPOCTR) {
            if ($this->parent->getSwePhp()->swed->topd->teval != $pedp->teval ||
                $this->parent->getSwePhp()->swed->topd->teval == 0) {
                if ($this->swi_get_observer($pedp->teval, $iflag | SweConst::SEFLG_NONUT, true, $xobs, $serr) != SweConst::OK)
                    return SweConst::ERR;
            } else {
                for ($i = 0; $i <= 5; $i++)
                    $xobs[$i] = $this->parent->getSwePhp()->swed->topd->xobs[$i];
            }
            // barycentric position of observer
            for ($i = 0; $i <= 5; $i++)
                $xobs[$i] = $xobs[$i] + $pedp->x[$i];
        } else if ($iflag & SweConst::SEFLG_BARYCTR) {
            for ($i = 0; $i <= 5; $i++)
                $xobs[$i] = 0;
        } else if ($iflag & SweConst::SEFLG_HELCTR) {
            if ($iflag & SweConst::SEFLG_MOSEPH) {
                for ($i = 0; $i <= 5; $i++)
                    $xobs[$i] = 0;
            } else {
                for ($i = 0; $i <= 5; $i++)
                    $xobs[$i] = $psdp->x[$i];
            }
        } else {
            for ($i = 0; $i <= 5; $i++)
                $xobs[$i] = $pedp->x[$i];
        }
        /*******************************
         * light-time                  *
         *******************************/
        if (!($iflag & SweConst::SEFLG_TRUEPOS)) {
            $niter = 1;
            if ($iflag & SweConst::SEFLG_SPEED) {
                //
                // Apparent speed is influenced by the fact that dt changes with
                // motion. This makes a difference of several hundredths of an
                // arc second. To take this into account, we compute
                // 1. true position - apparent position at time t - 1.
                // 2. true position - apparent position at time t
                // 3. the different between the two is the daily motion resulting from
                // the change of dt.
                //
                for ($i = 0; $i <= 2; $i++)
                    $xxsv[$i] = $xxsp[$i] = $xx[$i] - $xx[$i + 3];
                for ($j = 0; $j <= $niter; $j++) {
                    for ($i = 0; $i <= 2; $i++) {
                        $dx[$i] = $xxsp[$i];
                        if (!($iflag & SweConst::SEFLG_HELCTR) && !($iflag & SweConst::SEFLG_BARYCTR))
                            $dx[$i] -= ($xobs[$i] - $xobs[$i + 3]);
                    }
                    // new dt
                    $dt = sqrt(Sweph::square_sum($dx)) * Sweph::AUNIT / Sweph::CLIGHT / 86400.0;
                    for ($i = 0; $i <= 2; $i++)
                        $xxsp[$i] = $xxsv[$i] - $dt * $pdp->x[$i + 3]; // rough apparent position
                }
                // true position - apparent position at time t-1
                for ($i = 0; $i <= 2; $i++)
                    $xxsp[$i] = $xxsv[$i] - $xxsp[$i];
            }
            // dt and t(apparent)
            for ($j = 0; $j <= $niter; $j++) {
                for ($i = 0; $i <= 2; $i++) {
                    $dx[$i] = $xx[$i];
                    if (!($iflag & SweConst::SEFLG_HELCTR) && !($iflag & SweConst::SEFLG_BARYCTR))
                        $dx[$i] -= $xobs[$i];
                }
                // new dt
                $dt = sqrt(Sweph::square_sum($dx)) * Sweph::AUNIT / Sweph::CLIGHT / 86400.0;
                $dtsave_for_defl = $dt;
                // new position: subtract t * speed
                //
                for ($i = 0; $i <= 2; $i++) {
                    $xx[$i] = $pdp->x[$i] - $dt * $pdp->x[$i + 3];
                    $xx[$i + 3] = $pdp->x[$i + 3];
                }
            }
            if ($iflag & SweConst::SEFLG_SPEED) {
                // part of daily motion resulting from change of dt
                for ($i = 0; $i <= 2; $i++)
                    $xxsp[$i] = $pdp->x[$i] - $xx[$i] - $xxsp[$i];
                $t = $pdp->teval - $dt;
                // for accuracy in speed, we will need earth as well
                $retc = $this->main_planet_bary($t, SweConst::SEI_EARTH, $epheflag, $iflag, false,
                    $xearth, $xearth, $xsun, $xmoon, $serr);
                if ($this->swi_osc_el_plan($t, $xx, $ipl - Sweph::SE_FICT_OFFSET, $ipli, $xearth, $xsun, $serr) != SweConst::OK)
                    return SweConst::ERR;
                if ($retc != SweConst::OK)
                    return $retc;
                if ($iflag & SweConst::SEFLG_TOPOCTR) {
                    if ($this->swi_get_observer($t, $iflag | SweConst::SEFLG_NONUT, false, $xobs2, $serr) != SweConst::OK)
                        return SweConst::ERR;
                    for ($i = 0; $i <= 5; $i++)
                        $xobs2[$i] += $xearth[$i];
                } else {
                    for ($i = 0; $i <= 5; $i++)
                        $xobs2[$i] = $xearth[$i];
                }
            }
        }
        /*******************************
         * conversion to geocenter     *
         *******************************/
        for ($i = 0; $i <= 5; $i++)
            $xx[$i] -= $xobs[$i];
        if (!($iflag & SweConst::SEFLG_TRUEPOS)) {
            //
            // Apparent speed is also influenced by
            // the change of dt during motion.
            // Neglect of this would result in an error of several 0.01"
            //
            if ($iflag & SweConst::SEFLG_SPEED)
                for ($i = 3; $i <= 5; $i++)
                    $xx[$i] -= $xxsp[$i - 3];
        }
        if (!($iflag & SweConst::SEFLG_SPEED))
            for ($i = 3; $i <= 5; $i++)
                $xx[$i] = 0;
        /************************************
         * relativistic deflection of light *
         ************************************/
        if (!($iflag & SweConst::SEFLG_TRUEPOS) && !($iflag & SweConst::SEFLG_NOGDEFL))
            $this->swi_deflect_light($xx, $dtsave_for_defl, $iflag);
        /**********************************
         * 'annual' aberration of light   *
         **********************************/
        if (!($iflag & SweConst::SEFLG_TRUEPOS) && !($iflag & SweConst::SEFLG_NOABERR)) {
            $this->swi_aberr_light($xx, $xobs, $iflag);
            //
            // Apparent speed is also influenced by
            // the difference of speed of the earth between t and t-dt.
            // Neglecting this would involve an error of several 0.1"
            //
            if ($iflag & SweConst::SEFLG_SPEED)
                for ($i = 3; $i <= 5; $i++)
                    $xx[$i] += $xobs[$i] - $xobs2[$i];
        }
        // save J2000 coordinates; required for sidereal positions
        for ($i = 0; $i <= 5; $i++)
            $xxsv[$i] = $xx[$i];
        /************************************************
         * precession, equator 2000 -> equator of date *
         ************************************************/
        if (!($iflag & SweConst::SEFLG_J2000)) {
            $this->parent->getSwePhp()->swephLib->swi_precess($xx, $pdp->teval, $iflag, SweConst::J2000_TO_J);
            if ($iflag & SweConst::SEFLG_SPEED)
                $this->swi_precess_speed($xx, $pdp->teval, $iflag, SweConst::J2000_TO_J);
            $oe =& $this->parent->getSwePhp()->swed->oec;
        } else
            $oe =& $this->parent->getSwePhp()->swed->oec2000;
        return $this->app_pos_rest($pdp, $iflag, $xx, $xxsv, $oe, $serr);
    }

    /* influence of precession on speed
     * xx		position and speed of planet in equatorial cartesian
     *		    coordinates */
    function swi_precess_speed(array &$xx, float $t, int $iflag, int $direction): void
    {
        $oe = new epsilon();
        $tprec = ($t - Sweph::J2000) / 36525.0;
        $prec_model = $this->parent->getSwePhp()->swed->astro_models[SweModel::MODEL_PREC_LONGTERM->value];
        if ($prec_model == 0) $prec_model = SweModelPrecession::default();
        if ($direction == SweConst::J2000_TO_J) {
            $fac = 1;
            $oe =& $this->parent->getSwePhp()->swed->oec;
        } else {
            $fac = -1;
            $oe =& $this->parent->getSwePhp()->swed->oec2000;
        }
        // first correct rotation.
        // this costs some sines and cosines, but neglect might
        // involve an error > 1"/day
        PointerUtils::pointerFn($xx, 3,
            fn(&$xxo) => $this->parent->getSwePhp()->swephLib->swi_precess($xxo, $t, $iflag, $direction));
        // then add 0.137"/day
        SwephCotransUtils::swi_coortrf2($xx, $xx, $oe->seps, $oe->ceps);
        SwephCotransUtils::swi_coortrf2_ptr($xx, 3, $xx, 3, $oe->seps, $oe->ceps);
        SwephCotransUtils::swi_cartpol_sp($xx, $xx);
        if ($prec_model == SweModelPrecession::MOD_PREC_VONDRAK_2011) {
            $this->parent->getSwePhp()->swephLib->swi_ldp_peps($t, $dpre);
            $this->parent->getSwePhp()->swephLib->swi_ldp_peps($t + 1, $dpre2);
            $xx[3] += ($dpre2 - $dpre) * $fac;
        } else {
            // formula from Montenbruck, German 1994, p. 18
            $xx[3] += (50.290966 + 0.0222226 * $tprec) / 3600 / 365.25 * SweConst::DEGTORAD * $fac;
        }
        SwephCotransUtils::swi_polcart_sp($xx, $xx);
        SwephCotransUtils::swi_coortrf2($xx, $xx, -$oe->seps, $oe->ceps);
        SwephCotransUtils::swi_coortrf2_ptr($xx, 3, $xx, 3, -$oe->seps, $oe->ceps);
    }

    /* multiplies cartesian equatorial coordinates with previously
     * calculated nutation matrix. also corrects speed.
     */
    function swi_nutate(array &$xx, int $iflag, bool $backward): void
    {
        $swed =& $this->parent->getSwePhp()->swed;
        for ($i = 0; $i <= 2; $i++) {
            if ($backward) {
                $x[$i] = $xx[0] * $swed->nut->matrix[$i][0] +
                    $xx[1] * $swed->nut->matrix[$i][1] +
                    $xx[2] * $swed->nut->matrix[$i][2];
            } else {
                $x[$i] = $xx[0] * $swed->nut->matrix[0][$i] +
                    $xx[1] * $swed->nut->matrix[1][$i] +
                    $xx[2] * $swed->nut->matrix[2][$i];
            }
        }
        if ($iflag & SweConst::SEFLG_SPEED) {
            // correct speed:
            // first correct rotation
            for ($i = 0; $i <= 2; $i++) {
                if ($backward) {
                    $x[$i + 3] = $xx[3] * $swed->nut->matrix[$i][0] +
                        $xx[4] * $swed->nut->matrix[$i][1] +
                        $xx[5] * $swed->nut->matrix[$i][2];
                } else {
                    $x[$i + 3] = $xx[3] * $swed->nut->matrix[0][$i] +
                        $xx[4] * $swed->nut->matrix[1][$i] +
                        $xx[5] * $swed->nut->matrix[2][$i];
                }
            }
            // then apparent motion due to change of nutation during day.
            // this makes a different of 0.01"
            for ($i = 0; $i <= 2; $i++) {
                if ($backward) {
                    $xv[$i] = $xx[0] * $swed->nutv->matrix[$i][0] +
                        $xx[1] * $swed->nutv->matrix[$i][1] +
                        $xx[2] * $swed->nutv->matrix[$i][2];
                } else {
                    $xv[$i] = $xx[0] * $swed->nutv->matrix[0][$i] +
                        $xx[1] * $swed->nutv->matrix[1][$i] +
                        $xx[2] * $swed->nutv->matrix[2][$i];
                }
                // new speed
                $xx[3 + $i] = $x[3 + $i] + ($x[$i] - $xv[$i]) / Sweph::NUT_SPEED_INTV;
            }
        }
        // new position
        for ($i = 0; $i <= 2; $i++)
            $xx[$i] = $x[$i];
    }

    /* computes 'annual' aberration
     * xx		planet's position accounted for light-time
     *          and gravitational light deflection
     * xe    	earth's position and speed
     */
    function aberr_light(array &$xx, array $xe): void
    {
        for ($i = 0; $i <= 5; $i++)
            $u[$i] = $xxs[$i] = $xx[$i];
        $ru = sqrt(Sweph::square_sum($u));
        for ($i = 0; $i <= 2; $i++)
            $v[$i] = $xe[$i + 3] / 24.0 / 3600.0 / Sweph::CLIGHT * Sweph::AUNIT;
        $v2 = Sweph::square_sum($v);
        $b_1 = sqrt(1 - $v2);
        $f1 = Sweph::dot_prod($u, $v) / $ru;
        $f2 = 1.0 + $f1 / (1.0 + $b_1);
        for ($i = 0; $i <= 2; $i++)
            $xx[$i] = ($b_1 * $xx[$i] + $f2 * $ru * $v[$i]) / (1.0 + $f1);
    }

    /* computes 'annual' aberration
     * xx		planet's position accounted for light-time
     *          and gravitational light deflection
     * xe    	earth's position and speed
     * xe_dt    earth's position and speed at t - dt
     * dt    	time difference for which xe_dt is given
     */
    function swi_aberr_light_ex(array &$xx, array $xe, array $xe_dt, float $dt, int $iflag): void
    {
        for ($i = 0; $i <= 5; $i++)
            $xxs[$i] = $xx[$i];
        $this->aberr_light($xx, $xe);
        // correction of speed
        // the influence of aberration on apparent velocity can
        // reach 0.4"/day
        //
        if ($iflag & SweConst::SEFLG_SPEED) {
            for ($i = 0; $i <= 2; $i++)
                $xx2[$i] = $xxs[$i] - $dt * $xxs[$i + 3];
            $this->aberr_light($xx2, $xe_dt);
            for ($i = 0; $i <= 2; $i++) {
                $xx[$i + 3] = ($xx[$i] - $xx2[$i]) / $dt;
            }
        }
    }

    /* computes 'annual' aberration
     * xx		planet's position accounted for light-time
     *          and gravitational light deflection
     * xe    	earth's position and speed
     */
    function swi_aberr_light(array &$xx, array $xe, int $iflag): void
    {
        $xx2 = [];
        $intv = Sweph::PLAN_SPEED_INTV;
        for ($i = 0; $i <= 5; $i++)
            $u[$i] = $xxs[$i] = $xx[$i];
        $ru = sqrt(Sweph::square_sum($u));
        for ($i = 0; $i <= 2; $i++)
            $v[$i] = $xe[$i + 3] / 24.0 / 3600.0 / Sweph::CLIGHT * Sweph::AUNIT;
        $v2 = Sweph::square_sum($v);
        $b_1 = sqrt(1 - $v2);
        $f1 = Sweph::dot_prod($u, $v) / $ru;
        $f2 = 1.0 + $f1 / (1.0 + $b_1);
        for ($i = 0; $i <= 2; $i++)
            $xx[$i] = ($b_1 * $xx[$i] + $f2 * $ru * $v[$i]) / (1.0 + $f1);
        if ($iflag & SweConst::SEFLG_SPEED) {
            // correction of speed
            // the influence of aberration on apparent velocity can
            // reach 0.4"/day
            //
            for ($i = 0; $i <= 2; $i++)
                $u[$i] = $xxs[$i] - $intv * $xxs[$i + 3];
            $ru = sqrt(Sweph::square_sum($u));
            $f1 = Sweph::dot_prod($u, $v) / $ru;
            $f2 = 1.0 + $f1 / (1.0 + $b_1);
            for ($i = 0; $i <= 2; $i++)
                $xx2[$i] = ($b_1 * $u[$i] + $f2 * $ru * $v[$i]) / (1.0 + $f1);
            for ($i = 0; $i <= 2; $i++) {
                $dx1 = $xx[$i] - $xxs[$i];
                $dx2 = $xx2[$i] - $u[$i];
                $dx1 -= $dx2;
                $xx[$i + 3] += $dx1 / $intv;
            }
        }
    }

    /* computes relativistic light deflection by the sun
     * ipli 	sweph internal planet number
     * xx		planet's position accounted for light-time
     * dt		dt of light-time
     */
    function swi_deflect_light(array &$xx, float $dt, int $iflag): void
    {
        $xx3 = [];
        $pedp =& $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_EARTH];
        $psdp =& $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_SUNBARY];
        $iephe = $pedp->iephe;
        for ($i = 0; $i <= 5; $i++)
            $xearth[$i] = $pedp->x[$i];
        if ($iflag & SweConst::SEFLG_TOPOCTR)
            for ($i = 0; $i <= 5; $i++)
                $xearth[$i] += $this->parent->getSwePhp()->swed->topd->xobs[$i];
        // U = planetbary(t-tau) - earthbary(t) = planetgeo
        for ($i = 0; $i <= 2; $i++)
            $u[$i] = $xx[$i];
        // Eh = earthbary(t) - sunbary(t) = earthhel
        if ($iephe == SweConst::SEFLG_JPLEPH || $iephe == SweConst::SEFLG_SWIEPH) {
            for ($i = 0; $i <= 2; $i++)
                $e[$i] = $xearth[$i] - $psdp->x[$i];
        } else {
            for ($i = 0; $i <= 2; $i++)
                $e[$i] = $xearth[$i];
        }
        // Q = planetbary(t-tau) - sunbary(t-tau) = 'planethel'
        // first compute sunbary(t-tau) for
        if ($iephe == SweConst::SEFLG_JPLEPH || $iephe == SweConst::SEFLG_SWIEPH) {
            for ($i = 0; $i <= 2; $i++)
                // this is sufficient precision
                $xsun[$i] = $psdp->x[$i] - $dt * $psdp->x[$i + 3];
            for ($i = 3; $i <= 5; $i++)
                $xsun[$i] = $psdp->x[$i];
        } else {
            for ($i = 0; $i <= 5; $i++)
                $xsun[$i] = $psdp->x[$i];
        }
        for ($i = 0; $i <= 2; $i++)
            $q[$i] = $xx[$i] + $xearth[$i] - $xsun[$i];
        $ru = sqrt(Sweph::square_sum($u));
        $rq = sqrt(Sweph::square_sum($q));
        $re = sqrt(Sweph::square_sum($e));
        for ($i = 0; $i <= 2; $i++) {
            $u[$i] /= $ru;
            $q[$i] /= $rq;
            $e[$i] /= $re;
        }
        $uq = Sweph::dot_prod($u, $q);
        $ue = Sweph::dot_prod($u, $e);
        $qe = Sweph::dot_prod($q, $e);
        // When a planet approaches the center of the sun in superior
        // conjunction, the formula for the deflection angle as given
        // in Expl. Suppl. p. 136 cannot be used. The deflection seems
        // to increase rapidly towards infinity. The reason is that the
        // formula considers the sun as a point mass. AA recommends to
        // set deflection = 0 in such a case.
        // However, to get a continuous motion, we modify the formula
        // for a non-point-mass, taking into account the mass distribution
        // within the sun. For more info, s. meff().
        //
        $sina = sqrt(1 - $ue * $ue);    // sin(angle) between sun and planet
        $sin_sunr = Sweph::SUN_RADIUS / $re;  // sine of sun radius (= sun radius)
        if ($sina < $sin_sunr) {
            $meff_fact = $this->meff($sina / $sin_sunr);
        } else {
            $meff_fact = 1;
        }
        $g1 = 2.0 * Sweph::HELGRAVCONST * $meff_fact / Sweph::CLIGHT / Sweph::CLIGHT / Sweph::AUNIT / $re;
        $g2 = 1.0 + $qe;
        // compute deflection position
        for ($i = 0; $i <= 2; $i++)
            $xx2[$i] = $ru * ($u[$i] + $g1 / $g2 * ($uq * $e[$i] - $ue * $q[$i]));
        if ($iflag & SweConst::SEFLG_SPEED) {
            // correction of speed
            // influence of light deflection on a planet's apparent speed:
            // for an outer planet at the solar limb with
            // |v(planet) - v(sun)| = 1 degree, this makes a difference of 7"/day.
            // if the planet is within the solar disc, the difference may increase
            // to 30" or more.
            // e.g. mercury at j2434871.45:
            //	distance from sun 		45"
            //	1. speed without deflection     2d10'10".4034
            //    2. speed with deflection        2d10'42".8460 (-speed flag)
            //    3. speed with deflection        2d10'43".4824 (< 3 positions/
            //							   -speed3 flag)
            // 3. is not very precise. Smaller dt would give result closer to 2.,
            // but will probably never be as good as 2, unless int32 doubles are
            // used. (try also j2434871.46!!)
            // however, in such a case speed changes rapidly. before being
            // passed by the sun, the planet accelerates, and after the sun
            // has passed it slows down. some time later it regains 'normal'
            // speed.
            // to compute speed, we do the same calculation as above with
            // slightly different u, e, q, and find out the difference in
            // deflection.
            //
            $dtsp = -Sweph::DEFL_SPEED_INTV;
            // U = planetbary(t-tau) - earthbary(t) = planetgeo
            for ($i = 0; $i <= 2; $i++)
                $u[$i] = $xx[$i] - $dtsp * $xx[$i + 3];
            // Eh = earthbary(t) - sunbary(t) = earthhel
            if ($iephe == SweConst::SEFLG_JPLEPH || $iephe == SweConst::SEFLG_SWIEPH) {
                for ($i = 0; $i <= 2; $i++)
                    $e[$i] = $xearth[$i] - $psdp->x[$i] -
                        $dtsp * ($xearth[$i + 3] - $psdp->x[$i + 3]);
            } else
                for ($i = 0; $i <= 2; $i++)
                    $e[$i] = $xearth[$i] - $dtsp * $xearth[$i + 3];
            // Q = planetbary(t-tau) - sunbary(t-tau) = 'planethel'
            for ($i = 0; $i <= 2; $i++)
                $q[$i] = $u[$i] + $xearth[$i] - $xsun[$i] -
                    $dtsp * ($xearth[$i + 3] - $xsun[$i + 3]);
            $ru = sqrt(Sweph::square_sum($u));
            $rq = sqrt(Sweph::square_sum($q));
            $re = sqrt(Sweph::square_sum($e));
            for ($i = 0; $i <= 2; $i++) {
                $u[$i] /= $ru;
                $q[$i] /= $rq;
                $e[$i] /= $re;
            }
            $uq = Sweph::dot_prod($u, $q);
            $ue = Sweph::dot_prod($u, $e);
            $qe = Sweph::dot_prod($q, $e);
            $sina = sqrt(1 - $ue * $ue);        // sin(angle) between sun and planet
            $sin_sunr = Sweph::SUN_RADIUS / $re;      // sine of sun radius (= sun radius)
            if ($sina < $sin_sunr) {
                $meff_fact = $this->meff($sina / $sin_sunr);
            } else {
                $meff_fact = 1;
            }
            $g1 = 2.0 * Sweph::HELGRAVCONST * $meff_fact / Sweph::CLIGHT / Sweph::CLIGHT / Sweph::AUNIT / $re;
            $g2 = 1.0 + $qe;
            for ($i = 0; $i <= 2; $i++)
                $xx3[$i] = $ru * ($u[$i] + $g1 / $g2 * ($uq * $e[$i] - $ue * $q[$i]));
            for ($i = 0; $i <= 2; $i++) {
                $dx1 = $xx2[$i] - $xx[$i];
                $dx2 = $xx3[$i] - $u[$i] * $ru;
                $xx[$i + 3] += $dx1 / $dtsp;
            }
        }
        // deflected position
        for ($i = 0; $i <= 2; $i++)
            $xx[$i] = $xx2[$i];
    }

    /* converts the sun from barycentric to geocentric,
     *          the earth from barycentric to heliocentric
     * computes
     * apparent position,
     * precession, and nutation
     * according to flags
     * iflag	    flags
     * serr         error string
     */
    function app_pos_etc_sun(int $iflag, ?string &$serr = null): int
    {
        $retc = SweConst::OK;
        $t = 0;
        $xobs = [];
        $xearth = [];
        $xsun = [];
        $pedp =& $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_EARTH];
        $psdp =& $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_SUNBARY];
        $oe =& $this->parent->getSwePhp()->swed->oec2000;
        // if the same conversions have already been done for the same
        // date, then return
        $flg1 = $iflag & ~SweConst::SEFLG_EQUATORIAL & ~SweConst::SEFLG_XYZ;
        $flg2 = $pedp->xflgs & ~SweConst::SEFLG_EQUATORIAL & ~SweConst::SEFLG_XYZ;
        if ($flg1 == $flg2) {
            $pedp->xflgs = $iflag;
            $pedp->iephe = $iflag & Sweph::SEFLG_EPHMASK;
            return SweConst::OK;
        }
        /************************************
         * observer: geocenter or topocenter
         ************************************/
        // if topocentric position is wanted
        if ($iflag & SweConst::SEFLG_TOPOCTR) {
            if ($this->parent->getSwePhp()->swed->topd->teval != $pedp->teval ||
                $this->parent->getSwePhp()->swed->topd->teval == 0) {
                if ($this->swi_get_observer($pedp->teval, $iflag | SweConst::SEFLG_NONUT, true, $xobs, $serr) != SweConst::OK)
                    return SweConst::ERR;
            } else {
                for ($i = 0; $i <= 5; $i++)
                    $xobs[$i] = $this->parent->getSwePhp()->swed->topd->xobs[$i];
            }
            // barycentric position of observer
            for ($i = 0; $i <= 5; $i++)
                $xobs[$i] = $xobs[$i] + $pedp->x[$i];
        } else {
            // barycentric position of geocenter
            for ($i = 0; $i <= 5; $i++)
                $xobs[$i] = $pedp->x[$i];
        }
        /***************************************
         * true heliocentric position of earth *
         ***************************************/
        if ($pedp->iephe == SweConst::SEFLG_MOSEPH || ($iflag & SweConst::SEFLG_BARYCTR)) {
            for ($i = 0; $i <= 5; $i++)
                $xx[$i] = $xobs[$i];
        } else {
            for ($i = 0; $i <= 5; $i++)
                $xx[$i] = $xobs[$i] - $psdp->x[$i];
        }
        /*******************************
         * light-time                  *
         *******************************/
        if (!($iflag & SweConst::SEFLG_TRUEPOS)) {
            /* number of iterations - 1
             * the following if() does the following:
             * with jpl and swiss ephemeris:
             *   with geocentric computation of sun:
             *     light-time correction of barycentric sun position.
             *   with heliocentric or barycentric computation of earth:
             *     light-time correction of barycentric earth position.
             * with moshier ephemeris (heliocentric!!!):
             *   with geocentric computation of sun:
             *     nothing! (aberration will be done later)
             *   with heliocentric or barycentric computation of earth:
             *     light-time correction of heliocentric earth position.
             */
            if ($pedp->iephe == SweConst::SEFLG_JPLEPH || $pedp->iephe == SweConst::SEFLG_SWIEPH ||
                ($iflag & SweConst::SEFLG_HELCTR) || ($iflag & SweConst::SEFLG_BARYCTR)) {
                for ($i = 0; $i <= 5; $i++) {
                    $xearth[$i] = $xobs[$i];
                    if ($pedp->iephe == SweConst::SEFLG_MOSEPH)
                        $xsun[$i] = 0;
                    else
                        $xsun[$i] = $psdp->x[$i];
                }
                $niter = 1;     // # of iterations
                for ($j = 0; $j <= $niter; $j++) {
                    // distance earth-sun
                    for ($i = 0; $i <= 2; $i++) {
                        $dx[$i] = $xearth[$i];
                        if (!($iflag & SweConst::SEFLG_BARYCTR))
                            $dx[$i] -= $xsun[$i];
                    }
                    // new t
                    $dt = sqrt(Sweph::square_sum($dx)) * Sweph::AUNIT / Sweph::CLIGHT / 86400.0;
                    $t = $pedp->teval - $dt;
                    // new position
                    switch ($pedp->iephe) {
                        // if geocentric sun, new sun at t'
                        // if heliocentric or barycentric earth, new earth at t'
                        case SweConst::SEFLG_JPLEPH:
                            if (($iflag & SweConst::SEFLG_HELCTR) || ($iflag & SweConst::SEFLG_BARYCTR))
                                $retc = $this->parent->getSwePhp()->sweJPL->swi_pleph($t, SweJPL::J_EARTH, SweJPL::J_SBARY, $xearth, $serr);
                            else
                                $retc = $this->parent->getSwePhp()->sweJPL->swi_pleph($t, SweJPL::J_SUN, SweJPL::J_SBARY, $xsun, $serr);
                            if ($retc != SweConst::OK) {
                                $this->parent->getSwePhp()->sweJPL->swi_close_jpl_file();
                                $this->parent->getSwePhp()->swed->jpl_file_is_open = false;
                                return $retc;
                            }
                            break;
                        case SweConst::SEFLG_SWIEPH:
                            if (($iflag & SweConst::SEFLG_HELCTR) || ($iflag & SweConst::SEFLG_BARYCTR)) {
                                $retc = $this->sweplan($t, SweConst::SEI_EARTH, SweConst::SEI_FILE_PLANET, $iflag,
                                    false, $xearth, xpsret: $xsun, serr: $serr);
                            } else {
                                $retc = $this->sweph($t, SweConst::SEI_SUNBARY, SweConst::SEI_FILE_PLANET, $iflag,
                                    null, false, $xsun, $serr);
                            }
                            break;
                        case SweConst::SEFLG_MOSEPH:
                            if (($iflag & SweConst::SEFLG_HELCTR) || ($iflag & SweConst::SEFLG_BARYCTR))
                                $retc = $this->parent->getSwePhp()->sweMPlan->swi_moshplan($t, SweConst::SEI_EARTH, false, $xearth, $xearth, $serr);
                            // with moshier there is not barycentric sun
                            break;
                        default:
                            $retc = SweConst::ERR;
                            break;
                    }
                    if ($retc != SweConst::OK)
                        return $retc;
                }
                // apparent heliocentric earth
                for ($i = 0; $i <= 5; $i++) {
                    $xx[$i] = $xearth[$i];
                    if (!($iflag & SweConst::SEFLG_BARYCTR))
                        $xx[$i] -= $xsun[$i];
                }
            }
        }
        if (!($iflag & SweConst::SEFLG_SPEED))
            for ($i = 3; $i <= 5; $i++)
                $xx[$i] = 0;
        /*******************************
         * conversion to geocenter     *
         *******************************/
        if (!($iflag & SweConst::SEFLG_HELCTR) && !($iflag & SweConst::SEFLG_BARYCTR))
            for ($i = 0; $i <= 5; $i++)
                $xx[$i] = -$xx[$i];
        /**********************************
         * 'annual' aberration of light   *
         **********************************/
        if (!($iflag & SweConst::SEFLG_TRUEPOS) && !($iflag & SweConst::SEFLG_NOABERR)) {
            $this->swi_aberr_light($xx, $xobs, $iflag);
        }
        if (!($iflag & SweConst::SEFLG_SPEED))
            for ($i = 3; $i <= 5; $i++)
                $xx[$i] = 0;
        // ICRS to J2000
        if (!($iflag & SweConst::SEFLG_ICRS) && $this->parent->swi_get_denum(SweConst::SEI_SUN, $iflag) >= 403) {
            $this->parent->getSwePhp()->swephLib->swi_bias($xx, $t, $iflag, false);
        }
        // save J2000 coordinates; required for sidereal positions
        for ($i = 0; $i <= 5; $i++)
            $xxsv[$i] = $xx[$i];
        /************************************************
         * precession, equator 2000 -> equator of date *
         ************************************************/
        if (!($iflag & SweConst::SEFLG_J2000)) {
            $this->parent->getSwePhp()->swephLib->swi_precess($xx, $pedp->teval, $iflag, SweConst::J2000_TO_J);
            if ($iflag & SweConst::SEFLG_SPEED)
                $this->swi_precess_speed($xx, $pedp->teval, $iflag, SweConst::J2000_TO_J);
            $oe =& $this->parent->getSwePhp()->swed->oec;
        } else
            $oe =& $this->parent->getSwePhp()->swed->oec2000;
        return $this->app_pos_rest($pedp, $iflag, $xx, $xxsv, $oe, $serr);
    }

    /* transforms the position of the moon:
     * heliocentric position
     * barycentric position
     * astrometric position
     * apparent position
     * precession and nutation
     *
     * note:
     * for apparent positions, we consider the earth-moon
     * system as independant.
     * for astrometric positions (SEFLG_NOABERR), we
     * consider the motions of the earth and the moon
     * related to the solar system barycenter.
     */
    function app_pos_etc_moon(int $iflag, ?string &$serr = null): int
    {
        $xobs = [];
        $xs = [];
        $xe = [];
        $xobs2 = [];
        $pedp =& $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_EARTH];
        $psdp =& $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_SUNBARY];
        $pdp =& $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_MOON];
        $oe =& $this->parent->getSwePhp()->swed->oec;
        $t = 0.;
        // if the same conversions have already been done for the same
        // date, then return
        $flg1 = $iflag & ~SweConst::SEFLG_EQUATORIAL & ~SweConst::SEFLG_XYZ;
        $flg2 = $pdp->xflgs & ~SweConst::SEFLG_EQUATORIAL & ~SweConst::SEFLG_XYZ;
        if ($flg1 == $flg2) {
            $pdp->xflgs = $iflag;
            $pdp->iephe = $iflag & Sweph::SEFLG_EPHMASK;
            return SweConst::OK;
        }
        // the conversions will be done with xx[].
        for ($i = 0; $i <= 5; $i++) {
            $xx[$i] = $pdp->x[$i];
            $xxm[$i] = $xx[$i];
        }
        /***********************************
         * to solar system barycentric
         ***********************************/
        for ($i = 0; $i <= 5; $i++)
            $xx[$i] += $pedp->x[$i];
        /*******************************
         * observer
         *******************************/
        if ($iflag & SweConst::SEFLG_TOPOCTR) {
            if ($this->parent->getSwePhp()->swed->topd->teval != $pdp->teval ||
                $this->parent->getSwePhp()->swed->topd->teval == 0) {
                if ($this->swi_get_observer($pdp->teval, $iflag | SweConst::SEFLG_NONUT, true, $xobs, $serr) != SweConst::OK)
                    return SweConst::ERR;
            } else {
                for ($i = 0; $i <= 5; $i++)
                    $xobs[$i] = $this->parent->getSwePhp()->swed->topd->xobs[$i];
            }
            for ($i = 0; $i <= 5; $i++)
                $xxm[$i] -= $xobs[$i];
            for ($i = 0; $i <= 5; $i++)
                $xobs[$i] += $pedp->x[$i];
        } else if ($iflag & SweConst::SEFLG_BARYCTR) {
            for ($i = 0; $i <= 5; $i++)
                $xxm[$i] += $pedp->x[$i];
        } else if ($iflag & SweConst::SEFLG_HELCTR) {
            for ($i = 0; $i <= 5; $i++)
                $xobs[$i] = $psdp->x[$i];
            for ($i = 0; $i <= 5; $i++)
                $xxm[$i] += $pedp->x[$i] - $psdp->x[$i];
        } else {
            for ($i = 0; $i <= 5; $i++)
                $xobs[$i] = $pedp->x[$i];
        }
        /*******************************
         * light-time                  *
         *******************************/
        $t = $pdp->teval;
        if (($iflag & SweConst::SEFLG_TRUEPOS) == 0) {
            $dt = sqrt(Sweph::square_sum($xxm)) * Sweph::AUNIT / Sweph::CLIGHT / 86400.0;
            $t = $pdp->teval - $dt;
            switch ($pdp->iephe) {
                case SweConst::SEFLG_JPLEPH:
                    $retc = $this->parent->getSwePhp()->sweJPL->swi_pleph($t, SweJPL::J_MARS, SweJPL::J_EARTH, $xx, $serr);
                    if ($retc == SweConst::OK)
                        $retc = $this->parent->getSwePhp()->sweJPL->swi_pleph($t, SweJPL::J_EARTH, SweJPL::J_SBARY, $xe, $serr);
                    if ($retc == SweConst::OK && ($iflag & SweConst::SEFLG_HELCTR))
                        $retc = $this->parent->getSwePhp()->sweJPL->swi_pleph($t, SweJPL::J_SUN, SweJPL::J_SBARY, $xs, $serr);
                    if ($retc != SweConst::OK) {
                        $this->parent->getSwePhp()->sweJPL->swi_close_jpl_file();
                        $this->parent->getSwePhp()->swed->jpl_file_is_open = false;
                    }
                    for ($i = 0; $i <= 5; $i++)
                        $xx[$i] += $xe[$i];
                    break;
                case SweConst::SEFLG_SWIEPH:
                    $retc = $this->sweplan($t, SweConst::SEI_MOON, SweConst::SEI_FILE_MOON, $iflag,
                        false, $xx, $xe, $xs, serr: $serr);
                    if ($retc != SweConst::OK)
                        return $retc;
                    for ($i = 0; $i <= 5; $i++)
                        $xx[$i] += $xe[$i];
                    break;
                case SweConst::SEFLG_MOSEPH:
                    // this method results in an error of a milliarcsec in speed
                    for ($i = 0; $i <= 2; $i++) {
                        $xx[$i] -= $dt * $xx[$i + 3];
                        $xe[$i] = $pedp->x[$i] - $dt * $pedp->x[$i + 3];
                        $xe[$i + 3] = $pedp->x[$i + 3];
                        $xs[$i] = 0;
                        $xs[$i + 3] = 0;
                    }
                    break;
            }
            if ($iflag & SweConst::SEFLG_TOPOCTR) {
                if ($this->swi_get_observer($t, $iflag | SweConst::SEFLG_NONUT, false, $xobs2, null) != SweConst::OK)
                    return SweConst::ERR;
                for ($i = 0; $i <= 5; $i++)
                    $xobs2[$i] += $xe[$i];
            } else if ($iflag & SweConst::SEFLG_BARYCTR) {
                for ($i = 0; $i <= 5; $i++)
                    $xobs2[$i] = 0;
            } else if ($iflag & SweConst::SEFLG_HELCTR) {
                for ($i = 0; $i <= 5; $i++)
                    $xobs2[$i] = $xs[$i];
            } else {
                for ($i = 0; $i <= 5; $i++)
                    $xobs2[$i] = $xe[$i];
            }
        }
        /*************************
         * to correct center
         *************************/
        for ($i = 0; $i <= 5; $i++)
            $xx[$i] -= $xobs[$i];
        /**********************************
         * 'annual' aberration of light   *
         **********************************/
        if (!($iflag & SweConst::SEFLG_TRUEPOS) && !($iflag & SweConst::SEFLG_NOABERR)) {
            $this->swi_aberr_light($xx, $xobs, $iflag);
            //
            // Apparent speed is also influenced by
            // the difference of speed of the earth between t and t-dt.
            // Neglecting this would lead to an error of several 0.1"
            //
            if ($iflag & SweConst::SEFLG_SPEED)
                for ($i = 3; $i <= 5; $i++)
                    $xx[$i] += $xobs[$i] - $xobs2[$i];
        }
        // if !speedflag, speed = 0
        if (!($iflag & SweConst::SEFLG_SPEED))
            for ($i = 3; $i <= 5; $i++)
                $xx[$i] = 0;
        // ICRS to J2000
        if (!($iflag & SweConst::SEFLG_ICRS) && $this->parent->swi_get_denum(SweConst::SEI_MOON, $iflag) >= 403) {
            $this->parent->getSwePhp()->swephLib->swi_bias($xx, $t, $iflag, false);
        }
        // save J2000 coordinates; required for sidereal positions
        for ($i = 0; $i <= 5; $i++)
            $xxsv[$i] = $xx[$i];
        /************************************************
         * precession, equator 2000 -> equator of date *
         ************************************************/
        if (!($iflag & SweConst::SEFLG_J2000)) {
            $this->parent->getSwePhp()->swephLib->swi_precess($xx, $pdp->teval, $iflag, SweConst::J2000_TO_J);
            if ($iflag & SweConst::SEFLG_SPEED)
                $this->swi_precess_speed($xx, $pdp->teval, $iflag, SweConst::J2000_TO_J);
            $oe =& $this->parent->getSwePhp()->swed->oec;
        } else
            $oe =& $this->parent->getSwePhp()->swed->oec2000;
        return $this->app_pos_rest($pdp, $iflag, $xx, $xxsv, $oe, $serr);
    }

    /* transforms the position of the barycentric sun:
     * precession and nutation
     * according to flags
     * iflag	    flags
     * serr         error string
     */
    function app_pos_etc_sbar(int $iflag, ?string &$serr = null): int
    {
        $psdp =& $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_EARTH];
        $psbdp =& $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_SUNBARY];
        $oe =& $this->parent->getSwePhp()->swed->oec;
        // the conversions will be done with xx[].
        for ($i = 0; $i <= 5; $i++)
            $xx[$i] = $psbdp->x[$i];
        /**************
         * light-time *
         **************/
        if (!($iflag & SweConst::SEFLG_TRUEPOS)) {
            $dt = sqrt(Sweph::square_sum($xx)) * Sweph::AUNIT / Sweph::CLIGHT / 86400.0;
            for ($i = 0; $i <= 2; $i++)
                $xx[$i] -= $dt * $xx[$i + 3];       // apparent position
        }
        if (!($iflag & SweConst::SEFLG_SPEED))
            for ($i = 3; $i <= 5; $i++)
                $xx[$i] = 0;
        // ICRS to J2000
        if (!($iflag & SweConst::SEFLG_ICRS) && $this->parent->swi_get_denum(SweConst::SEI_SUN, $iflag) >= 403) {
            $this->parent->getSwePhp()->swephLib->swi_bias($xx, $psdp->teval, $iflag, false);
        }
        // save J2000 coordinates; required for sidereal positions
        for ($i = 0; $i <= 5; $i++)
            $xxsv[$i] = $xx[$i];
        /************************************************
         * precession, equator 2000 -> equator of date *
         ************************************************/
        if (!($iflag & SweConst::SEFLG_J2000)) {
            $this->parent->getSwePhp()->swephLib->swi_precess($xx, $psbdp->teval, $iflag, SweConst::J2000_TO_J);
            if ($iflag & SweConst::SEFLG_SPEED)
                $this->swi_precess_speed($xx, $psbdp->teval, $iflag, SweConst::J2000_TO_J);
            $oe =& $this->parent->getSwePhp()->swed->oec;
        } else
            $oe =& $this->parent->getSwePhp()->swed->oec2000;
        return $this->app_pos_rest($psdp, $iflag, $xx, $xxsv, $oe, $serr);
    }

    /* transforms position of mean lunar node or apogee:
     * input is polar coordinates in mean ecliptic of date.
     * output is, according to iflag:
     * position accounted for light-time
     * position referred to J2000 (i.e. precession subtracted)
     * position with nutation
     * equatorial coordinates
     * cartesian coordinates
     * heliocentric position is not allowed ??????????????
     *         DAS WAERE ZIEMLICH AUFWENDIG. SONNE UND ERDE MUESSTEN
     *         SCHON VORHANDEN SEIN!
     * ipl		bodynumber (SE_MEAN_NODE or SE_MEAN_APOG)
     * iflag	flags
     * serr         error string
     */
    function app_pos_etc_mean(int $ipl, int $iflag, ?string &$serr = null): int
    {
        $pdp =& $this->parent->getSwePhp()->swed->nddat[$ipl];
        // if the same conversions have already been done for the same
        // date, then return
        $flg1 = $iflag & ~SweConst::SEFLG_EQUATORIAL & ~SweConst::SEFLG_XYZ;
        $flg2 = $pdp->xflgs & ~SweConst::SEFLG_EQUATORIAL & ~SweConst::SEFLG_XYZ;
        if ($flg1 == $flg2) {
            $pdp->xflgs = $iflag;
            $pdp->iephe = $iflag & Sweph::SEFLG_EPHMASK;
            return SweConst::OK;
        }
        for ($i = 0; $i <= 5; $i++)
            $xx[$i] = $pdp->x[$i];
        // cartesian equatorial coordinates
        SwephCotransUtils::swi_polcart_sp($xx, $xx);
        SwephCotransUtils::swi_coortrf2($xx, $xx,
            -$this->parent->getSwePhp()->swed->oec->seps,
            $this->parent->getSwePhp()->swed->oec->ceps);
        SwephCotransUtils::swi_coortrf2_ptr($xx, 3, $xx, 3,
            -$this->parent->getSwePhp()->swed->oec->seps,
            $this->parent->getSwePhp()->swed->oec->ceps);
        if (!($iflag & SweConst::SEFLG_SPEED))
            for ($i = 3; $i <= 5; $i++)
                $xx[$i] = 0;
        // J2000 coordinates; required for sidereal positions
        if ((($iflag & SweConst::SEFLG_SIDEREAL) &&
                ($this->parent->getSwePhp()->swed->sidd->sid_mode & SweConst::SE_SIDBIT_ECL_T0)) ||
            ($this->parent->getSwePhp()->swed->sidd->sid_mode & SweConst::SE_SIDBIT_SSY_PLANE)) {
            for ($i = 0; $i <= 5; $i++)
                $xxsv[$i] = $xx[$i];
            // xxsv is not J2000 yet!
            if ($pdp->teval != Sweph::J2000) {
                $this->parent->getSwePhp()->swephLib->swi_precess($xxsv, $pdp->teval, $iflag, SweConst::J_TO_J2000);
                if ($iflag & SweConst::SEFLG_SPEED)
                    $this->swi_precess_speed($xxsv, $pdp->teval, $iflag, SweConst::J_TO_J2000);
            }
        }
        /*****************************************************
         * if no precession, equator of date -> equator 2000 *
         *****************************************************/
        if ($iflag & SweConst::SEFLG_J2000) {
            $this->parent->getSwePhp()->swephLib->swi_precess($xx, $pdp->teval, $iflag, SweConst::J_TO_J2000);
            if ($iflag & SweConst::SEFLG_SPEED)
                $this->swi_precess_speed($xx, $pdp->teval, $iflag, SweConst::J_TO_J2000);
            $oe =& $this->parent->getSwePhp()->swed->oec2000;
        } else
            $oe =& $this->parent->getSwePhp()->swed->oec;
        return $this->app_pos_rest($pdp, $iflag, $xx, $xxsv, $oe, $serr);
    }

    const int SEI_CURR_FPOS = -1;
    const int SEI_FILE_LITTLEENDIAN = 1;
    const int SEI_FILE_REORD = 2;

    /* fetch chebyshew coefficients from sweph file for
     * tjd 		time
     * ipli		planet number
     * ifno		file number
     * serr		error string
     */
    function get_new_segment(float $tjd, int $ipli, int $ifno, ?string &$serr = null): int
    {
        $c = [];
        $pdp =& $this->parent->getSwePhp()->swed->pldat[$ipli];
        $fdp =& $this->parent->getSwePhp()->swed->fidat[$ifno];
        $fp = $fdp->fptr;
        $freord = (int)$fdp->iflg & self::SEI_FILE_REORD;
        $fendian = (int)$fdp->iflg & self::SEI_FILE_LITTLEENDIAN;
        // compute segment number
        $iseg = (int)(($tjd - $pdp->tfstart) / $pdp->dseg);
        $pdp->tseg0 = $pdp->tfstart + $iseg * $pdp->dseg;
        $pdp->tseg1 = $pdp->tseg0 + $pdp->dseg;
        // get file position of coefficients from file
        $fpos = $pdp->lndx0 + $iseg * 3;
        $retc = $this->do_fread($fpos, 3, 1, 4, $fp, $fpos, $freord, $fendian, $ifno, $serr);
        if ($retc != SweConst::OK)
            goto return_error_gns;
        fseek($fp, $fpos, SEEK_SET);
        // clear space of chebyshew coefficients
        if ($pdp->segp == null)
            $pdp->segp = [];
        // read coefficients for 3 coordinates
        for ($icoord = 0; $icoord < 3; $icoord++) {
            $idbl = $icoord * $pdp->ncoe;
            // first read header
            // first bit indicates number of sizes of packed coefficients
            $retc = $this->do_fread($c, 1, 2, 1, $fp, self::SEI_CURR_FPOS, $freord, $fendian, $ifno, $serr);
            if ($retc != SweConst::OK)
                goto return_error_gns;
            if ($c[0] & 128) {
                $nsizes = 6;
                // TODO: TBD
            }
        }
        return_error_gns:
        // TODO: TBD
        return SweConst::OK;
    }

    /* SWISSEPH
     * reads constants on ephemeris file
     * ifno         file #
     * serr         error string
     */
    function read_const(int $ifno, ?string &$serr = null): int
    {
        $lastnam = 19;
        $fdp =& $this->parent->getSwePhp()->swed->fidat[$ifno];
        $serr_file_damage = "Ephemeris file %s is damages (0%s).";
        $smsg = "";
        $nbytes_ipl = 2;
        $fp = $fdp->fptr;
        /*************************************
         * version number of file            *
         *************************************/
        // TODO: TBD
        return SweConst::OK;
    }

    /* SWISSEPH
     * reads from a file and, if necessary, reorders bytes
     * targ 	target pointer
     * size		size of item to be read
     * count	number of items
     * corrsize	in what size should it be returned
     *		(e.g. 3 byte int -> 4 byte int)
     * fp		file pointer
     * fpos		file position: if (fpos >= 0) then fseek
     * freord	reorder bytes or no
     * fendian	little/bigendian
     * ifno		file number
     * serr		error string
     */
    function do_fread(array &$trg, int $size, int $count, int $corrsize, $fp, int $fpos, int $freord,
                      int   $fendian, int $ifno, ?string &$serr = null): int
    {
        // TODO: TBD
        return SweConst::OK;
    }

    /* SWISSEPH
     * adds reference orbit to chebyshew series (if SEI_FLG_ELLIPSE),
     * rotates series to mean equinox of J2000
     *
     * ipli		planet number
     */
    function rot_back(int $ipli): void
    {
        // double eps2000 = 0.409092804; // eps 2000 in radians
        $seps2000 = 0.39777715572793088; // sin(eps2000)
        $ceps2000 = 0.91748206215761929; // cos(eps2000)
        $pdp =& $this->parent->getSwePhp()->swed->pldat[$ipli];
        $nco = $pdp->ncoe;
        $t = $pdp->tseg0 + $pdp->dseg / 2;
        $chcfx = $pdp->segp;
        $chcfy = $chcfx + $nco;
        $chcfz = $chcfx + 2 * $nco;
        $tdiff = ($t - $pdp->telem) / 365250.0;
        if ($ipli == SweConst::SEI_MOON) {
            $dn = $pdp->prot + $tdiff * $pdp->dprot;
            $i = (int)($dn / SweConst::TWOPI);
            $dn -= $i * SweConst::TWOPI;
            $qav = ($pdp->qrot + $tdiff * $pdp->dqrot) * cos($dn);
            $pav = ($pdp->qrot + $tdiff * $pdp->dqrot) * sin($dn);
        } else {
            $qav = $pdp->qrot + $tdiff * $pdp->dqrot;
            $pav = $pdp->prot + $tdiff * $pdp->dprot;
        }
        // calculate cosine and sine of average perihelion longitude.
        for ($i = 0; $i < $nco; $i++) {
            // TODO: What's happening here?
            $x[$i][0] = $chcfx[$i];
            $x[$i][1] = $chcfy[$i];
            $x[$i][2] = $chcfz[$i];
        }
        if ($pdp->iflg & SweConst::SEI_FLG_ELLIPSE) {
            $refepx = $pdp->refep;
            $refepy = $refepx + $nco;
            $omtild = $pdp->peri + $tdiff * $pdp->dperi;
            $i = (int)($omtild / SweConst::TWOPI);
            $omtild -= $i * SweConst::TWOPI;
            $com = cos($omtild);
            $som = sin($omtild);
            // add reference orbit.
            for ($i = 0; $i < $nco; $i++) {
                $x[$i][0] = $chcfx[$i] + $com * $refepx[$i] - $som * $refepy[$i];
                $x[$i][1] = $chcfy[$i] + $com * $refepy[$i] + $som * $refepx[$i];
            }
        }
        // construct right-handed orthonormal system with first axis along
        // origin of longitudes and third axis along angular momentum
        // this uses the standard formulas for equinoctial variables
        // (see papers by broucke by cefola).
        $cosih2 = 1.0 / (1.0 + $qav * $qav + $pav * $pav);
        // calculate orbit pole.
        $uiz[0] = 2.0 * $pav * $cosih2;
        $uiz[1] = -2.0 * $qav * $cosih2;
        $uiz[2] = (1.0 - $qav * $qav - $pav * $pav) * $cosih2;
        // calculate origin of longitudes vector.
        $uix[0] = (1.0 + $qav * $qav - $pav * $pav) * $cosih2;
        $uix[1] = 2.0 * $qav * $pav * $cosih2;
        $uix[2] = -2.0 * $pav * $cosih2;
        // calculate vector in orbital plane orthogonal to origin of
        // longitudes
        $uiy[0] = 2.0 * $qav * $pav * $cosih2;
        $uiy[1] = (1.0 - $qav * $qav + $pav * $pav) * $cosih2;
        $uiy[2] = 2.0 * $qav * $cosih2;
        // rotate to actual orientation in space.
        for ($i = 0; $i < $nco; $i++) {
            $xrot = $x[$i][0] * $uix[0] + $x[$i][1] * $uiy[0] + $x[$i][2] * $uiz[0];
            $yrot = $x[$i][0] * $uix[1] + $x[$i][1] * $uiy[0] + $x[$i][2] * $uiz[1];
            $zrot = $x[$i][0] * $uix[2] + $x[$i][1] * $uiy[2] + $x[$i][2] * $uiz[2];
            if (abs($xrot) + abs($yrot) + abs($zrot) >= 1e-14)
                $pdp->neval = $i;
            $x[$i][0] = $xrot;
            $x[$i][1] = $yrot;
            $x[$i][2] = $zrot;
            if ($ipli == SweConst::SEI_MOON) {
                // rotate to j2000 equator
                $x[$i][1] = $ceps2000 * $yrot - $seps2000 * $zrot;
                $x[$i][2] = $seps2000 * $yrot + $ceps2000 * $zrot;
            }
        }
        for ($i = 0; $i < $nco; $i++) {
            // TODO: What's happening here?
            $chcfx[$i] = $x[$i][0];
            $chcfy[$i] = $x[$i][1];
            $chcfz[$i] = $x[$i][2];
        }
    }

    /* Adjust position from Earth-Moon barycenter to Earth
     *
     * xemb = hel./bar. position or velocity vectors of emb (input)
     *                                                  earth (output)
     * xmoon= geocentric position or velocity vector of moon
     */
    function embofs(array &$xemb, array $xmoon): void
    {
        for ($i = 0; $i <= 2; $i++)
            $xemb[$i] -= $xmoon[$i] / (Sweph::EARTH_MOON_MRAT + 1.0);
    }

    /* calculates the nutation matrix
     * nu		pointer to nutation data structure
     * oe		pointer to epsilon data structure
     */
    function nut_matrix(nut $nu, epsilon $oe): void
    {
        $psi = $nu->nutlo[0];
        $eps = $oe->eps + $nu->nutlo[1];
        $sinpsi = sin($psi);
        $cospsi = cos($psi);
        $sineps0 = $oe->seps;
        $coseps0 = $oe->ceps;
        $sineps = sin($eps);
        $coseps = cos($eps);
        $nu->matrix[0][0] = $cospsi;
        $nu->matrix[0][1] = $sinpsi * $coseps;
        $nu->matrix[0][2] = $sinpsi * $sineps;
        $nu->matrix[1][0] = -$sinpsi * $coseps0;
        $nu->matrix[1][1] = $cospsi * $coseps * $coseps0 + $sineps * $sineps0;
        $nu->matrix[1][2] = $cospsi * $sineps * $coseps0 - $coseps * $sineps0;
        $nu->matrix[2][0] = -$sinpsi * $sineps0;
        $nu->matrix[2][1] = $cospsi * $coseps * $sineps0 - $sineps * $coseps0;
        $nu->matrix[2][2] = $cospsi * $sineps * $sineps0 + $coseps * $coseps0;
    }

    /* lunar osculating elements, i.e.
     * osculating node ('true' node) and
     * osculating apogee ('black moon', 'lilith').
     * tjd		julian day
     * ipl		body number, i.e. SEI_TRUE_NODE or SEI_OSCU_APOG
     * iflag	flags (which ephemeris, nutation, etc.)
     * serr		error string
     *
     * definitions and remarks:
     * the osculating node and the osculating apogee are defined
     * as the orbital elements of the momentary lunar orbit.
     * their advantage is that when the moon crosses the ecliptic,
     * it is really at the osculating node, and when it passes
     * its greatest distance from earth it is really at the
     * osculating apogee. with the mean elements this is not
     * the case. (some define the apogee as the second focus of
     * the lunar ellipse. but, as seen from the geocenter, both
     * points are in the same direction.)
     * problems:
     * the osculating apogee is given in the 'New International
     * Ephemerides' (Editions St. Michel) as the 'True Lilith'.
     * however, this name is misleading. this point is based on
     * the idea that the lunar orbit can be approximated by an
     * ellipse.
     * arguments against this:
     * 1. this procedure considers celestial motions as two body
     *    problems. this is quite good for planets, but not for
     *    the moon. the strong gravitational attraction of the sun
     *    destroys the idea of an ellipse.
     * 2. the NIE 'True Lilith' has strong oscillations around the
     *    mean one with an amplitude of about 30 degrees. however,
     *    when the moon is in apogee, its distance from the mean
     *    apogee never exceeds 5 degrees.
     * besides, the computation of NIE is INACCURATE. the mistake
     * reaches 20 arc minutes.
     * According to Santoni, the point was calculated using 'les 58
     * premiers termes correctifs au Perigee moyen' published by
     * Chapront and Chapront-Touze. And he adds: "Nous constatons
     * que meme en utilisant ces 58 termes CORRECTIFS, l'erreur peut
     * atteindre 0,5d!" (p. 13) We avoid this error, computing the
     * orbital elements directly from the position and the speed vector.
     *
     * how about the node? it is less problematic, because we
     * we needn't derive it from an orbital ellipse. we can say:
     * the axis of the osculating nodes is the intersection line of
     * the actual orbital plane of the moon and the plane of the
     * ecliptic. or: the osculating nodes are the intersections of
     * the two great circles representing the momentary apparent
     * orbit of the moon and the ecliptic. in this way they make
     * some sense. then, the nodes are really an axis, and they
     * have no geocentric distance. however, in this routine
     * we give a distance derived from the osculating ellipse.
     * the node could also be defined as the intersection axis
     * of the lunar orbital plane and the solar orbital plane,
     * which is not precisely identical to the ecliptic. this
     * would make a difference of several arcseconds.
     *
     * is it possible to keep the idea of a continuously moving
     * apogee that is exact at the moment when the moon passes
     * its greatest distance from earth?
     * to achieve this, we would probably have to interpolate between
     * the actual apogees.
     * the nodes could also be computed by interpolation. the resulting
     * nodes would deviate from the so-called 'true node' by less than
     * 30 arc minutes.
     *
     * sidereal and j2000 true node are first computed for the ecliptic
     * of epoch and then precessed to ecliptic of t0(ayanamsa) or J2000.
     * there is another procedure that computes the node for the ecliptic
     * of t0(ayanamsa) or J2000. it is excluded by
     * #ifdef SID_TNODE_FROM_ECL_T0
     */
    function lunar_osc_elem(float $tjd, int $ipl, int $iflag, ?string &$serr = null): int
    {
        $ipli = SweConst::SEI_MOON;
        $epheflag = SweConst::SEFLG_DEFAULTEPH;
        $retc = SweConst::ERR;
        $speed_intv = Sweph::NODE_CALC_INTV; // to silence gcc warning
        $xpos = ArrayUtils::createArray2D(3, 6);
        $xx = ArrayUtils::createArray2D(3, 6);
        $xnorm = [];
        $r = [];
        // TODO: SID_TNODE_FROM_ECL_T0
        $oe =& $this->parent->getSwePhp()->swed->oec;
        // TODO: SID_TNODE_FROM_ECL_T0
        $ndp =& $this->parent->getSwePhp()->swed->nddat[$ipl];
        // if elements have already been computed for this date, return
        // if speed flag has been turned on, recompute
        $flg1 = $iflag & ~SweConst::SEFLG_EQUATORIAL & ~SweConst::SEFLG_XYZ;
        $flg2 = $ndp->xflgs & ~SweConst::SEFLG_EQUATORIAL & ~SweConst::SEFLG_XYZ;
        $speedf1 = $ndp->xflgs & SweConst::SEFLG_SPEED;
        $speedf2 = $iflag & SweConst::SEFLG_SPEED;
        if ($tjd == $ndp->teval && $tjd != 0 && $flg1 == $flg2 && (!$speedf2 || $speedf1)) {
            $ndp->xflgs = $iflag;
            $ndp->iephe = $iflag & Sweph::SEFLG_EPHMASK;
            return SweConst::OK;
        }
        /* the geocentric position vector and the speed vector of the
       * moon make up the lunar orbital plane. the position vector
       * of the node is along the intersection line of the orbital
       * plane and the plane of the ecliptic.
       * to calculate the osculating node, we need one lunar position
       * with speed.
       * to calculate the speed of the osculating node, we need
       * three lunar positions and the speed of each of them.
       * this is relatively cheap, if the jpl-moon or the swisseph
       * moon is used. with the moshier moon this is much more
       * expensive, because then we need 9 lunar positions for
       * three speeds. but one position and speed can normally
       * be taken from swed.pldat[moon], which corresponds to
       * three moshier moon calculations.
       * the same is also true for the osculating apogee: we need
       * three lunar positions and speeds.
       */
        /*********************************************
         * now three lunar positions with speeds     *
         *********************************************/
        if ($iflag & SweConst::SEFLG_MOSEPH) {
            $epheflag = SweConst::SEFLG_MOSEPH;
        } else if ($iflag & SweConst::SEFLG_SWIEPH) {
            $epheflag = SweConst::SEFLG_SWIEPH;
        } else if ($iflag & SweConst::SEFLG_JPLEPH) {
            $epheflag = SweConst::SEFLG_JPLEPH;
        }
        // there may be a moon of wrong ephemeris in save area
        // force new computation:
        $this->parent->getSwePhp()->swed->pldat[SweConst::SEI_MOON]->teval = 0;
        if ($iflag & SweConst::SEFLG_SPEED) {
            $istart = 0;
        } else {
            $istart = 2;
        }
        if (isset($serr))
            $serr = "";
        three_positions:
        switch ($epheflag) {
            case SweConst::SEFLG_JPLEPH:
                $speed_intv = Sweph::NODE_CALC_INTV;
                for ($i = $istart; $i <= 2; $i++) {
                    if ($i == 0)
                        $t = $tjd - $speed_intv;
                    else if ($i == 1)
                        $t = $tjd + $speed_intv;
                    else
                        $t = $tjd;
                    $xp = $xpos[$i];
                    $retc = $this->jplplan($t, $ipli, $iflag, false, $xp, serr: $serr);
                    // read error or corrupt file
                    if ($retc == SweConst::ERR)
                        return SweConst::ERR;
                    // light-time-corrected moon for apparent node
                    // this makes a difference of several milliarcseconds with
                    // the node and 0.1" with the apogee.
                    // the simple formula 'x[j] -= dt * speed' should not be
                    // used here. the error would be greater than the advantage
                    // of computation speed.
                    if (($iflag & SweConst::SEFLG_TRUEPOS) == 0 && $retc >= SweConst::OK) {
                        $dt = sqrt(Sweph::square_sum($xpos[$i])) * Sweph::AUNIT / Sweph::CLIGHT / 86400.0;
                        $retc = $this->jplplan($t - $dt, $ipli, $iflag, false, $xpos[$i], serr: $serr);
                        // read error or corrupt file
                        if ($retc == SweConst::ERR)
                            return SweConst::ERR;
                    }
                    // jpl ephemeris not on disk, or date beyond ephemeris range
                    if ($retc == SweConst::NOT_AVAILABLE) {
                        $iflag = ($iflag & ~SweConst::SEFLG_JPLEPH) | SweConst::SEFLG_SWIEPH;
                        $epheflag = SweConst::SEFLG_SWIEPH;
                        if (isset($serr))
                            $serr .= " \ntrying Swiss Eph; ";
                        break;
                    } else if ($retc == SweConst::BEYOND_EPH_LIMITS) {
                        if ($tjd > SweConst::MOSHPLEPH_START && $tjd < SweConst::MOSHLUEPH_END) {
                            $iflag = ($iflag & ~SweConst::SEFLG_JPLEPH) | SweConst::SEFLG_MOSEPH;
                            $epheflag = SweConst::SEFLG_MOSEPH;
                            if (isset($serr))
                                $serr .= " \nusing Moshier Eph; ";
                            break;
                        } else
                            return SweConst::ERR;
                    }
                    // precessiojn and nutation etc.
                    $retc = $this->swi_plan_for_osc_elem($iflag | SweConst::SEFLG_SPEED, $t, $xpos[$i]); // retc is always ok
                }
                break;
            case SweConst::SEFLG_SWIEPH:
                $speed_intv = Sweph::NODE_CALC_INTV;
                for ($i = $istart; $i <= 2; $i++) {
                    if ($i == 0)
                        $t = $tjd - $speed_intv;
                    else if ($i == 1)
                        $t = $tjd + $speed_intv;
                    else
                        $t = $tjd;
                    $retc = $this->swemoon($t, $iflag | SweConst::SEFLG_SPEED, false, $xpos[$i], $serr);
                    if ($retc == SweConst::ERR)
                        return SweConst::ERR;
                    // light-time-corrected moon for apparent node (~ 0.006")
                    if (($iflag & SweConst::SEFLG_TRUEPOS) == 0 && $retc >= SweConst::OK) {
                        $dt = sqrt(Sweph::square_sum($xpos[$i])) * Sweph::AUNIT / Sweph::CLIGHT / 86400.0;
                        $retc = $this->swemoon($t - $dt, $iflag | SweConst::SEFLG_SPEED, false, $xpos[$i], $serr);
                        if ($retc == SweConst::ERR)
                            return SweConst::ERR;
                    }
                    if ($retc == SweConst::NOT_AVAILABLE) {
                        if ($tjd > SweConst::MOSHPLEPH_START && $tjd < SweConst::MOSHPLEPH_END) {
                            $iflag = ($iflag & ~SweConst::SEFLG_SWIEPH) | SweConst::SEFLG_MOSEPH;
                            $epheflag = SweConst::SEFLG_MOSEPH;
                            if (isset($serr))
                                $serr .= " \nusing Moshier eph.; ";
                            break;
                        } else
                            return SweConst::ERR;
                    }
                    // precession and nutation etc.
                    $retc = $this->swi_plan_for_osc_elem($iflag | SweConst::SEFLG_SPEED, $t, $xpos[$i]); // retc is always ok
                }
                break;
            case SweConst::SEFLG_MOSEPH:
                // with moshier moon, we need a greater speed_intv, because here the
                // node and apogee oscillate wildly within small intervals
                $speed_intv = Sweph::NODE_CALC_INTV_MOSH;
                for ($i = $istart; $i <= 2; $i++) {
                    if ($i == 0)
                        $t = $tjd - $speed_intv;
                    else if ($i == 1)
                        $t = $tjd + $speed_intv;
                    else
                        $t = $tjd;
                    $retc = $this->parent->getSwePhp()->sweMMoon->swi_moshmoon($t, false, $xpos[$i], $serr);
                    if ($retc == SweConst::ERR)
                        return $retc;
                    // precession and nutation etc.
                    $retc = $this->swi_plan_for_osc_elem($iflag | SweConst::SEFLG_SPEED, $t, $xpos[$i]);
                }
                break;
            default:
                break;
        }
        if ($retc == SweConst::NOT_AVAILABLE || $retc == SweConst::BEYOND_EPH_LIMITS)
            goto three_positions;
        /*********************************************
         * node with speed                           *
         *********************************************/
        // node is always needed, even if apogee is wanted
        $ndnp =& $this->parent->getSwePhp()->swed->nddat[SweConst::SEI_TRUE_NODE];
        // three nodes
        for ($i = $istart; $i <= 2; $i++) {
            if (abs($xpos[$i][5]) < 1e-15)
                $xpos[$i][5] = 1e-15;
            $fac = $xpos[$i][2] / $xpos[$i][5];
            $sgn = $xpos[$i][5] / abs($xpos[$i][5]);
            for ($j = 0; $j <= 2; $j++)
                $xx[$i][$j] = ($xpos[$i][$j] - $fac * $xpos[$i][$j + 3]) * $sgn;
        }
        // now we have the correct direction of the node, the
        // intersection of the lunar plane and the ecliptic plane.
        // the distance is the distance of the point where the tangent
        // of the lunar motion penetrates the ecliptic plane.
        // this can be very large, e.g. j2415080.37372.
        // below, a new distance will be derived from the osculating
        // ellipse.
        //

        // save position and speed
        for ($i = 0; $i <= 2; $i++) {
            $ndnp->x[$i] = $xx[2][$i];
            if ($iflag & SweConst::SEFLG_SPEED) {
                $b = ($xx[1][$i] - $xx[0][$i]) / 2;
                $a = ($xx[1][$i] + $xx[0][$i]) / 2 - $xx[2][$i];
                $ndnp->x[$i + 3] = (2 * $a + $b) / $speed_intv;
            } else
                $ndnp->x[$i + 3] = 0;
            $ndnp->teval = $tjd;
            $ndnp->iephe = $epheflag;
        }
        /************************************************************
         * apogee with speed                                        *
         * must be computed anyway to get the node's distance       *
         ************************************************************/
        $ndap =& $this->parent->getSwePhp()->swed->nddat[SweConst::SEI_OSCU_APOG];
        $Gmsm = Sweph::GEOGCONST * (1 + 1 / Sweph::EARTH_MOON_MRAT) / Sweph::AUNIT / Sweph::AUNIT / Sweph::AUNIT * 86400.0 * 86400.0;
        // three apogees
        for ($i = $istart; $i <= 2; $i++) {
            // node
            $rxy = sqrt($xx[$i][0] * $xx[$i][0] + $xx[$i][1] * $xx[$i][1]);
            $cosnode = $xx[$i][0] / $rxy;
            $sinnode = $xx[$i][1] / $rxy;
            // inclination
            SwephLib::swi_cross_prod($xpos[$i], array_values(array_slice($xpos[$i], 3)), $xnorm);
            $rxy = $xnorm[0] * $xnorm[0] + $xnorm[1] * $xnorm[1];
            $c2 = ($rxy + $xnorm[2] * $xnorm[2]);
            $rxyz = sqrt($c2);
            $rxy = sqrt($rxy);
            $sinincl = $rxy / $rxyz;
            $cosincl = sqrt(1 - $sinincl * $sinincl);
            // argument of latitude
            $cosu = $xpos[$i][0] * $cosnode + $xpos[$i][1] * $sinnode;
            $sinu = $xpos[$i][2] / $sinincl;
            $uu = atan2($sinu, $cosu);
            // semi-axis
            $rxyz = sqrt(Sweph::square_sum($xpos[$i]));
            $v2 = Sweph::square_sum(array_values(array_slice($xpos[$i], 3)));
            $sema = 1 / (2 / $rxyz - $v2 / $Gmsm);
            // eccentricity
            $pp = $c2 / $Gmsm;
            $ecce = sqrt(1 - $pp / $sema);
            // eccentric anomaly
            $cosE = 1 / $ecce * (1 - $rxyz / $sema);
            $sinE = 1 / $ecce / sqrt($sema * $Gmsm) * Sweph::dot_prod($xpos[$i],
                    array_values(array_slice($xpos[$i], 3)));
            // true anomaly
            $ny = 2 * atan(sqrt((1 + $ecce) / (1 - $ecce)) * $sinE / (1 + $cosE));
            // distance of apogee from ascending node
            $xxa[$i][0] = SwephLib::swi_mod2PI($uu - $ny + M_PI);
            $xxa[$i][1] = 0;                        // latitude
            $xxa[$i][2] = $sema * (1 + $ecce);      // distance
            // transformation to ecliptic coordinates
            SwephCotransUtils::swi_polcart($xxa[$i], $xxa[$i]);
            SwephCotransUtils::swi_coortrf2($xxa[$i], $xxa[$i], $sinincl, $cosincl);
            SwephCotransUtils::swi_cartpol($xxa[$i], $xxa[$i]);
            // adding node, we get apogee in ecl. coord.
            $xxa[$i][0] += atan2($sinnode, $cosnode);
            SwephCotransUtils::swi_polcart($xxa[$i], $xxa[$i]);
            // new distance of node from orbital ellipse:
            // true anomaly of node:
            $ny = SwephLib::swi_mod2PI($ny - $uu);
            // eccentric anomaly
            $cosE = cos(2 * atan(tan($ny / 2) / sqrt((1 + $ecce) / (1 - $ecce))));
            // new distance
            $r[0] = $sema * (1 - $ecce * $cosE);
            // old node distance
            $r[1] = sqrt(Sweph::square_sum($xx[$i]));
            // correct length of position vector
            for ($j = 0; $j <= 2; $j++)
                $xx[$i][$j] *= $r[0] / $r[1];
        }
        // save position and speed
        for ($i = 0; $i <= 2; $i++) {
            // apogee
            $ndap->x[$i] = $xxa[2][$i];
            if ($iflag & SweConst::SEFLG_SPEED) {
                $ndap->x[$i + 3] = ($xxa[1][$i] - $xxa[0][$i]) / $speed_intv / 2;
            } else {
                $ndap->x[$i + 3] = 0;
            }
            $ndap->teval = $tjd;
            $ndap->iephe = $epheflag;
            // node
            $ndnp->x[$i] = $xx[2][$i];
            if ($iflag & SweConst::SEFLG_SPEED) {
                $ndnp->x[$i + 3] = ($xx[1][$i] - $xx[0][$i]) / $speed_intv / 2;
            } else {
                $ndnp->x[$i + 3] = 0;
            }
        }
        /**********************************************************************
         * precession and nutation have already been taken into account
         * because the computation is on the basis of lunar positions
         * that have gone through swi_plan_for_osc_elem.
         * light-time is already contained in lunar positions.
         * now compute polar and equatorial coordinates:
         **********************************************************************/
        for ($j = 0; $j <= 1; $j++) {
            $x = [];
            if ($j == 0)
                $ndp =& $this->parent->getSwePhp()->swed->nddat[SweConst::SEI_TRUE_NODE];
            else
                $ndp =& $this->parent->getSwePhp()->swed->nddat[SweConst::SEI_OSCU_APOG];
            $ndp->xreturn = [];
            // cartesian ecliptic
            for ($i = 0; $i <= 5; $i++)
                $ndp->xreturn[6 + $i] = $ndp->x[$i];
            // polar ecliptic
            SwephCotransUtils::swi_cartpol_sp_ptr($ndp->xreturn, 6, $ndp->xreturn, 0);
            // cartesian equatorial
            SwephCotransUtils::swi_coortrf2_ptr($ndp->xreturn, 6,
                $ndp->xreturn, 18, -$oe->seps, $oe->ceps);
            if ($iflag & SweConst::SEFLG_SPEED)
                SwephCotransUtils::swi_coortrf2_ptr($ndp->xreturn, 9,
                    $ndp->xreturn, 21, -$oe->seps, $oe->ceps);
            // TODO: SID_TNODE_FROM_ECL_T0
            if (!($iflag & SweConst::SEFLG_NONUT)) {
                SwephCotransUtils::swi_coortrf2_ptr($ndp->xreturn, 18,
                    $ndp->xreturn, 18,
                    -$this->parent->getSwePhp()->swed->nut->snut,
                    $this->parent->getSwePhp()->swed->nut->cnut);
                if ($iflag & SweConst::SEFLG_SPEED)
                    SwephCotransUtils::swi_coortrf2_ptr($ndp->xreturn, 21,
                        $ndp->xreturn, 21,
                        -$this->parent->getSwePhp()->swed->nut->snut,
                        $this->parent->getSwePhp()->swed->nut->cnut
                    );
            }
            // polar equatorial
            SwephCotransUtils::swi_cartpol_ptr($ndp->xreturn, 18, $ndp->xreturn, 12);
            $ndp->xflgs = $iflag;
            $ndp->iephe = $iflag & Sweph::SEFLG_EPHMASK;
            if (false) {
                // TODO: SID_TNODE_FROM_ECL_T0
            } else {
                if ($iflag & SweConst::SEFLG_SIDEREAL) {
                    // node and apogee are referred to t;
                    // the ecliptic position must be transformed to t0

                    // rigorous algorithm
                    if (($this->parent->getSwePhp()->swed->sidd->sid_mode & SweConst::SE_SIDBIT_ECL_T0) ||
                        ($this->parent->getSwePhp()->swed->sidd->sid_mode & SweConst::SE_SIDBIT_SSY_PLANE)) {
                        for ($i = 0; $i <= 5; $i++)
                            $x[$i] = $ndp->xreturn[18 + $i];
                        // remove nutation
                        if (!($iflag & SweConst::SEFLG_NONUT))
                            $this->swi_nutate($x, $iflag, true);
                        // precess to J2000
                        $this->parent->getSwePhp()->swephLib->swi_precess($x, $tjd, $iflag, SweConst::J_TO_J2000);
                        if ($iflag & SweConst::SEFLG_SPEED)
                            $this->swi_precess_speed($x, $tjd, $iflag, SweConst::J_TO_J2000);
                        if ($this->parent->getSwePhp()->swed->sidd->sid_mode & SweConst::SE_SIDBIT_ECL_T0) {
                            PointerUtils::pointer2Fn($ndp->xreturn, $ndp->xreturn, 6, 18,
                                fn(&$xreturn1, &$xreturn2) => $this->swi_trop_ra2sid_lon($x, $xreturn1, $xreturn2, $iflag));
                            // project onto solar system equator
                        } else if ($this->parent->getSwePhp()->swed->sidd->sid_mode & SweConst::SE_SIDBIT_SSY_PLANE) {
                            PointerUtils::pointerFn($ndp->xreturn, 6,
                                fn(&$xreturn) => $this->swi_trop_ra2sid_lon_sosy($x, $xreturn, $iflag));
                        }
                        // to polar
                        SwephCotransUtils::swi_cartpol_sp(array_values(array_slice($ndp->xreturn, 6)), $ndp->xreturn);
                        PointerUtils::pointerFn($ndp->xreturn, 12,
                            fn(&$xreturn) => SwephCotransUtils::swi_cartpol_sp(
                                array_values(array_slice($ndp->xreturn, 18)), $xreturn));
                        // traditional algorithm;
                        // this is a bit clumsy, but allows us to keep the
                        // sidereal code together
                    } else {
                        SwephCotransUtils::swi_cartpol_sp(array_values(array_slice($ndp->xreturn, 6)), $ndp->xreturn);
                        if ($this->swi_get_ayanamsa_with_speed($ndp->teval, $iflag, $daya, $serr) == SweConst::ERR)
                            return SweConst::ERR;
                        $ndp->xreturn[0] -= $daya[0] * SweConst::DEGTORAD;
                        $ndp->xreturn[3] -= $daya[1] * SweConst::DEGTORAD;
                        PointerUtils::pointerFn($ndp->xreturn, 6,
                            fn(&$xreturn) => SwephCotransUtils::swi_polcart_sp($ndp->xreturn, $xreturn));
                    }
                } else if ($iflag & SweConst::SEFLG_J2000) {
                    // node and apogee are referred to t;
                    // the ecliptic position must be transformed to J2000
                    for ($i = 0; $i <= 5; $i++)
                        $x[$i] = $ndp->xreturn[18 + $i];
                    // precess to J2000
                    $this->parent->getSwePhp()->swephLib->swi_precess($x, $tjd, $iflag, SweConst::J_TO_J2000);
                    if ($iflag & SweConst::SEFLG_SPEED)
                        $this->swi_precess_speed($x, $tjd, $iflag, SweConst::J_TO_J2000);
                    for ($i = 0; $i <= 5; $i++)
                        $ndp->xreturn[18 + $i] = $x[$i];
                    PointerUtils::pointerFn($ndp->xreturn, 12,
                        fn(&$xreturn) => SwephCotransUtils::swi_cartpol_sp(
                            array_values(array_slice($ndp->xreturn, 18)), $xreturn));
                    SwephCotransUtils::swi_coortrf2_ptr($ndp->xreturn, 18,
                        $ndp->xreturn, 6,
                        $this->parent->getSwePhp()->swed->oec2000->seps,
                        $this->parent->getSwePhp()->swed->oec2000->ceps);
                    if ($iflag & SweConst::SEFLG_SPEED)
                        SwephCotransUtils::swi_coortrf2_ptr($ndp->xreturn, 21,
                            $ndp->xreturn, 6,
                            $this->parent->getSwePhp()->swed->oec2000->seps,
                            $this->parent->getSwePhp()->swed->oec2000->ceps);
                    SwephCotransUtils::swi_cartpol_sp(array_values(array_slice($ndp->xreturn, 6)), $ndp->xreturn);
                }
            }
            /**********************
             * radians to degrees *
             **********************/
            for ($i = 0; $i < 2; $i++) {
                $ndp->xreturn[$i] *= SweConst::RADTODEG;            // ecliptic
                $ndp->xreturn[$i + 3] *= SweConst::RADTODEG;
                $ndp->xreturn[$i + 12] *= SweConst::RADTODEG;       // equator
                $ndp->xreturn[$i + 15] *= SweConst::RADTODEG;
            }
            $ndp->xreturn[0] = SwephLib::swe_degnorm($ndp->xreturn[0]);
            $ndp->xreturn[12] = SwephLib::swe_degnorm($ndp->xreturn[12]);
        }
        return SweConst::OK;
    }

    /* lunar osculating elements, i.e.
     */
    function intp_apsides(float $tjd, int $ipl, int $iflag, ?string &$serr = null): int
    {
        $speed_intv = 0.1;
        $xpos = ArrayUtils::createArray2D(3, 6);
        $oe =& $this->parent->getSwePhp()->swed->oec;
        $nut =& $this->parent->getSwePhp()->swed->nut;
        $ndp =& $this->parent->getSwePhp()->swed->nddat[$ipl];
        // if same calculation was done before, return
        // if speed flag has been turned on, recompute
        $flg1 = $iflag & ~SweConst::SEFLG_EQUATORIAL & ~SweConst::SEFLG_XYZ;
        $flg2 = $ndp->xflgs & ~SweConst::SEFLG_EQUATORIAL & ~SweConst::SEFLG_XYZ;
        $speedf1 = $ndp->xflgs & SweConst::SEFLG_SPEED;
        $speedf2 = $iflag & SweConst::SEFLG_SPEED;
        if ($tjd == $ndp->teval && $tjd != 0 && $flg1 == $flg2 && (!$speedf2 || $speedf1)) {
            $ndp->xflgs = $iflag;
            $ndp->iephe = $iflag & SweConst::SEFLG_MOSEPH;
            return SweConst::OK;
        }
        /*********************************************
         * now three apsides *
         *********************************************/
        for ($t = $tjd - $speedf1, $i = 0; $i < 3; $t += $speed_intv, $i++) {
            if (!($iflag & SweConst::SEFLG_SPEED) && $i != 1) continue;
            $this->parent->getSwePhp()->sweMMoon->swi_intp_apsides($t, $xpos[$i], $ipl);
        }
        /************************************************************
         * apsis with speed                                         *
         ************************************************************/
        for ($i = 0; $i < 3; $i++) {
            $xx[$i] = $xpos[1][$i];
            $xx[$i + 3] = 0;
        }
        if ($iflag & SweConst::SEFLG_SPEED) {
            $xx[3] = SwephLib::swe_difrad2n($xpos[2][0], $xpos[0][0]) / $speed_intv / 2.0;
            $xx[4] = ($xpos[2][1] - $xpos[0][1]) / $speed_intv / 2.0;
            $xx[5] = ($xpos[2][2] - $xpos[0][2]) / $speed_intv / 2.0;
        }
        $ndp->xreturn = [];
        // ecliptic polar to cartesian
        SwephCotransUtils::swi_polcart_sp($xx, $xx);
        // light-time
        if (!($iflag & SweConst::SEFLG_TRUEPOS)) {
            $dt = sqrt(Sweph::square_sum($xx)) * Sweph::AUNIT / Sweph::CLIGHT / 86400.0;
            for ($i = 1; $i < 3; $i++)
                $xx[$i] -= $dt * $xx[$i + 3];
        }
        for ($i = 0; $i <= 5; $i++)
            $ndp->xreturn[$i + 6] = $xx[$i];
        // equatorial cartesian
        SwephCotransUtils::swi_coortrf2_ptr($ndp->xreturn, 6,
            $ndp->xreturn, 18, -$oe->seps, $oe->ceps);
        if ($iflag & SweConst::SEFLG_SPEED)
            SwephCotransUtils::swi_coortrf2_ptr($ndp->xreturn, 9,
                $ndp->xreturn, 21, -$oe->seps, $oe->ceps);
        $ndp->teval = $tjd;
        $ndp->xflgs = $iflag;
        $ndp->iephe = $iflag & Sweph::SEFLG_EPHMASK;
        if ($iflag & SweConst::SEFLG_SIDEREAL) {
            // apogee is referred to t;
            // the ecliptic position must be transformed to t0

            // rigorous algorithm
            if (($this->parent->getSwePhp()->swed->sidd->sid_mode & SweConst::SE_SIDBIT_ECL_T0) ||
                ($this->parent->getSwePhp()->swed->sidd->sid_mode & SweConst::SE_SIDBIT_SSY_PLANE)) {
                for ($i = 0; $i <= 5; $i++)
                    $x[$i] = $ndp->xreturn[18 + $i];
                // precess to J2000
                $this->parent->getSwePhp()->swephLib->swi_precess($x, $tjd, $iflag, SweConst::J_TO_J2000);
                if ($iflag & SweConst::SEFLG_SPEED)
                    $this->swi_precess_speed($x, $tjd, $iflag, SweConst::J_TO_J2000);
                if ($this->parent->getSwePhp()->swed->sidd->sid_mode & SweConst::SE_SIDBIT_ECL_T0) {
                    PointerUtils::pointer2Fn($ndp->xreturn, $ndp->xreturn, 6, 18,
                        fn(&$xout, &$xoutr) => $this->swi_trop_ra2sid_lon($x, $xout, $xoutr, $iflag));
                    // project onto solar system equator
                } else if ($this->parent->getSwePhp()->swed->sidd->sid_mode & SweConst::SE_SIDBIT_SSY_PLANE) {
                    PointerUtils::pointerFn($ndp->xreturn, 6,
                        fn(&$xout) => $this->swi_trop_ra2sid_lon_sosy($x, $xout, $iflag));
                }
                // to polar
                SwephCotransUtils::swi_cartpol_sp_ptr($ndp->xreturn, 6, $ndp->xreturn, 0);
                SwephCotransUtils::swi_cartpol_sp_ptr($ndp->xreturn, 18, $ndp->xreturn, 12);
            }
        } else if ($iflag & SweConst::SEFLG_J2000) {
            // node and apogee are referred to t;
            // the ecliptic position must be transformed to J2000
            for ($i = 0; $i <= 5; $i++)
                $x[$i] = $ndp->xreturn[18 + $i];
            // precess to J2000
            $this->parent->getSwePhp()->swephLib->swi_precess($x, $tjd, $iflag, SweConst::J_TO_J2000);
            if ($iflag & SweConst::SEFLG_SPEED)
                $this->swi_precess_speed($x, $tjd, $iflag, SweConst::J_TO_J2000);
            for ($i = 0; $i <= 5; $i++)
                $ndp->xreturn[18 + $i] = $x[$i];
            SwephCotransUtils::swi_cartpol_sp_ptr($ndp->xreturn, 18, $ndp->xreturn, 12);
            SwephCotransUtils::swi_coortrf2_ptr($ndp->xreturn, 18, $ndp->xreturn, 6,
                $this->parent->getSwePhp()->swed->oec2000->seps,
                $this->parent->getSwePhp()->swed->oec2000->ceps);
            if ($iflag & SweConst::SEFLG_SPEED)
                SwephCotransUtils::swi_coortrf2_ptr($ndp->xreturn, 21, $ndp->xreturn, 9,
                    $this->parent->getSwePhp()->swed->oec2000->seps,
                    $this->parent->getSwePhp()->swed->oec2000->ceps);
            SwephCotransUtils::swi_cartpol_sp_ptr($ndp->xreturn, 6, $ndp->xreturn, 0);
        } else {
            // tropical ecliptic positions
            // prcession has already been taken into account, but not nutation
            if (!($iflag & SweConst::SEFLG_NONUT)) {
                PointerUtils::pointerFn($ndp->xreturn, 18,
                    fn(&$xx) => $this->swi_nutate($xx, $iflag, false));
            }
            // equatorial polar
            SwephCotransUtils::swi_cartpol_sp_ptr($ndp->xreturn, 18, $ndp->xreturn, 12);
            // ecliptic cartesian
            SwephCotransUtils::swi_coortrf2_ptr($ndp->xreturn, 18, $ndp->xreturn, 6, $oe->seps, $oe->ceps);
            if ($iflag & SweConst::SEFLG_SPEED)
                SwephCotransUtils::swi_coortrf2_ptr($ndp->xreturn, 21, $ndp->xreturn, 9, $oe->seps, $oe->ceps);
            if (!($iflag & SweConst::SEFLG_NONUT)) {
                SwephCotransUtils::swi_coortrf2_ptr($ndp->xreturn, 6, $ndp->xreturn, 6, $nut->snut, $nut->cnut);
                if ($iflag & SweConst::SEFLG_SPEED)
                    SwephCotransUtils::swi_coortrf2_ptr($ndp->xreturn, 9, $ndp->xreturn, 9, $nut->snut, $nut->cnut);
            }
            // ecliptic polar
            SwephCotransUtils::swi_cartpol_sp_ptr($ndp->xreturn, 6, $ndp->xreturn, 0);
        }
        /**********************
         * radians to degrees *
         **********************/
        for ($i = 0; $i < 2; $i++) {
            $ndp->xreturn[$i] *= SweConst::RADTODEG;        // ecliptic
            $ndp->xreturn[$i + 3] *= SweConst::RADTODEG;
            $ndp->xreturn[$i + 12] *= SweConst::RADTODEG;   // equator
            $ndp->xreturn[$i + 15] *= SweConst::RADTODEG;
        }
        $ndp->xreturn[0] = SwephLib::swe_degnorm($ndp->xreturn[0]);
        $ndp->xreturn[12] = SwephLib::swe_degnorm($ndp->xreturn[12]);
        return SweConst::OK;
    }

    /* transforms the position of the moon in a way we can use it
     * for calculation of osculating node and apogee:
     * precession and nutation (attention to speed vector!)
     * according to flags
     * iflag	flags
     * tjd          time for which the element is computed
     *              i.e. date of ecliptic
     * xx           array equatorial cartesian position and speed
     * serr         error string
     */
    function swi_plan_for_osc_elem(int $iflag, float $tjd, array &$xx): int
    {
        $nuttmp = new nut();
        $nut =& $nuttmp;      // dummy assign, to silence gcc warning
        $oe =& $this->parent->getSwePhp()->swed->oec;
        $oectmp = new epsilon();
        // ICRS to J2000
        if (!($iflag & SweConst::SEFLG_ICRS) && $this->parent->swi_get_denum(SweConst::SEI_SUN, $iflag) >= 403) {
            $this->parent->getSwePhp()->swephLib->swi_bias($xx, $tjd, $iflag, false);
        }
        /************************************************
         * precession, equator 2000 -> equator of date  *
         * attention: speed vector has to be rotated,   *
         * but daily precession 0.137" may not be added!*/
        // TODO: SID_TNODE_FROM_ECL_T0
        $this->parent->getSwePhp()->swephLib->swi_precess($xx, $tjd, $iflag, SweConst::J2000_TO_J);
        PointerUtils::pointerFn($xx, 3,
            fn(&$R) => $this->parent->getSwePhp()->swephLib->swi_precess($R, $tjd, $iflag, SweConst::J2000_TO_J));
        // epsilon
        if ($tjd == $this->parent->getSwePhp()->swed->oec->teps) {
            $oe =& $this->parent->getSwePhp()->swed->oec;
        } else if ($tjd == Sweph::J2000) {
            $oe =& $this->parent->getSwePhp()->swed->oec2000;
        } else {
            $this->calc_epsilon($tjd, $iflag, $oectmp);
            $oe =& $oectmp;
        }
        // TODO: SID_TNODE_FROM_ECL_T0
        /************************************************
         * nutation                                     *
         * again: speed vector must be rotated, but not *
         * added 'speed' of nutation                    *
         ************************************************/
        if (!($iflag & SweConst::SEFLG_NONUT)) {
            if ($tjd == $this->parent->getSwePhp()->swed->nut->tnut) {
                $nutp =& $this->parent->getSwePhp()->swed->nut;
            } else if ($tjd == Sweph::J2000) {
                $nutp =& $this->parent->getSwePhp()->swed->nut2000;
            } else if ($tjd == $this->parent->getSwePhp()->swed->nutv->tnut) {
                $nutp =& $this->parent->getSwePhp()->swed->nutv;
            } else {
                $nutp =& $nuttmp;
                $this->parent->getSwePhp()->swephLib->swi_nutation($tjd, $iflag, $nutp->nutlo);
                $nutp->tnut = $tjd;
                $nutp->snut = sin($nutp->nutlo[1]);
                $nutp->cnut = cos($nutp->nutlo[1]);
                $this->nut_matrix($nutp, $oe);
            }
            for ($i = 0; $i <= 2; $i++) {
                $x[$i] = $xx[0] * $nutp->matrix[0][$i] +
                    $xx[1] * $nutp->matrix[1][$i] +
                    $xx[2] * $nutp->matrix[2][$i];
            }
            // speed:
            // rotation only
            for ($i = 0; $i <= 2; $i++) {
                $x[$i + 3] = $xx[3] * $nutp->matrix[0][$i] +
                    $xx[4] * $nutp->matrix[1][$i] +
                    $xx[5] * $nutp->matrix[2][$i];
            }
            for ($i = 0; $i <= 5; $i++)
                $xx[$i] = $x[$i];
        }
        /************************************************
         * transformation to ecliptic                   *
         ************************************************/
        SwephCotransUtils::swi_coortrf2($xx, $xx, $oe->seps, $oe->ceps);
        SwephCotransUtils::swi_coortrf2_ptr($xx, 3, $xx, 3, $oe->seps, $oe->ceps);
        // TODO: SID_TNODE_FROM_ECL_T0
        if (!($iflag & SweConst::SEFLG_NONUT)) {
            SwephCotransUtils::swi_coortrf2($xx, $xx, $nutp->snut, $nutp->cnut);
            SwephCotransUtils::swi_coortrf2_ptr($xx, 3, $xx, 3, $nutp->snut, $nutp->cnut);
        }
        return SweConst::OK;
    }

    static array $eff_arr = [];

    static function init_eff_arr(): void
    {
        self::$eff_arr = [
            /*
               * r , m_eff for photon passing the sun at min distance r (fraction of Rsun)
               * the values where computed with sun_model.c, which is a classic
               * treatment of a photon passing a gravity field, multiplied by 2.
               * The sun mass distribution m(r) is from Michael Stix, The Sun, p. 47.
               */
            new meff_ele(1.000, 1.000000),
            new meff_ele(0.990, 0.999979),
            new meff_ele(0.980, 0.999940),
            new meff_ele(0.970, 0.999881),
            new meff_ele(0.960, 0.999811),
            new meff_ele(0.950, 0.999724),
            new meff_ele(0.940, 0.999622),
            new meff_ele(0.930, 0.999497),
            new meff_ele(0.920, 0.999354),
            new meff_ele(0.910, 0.999192),
            new meff_ele(0.900, 0.999000),
            new meff_ele(0.890, 0.998786),
            new meff_ele(0.880, 0.998535),
            new meff_ele(0.870, 0.998242),
            new meff_ele(0.860, 0.997919),
            new meff_ele(0.850, 0.997571),
            new meff_ele(0.840, 0.997198),
            new meff_ele(0.830, 0.996792),
            new meff_ele(0.820, 0.996316),
            new meff_ele(0.810, 0.995791),
            new meff_ele(0.800, 0.995226),
            new meff_ele(0.790, 0.994625),
            new meff_ele(0.780, 0.993991),
            new meff_ele(0.770, 0.993326),
            new meff_ele(0.760, 0.992598),
            new meff_ele(0.750, 0.991770),
            new meff_ele(0.740, 0.990873),
            new meff_ele(0.730, 0.989919),
            new meff_ele(0.720, 0.988912),
            new meff_ele(0.710, 0.987856),
            new meff_ele(0.700, 0.986755),
            new meff_ele(0.690, 0.985610),
            new meff_ele(0.680, 0.984398),
            new meff_ele(0.670, 0.982986),
            new meff_ele(0.660, 0.981437),
            new meff_ele(0.650, 0.979779),
            new meff_ele(0.640, 0.978024),
            new meff_ele(0.630, 0.976182),
            new meff_ele(0.620, 0.974256),
            new meff_ele(0.610, 0.972253),
            new meff_ele(0.600, 0.970174),
            new meff_ele(0.590, 0.968024),
            new meff_ele(0.580, 0.965594),
            new meff_ele(0.570, 0.962797),
            new meff_ele(0.560, 0.959758),
            new meff_ele(0.550, 0.956515),
            new meff_ele(0.540, 0.953088),
            new meff_ele(0.530, 0.949495),
            new meff_ele(0.520, 0.945741),
            new meff_ele(0.510, 0.941838),
            new meff_ele(0.500, 0.937790),
            new meff_ele(0.490, 0.933563),
            new meff_ele(0.480, 0.928668),
            new meff_ele(0.470, 0.923288),
            new meff_ele(0.460, 0.917527),
            new meff_ele(0.450, 0.911432),
            new meff_ele(0.440, 0.905035),
            new meff_ele(0.430, 0.898353),
            new meff_ele(0.420, 0.891022),
            new meff_ele(0.410, 0.882940),
            new meff_ele(0.400, 0.874312),
            new meff_ele(0.390, 0.865206),
            new meff_ele(0.380, 0.855423),
            new meff_ele(0.370, 0.844619),
            new meff_ele(0.360, 0.833074),
            new meff_ele(0.350, 0.820876),
            new meff_ele(0.340, 0.808031),
            new meff_ele(0.330, 0.793962),
            new meff_ele(0.320, 0.778931),
            new meff_ele(0.310, 0.763021),
            new meff_ele(0.300, 0.745815),
            new meff_ele(0.290, 0.727557),
            new meff_ele(0.280, 0.708234),
            new meff_ele(0.270, 0.687583),
            new meff_ele(0.260, 0.665741),
            new meff_ele(0.250, 0.642597),
            new meff_ele(0.240, 0.618252),
            new meff_ele(0.230, 0.592586),
            new meff_ele(0.220, 0.565747),
            new meff_ele(0.210, 0.537697),
            new meff_ele(0.200, 0.508554),
            new meff_ele(0.190, 0.478420),
            new meff_ele(0.180, 0.447322),
            new meff_ele(0.170, 0.415454),
            new meff_ele(0.160, 0.382892),
            new meff_ele(0.150, 0.349955),
            new meff_ele(0.140, 0.316691),
            new meff_ele(0.130, 0.283565),
            new meff_ele(0.120, 0.250431),
            new meff_ele(0.110, 0.218327),
            new meff_ele(0.100, 0.186794),
            new meff_ele(0.090, 0.156287),
            new meff_ele(0.080, 0.128421),
            new meff_ele(0.070, 0.102237),
            new meff_ele(0.060, 0.077393),
            new meff_ele(0.050, 0.054833),
            new meff_ele(0.040, 0.036361),
            new meff_ele(0.030, 0.020953),
            new meff_ele(0.020, 0.009645),
            new meff_ele(0.010, 0.002767),
            new meff_ele(0.000, 0.000000)
        ];
    }

    function meff(float $r): float
    {
        if ($r <= 0) return 0.0;
        elseif ($r >= 1) return 1.0;
        for ($i = 0; self::$eff_arr[$i]->r > $r; $i++)
            ;
        $f = ($r - self::$eff_arr[$i - 1]->r) / (self::$eff_arr[$i]->r - self::$eff_arr[$i - 1]->r);
        $m = self::$eff_arr[$i - 1]->m + $f * (self::$eff_arr[$i]->m - self::$eff_arr[$i - 1]->m);
        return $m;
    }

    function denormalize_positions(array &$x0, array $x1, array &$x2): void
    {
        // x*[0] = ecliptic longitude, x*[12] = rectascension
        for ($i = 0; $i <= 12; $i += 12) {
            if ($x1[$i] - $x0[$i] < -180)
                $x0[$i] -= 360;
            if ($x1[$i] - $x0[$i] > 180)
                $x0[$i] += 360;
            if ($x1[$i] - $x2[$i] < -180)
                $x2[$i] -= 360;
            if ($x1[$i] - $x2[$i] > 180)
                $x2[$i] += 360;
        }
    }

    function calc_speed(array $x0, array &$x1, array &$x2, float $dt): void
    {
        for ($j = 0; $j <= 18; $j += 6) {
            for ($i = 0; $i < 3; $i++) {
                $k = $j + $i;
                $b = ($x2[$k] - $x0[$k]) / 2;
                $a = ($x2[$k] - $x0[$k]) / 2 - $x1[$k];
                $x1[$k + 3] = (2 * $a + $b) / $dt;
            }
        }
    }

    function swi_check_ecliptic(float $tjd, int $iflag): void
    {
        $swed =& $this->parent->getSwePhp()->swed;

        if ($swed->oec2000->teps != Sweph::J2000) {
            $this->calc_epsilon(Sweph::J2000, $iflag, $swed->oec2000);
        }
        if ($tjd == Sweph::J2000) {
            $swed->oec->teps = $swed->oec2000->teps;
            $swed->oec->eps = $swed->oec2000->eps;
            $swed->oec->seps = $swed->oec2000->seps;
            $swed->oec->ceps = $swed->oec2000->ceps;
            return;
        }
        if ($swed->oec->teps != $tjd || $tjd == 0) {
            $this->calc_epsilon($tjd, $iflag, $swed->oec);
        }
    }

    /* computes nutation, if it is wanted and has not yet been computed.
     * if speed flag has been turned on since last computation,
     * nutation is recomputed */
    function swi_check_nutation(float $tjd, int $iflag): void
    {
        $swed =& $this->parent->getSwePhp()->swed;

        static $nutflag = 0;
        $speedf1 = $nutflag & SweConst::SEFLG_SPEED;
        $speedf2 = $iflag & SweConst::SEFLG_SPEED;
        if (!($iflag & SweConst::SEFLG_NONUT) &&
            ($tjd != $swed->nut->tnut || $tjd == 0) ||
            (!$speedf1 && $speedf2)) {
            $this->parent->getSwePhp()->swephLib->swi_nutation($tjd, $iflag, $swed->nut->nutlo);
            $swed->nut->tnut = $tjd;
            $swed->nut->snut = sin($swed->nut->nutlo[1]);
            $swed->nut->cnut = cos($swed->nut->nutlo[1]);
            $nutflag = $iflag;
            $this->nut_matrix($swed->nut, $swed->oec);
            if ($iflag & SweConst::SEFLG_SPEED) {
                // once more for 'speed' of nutation, which is needed for
                // planetary speeds
                $t = $tjd - Sweph::NUT_SPEED_INTV;
                $this->parent->getSwePhp()->swephLib->swi_nutation($t, $iflag, $swed->nutv->nutlo);
                $swed->nutv->tnut = $t;
                $swed->nutv->snut = sin($swed->nutv->nutlo[1]);
                $swed->nutv->cnut = cos($swed->nutv->nutlo[1]);
                $this->nut_matrix($swed->nutv, $swed->oec);
            }
        }
    }

    /* function
     * - corrects nonsensical iflags
     * - completes incomplete iflags
     */
    function plaus_iflag(int $iflag, int $ipl, float $tjd, ?string &$serr = null): int
    {
        $epheflag = 0;
        $jplhor_model = $this->parent->getSwePhp()->swed->astro_models[SweModel::MODEL_JPLHOR_MODE->value];
        $jplhora_model = $this->parent->getSwePhp()->swed->astro_models[SweModel::MODEL_JPLHORA_MODE->value];
        if ($jplhor_model == 0) $jplhor_model = SweModelJPLHorizon::default();
        if ($jplhora_model == 0) $jplhora_model = SweModelJPLHorizonApprox::default();
        // either Horizons mode or simplified Horizons mode, not both
        if ($iflag & SweConst::SEFLG_JPLHOR)
            $iflag &= ~SweConst::SEFLG_JPLHOR_APPROX;
        // if topocentric bit, turn helio- and barycentric bits off;
        //
        if ($iflag & SweConst::SEFLG_TOPOCTR) {
            $iflag = $iflag & ~(SweConst::SEFLG_HELCTR | SweConst::SEFLG_BARYCTR);
        }
        // if barycentric bit, turn heliocentric bit off
        if ($iflag & SweConst::SEFLG_BARYCTR)
            $iflag = $iflag & ~(SweConst::SEFLG_HELCTR);
        if ($iflag & SweConst::SEFLG_HELCTR)
            $iflag = $iflag & ~(SweConst::SEFLG_BARYCTR);
        // if heliocentric bit, turn aberration and deflection off
        if ($iflag & (SweConst::SEFLG_HELCTR | SweConst::SEFLG_BARYCTR))
            $iflag |= SweConst::SEFLG_NOABERR | SweConst::SEFLG_NOGDEFL;
        // if no_precession bit is set, set also no_nutation bit
        if ($iflag & SweConst::SEFLG_J2000)
            $iflag |= SweConst::SEFLG_NONUT;
        // if sidereal bit is set, set also no_nutation bit
        // also turn JPL Horizons mode off
        if ($iflag & SweConst::SEFLG_SIDEREAL) {
            $iflag |= SweConst::SEFLG_NONUT;
            $iflag = $iflag & ~(SweConst::SEFLG_JPLHOR | SweConst::SEFLG_JPLHOR_APPROX);
        }
        // if truepos is set, turn off grav. def. and aberration
        if ($iflag & SweConst::SEFLG_TRUEPOS)
            $iflag |= (SweConst::SEFLG_NOGDEFL | SweConst::SEFLG_NOABERR);
        if ($iflag & SweConst::SEFLG_MOSEPH)
            $epheflag = SweConst::SEFLG_MOSEPH;
        if ($iflag & SweConst::SEFLG_SWIEPH)
            $epheflag = SweConst::SEFLG_SWIEPH;
        if ($iflag & SweConst::SEFLG_JPLEPH)
            $epheflag = SweConst::SEFLG_JPLEPH;
        if ($epheflag == 0)
            $epheflag = SweConst::SEFLG_DEFAULTEPH;
        $iflag = ($iflag & ~Sweph::SEFLG_EPHMASK) | $epheflag;
        // SEFLG_JPLHOR only with JPL and Swiss Ephemeris
        if (!($epheflag & SweConst::SEFLG_JPLEPH))
            $iflag = $iflag & ~(SweConst::SEFLG_JPLHOR | SweConst::SEFLG_JPLHOR_APPROX);
        // planets that have no JPL Horisons mode
        if ($ipl == SwePlanet::OSCU_APOG->value || $ipl == SwePlanet::TRUE_NODE->value ||
            $ipl == SwePlanet::MEAN_APOG->value || $ipl == SwePlanet::MEAN_NODE->value ||
            $ipl == SwePlanet::INTP_APOG || $ipl == SwePlanet::INTP_PERG)
            $iflag = $iflag & ~(SweConst::SEFLG_JPLHOR | SweConst::SEFLG_JPLHOR_APPROX);
        if ($ipl >= Sweph::SE_FICT_OFFSET && $ipl <= Sweph::SE_FICT_MAX)
            $iflag = $iflag & ~(SweConst::SEFLG_JPLHOR | SweConst::SEFLG_JPLHOR_APPROX);
        // SEFLG_JPLHOR required SEFLG_ICRS, if calculated with * precession/nutation
        // IAU 1980 and corrections dpsi, deps
        if ($iflag & SweConst::SEFLG_JPLHOR) {
            if ($this->parent->getSwePhp()->swed->eop_dpsi_loaded <= 0) {
                if (isset($serr)) {
                    switch ($this->parent->getSwePhp()->swed->eop_dpsi_loaded) {
                        case 0:
                            $serr = "you did not call swe_set_jpl_file(); default to SEFLG_JPLHOR_APPROX";
                            break;
                        case -1:
                            $serr = "file eop_1962_today.txt not found; default to SEFLG_JPLHOR_APPROX";
                            break;
                        case -2:
                            $serr = "file eop_1962_today.txt corrupt; default to SEFLG_JPLHOR_APPROX";
                            break;
                        case -3:
                            $serr = "file eop_finals.txt corrupt; default to SEFLG_JPLHOR_APPROX";
                            break;
                    }
                }
                $iflag &= ~SweConst::SEFLG_JPLHOR;
                $iflag |= SweConst::SEFLG_JPLHOR_APPROX;
            }
        }
        if ($iflag & SweConst::SEFLG_JPLHOR)
            $iflag |= SweConst::SEFLG_ICRS;
        if (($iflag & SweConst::SEFLG_JPLHOR_APPROX) && $jplhora_model == SweModelJPLHorizonApprox::MOD_JPLHORA_2)
            $iflag |= SweConst::SEFLG_ICRS;
        return $iflag;
    }

    function swi_force_app_pos_etc(): void
    {
        $swed =& $this->parent->getSwePhp()->swed;
        for ($i = 0; $i < SweConst::SEI_NPLANETS; $i++)
            $swed->pldat[$i]->xflgs = -1;
        for ($i = 0; $i < SweConst::SEI_NNODE_ETC; $i++)
            $swed->nddat[$i]->xflgs = -1;
        for ($i = 0; $i < SwePlanet::count(); $i++) {
            $swed->savedat[$i]->tsave = 0;
            $swed->savedat[$i]->iflgsave = -1;
        }
    }
}

sweph_calc::init_eff_arr();