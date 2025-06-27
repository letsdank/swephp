<?php

namespace Enums;

enum SweModelPrecession: int
{
    case MOD_PREC_IAU_1976 = 1;
    case MOD_PREC_LASKAR_1986 = 2;
    case MOD_PREC_WILL_EPS_LASK = 3;
    case MOD_PREC_WILLIAMS_1994 = 4;
    case MOD_PREC_SIMON_1994 = 5;
    case MOD_PREC_IAU_2000 = 6;
    case MOD_PREC_BRETAGNON_2003 = 7;
    case MOD_PREC_IAU_2006 = 8;
    case MOD_PREC_VONDRAK_2011 = 9;
    case MOD_PREC_OWEN_1990 = 10;
    case MOD_PREC_NEWCOMB = 11;

    public static function count(): int
    {
        return 11; // TODO: Make it dynamic?
    }

    public static function default(): SweModelPrecession
    {
        return SweModelPrecession::MOD_PREC_VONDRAK_2011;
    }

    public static function defaultShort(): SweModelPrecession
    {
        return SweModelPrecession::MOD_PREC_VONDRAK_2011;
    }
}