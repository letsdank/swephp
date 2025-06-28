<?php

namespace Tests;

use Tests\Base\SweTestCase;

class SweCs2DegstrTest extends SweTestCase
{
    public function test_01()
    {
        $s = $this->swe->swephLib->swe_cs2degstr(98923700);
        $this->assertEquals(" 4°47'17", $s);
    }
}