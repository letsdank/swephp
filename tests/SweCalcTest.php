<?php

namespace Tests;

use Enums\SwePlanet;
use SweConst;
use Tests\Base\SweTestCase;

class SweCalcTest extends SweTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->swe->sweph->swe_set_ephe_path(null);
    }

    public function test_01()
    {
        $flags = SweConst::SEFLG_MOSEPH | SweConst::SEFLG_SPEED;
        $xx = [];
        $retflags = $this->swe->sweph->swe_calc(2452275.5, SwePlanet::SUN->value, $flags, $xx);
        $this->assertCount(6, $xx);
        $this->assertEquals(260, $retflags);
        $this->assertEquals($flags, $retflags);
        $this->assertEquals(280.38296810621137, $xx[0]);
        $this->assertEquals(0.0001496807056552454, $xx[1]);
        $this->assertEquals(0.9832978391484491, $xx[2]);
        $this->assertEquals(1.0188772348975301, $xx[3]);
        $this->assertEquals(1.7232637573749195e-05, $xx[4]);
        $this->assertEquals(-1.0220875853441474e-05, $xx[5]);
    }

    // TODO: Error handling
}