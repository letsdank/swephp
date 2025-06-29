<?php

namespace Tests;

use Enums\SweSiderealMode;
use Tests\Base\SweTestCase;

class SweGetAyanamsaTest extends SweTestCase
{
    public function test_01()
    {
        $this->swe->sweph->swe_set_sid_mode(SweSiderealMode::SIDM_FAGAN_BRADLEY->value, 0, 0);
        $ay=$this->swe->sweph->swe_get_ayanamsa(2452275.5);
        $this->assertEquals(24.768237848066065, $ay);
    }
    public function test_02()
    {
        $this->swe->sweph->swe_set_sid_mode(SweSiderealMode::SIDM_LAHIRI->value, 0, 0);
        $ay=$this->swe->sweph->swe_get_ayanamsa(2452275.5);
        $this->assertEquals(23.88503020705366, $ay);
    }
}