<?php

namespace Structs;

class file_data
{
    public string $fnam = '';       // ephemeris file name
    public int $fversion = 0;       // version number of file
    public string $astnam = '';     // DE number of JPL ephemeris, which this file is derived from.
    public $fptr;                   // ephemeris file pointer
    public float $tfstart = 0;      // file may be used from this date
    public float $tfend = 0;        // through this date
    public int $iflg = 0;           // byte reorder flag and little/bigendian flag
    public array $ipl = [];         // planet numbers
}