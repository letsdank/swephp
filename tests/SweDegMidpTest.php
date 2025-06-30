<?php

namespace Tests;

use SwephLib;
use Tests\Base\SweTestCase;

class SweDegMidpTest extends SweTestCase
{
    public function test_01()
    {
        $this->assertEquals(90, SwephLib::swe_deg_midp(0, 180));
    }

    public function test_02()
    {
        $this->assertEquals(270, SwephLib::swe_deg_midp(180, 0));
    }
}