<?php

namespace Enums;

enum SweModelSidereal: int
{
    case MOD_SIDT_IAU_1976 = 1;
    case MOD_SIDT_IAU_2006 = 2;
    case MOD_SIDT_IERS_CONV_2010 = 3;
    case MOD_SIDT_LONGTERM = 4;

    public static function count(): int
    {
        return 4; // TODO: Make it dynamic?
    }

    public static function default(): SweModelSidereal
    {
        return self::MOD_SIDT_LONGTERM;
    }
}