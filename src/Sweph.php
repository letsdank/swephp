<?php

use Enums\SweModel;
use Enums\SweModelNutation;
use Enums\SweModelPrecession;
use Enums\SwePlanet;
use Enums\SweSiderealMode;
use Enums\SweTidalAccel;
use Structs\aya_init;
use Structs\epsilon;
use Structs\file_data;
use Structs\nut;
use Structs\plan_data;
use Structs\save_positions;
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

    // node of ecliptic measured on ecliptic 2000
    const float SSY_PLANE_NODE_E2000 = (107.582569 * SweConst::DEGTORAD);
    // node of ecliptic measured on solar system rotation plane
    const float SSY_PLANE_NODE = (107.58883388 * SweConst::DEGTORAD);
    // inclination of ecliptic against solar system rotation plane
    const float SSY_PLANE_INCL = (1.578701 * SweConst::DEGTORAD);

    const float KM_S_TO_AU_CTY = 21.095;                // km/s to AU/century
    const float MOON_SPEED_INTV = 0.00005;              // 4.32 seconds (in days)
    const float PLAN_SPEED_INTV = 0.0001;               // 8.64 seconds (in days)
    const float MEAN_NODE_SPEED_INTV = 0.001;
    const float NODE_CALC_INTV = 0.0001;
    const float NODE_CALC_INTV_MOSH = 0.1;
    const float NUT_SPEED_INTV = 0.0001;
    const float DEFL_SPEED_INTV = 0.0000005;

    const float SE_LAPSE_RATE = 0.0065;                 // deg K / m, for refraction

    public static function square_sum(array $x): float
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

    static array $ayanamsa = [];

    static function init_ayanamsa()
    {
        self::$ayanamsa = [
            /* 0: Fagan/Bradley (Default)
                "The American Sidereal Ephemeris, 1976-2000" (Astro Computing Services, 1981)
                states on S.V.P. ("Synetic Vernal Point"):
                "The S.V.P. is the Sidereal longitude of the Vernal Equinox (the
                Tropical zero-point) in the Fagan-Bradley school of Western Sidereal
                astrology. It was determined empirically, its mean value being defined
                as 335°57'28".64 for the epoch 1950.0."
                Fagan/Firebrace, "Primer of Sidereal Astrology", p. 13:
                "It was during 1957 that Garth Allen .... experimenting ... But when
                progressed for the dates of the calamities, all were found by him to be
                slightly out, the mean error being equivalent to an increase of 0°06'05"
                in the then-adopted sidereal longitude of the vernal point, determined
                from Spica in 29 Virgo (i.e. 29°06'05" Virgo; D.K.), and the proper motion
                having been allowed for. In short, for the epoch 1950.0 he proposed as the
                mean longitude of the vernal point 335°57'28.64", proper motion being
                disregarded."
                If "1950.0" means the standard epoch B1950 = JD 2433282.423, and based
                on the then-used precession model of Newcomb, this ayanamsha leads to
                a true position of 29°06'05.965" Virgo, based on Hipparcos position of
                the star. */
            new aya_init(2433282.42346, 24.042044444, FALSE, SweModelPrecession::MOD_PREC_NEWCOMB->value), // 0: Fagan/Bradley
            /*************************/
            /* 1: Standard Lahiri
                 according to program NOVA by Robert Hand:
                 {J1900, 360 - 337.53953},
                 This corresponds to an ayanamsha 22°27'37.69 as given in
                 Indian Ephemeris and Nautical Almanac" 1965, p. 459.
                 Note, however, this value should only with a precession formula
                 where T is measured in tropical centuries. Swiss Ephemeris always
                 uses Julian centuries.
                 The following definition is according to:
                 Calendar Reform Committee 1956; the subtracted value is nutation:
                 {2435553.5, 23.25 - 0.00464207, FALSE},
                 Lahiri (derived from: Indian Astronomical Ephemeris 1989, p. 556;
                 the subtracted value is nutation, according to Wahr 1980) */
            new aya_init(2435553.5, 23.250182778 - 0.004658035, FALSE, SweModelPrecession::MOD_PREC_IAU_1976->value), // 1: Lahiri
            /*************************/
            /* 2: Robert DeLuce (Constellational Astrology ... p. 5;
                 birth of Jesus assumed on 1 Jan 1 BC (= 0) jul.,
                 based on Newcomb precession.
                 {J1900, 360 - 333.58695, FALSE, 0},
                 Ayanamsha was corrected with SE 2.09 as follows:
                 Started at zero ayanamsha epoch with value 0 and
                 run with standard precession.
                 This makes a difference of 22" compared with previous version: */
            new aya_init(1721057.5, 0, TRUE, 0), // 2: DeLuce
            /*************************/
            /* 3: B.V. Raman (Robert Hand)
                 See B.V. Raman, "Hindu Predictive Astrology" (1938, Introduction),
                 pp. 279, 287.
                 This ayanamsha is apparently not based on a valid precession theory (e.g.
                 Newcomb). We cannot reproduce precisely the ayanamsha values on p. 287. */
            new aya_init(Sweph::J1900, 360 - 338.98556, FALSE, SweModelPrecession::MOD_PREC_NEWCOMB->value), // 3: Raman
            /*************************/
            /* 4: Usha/Shashi (Robert Hand)
                 Usha and Shashi, "Hindu Astrological Calculations" (1978,
                 Sagar Publications, New Delhi).
                 We do not have this book. */
            new aya_init(Sweph::J1900, 360 - 341.33904, FALSE, -1), // 4: Usha/Shashi
            /*************************/
            /* 5: Krishnamurti (Robert Hand)
                 K.S. Krishnamurti, "Reader 1", pp. 55-59.
                 Autor does not give precise information. Zero ayanamsha year is said to
                 be 291 CE, and there is an ayanamsha table with arc min precision for
                 1840 to 2000 on p. 58.
                 This ayanamsha reproduces the table quite well, if 1 Jan of each year
                 is taken. (Turn off Newcomb precession in order to verify.)
                 However, D. Senthilathiban believes the values are given for the date
                 of sidereal Aries ingress of each year. ("Study of KP Ayanamsa with
                 Modern Precession Theories", pp. 126f. */
            new aya_init(Sweph::J1900, 360 - 337.636111, FALSE, SweModelPrecession::MOD_PREC_NEWCOMB->value), // 5: Krishnamurti
            /*************************/
            /* 6: Djwhal Khool (Graham Dawson),
                 "Channeled" information: Aquarius ingress of VP on 1 July 2117
                 See Philipp Lindsay, “The Beginning of the Age of Aquarius: 2,117 A.D.”,
                 http://esotericastrologer.org/newsletters/the-age-of-aquarius-ray-and-zodiac-cycles/ */
            /*************************/
            new aya_init(Sweph::J1900, 360 - 333.0369024, FALSE, 0), // 6: Djwhal Khool
            /* 7: Shri Yukteshwar; (David Cochrane)
                 This ayanamsha seems to be wrong.
                 Swami Sri Yukteswar, "The Holy Science", 1920 (1949, 1957 and 1977,
                 partly revised), Yogoda Satsanga Society of India.
                 Ayanamsha on the spring equinox 1893 was 20°54'36" (1894 according to
                 the revised edition of 1977) At the same time he believed that this was the
                 distance of the spring equinox from the star Revati, which he put at the
                 initial point of Aries.  Unfortunately, this is wrong, because on that date
                 Revati was actually 18°23' away from the vernal point. The error is
                 explained from the fact that Yukteshwar used the zero ayanamsha year 499 CE
                 and an inaccurate Suryasiddhantic precession rate of 360°/24'000 years = 54
                 arcsec/year. It is obvious that Yukteshwar actually intended an ayanamsha
                 that starts at the star Revati.  */
            new aya_init(Sweph:: J1900, 360 - 338.917778, FALSE, -1),  // 7: Shri Yukteshwar
            //{2412543.5, 20.91, TRUE, -1},        // 7: Shri Yukteshwar; (Holy Science, p. xx)
            /*************************/
            /* 8: J.N. Bhasin; (David Cochrane)
                 We don't have any sources or detailed information about this ayanamsha. */
            new aya_init(Sweph::J1900, 360 - 338.634444, FALSE, -1), // 8: Bhasin
            /*************************/
            /* 14 Sept. 2018: the following three ayanamshas have been wrong for
               many years */
            /* 9 - 11: Babylonian, Kugler */
            new aya_init(1684532.5, -5.66667, TRUE, -1),     //  9: Babylonian, Kugler 1
            new aya_init(1684532.5, -4.26667, TRUE, -1),     // 10: Babylonian, Kugler 2
            new aya_init(1684532.5, -3.41667, TRUE, -1),     // 11: Babylonian, Kugler 3
            /*************************/
            /* 12: Babylonian, Huber
                  P. Huber, "Über den Nullpunkt der babylonischen Ekliptik", in: Centaurus
                  1958, 5, p. 192-208.
                  This ayanamsha had a wrong initial value until 14 Sept. 2018. */
            new aya_init(1684532.5, -4.46667, TRUE, -1),     // 12: Baylonian, Huber
            /*************************/
            /* 13: Babylonian, Mercier; eta Piscium culminates with zero point */
            new aya_init(1673941, -5.079167, TRUE, -1),      // 13: Babylonian, Mercier
            /*************************/
            /* 14: t0 is defined by Aldebaran at 15 Taurus in year -100 */
            new aya_init(1684532.5, -4.44138598, TRUE, 0),  // 14: Babylonian/Aldebaran = 15 Tau
            /*************************/
            /* 15: Hipparchos */
            new aya_init(1674484.0, -9.33333, TRUE, -1),       // 15: Hipparchos
            /*************************/
            /* 16: Sassanian */
            new aya_init(1927135.8747793, 0, TRUE, -1),      // 16: Sassanian
            /*************************/
            /* 17: Galactic Center at 0 Sagittarius */
            new aya_init(0, 0, FALSE, 0),                    // 17: Galactic Center at 0 Sagittarius
            /*************************/
            /* 18: J2000 */
            new aya_init(Sweph::J2000, 0, FALSE, 0),                 // 18: J2000
            /*************************/
            /* 19: J1900 */
            new aya_init(Sweph::J1900, 0, FALSE, 0),                 // 19: J1900
            /*************************/
            /* 20: B1950 */
            new aya_init(Sweph::B1950, 0, FALSE, 0),                 // 20: B1950
            /*************************/
            /* 21: Suryasiddhanta, assuming ingress of mean Sun into Aries at point of mean
                  equinox of date on 21.3.499, near noon, Ujjain (75.7684565 E)
                  = 7:30:31.57 UT = 12:33:36 LMT*/
            new aya_init(1903396.8128654, 0, TRUE, 0),         // 21: Suryasiddhanta
            /*************************/
            /* 22: Suryasiddhanta, assuming ingress of mean Sun into Aries at true position
                  of mean Sun at same epoch */
            new aya_init(1903396.8128654, -0.21463395, TRUE, 0), // 22: Suryasiddhanta, mean Sun
            /*************************/
            /* 23: Aryabhata, same date, but UT 6:56:55.57 analogous to 21 */
            new aya_init(1903396.7895321, 0, TRUE, 0),         // 23: Aryabhata
            /*************************/
            /* 24: Aryabhata, analogous 22 */
            new aya_init(1903396.7895321, -0.23763238, TRUE, 0), // 24: Aryabhata, mean Sun
            /*************************/
            /* 25: Suryasiddhanta, Revati/zePsc at polar long. 359°50'*/
            new aya_init(1903396.8128654, -0.79167046, TRUE, 0), // 25: SS Revati
            /*************************/
            /* 26: Suryasiddhanta, Citra/Spica at polar long. 180° */
            new aya_init(1903396.8128654, 2.11070444, TRUE, 0), // 26: SS Citra
            /*************************/
            /* 27: True Citra (Spica exactly at 0 Libra) */
            new aya_init(0, 0, FALSE, 0),                 // 27: True Citra
            /*************************/
            /* 28: True Revati (zeta Psc exactly at 29°50' Pisces) */
            new aya_init(0, 0, FALSE, 0),                 // 28: True Revati
            /*************************/
            /* 29: True Pushya (delta Cnc exactly a 16 Cancer */
            new aya_init(0, 0, FALSE, 0),             // 29: True Pushya
            /*************************/
            /* 30: R. Gil Brand; Galactic Center at golden section between 0 Sco and 0 Aqu;
                  note: 0° Aqu/Leo is the symmetric axis of rulerships */
            new aya_init(0, 0, FALSE, 0),                    // 30: Gil Brand
            /*************************/
            /* 31: Galactic Equator IAU 1958, i.e. galactic/ecliptic intersection point
                  based on galactic coordinate system */
            new aya_init(0, 0, FALSE, 0),                 // 31: GE IAU 1958
            /*************************/
            /* 32: Galactic Equator True, i.e. galactic/ecliptic intersection point based
                 on the galactic pole as given in: Liu/Zhu/Zhang, „Reconsidering the
                 galactic coordinate system“, A & A No. AA2010, Oct. 2010 */
            new aya_init(0, 0, FALSE, 0),                 // 32: GE true
            /*************************/
            /* 33: Galactic Equator Mula, i.e. galactic/ecliptic intersection point in the
                  middle of lunar mansion Mula */
            new aya_init(0, 0, FALSE, 0),                 // 33: GE Mula
            /*************************/
            /* 34: Skydram/Galactic Alignment (R. Mardyks); autumn equinox aligned with
                  Galactic Equator/Pole */
            new aya_init(2451079.734892000, 30, FALSE, 0),   // 34: Skydram/Mardyks
            /*************************/
            /* 35: Chandra Hari */
            new aya_init(0, 0, FALSE, 0),                 // 35: Chandra Hari
            /*************************/
            /* 36: Dhruva Galactic Centre Middle of Mula (Ernst Wilhelm) */
            new aya_init(0, 0, FALSE, 0),                 // 36: Ernst Wilhelm
            /*************************/
            /* 37: Kali 3623 = 522 CE, Ujjain (75.7684565), based on Kali midnight and
                  year length of Suryasiddhanta */
            new aya_init(1911797.740782065, 0, TRUE, 0),     // 36: 0 ayanamsha in year 522
            /*************************/
            /* 38: Babylonian (Britton 2010)
                  John P. Britton, "Studies in Babylonian lunar theory: part III. The
                  introduction of the uniform zodiac", in Arch. Hist. Exact. Sci.
                  (2010)64:617-663, p. 630. */
            new aya_init(1721057.5, -3.2, TRUE, -1),         // 38: Babylonian (Britton 2010)
            /*************************/
            /* 39: Sunil Sheoran ("Vedic")
                  S. Sheoran, "The Science of Time and Timeline of World History", 2017. */
            new aya_init(0, 0, FALSE, 0),                    // 39: Sunil Sheoran ("Vedic")
            /*************************/
            /* 40: Galactic Center at 0 Capricon (Cochrane) */
            new aya_init(0, 0, FALSE, 0),                    // 40: Cochrane
            /*************************/
            /* 41: "Galactic Equatorial" (N.A. Fiorenza) */
            new aya_init(2451544.5, 25.0, TRUE, 0),          // 41: N.A. Fiorenza */
            /*************************/
            /* 42: Vettius Valens (Moon; derived from Holden 1995 p. 12 for epoch of Valens
                  1 Jan. 150 CE julian) */
            new aya_init(1775845.5, -2.9422, TRUE, -1),      // 42: Vettius Valens
            /*************************/
            /* 43: Lahiri (1940), book "Panchanga darpan":
                  22°26'45".50 + 50".25748T + 0".00011115T^2 */
            new aya_init(Sweph::J1900, 22.44597222, FALSE, SweModelPrecession::MOD_PREC_NEWCOMB->value), // 43: Lahiri (1940)
            /*************************/
            /* 44: Lahiri (VP285), mean sun at 360° in 285CE; epoch for mean sun at 0 acc.
                  to Simon 1994, corrected for Vondrak precession
                  (Preface to Lahiri's "Indian Ephemeris" 1980) */
            new aya_init(1825235.2458513028, 0.0, FALSE, 0), // 44: Lahiri VP285 (1980)
            /*************************/
            /* 45: Krishnamurti from mean equinox 291, based on Newcomb precession,
                  according to D. Senthilathiban, "Study of KP Ayanamsa with Modern
                  Precession Theories" (2019), but using precession Vondrak 2011 and
                  correction base on Newcomb precession. */
            //{1827424.752255678, 0.0, FALSE, SEMOD_PREC_NEWCOMB}, // 45: Krishnamurti VP291
            new aya_init(1827424.752255678, 0.0, FALSE, 0), // 45: Krishnamurti VP291
            /*************************/
            /* 46: Lahiri original: Calendar Reform Committee 1956,
                  before the correction by 0.658" in IAE 1985.
                  The subtracted value is nutation according to Woolard 1953.
                  However, nutation Woolard was used by IENA/IAE only from 1960 on,
                  so this value is not correct. In order to reproduce mean ayanamshas
                  of IENA >=1960, we could choose 23.25 - 0.00464207 + 0.07 / 3600.0
                  as initial value in 1956. However this will not help to reproduce
                  true ayanamshas. A deviation of around 0.1" remains,
                  for unknown reasons. The difference between Lahiri (1) and
                  Lahiri ICRC (45) amounts to 1.1". */
            new aya_init(2435553.5, 23.25 - 0.00464207, FALSE, SweModelPrecession::MOD_PREC_NEWCOMB->value), // 46: SE_SIDM_LAHIRI_ICRC
            /*************************/
        ];
    }

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
        $swed =& $this->swePhp->swed;
        // initialisation of swed, when called first time from
        if (!$swed->swed_is_initialized) {
            $swed = new swe_data();
            $swed->ephepath = "sweph/ephe/";
            $swed->jplfnam = SweConst::SE_FNAME_DFT;
            $this->swePhp->swephLib->swe_set_tid_acc(SweTidalAccel::SE_TIDAL_AUTOMATIC);
            $swed->swed_is_initialized = true;
            for ($i = 0; $i <= SweConst::SEI_NPLANETS; $i++) // "<=" is correct! see decl.
                $swed->savedat[$i] = new save_positions();
            // clean node data space
            for ($i = 0; $i < SweConst::SEI_NNODE_ETC; $i++)
                $swed->nddat[$i] = new plan_data();
            for ($i = 0; $i < SweConst::SEI_NPLANETS; $i++)
                $swed->pldat[$i] = new plan_data();
            for ($i = 0; $i < Sweph::SEI_NEPHFILES; $i++)
                $swed->fidat[$i] = new file_data();
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
        $this->swePhp->sweJPL->swi_close_jpl_file();
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
        $s1 = $ephepath;
        // According to swi_cutstr() description, there is the one-line analogue:
        $cpos = array_filter(explode(DIRECTORY_SEPARATOR, $s1));
        try {
            for ($i = 0; $i <= count($cpos); $i++) {
                $s = $cpos[$i];
                if (strcmp($s, ".") == 0) { // current directory
                    $s = '';
                } else {
                    $j = strlen($s);
                    if (!empty($s) && $s[$j - 1] != DIRECTORY_SEPARATOR)
                        $s .= DIRECTORY_SEPARATOR;
                }
                $s .= $fname;
                $fnamp = $s;
                $fp = fopen($fnamp, 'r');
                if ($fp != null) return $fp;
            }
        } catch (Exception $e) {
            $s = sprintf("SwissEph file '%s' not found in PATH '%s'", $fname, $ephepath);
            if (isset($serr)) $serr = $s;
        }
        return null;
    }

    function swi_get_denum(int $ipli, int $iflag)
    {
        $fdp = null;
        if ($iflag & SweConst::SEFLG_MOSEPH)
            return 403;
        if ($iflag & SweConst::SEFLG_JPLEPH) {
            if ($this->swePhp->swed->jpldenum > 0) {
                return $this->swePhp->swed->jpldenum;
            } else {
                return SweConst::SE_DE_NUMBER;
            }
        }
        if ($ipli > Sweph::SE_AST_OFFSET) {
            $fdp =& $this->swePhp->swed->fidat[SweConst::SEI_FILE_ANY_AST];
        } else if ($ipli > Sweph::SE_PLMOON_OFFSET) {
            $fdp =& $this->swePhp->swed->fidat[SweConst::SEI_FILE_ANY_AST];
        } else if ($ipli == SweConst::SEI_CHIRON ||
            $ipli == SweConst::SEI_PHOLUS ||
            $ipli == SweConst::SEI_CERES ||
            $ipli == SweConst::SEI_PALLAS ||
            $ipli == SweConst::SEI_JUNO ||
            $ipli == SweConst::SEI_VESTA) {
            $fdp =& $this->swePhp->swed->fidat[SweConst::SEI_FILE_MAIN_AST];
        } else if ($ipli == SweConst::SEI_MOON) {
            $fdp =& $this->swePhp->swed->fidat[SweConst::SEI_FILE_MOON];
        } else {
            $fdp =& $this->swePhp->swed->fidat[SweConst::SEI_FILE_PLANET];
        }
        if ($fdp != null) {
            if ($fdp->sweph_denum != 0) {
                return $fdp->sweph_denum;
            } else {
                return SweConst::SE_DE_NUMBER;
            }
        }
        return SweConst::SE_DE_NUMBER;
    }

    // TODO: Make as enum
    public function swe_set_sid_mode(int $sid_mode, float $t0, float $ayan_t0): void
    {
        $sip =& $this->swePhp->swed->sidd;
        $this->swi_init_swed_if_start();
        if ($sid_mode < 0)
            $sid_mode = 0;
        $sip->sid_mode = $sid_mode;
        if ($sid_mode >= SweConst::SE_SIDBITS)
            $sid_mode %= SweConst::SE_SIDBITS;
        // standard equinoxes: positions always referred to ecliptic of t0
        if ($sid_mode == SweSiderealMode::SE_SIDM_J2000->value ||
            $sid_mode == SweSiderealMode::SE_SIDM_J1900->value ||
            $sid_mode == SweSiderealMode::SE_SIDM_B1950->value ||
            $sid_mode == SweSiderealMode::SE_SIDM_GALALIGN_MARDYKS->value) {
            $sip->sid_mode = $sid_mode;
            $sip->sid_mode |= SweConst::SE_SIDBIT_ECL_T0;
        }
        if ($sid_mode == SweSiderealMode::SE_SIDM_TRUE_CITRA->value ||
            $sid_mode == SweSiderealMode::SE_SIDM_TRUE_REVATI->value ||
            $sid_mode == SweSiderealMode::SE_SIDM_TRUE_PUSHYA->value ||
            $sid_mode == SweSiderealMode::SE_SIDM_TRUE_SHEORAN->value ||
            $sid_mode == SweSiderealMode::SE_SIDM_TRUE_MULA->value ||
            $sid_mode == SweSiderealMode::SE_SIDM_GALCENT_0SAG->value ||
            $sid_mode == SweSiderealMode::SE_SIDM_GALCENT_COCHRANE->value ||
            $sid_mode == SweSiderealMode::SE_SIDM_GALCENT_RGILBRAND->value ||
            $sid_mode == SweSiderealMode::SE_SIDM_GALCENT_MULA_WILHELM->value ||
            $sid_mode == SweSiderealMode::SE_SIDM_GALEQU_IAU1958->value ||
            $sid_mode == SweSiderealMode::SE_SIDM_GALEQU_TRUE->value ||
            $sid_mode == SweSiderealMode::SE_SIDM_GALEQU_MULA->value) {
            $sip->sid_mode = $sid_mode->value;
        }
        // make sure that sid_mode is either SE_SIDM_USER or < SE_NSIDM_PREDEF
        if ($sid_mode >= SweSiderealMode::SE_NSIDM_PREDEF && $sid_mode != SweSiderealMode::SE_SIDM_USER->value)
            $sip->sid_mode = $sid_mode = SweSiderealMode::SIDM_FAGAN_BRADLEY->value;
        $this->swePhp->swed->ayana_is_set = true;
        if ($sid_mode == SweSiderealMode::SE_SIDM_USER->value) {
            $sip->t0 = $t0;
            $sip->ayan_t0 = $ayan_t0;
            $sip->t0_is_UT = false;
            if ($sip->sid_mode & SweConst::SE_SIDBIT_USER_UT)
                $sip->t0_is_UT = true;
        } else {
            $sip->t0 = self::$ayanamsa[$sid_mode]->t0;
            $sip->ayan_t0 = self::$ayanamsa[$sid_mode]->ayan_t0;
            $sip->t0_is_UT = self::$ayanamsa[$sid_mode]->t0_is_UT;
        }
        // test feature: ayanamsha using its original precession model
        if ($sid_mode < SweSiderealMode::SE_NSIDM_PREDEF &&
            ($sip->sid_mode & SweConst::SE_SIDBIT_PREC_ORIG) &&
            self::$ayanamsa[$sid_mode]->prec_offset > 0) {
            $this->swePhp->swed->astro_models[SweModel::MODEL_PREC_LONGTERM->value] = self::$ayanamsa[$sid_mode]->prec_offset;
            $this->swePhp->swed->astro_models[SweModel::MODEL_PREC_SHORTTERM->value] = self::$ayanamsa[$sid_mode]->prec_offset;
            // add a corresponding nutation model
            switch (self::$ayanamsa[$sid_mode]->prec_offset) {
                case SweModelPrecession::MOD_PREC_NEWCOMB->value:
                    $this->swePhp->swed->astro_models[SweModel::MODEL_NUT->value] = SweModelNutation::MOD_NUT_WOOLARD;
                    break;
                case SweModelPrecession::MOD_PREC_IAU_1976->value:
                    $this->swePhp->swed->astro_models[SweModel::MODEL_NUT->value] = SweModelNutation::MOD_NUT_IAU_1980;
                    break;
                default:
                    break;
            }
        }
        $this->calc->swi_force_app_pos_etc();
    }

    // the ayanamsa (precession in longitude)
    // according to Newcomb's definition: 360 -
    // longitude of the vernal point of t referred to the
    // ecliptic of t0.
    //
    public function swe_get_ayanamsa(float $tjd_et): float
    {
        $daya = .0;
        $iflag = $this->swePhp->swephLib->swi_guess_ephe_flag();
        // swi... function never includes nutation
        $this->calc->swi_get_ayanamsa_ex($tjd_et, $iflag, $daya);
        return $daya;
    }

    public function swe_get_ayanamsa_ut(float $tjd_ut): float
    {
        $daya = .0;
        $iflag = $this->swePhp->swephLib->swi_guess_ephe_flag();
        $this->calc->swi_get_ayanamsa_ex($tjd_ut +
            $this->swePhp->swephLib->swe_deltat_ex($tjd_ut, $iflag), 0, $daya);
        return $daya;
    }
}

Sweph::init_ayanamsa();