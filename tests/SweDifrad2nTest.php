<?php

namespace Tests;

use SwephLib;
use Tests\Base\SweTestCase;

class SweDifrad2nTest extends SweTestCase
{
    public function test_01()
    {
        $d = SwephLib::swe_difrad2n(6.283185, 9.424777);
        $this->assertEquals(-3.141592000000001, $d);
    }
}