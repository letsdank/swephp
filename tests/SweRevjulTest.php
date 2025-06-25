<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use SweDate;

class SweRevjulTest extends TestCase
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
        $jyear = 0;
        $jmonth = 0;
        $jday = 0;
        $jut = 0.0;
        $this->sweDate->swe_revjul(2452275.5, SweDate::SE_GREG_CAL,
            $jyear, $jmonth, $jday, $jut);
        $this->assertEquals([2002, 1, 1, 0.0], [$jyear, $jmonth, $jday, $jut]);
    }
}