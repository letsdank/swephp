<?php

namespace Tests;

use SwephLib;
use Tests\Base\SweTestCase;

class SweDegnormTest extends SweTestCase
{
    public function test_01()
    {
        $this->assertEquals(0, SwephLib::swe_degnorm(0));
    }

    public function test_02()
    {
        $this->assertEquals(0, SwephLib::swe_degnorm(360));
    }

    public function test_03()
    {
        $this->assertEquals(359, SwephLib::swe_degnorm(-1));
    }
}