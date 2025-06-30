<?php

namespace Tests;

use SwephLib;
use Tests\Base\SweTestCase;

class SweCsnormTest extends SweTestCase
{
    public function test_01()
    {
        $cs = SwephLib::swe_csnorm(360 * 360000);
        $this->assertEquals(0, $cs);
    }

    public function test_02()
    {
        $cs = SwephLib::swe_csnorm(540 * 360000);
        $this->assertEquals(64800000, $cs);
        $this->assertEquals(SwephLib::swe_csnorm(180 * 360000), $cs);
    }

    public function test_03()
    {
        $cs = SwephLib::swe_csnorm(-720 * 360000);
        $this->assertEquals(0, $cs);
    }
}