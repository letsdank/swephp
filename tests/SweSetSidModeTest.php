<?php

namespace Tests;

use Enums\SwePlanet;
use Enums\SweSiderealMode;
use SweConst;
use SweDate;
use Tests\Base\SweTestCase;

class SweSetSidModeTest extends SweTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->swe->sweph->swe_set_ephe_path(null);
    }

    public function test_01()
    {
        $jd = $this->swe->sweDate->swe_julday(2021, 8, 20, 12, SweDate::SE_JUL_CAL);
        $xx = [];
        $this->swe->sweph->swe_calc_ut($jd, SwePlanet::VENUS->value, 0, $xx);
        $this->assertEquals(185.09289080174835, $xx[0]);
        $this->swe->sweph->swe_set_sid_mode(SweSiderealMode::SIDM_LAHIRI->value, 0, 0);
        $xx = [];
        $this->swe->sweph->swe_calc_ut($jd, SwePlanet::VENUS->value,
            SweConst::SEFLG_SWIEPH | SweConst::SEFLG_SIDEREAL, $xx);
        $this->assertEquals(160.93755436261293, $xx[0]);
    }
}