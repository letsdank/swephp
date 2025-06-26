<?php

namespace Tests;

use Tests\Base\SweTestCase;

class SweUtcTimeZoneTest extends SweTestCase
{
    public function test_01()
    {
        $iyear = 0;
        $imonth = 0;
        $iday = 0;
        $ihour = 0;
        $imin = 0;
        $dsec = 0.0;
        $this->swe->sweDate->swe_utc_time_zone(2000, 1, 1, 0, 0, 0,
            7, $iyear, $imonth, $iday,
            $ihour, $imin, $dsec);

        $this->assertEquals([1999, 12, 31, 17, 0, 0.0], [$iyear, $imonth, $iday, $ihour, $imin, $dsec]);
    }
}