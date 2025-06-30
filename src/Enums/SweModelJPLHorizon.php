<?php

namespace Enums;

enum SweModelJPLHorizon: int
{
    // daily dpsi and deps from file are
    // limited to 1962 - today. JPL uses the
    // first and last value for all dates
    // beyond this time range.
    case MOD_JPLHOR_LONG_AGREEMENT = 1;

    public static function count(): int
    {
        return 2;
    }

    public static function default(): SweModelJPLHorizon
    {
        return self::MOD_JPLHOR_LONG_AGREEMENT;
    }
}