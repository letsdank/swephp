<?php

namespace Structs;

class file_data
{
    public string $fnam;        // ephemeris file name
    public int $fversion;       // version number of file
    public string $astnam;      // DE number of JPL ephemeris, which this file is derived from.
    public $fptr;               // ephemeris file pointer
    public float $tfstart;      // file may be used from this date
    public float $tfend;        // through this date
    public int $iflg;           // byte reorder flag and little/bigendian flag
    public array $ipl;          // planet numbers
}