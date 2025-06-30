<?php

namespace Tests;

use SwephLib;
use Tests\Base\SweTestCase;

class SweDifdegnTest extends SweTestCase
{
    public function test_01()
    {
        $this->assertEquals(180.5, SwephLib::swe_difdegn(360.5, 540));
    }
}