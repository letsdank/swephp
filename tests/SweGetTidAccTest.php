<?php

namespace Tests;

use Tests\Base\SweTestCase;

class SweGetTidAccTest extends SweTestCase
{
    public function __construct()
    {
        parent::__construct();
        // TODO: Need to implement swe_set_ephe_path() to bring this test to work
        // $this->swe->swephLib->swe_set_ephe_path();
    }

    public function test_01()
    {
        $tidacc = $this->swe->swephLib->swe_get_tid_acc();
        $this->assertEquals(-25.8, $tidacc);
    }
}