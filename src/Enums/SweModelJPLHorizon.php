<?php

namespace Enums;

enum SweModelJPLHorizon: int
{
    case MOD_JPLHORA_1 = 1;
    case MOD_JPLHORA_2 = 2;
    case MOD_JPLHORA_3 = 3;

    public static function count(): int
    {
        return 3; // TODO: Make it dynamic?
    }

    public static function default(): SweModelJPLHorizon
    {
        return SweModelJPLHorizon::MOD_JPLHORA_3;
    }
}