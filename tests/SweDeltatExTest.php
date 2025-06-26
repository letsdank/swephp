<?php

namespace Tests;

use SweConst;

class SweDeltatExTest extends SweDeltatTest
{
    public function __construct()
    {
        parent::__construct();
        // TODO: Need to implement swe_set_ephe_path() to bring this test to work
        // $this->swe->swephLib->swe_set_ephe_path();
    }

    public function test_01()
    {
        $dt = $this->swe->swephLib->swe_deltat_ex(2452275.5, SweConst::SEFLG_SWIEPH);
        $this->assertEquals($dt, 0.0007442138247935472, $dt);
        $this->assertEquals($dt * 86400, 64.30007446216248);
    }
}