<?php

use Structs\swe_data;

class Sweph extends SweModule
{
    public swe_data $swed;

    public function __construct(SwePhp $base)
    {
        parent::__construct($base);
        $this->swed = new swe_data();
    }

    public function swi_fopen(int $ifno, string $fname, string $ephepath, ?string &$serr = null)
    {
        // TODO:
        return fopen($ephepath . $fname, 'r');
    }
}