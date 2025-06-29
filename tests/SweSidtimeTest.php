<?php

namespace Tests;

use Tests\Base\SweTestCase;

class SweSidtimeTest extends SweTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->swe->sweph->swe_set_ephe_path(null);
    }

    public function test_01()
    {
        $sidtime = $this->swe->swephLib->swe_sidtime(2452275.5);
        $this->assertEquals(6.69812123973034, $sidtime);
    }
}