<?php

class SweConst
{
    const int OK = 0;
    const int ERR = -1;
    const int NOT_AVAILABLE = -2;
    const int BEYOND_EPH_LIMITS = -3;

    const int J_TO_J2000 = 1;
    const int J2000_TO_J = -1;

    const int AS_MAXCH = 256;

    // Mathematical constants
    const float TWOPI = 2.0 * M_PI;

    const int ENDMARK = -99;
    const float DEGTORAD = M_PI / 180.0;
    const float RADTODEG = 180.0 / M_PI;

    const int SEI_EPSILON = -2;
    const int SEI_NUTATION = -1;
    const int SEI_EMB = 0;
    const int SEI_EARTH = 0;
    const int SEI_SUN = 0;
    const int SEI_MOON = 1;
    const int SEI_MERCURY = 2;
    const int SEI_VENUS = 3;
    const int SEI_MARS = 4;
    const int SEI_JUPITER = 5;
    const int SEI_SATURN = 6;
    const int SEI_URANUS = 7;
    const int SEI_NEPTUNE = 8;
    const int SEI_PLUTO = 9;
    const int SEI_SUNBARY = 10;    /* barycentric sun */
    const int SEI_ANYBODY = 11;    /* any asteroid */
    const int SEI_CHIRON = 12;
    const int SEI_PHOLUS = 13;
    const int SEI_CERES = 14;
    const int SEI_PALLAS = 15;
    const int SEI_JUNO = 16;
    const int SEI_VESTA = 17;

    const int SEI_NPLANETS = 18;

    const int SEI_MEAN_NODE = 0;
    const int SEI_TRUE_NODE = 1;
    const int SEI_MEAN_APOG = 2;
    const int SEI_OSCU_APOG = 3;
    const int SEI_INTP_APOG = 4;
    const int SEI_INTP_PERG = 5;

    const int SEI_NNODE_ETC = 6;

    const int SEI_FLG_HELIO = 1;
    const int SEI_FLG_ROTATE = 2;
    const int SEI_FLG_ELLIPSE = 4;
    // TRUE, if heliocentric earth is given instead of barycentric
    // i.e. bary sun is computed from barycentric and heliocentric earth
    const int SEI_FLG_EMBHEL = 8;

    const int SEI_FILE_PLANET = 0;
    const int SEI_FILE_MOON = 1;
    const int SEI_FILE_MAIN_AST = 2;
    const int SEI_FILE_ANY_AST = 3;
    const int SEI_FILE_FIXSTAR = 4;
    const int SEI_FILE_PLMOON = 5;

    // Chiron's orbit becomes chaotic
    // before 720 AD and after 4606 AD, because of close encounters
    // with Saturn. Accepting a maximum error of 5 degrees,
    // the ephemeris is good between the following dates:
    // const float CHIRON_START = 1958470.5;    // 1.1.650 old limit until v. 2.00
    const float CHIRON_START = 1967601.5;       // 1.1.675
    const float CHIRON_END = 3419437.5;         // 1.1.4650

    // Pholus's orbit is unstable as well, because he sometimes
    // approaches Saturn.
    // Accepting a maximum error of 5 degrees,
    // the ephemeris is good after the following date:
    //
    const float PHOLUS_START = 640648.5;        // 1.1.-2958 jul
    const float PHOLUS_END = 4390617.5;         // 1.1.7309

    const float MOSHPLEPH_START = 625000.5;
    const float MOSHPLEPH_END = 2818000.5;
    const float MOSHLUEPH_START = 625000.5;
    const float MOSHLUEPH_END = 2818000.5;
    // const float MOSHNDEPH_START = -254900.5; // 14 Feb -5410 00:00 ET jul.cal.
    // const float MOSHNDEPH_END = 3697000.5;   // 11 Dec 5409 00:00 ET, greg. cal
    const float MOSHNDEPH_START = -3100015.5;   // 15 Aug -13200 00:00 ET jul.cal.
    const float MOSHNDEPH_END = 8000016.5;      // 15 Mar 17191 00:00 ET, greg. cal

    const float JPL_DE431_START = -3027215.5;
    const float JPL_DE431_END = 7930192.5;

    const int DEG = 360000;     // degree expressed in centiseconds
    const int DEG7_30 = 2700000;// 7.5 degrees
    const int DEG15 = 15 * self::DEG;
    const int DEG24 = 24 * self::DEG;
    const int DEG30 = 30 * self::DEG;
    const int DEG60 = 60 * self::DEG;
    const int DEG90 = 90 * self::DEG;
    const int DEG120 = 120 * self::DEG;
    const int DEG150 = 150 * self::DEG;
    const int DEG180 = 180 * self::DEG;
    const int DEG270 = 270 * self::DEG;
    const int DEG360 = 360 * self::DEG;

    const float CSTORAD = (self::DEGTORAD / 360000.0);
    const float RADTOCS = (self::RADTODEG * 360000.0);

    //
    // flag bits for parameter iflag in function swe_calc()
    // The flag bits are defined in such a way that iflag = 0 delivers what one
    // usually wants:
    //    - the default ephemeris (SWISS EPHEMERIS) is used,
    //    - apparent geocentric positions referring to the true equinox of date
    //      are returned.
    // If not only coordinates, but also speed values are required, use
    // flag = SEFLG_SPEED.
    //
    // The 'L' behind the number indicates that 32-bit integers (Long) are used.
    //
    const int SEFLG_JPLEPH = 0x00000001;            // use JPL ephemeris
    const int SEFLG_SWIEPH = 0x00000002;            // use SWISSEPH ephemeris
    const int SEFLG_MOSEPH = 0x00000004;            // use Moshier ephemeris

    const int SEFLG_HELCTR = 0x00000008;            // heliocentric position
    const int SEFLG_TRUEPOS = 0x00000010;           // true/geometric position, not apparent position
    const int SEFLG_J2000 = 0x00000020;             // no precession, i.e. give J2000 equinox
    const int SEFLG_NONUT = 0x00000040;             // no nutation, i.e. mean equinox of date
    // speed from 3 positions (do not use it, SEFLG_SPEED is faster and more precise.)
    const int SEFLG_SPEED3 = 0x00000080;
    const int SEFLG_SPEED = 0x00000100;             // high precision speed
    const int SEFLG_NOGDEFL = 0x00000200;           // turn off gravitational deflection
    const int SEFLG_NOABERR = 0x00000400;           // turn off 'annual' aberration of light
    // astrometric position, i.e. with light-time, but without aberration and light deflection
    const int SEFLG_ASTROMETRIC = (self::SEFLG_NOABERR | self::SEFLG_NOGDEFL);
    const int SEFLG_EQUATORIAL = 0x00000800;        // equatorial positions are wanted
    const int SEFLG_XYZ = 0x00001000;               // cartesian, not polar, coordinates
    const int SEFLG_RADIANS = 0x00002000;           // coordinates in radians, not degrees
    const int SEFLG_BARYCTR = 0x00004000;           // barycentric position
    const int SEFLG_TOPOCTR = 0x00008000;           // topocentric position
    // used for Astronomical Almanac mode in calculation of Kepler ellipses
    const int SEFLG_ORBEL_AA = self::SEFLG_TOPOCTR;
    const int SEFLG_TROPICAL = 0;                   // tropical position (default)
    const int SEFLG_SIDEREAL = 0x00010000;          // sidereal position
    const int SEFLG_ICRS = 0x00020000;              // ICRS (DE406 reference frame)
    const int SEFLG_DPSIDEPS_1980 = 0x00040000;     // reproduce JPL horizons 1962 - today to 0.002 arcsec.
    const int SEFLG_JPLHOR = self::SEFLG_DPSIDEPS_1980;
    const int SEFLG_JPLHOR_APPROX = 0x00080000;     // approximate JPL horizons 1962 - today
    // calculate position of center of body (COB) of planet, not barycenter of its system
    const int SEFLG_CENTER_BODY = 0x00100000;
    // test raw data in files sepm9*
    const int SEFLG_TEST_PLMOON = 0x00200000 | self::SEFLG_J2000 | self::SEFLG_ICRS | self::SEFLG_HELCTR | self::SEFLG_TRUEPOS;

    const int SE_SIDBITS = 256;
    // for projection onto ecliptic of t0
    const int SE_SIDBIT_ECL_T0 = 256;
    // for projection onto solar system plane
    const int SE_SIDBIT_SSY_PLANE = 512;
    // with user-defined ayanamsha, t0 is UT
    const int SE_SIDBIT_USER_UT = 1024;
    // ayanamsha measured on ecliptic of date;
    // see commentaries in Sweph:swi_get_ayanamsa_ex().
    const int SE_SIDBIT_ECL_DATE = 2048;
    // test feature: don't apply constant offset to ayanamsha
    // see commentary above Sweph:get_aya_correction()
    const int SE_SIDBIT_NO_PREC_OFFSET = 4096;
    // test feature: calculate ayanamsha using its original precession model
    const int SE_SIDBIT_PREC_ORIG = 8192;


    // default ephemeris used when no ephemeris flagbit is set
    const int SEFLG_DEFAULTEPH = self::SEFLG_SWIEPH;

    //
    // only used for experimenting with various JPL ephemeris files
    // which are available at Astrodienst's internal network
    //
    const int SE_DE_NUMBER = 431;
    const string SE_FNAME_DE200 = "de200.eph";
    const string SE_FNAME_DE403 = "de403.eph";
    const string SE_FNAME_DE404 = "de404.eph";
    const string SE_FNAME_DE405 = "de405.eph";
    const string SE_FNAME_DE406 = "de406.eph";
    const string SE_FNAME_DE431 = "de431.eph";
    const string SE_FNAME_DFT = self::SE_FNAME_DE431;
    const string SE_FNAME_DFT2 = self::SE_FNAME_DE406;
    const string SE_STARFILE_OLD = "fixstars.cat";
    const string SE_STARFILE = "sefstars.txt";
    const string SE_ASTNAMFILE = "seasnam.txt";
    const string SE_FICTFILE = "seorbel.txt";
    const string SE_FILE_SUFFIX = "se1";

    // dpsi and deps loaded for 100 years after 1962
    const int SWE_DATA_DPSI_DEPS = 36525;
}