<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use SweDate;

class SweJuldayTest extends TestCase
{
    private SweDate $sweDate;

    public function __construct()
    {
        parent::__construct();
        // TODO: Get rid of this
        $this->sweDate = new SweDate();
    }

    public function test_01()
    {
        $jd = $this->sweDate->swe_julday(2002, 1, 1, 0, SweDate::SE_GREG_CAL);
        $this->assertEquals(2452275.5, $jd);
    }
}