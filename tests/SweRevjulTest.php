<?php

namespace Tests;

use SweDate;
use Tests\Base\SweTestCase;

class SweRevjulTest extends SweTestCase
{

    public function test_01()
    {
        $jyear = 0;
        $jmonth = 0;
        $jday = 0;
        $jut = 0.0;
        $this->swe->sweDate->swe_revjul(2452275.5, SweDate::SE_GREG_CAL,
            $jyear, $jmonth, $jday, $jut);
        $this->assertEquals([2002, 1, 1, 0.0], [$jyear, $jmonth, $jday, $jut]);
    }
}