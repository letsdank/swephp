<?php

class SwephLib extends SweModule
{
    public function __construct(SwePhp $base)
    {
        parent::__construct($base);
    }

    public function swe_deltat_ex(float $tjd, int $iflag, ?string &$serr = null): float
    {
        // TODO:
        return 0;
    }
}