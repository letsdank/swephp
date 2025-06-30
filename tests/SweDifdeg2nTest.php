<?php

namespace Tests;

use SwephLib;
use Tests\Base\SweTestCase;

class SweDifdeg2nTest extends SweTestCase
{
    public function test_01()
    {
        $this->assertEquals(-179.5, SwephLib::swe_difdeg2n(360.5, 540));
    }
}