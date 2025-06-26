<?php

namespace Tests\Base;

use PHPUnit\Framework\TestCase;
use SweConst;
use SwePhp;

class SweTestCase extends TestCase
{
    protected SwePhp $swe;

    public function __construct()
    {
        parent::__construct();
        $this->swe = SwePhp::getInstance();
    }

    public function assertSweError($iret)
    {
        $this->assertEquals(SweConst::ERR, $iret, "Failed asserting that result matches expected Sweph library error");
    }

    public function assertSweOk($iret)
    {
        $this->assertEquals(SweConst::OK, $iret, "Failed asserting that result matches expected Sweph library OK result");
    }
}