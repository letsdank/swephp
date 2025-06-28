<?php

namespace Tests;

use Tests\Base\SweTestCase;

class SweDifrad2nTest extends SweTestCase
{
    public function test_01()
    {
        $d = $this->swe->swephLib->swe_difrad2n(6.283185, 9.424777);
        $this->assertEquals(-3.141592000000001, $d);
    }
}