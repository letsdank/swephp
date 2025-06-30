<?php

namespace Tests;

use SwephLib;
use Tests\Base\SweTestCase;

class SweDifcsnTest extends SweTestCase
{
    public function test_01()
    {
        $this->assertEquals(64800000, SwephLib::swe_difcsn(360 * 360000, 540 * 360000));
    }
}