<?php

use Structs\swe_data;

class Sweph extends SweModule
{
    public swe_data $swed;

    const float J2000 = 2451545.0;

    // TODO: Review these constants
    const int SEI_FILE_PLANET = 0;
    const int SEI_FILE_MOON = 1;
    const int SEI_FILE_MAIN_AST = 2;
    const int SEI_FILE_ANY_AST = 3;
    const int SEI_FILE_FIXSTAR = 4;
    const int SEI_FILE_PLMOON = 5;

    public function __construct(SwePhp $base)
    {
        parent::__construct($base);
        $this->swed = new swe_data();
    }

    public function swi_fopen(int $ifno, string $fname, string $ephepath, ?string &$serr = null)
    {
        // TODO:
        try {
            return fopen($ephepath . $fname, 'r');
        } catch (Exception $e) {
            if ($serr)
                $serr = $e->getMessage();
            return null;
        }
    }
}