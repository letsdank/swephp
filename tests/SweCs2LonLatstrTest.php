<?php

namespace Tests;

use SwephLib;
use Tests\Base\SweTestCase;

class SweCs2LonLatstrTest extends SweTestCase
{
    public function test_01()
    {
        $s = SwephLib::swe_cs2lonlatstr(98923700, ' ', '.');
        $this->assertEquals("274 47'17", $s);
    }

    public function test_02()
    {
        $s = SwephLib::swe_cs2lonlatstr(-98923700, ' ', '.');
        $this->assertEquals("274.47'17", $s);
    }

    // TODO: Error handling
}