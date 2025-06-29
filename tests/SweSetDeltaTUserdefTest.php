<?php

namespace Tests;

use Tests\Base\SweTestCase;

class SweSetDeltaTUserdefTest extends SweTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->swe->sweph->swe_set_ephe_path(null);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->swe->swephLib->swe_set_delta_t_userdef(\SwephLib::SE_DELTAT_AUTOMATIC);
    }

    public function test_01()
    {
        $this->assertNull($this->swe->swephLib->swe_set_delta_t_userdef(0.0008) ?? null);
        $dt = $this->swe->swephLib->swe_deltat(2452275.5);
        $this->assertEquals(0.0008, $dt);
    }
}