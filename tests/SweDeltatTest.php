<?php

namespace Tests;

use Tests\Base\SweTestCase;

class SweDeltatTest extends SweTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->swe->sweph->swe_set_ephe_path(null);
    }

    public function test_01()
    {
        $dt = $this->swe->swephLib->swe_deltat(2452275.5);
        $this->assertEquals(0.0007442138247935472, $dt);
        $this->assertEquals(64.30007446216248, $dt * 86400);
    }
}