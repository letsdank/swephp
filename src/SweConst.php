<?php

class SweConst
{
    const int OK = 0;
    const ERR = -1;
    const AS_MAXCH = 256;

    //
    // flag bits for parameter iflag in function swe_calc()
    // The flag bits are defined in such a way that iflag = 0 delivers what one
    // usually wants:
    //    - the default ephemeris (SWISS EPHEMERIS) is used,
    //    - apparent geocentric positions referring to the true equinox of date
    //      are returned.
    // If not only coordinates, but also speed values are required, use
    // flag = SEFLG_SPEED.
    //
    // The 'L' behind the number indicates that 32-bit integers (Long) are used.
    //
    const int SEFLG_JPLEPH = 0x00000001;        // use JPL ephemeris
    const int SEFLG_SWIEPH = 0x00000002;        // use SWISSEPH ephemeris
    const int SEFLG_MOSEPH = 0x00000004;        // use Moshier ephemeris

    //
    // only used for experimenting with various JPL ephemeris files
    // which are available at Astrodienst's internal network
    //
    const int SE_DE_NUMBER = 431;
}