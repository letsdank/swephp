<?php

namespace Tests;

use SwephLib;
use Tests\Base\SweTestCase;

class SweRadnormTest extends SweTestCase
{
    public function test_01()
    {
        $this->assertEquals(0.0, SwephLib::swe_radnorm(0));
    }

    public function test_02()
    {
        $this->assertEquals(0.0, SwephLib::swe_radnorm(6.2831853071796));
    }

    public function test_03()
    {
        $x = SwephLib::swe_radnorm(-0.017453292519943);
        $this->assertEquals(6.265732014659643, $x);
    }
}