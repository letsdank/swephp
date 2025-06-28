<?php

namespace Tests;

use Tests\Base\SweTestCase;

class SweCs2LonLatstrTest extends SweTestCase
{
    public function test_01()
    {
        $s = $this->swe->swephLib->swe_cs2lonlatstr(98923700, ' ', '.');
        $this->assertEquals("274 47'17", $s);
    }

    public function test_02()
    {
        $s = $this->swe->swephLib->swe_cs2lonlatstr(-98923700, ' ', '.');
        $this->assertEquals("274.47'17", $s);
    }

    // TODO: Error handling
}