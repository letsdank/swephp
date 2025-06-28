<?php

namespace Tests;

use Tests\Base\SweTestCase;

class SweDifdegnTest extends SweTestCase
{
    public function test_01()
    {
        $this->assertEquals(180.5, $this->swe->swephLib->swe_difdegn(360.5, 540));
    }
}