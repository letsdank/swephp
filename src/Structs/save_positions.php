<?php

namespace Structs;

class save_positions
{
    public int $ipl=0;
    public float $tsave = 0.;
    public int $iflgsave=0;
    // position at t = tsave,
    // in ecliptic polar (offset 0),
    //    ecliptic cartesian (offset 6),
    //    equatorial polar (offset 12),
    //   and equatorial cartesian coordinates (offset 18).
    // 6 doubles each for position and speed coordinates.
    //
    public array $xsaves = [];
}