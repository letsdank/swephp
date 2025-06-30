<?php

namespace Tests;

use SwephLib;
use Tests\Base\SweTestCase;

class SweDifcs2nTest extends SweTestCase
{
    public function test_01()
    {
        $this->assertEquals(-64800000, SwephLib::swe_difcs2n(360 * 360000, 540 * 360000));
    }
}