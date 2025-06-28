<?php

namespace Tests;

use Tests\Base\SweTestCase;

class SweDifcs2nTest extends SweTestCase
{
    public function test_01()
    {
        $this->assertEquals(-64800000, $this->swe->swephLib->swe_difcs2n(
            360 * 360000, 540 * 360000));
    }
}