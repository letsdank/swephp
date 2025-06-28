<?php

namespace Enums;

enum SwePlanet: int
{
    case ECL_NUT = -1;
    case SUN = 0;
    case MOON = 1;
    case MERCURY = 2;
    case VENUS = 3;
    case MARS = 4;
    case JUPITER = 5;
    case SATURN = 6;
    case URANUS = 7;
    case NEPTUNE = 8;
    case PLUTO = 9;
    case MEAN_NODE = 10;
    case TRUE_NODE = 11;
    case MEAN_APOG = 12;
    case OSCU_APOG = 13;
    case EARTH = 14;
    case CHIRON = 15;
    case PHOLUS = 16;
    case CERES = 17;
    case PALLAS = 18;
    case JUNO = 19;
    case VESTA = 20;
    case INTP_APOG = 21;
    case INTP_PERG  =  22;

    public static function count(): int
    {
        return 23; // TODO: Make it dynamic?
    }
}