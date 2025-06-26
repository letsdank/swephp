<?php

namespace Tests;

use SweDate;
use Tests\Base\SweTestCase;

class SweUtcToJdTest extends SweTestCase
{
    public function test_01()
    {
        $dret = [0.0, 0.0];
        $iret = $this->swe->sweDate->swe_utc_to_jd(2000, 1, 1, 0, 0, 0,
            SweDate::SE_GREG_CAL, $dret);
        $this->assertSweError($iret);
        $this->assertEquals(2451544.5007428704, $dret[0]);
        $this->assertEquals(2451544.5000041146, $dret[1]);
    }
}