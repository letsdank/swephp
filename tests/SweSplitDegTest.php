<?php

namespace Tests;

use SwephLib;
use Tests\Base\SweTestCase;

class SweSplitDegTest extends SweTestCase
{
    public function test_00()
    {
        $ideg = 0;
        $imin = 0;
        $isec = 0;
        $dsecfr = 0.;
        $isgn = 0;
        $this->swe->swephLib->swe_split_deg(123.123, 0,
            $ideg, $imin, $isec, $dsecfr, $isgn);
        $this->assertEquals(123, $ideg);
        $this->assertEquals(7, $imin);
        $this->assertEquals(22, $isec);
        $this->assertEquals(0.8000000000167731, $dsecfr);
        $this->assertEquals(1, $isgn);
    }

    public function test_01()
    {
        $ideg = 0;
        $imin = 0;
        $isec = 0;
        $dsecfr = 0.;
        $isgn = 0;
        $this->swe->swephLib->swe_split_deg(123.123, SwephLib::SE_SPLIT_DEG_ROUND_SEC,
            $ideg, $imin, $isec, $dsecfr, $isgn);
        $this->assertEquals(123, $ideg);
        $this->assertEquals(7, $imin);
        $this->assertEquals(23, $isec);
        $this->assertEquals(0, $dsecfr);
        $this->assertEquals(1, $isgn);
    }

    public function test_02()
    {
        $ideg = 0;
        $imin = 0;
        $isec = 0;
        $dsecfr = 0.;
        $isgn = 0;
        $this->swe->swephLib->swe_split_deg(123.123, SwephLib::SE_SPLIT_DEG_ROUND_MIN,
            $ideg, $imin, $isec, $dsecfr, $isgn);
        $this->assertEquals(123, $ideg);
        $this->assertEquals(7, $imin);
        $this->assertEquals(52, $isec);
        $this->assertEquals(0, $dsecfr);
        $this->assertEquals(1, $isgn);
    }

    public function test_03()
    {
        $ideg = 0;
        $imin = 0;
        $isec = 0;
        $dsecfr = 0.;
        $isgn = 0;
        $this->swe->swephLib->swe_split_deg(123.123, SwephLib::SE_SPLIT_DEG_ROUND_DEG,
            $ideg, $imin, $isec, $dsecfr, $isgn);
        $this->assertEquals(123, $ideg);
        $this->assertEquals(37, $imin);
        $this->assertEquals(22, $isec);
        $this->assertEquals(0, $dsecfr);
        $this->assertEquals(1, $isgn);
    }

    public function test_04()
    {
        $ideg = 0;
        $imin = 0;
        $isec = 0;
        $dsecfr = 0.;
        $isgn = 0;
        $this->swe->swephLib->swe_split_deg(123.123, SwephLib::SE_SPLIT_DEG_ZODIACAL,
            $ideg, $imin, $isec, $dsecfr, $isgn);
        $this->assertEquals(3, $ideg);
        $this->assertEquals(7, $imin);
        $this->assertEquals(22, $isec);
        $this->assertEquals(0.8000000000167731, $dsecfr);
        $this->assertEquals(4, $isgn);
    }

    public function test_05()
    {
        $ideg = 0;
        $imin = 0;
        $isec = 0;
        $dsecfr = 0.;
        $isgn = 0;
        $this->swe->swephLib->swe_split_deg(123.123, SwephLib::SE_SPLIT_DEG_NAKSHATRA,
            $ideg, $imin, $isec, $dsecfr, $isgn);
        $this->assertEquals(3, $ideg);
        $this->assertEquals(7, $imin);
        $this->assertEquals(22, $isec);
        $this->assertEquals(0.8000000001126963, $dsecfr);
        $this->assertEquals(9, $isgn);
    }

    public function test_06()
    {
        $ideg = 0;
        $imin = 0;
        $isec = 0;
        $dsecfr = 0.;
        $isgn = 0;
        $this->swe->swephLib->swe_split_deg(123.123, SwephLib::SE_SPLIT_DEG_KEEP_SIGN,
            $ideg, $imin, $isec, $dsecfr, $isgn);
        $this->assertEquals(123, $ideg);
        $this->assertEquals(7, $imin);
        $this->assertEquals(22, $isec);
        $this->assertEquals(0.8000000000167731, $dsecfr);
        $this->assertEquals(1, $isgn);
    }
}