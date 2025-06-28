<?php

namespace Tests;

use Tests\Base\SweTestCase;

class SweSidtimeTest extends SweTestCase
{
    public function __construct()
    {
        parent::__construct();
        // TODO: Need to implement swe_set_ephe_path() to bring this test to work
        // $this->swe->swephLib->swe_set_ephe_path();
    }

    public function test_01()
    {
        $sidtime = $this->swe->swephLib->swe_sidtime(2452275.5);
        $this->assertEquals(6.69812123973034, $sidtime);
    }
}