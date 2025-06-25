<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use SweDate;

class SweDateConversionTest extends TestCase
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
        $jd = 0.0;
        $res = $this->sweDate->swe_date_conversion(2002, 13, 1, 0, 'g', $jd);
        // Assert that provided date is not valid
        $this->assertEquals(\SweConst::ERR, $res);
        $this->assertEquals(2452640.5, $jd);

        // Get revjul value and assert that it returns correct result
        $year = 0;
        $month = 0;
        $day = 0;
        $ut = 0.0;
        $this->sweDate->swe_revjul($jd, SweDate::SE_GREG_CAL, $year, $month, $day, $ut);
        $this->assertEquals([2003, 1, 1, 0.0], [$year, $month, $day, $ut]);
    }

    public function test_02()
    {
        $jd = 0.0;
        $res = $this->sweDate->swe_date_conversion(2002, 1, 1, 0, 'g', $jd);
        // Assert that provided date is valid
        $this->assertEquals(\SweConst::OK, $res);
        $this->assertEquals(2452275.5, $jd);
    }

    public function test_invalidCal()
    {
        $this->expectException(\ValueError::class);
        $jd = 0.0;
        $this->sweDate->swe_date_conversion(2020, 4, 23, 23.654, 'z', $jd);
    }
}