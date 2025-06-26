<?php

namespace Enums;

enum SweModelDeltaT: int
{
    case MOD_DELTAT_STEPHENSON_MORRISON_1984 = 1;
    case MOD_DELTAT_STEPHENSON_1997 = 2;
    case MOD_DELTAT_STEPHENSON_MORRISON_2004 = 3;
    case MOD_DELTAT_ESPENAK_MEEUS_2006 = 4;
    case MOD_DELTAT_STEPHENSON_ETC_2016 = 5;

    public static function default(): SweModelDeltaT
    {
        return self::MOD_DELTAT_STEPHENSON_ETC_2016;
    }
}