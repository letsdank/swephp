<?php

namespace Enums;

enum SweSiderealMode: int
{
    case SIDM_FAGAN_BRADLEY = 0;
    case SIDM_LAHIRI = 1;
    case SIDM_DELUCE = 2;
    case SE_SIDM_RAMAN = 3;
    case SE_SIDM_USHASHASHI = 4;
    case SE_SIDM_KRISHNAMURTI = 5;
    case SE_SIDM_DJWHAL_KHUL = 6;
    case SE_SIDM_YUKTESHWAR = 7;
    case SE_SIDM_JN_BHASIN = 8;
    case SE_SIDM_BABYL_KUGLER1 = 9;
    case SE_SIDM_BABYL_KUGLER2 = 10;
    case SE_SIDM_BABYL_KUGLER3 = 11;
    case SE_SIDM_BABYL_HUBER = 12;
    case SE_SIDM_BABYL_ETPSC = 13;
    case SE_SIDM_ALDEBARAN_15TAU = 14;
    case SE_SIDM_HIPPARCHOS = 15;
    case SE_SIDM_SASSANIAN = 16;
    case SE_SIDM_GALCENT_0SAG = 17;
    case SE_SIDM_J2000 = 18;
    case SE_SIDM_J1900 = 19;
    case SE_SIDM_B1950 = 20;
    case SE_SIDM_SURYASIDDHANTA = 21;
    case SE_SIDM_SURYASIDDHANTA_MSUN = 22;
    case SE_SIDM_ARYABHATA = 23;
    case SE_SIDM_ARYABHATA_MSUN = 24;
    case SE_SIDM_SS_REVATI = 25;
    case SE_SIDM_SS_CITRA = 26;
    case SE_SIDM_TRUE_CITRA = 27;
    case SE_SIDM_TRUE_REVATI = 28;
    case SE_SIDM_TRUE_PUSHYA = 29;
    case SE_SIDM_GALCENT_RGILBRAND = 30;
    case SE_SIDM_GALEQU_IAU1958 = 31;
    case SE_SIDM_GALEQU_TRUE = 32;
    case SE_SIDM_GALEQU_MULA = 33;
    case SE_SIDM_GALALIGN_MARDYKS = 34;
    case SE_SIDM_TRUE_MULA = 35;
    case SE_SIDM_GALCENT_MULA_WILHELM = 36;
    case SE_SIDM_ARYABHATA_522 = 37;
    case SE_SIDM_BABYL_BRITTON = 38;
    case SE_SIDM_TRUE_SHEORAN = 39;
    case SE_SIDM_GALCENT_COCHRANE = 40;
    case SE_SIDM_GALEQU_FIORENZA = 41;
    case SE_SIDM_VALENS_MOON = 42;
    case SE_SIDM_LAHIRI_1940 = 43;
    case SE_SIDM_LAHIRI_VP285 = 44;
    case SE_SIDM_KRISHNAMURTI_VP291 = 45;
    case SE_SIDM_LAHIRI_ICRC = 46;
    case SE_SIDM_USER = 255; // user-defined ayanamsha, t0 is TT

    const int SE_NSIDM_PREDEF = 47;
}