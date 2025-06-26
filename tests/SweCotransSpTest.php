<?php

namespace Tests;

use Tests\Base\SweTestCase;

class SweCotransSpTest extends SweTestCase
{
    public function test_01()
    {
        $coord = [121.34, 43.57, 1.0, 1.1, 5.5, 1.0];
        $xx = [];
        $this->swe->swephLib->swe_cotrans_sp($coord, $xx, 23.4);
        $this->assertIsArray($xx);
        $this->assertCount(6, $xx);
        $this->assertEquals(114.11984833491826, $xx[0]);
        $this->assertEquals(22.754921351892474, $xx[1]);
        $this->assertEquals(1.0, $xx[2]);
        $this->assertEquals(-0.4936723489509314, $xx[3]);
        $this->assertEquals(5.538766604142424, $xx[4]);
        $this->assertEquals(1.0, $xx[5]);
    }
}