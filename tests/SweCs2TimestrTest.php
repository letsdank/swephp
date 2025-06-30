<?php

namespace Tests;

use SwephLib;
use Tests\Base\SweTestCase;

class SweCs2TimestrTest extends SweTestCase
{
    public function test_01()
    {
        $s = SwephLib::swe_cs2timestr(8496000, ':', false);
        $this->assertEquals("23:36:00", $s);
    }

    public function test_02()
    {
        $s = SwephLib::swe_cs2timestr(8496000, ':', true);
        $this->assertEquals("23:36", $s);
    }

    // TODO: Error handling on separator more than 1 string length
}