<?php

namespace Structs;

class plantbl
{
    public function __construct(
        public array $max_harmonic = [],
        public int   $max_power_of_t = 0,
        public array &$arg_tbl = [],
        public array &$lon_tbl = [],
        public array &$lat_tbl = [],
        public array &$rad_tbl = [],
        public float $distance = 0.,
    )
    {
    }
}