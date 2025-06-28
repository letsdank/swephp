<?php

namespace Enums;

enum SweModelNutation: int
{
    case MOD_NUT_IAU_1980 = 1;
    /** Herring's (1987) corrections to IAU 1980 nutation series. AA (1996) neglects them. */
    case MOD_NUT_IAU_CORR_1987 = 2;
    /** very time-consuming ! */
    case MOD_NUT_IAU_2000A = 3;
    /** fast, but precision of milli-arcsec */
    case MOD_NUT_IAU_2000B = 4;
    case MOD_NUT_WOOLARD = 5;

    public static function count(): int
    {
        return 5; // TODO: Make it dynamic?
    }

    public static function default(): SweModelNutation
    {
        return self::MOD_NUT_IAU_2000B;
    }
}