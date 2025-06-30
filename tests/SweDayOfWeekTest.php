<?php

namespace Tests;

use SwephLib;
use Tests\Base\SweTestCase;

class SweDayOfWeekTest extends SweTestCase
{
    public function test_01()
    {
        $this->assertEquals(1, SwephLib::swe_day_of_week(2452275.5));
    }

    public function test_02()
    {
        $this->assertEquals(1, SwephLib::swe_day_of_week(2459444.0));
    }
}