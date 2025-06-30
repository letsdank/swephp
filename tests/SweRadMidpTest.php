<?php

namespace Tests;

use SwephLib;
use Tests\Base\SweTestCase;

class SweRadMidpTest extends SweTestCase
{
    public function test_01()
    {
        $x = SwephLib::swe_rad_midp(0, 3.14159);
        $this->assertEquals(1.570795, $x);
    }

    public function test_02()
    {
        $x = SwephLib::swe_rad_midp(3.14159, 0);
        $this->assertEquals(1.570795, $x);
    }
}