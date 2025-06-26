<?php

namespace Tests;

use Tests\Base\SweTestCase;

class SweCotransTest extends SweTestCase
{
    public function test_01()
    {
        $coord = [121.34, 43.57, 1.0];
        $xx = [];
        $this->swe->swephLib->swe_cotrans($coord, $xx, 23.4);
        $this->assertIsArray($xx);
        $this->assertCount(3, $xx);
        $this->assertEquals(114.11984833491826, $xx[0]);
        $this->assertEquals(22.754921351892474, $xx[1]);
        $this->assertEquals(1.0, $xx[2]);
    }
}