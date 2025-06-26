<?php

namespace Tests;

use SweDate;
use Tests\Base\SweTestCase;

class SweJuldayTest extends SweTestCase
{
    public function test_01()
    {
        $jd = $this->swe->sweDate->swe_julday(2002, 1, 1, 0,
            SweDate::SE_GREG_CAL);
        $this->assertEquals(2452275.5, $jd);
    }
}