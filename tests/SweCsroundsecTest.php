<?php

namespace Tests;

use SwephLib;
use Tests\Base\SweTestCase;

class SweCsroundsecTest extends SweTestCase
{
    public function test_01()
    {
        $cs = SwephLib::swe_csroundsec(145);
        $this->assertEquals(100, $cs);
    }

    public function test_02()
    {
        $cs = SwephLib::swe_csroundsec(150);
        $this->assertEquals(200, $cs);
    }

    public function test_03()
    {
        $cs = SwephLib::swe_csroundsec(595900);
        $this->assertEquals(595900, $cs);
    }
}