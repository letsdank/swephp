<?php

namespace Tests;

use Enums\SweSiderealMode;
use SweConst;
use Tests\Base\SweTestCase;

class SweGetAyanamsaExTest extends SweTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->swe->sweph->swe_set_ephe_path(null);
    }

    public function test_01()
    {
        // TODO: TBD
        $flags = SweConst::SEFLG_SWIEPH | SweConst::SEFLG_NONUT;
        $this->swe->sweph->swe_set_sid_mode(SweSiderealMode::SIDM_FAGAN_BRADLEY->value,0,0);
        $daya = .0;
//        $retflags=$this->swe->sweph->swe_get_ayanamsa_ex(2452275.5, $flags)
    }
}