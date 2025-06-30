<?php

namespace Tests;

use SwephLib;
use Tests\Base\SweTestCase;

class SweCs2DegstrTest extends SweTestCase
{
    public function test_01()
    {
        $s = SwephLib::swe_cs2degstr(98923700);
        $this->assertEquals(" 4°47'17", $s);
    }
}