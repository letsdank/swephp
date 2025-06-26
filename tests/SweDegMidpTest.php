<?php

namespace Tests;

use Tests\Base\SweTestCase;

class SweDegMidpTest extends SweTestCase
{
    public function test_01()
    {
        $this->assertEquals(90, $this->swe->swephLib->swe_deg_midp(0, 180));
    }

    public function test_02()
    {
        $this->assertEquals(270, $this->swe->swephLib->swe_deg_midp(180, 0));
    }
}