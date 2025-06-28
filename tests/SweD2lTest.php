<?php

namespace Tests;

use Tests\Base\SweTestCase;

class SweD2lTest extends SweTestCase
{
    public function test_01()
    {
        $this->assertEquals(90, $this->swe->swephLib->swe_d2l(90.4));
    }

    public function test_02()
    {
        $this->assertEquals(91, $this->swe->swephLib->swe_d2l(90.5));
    }
}