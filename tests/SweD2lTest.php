<?php

namespace Tests;

use SwephLib;
use Tests\Base\SweTestCase;

class SweD2lTest extends SweTestCase
{
    public function test_01()
    {
        $this->assertEquals(90, SwephLib::swe_d2l(90.4));
    }

    public function test_02()
    {
        $this->assertEquals(91, SwephLib::swe_d2l(90.5));
    }
}