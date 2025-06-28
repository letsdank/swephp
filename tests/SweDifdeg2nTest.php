<?php

namespace Tests;

use Tests\Base\SweTestCase;

class SweDifdeg2nTest extends SweTestCase
{
    public function test_01()
    {
        $this->assertEquals(-179.5, $this->swe->swephLib->swe_difdeg2n(360.5, 540));
    }
}