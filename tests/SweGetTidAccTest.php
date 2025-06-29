<?php

namespace Tests;

use Tests\Base\SweTestCase;

class SweGetTidAccTest extends SweTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->swe->sweph->swe_set_ephe_path(null);
    }

    public function test_01()
    {
        $tidacc = $this->swe->swephLib->swe_get_tid_acc();
        $this->assertEquals(-25.8, $tidacc);
    }
}