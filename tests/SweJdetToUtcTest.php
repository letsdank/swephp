<?php

namespace Tests;

use SweDate;
use Tests\Base\SweTestCase;

class SweJdetToUtcTest extends SweTestCase
{
    public function test_01()
    {
        $y = 0;
        $m = 0;
        $d = 0;
        $h = 0;
        $mi = 0;
        $s = 0.0;
        $this->swe->sweDate->swe_jdet_to_utc(2452275.5, SweDate::SE_GREG_CAL,
            $y, $m, $d, $h, $mi, $s);
        $this->assertEquals(2001, $y);
        $this->assertEquals(12, $m);
        $this->assertEquals(31, $d);
        $this->assertEquals(23, $h);
        $this->assertEquals(58, $mi);
        $this->assertEquals(55.815998911857605, $s);
    }
}