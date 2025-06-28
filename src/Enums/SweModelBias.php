<?php

namespace Enums;

enum SweModelBias: int
{
    case MOD_BIAS_NONE = 1;     // ignore frame bias
    case MOD_BIAS_IAU2000 = 2;  // use frame bias matrix IAU 2000
    case MOD_BIAS_IAU2006 = 3;  // use frame bias matrix IAU 2006

    public static function count(): int
    {
        return 3; // TODO: Make it dynamic?
    }

    public static function default(): SweModelBias
    {
        return self::MOD_BIAS_IAU2006;
    }
}