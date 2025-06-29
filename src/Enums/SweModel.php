<?php

namespace Enums;

enum SweModel: int
{
    case MODEL_DELTAT = 0;
    case MODEL_PREC_LONGTERM = 1;
    case MODEL_PREC_SHORTTERM = 2;
    case MODEL_NUT = 3;
    case MODEL_BIAS = 4;
    case MODEL_JPLHOR_MODE = 5;
    case MODEL_JPLHORA_MODE = 6;
    case MODEL_SIDT = 7;

    public static function count(): int
    {
        return 8; // TODO: Dynamic?
    }
}