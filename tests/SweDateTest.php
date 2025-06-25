<?php

class SweDateTest extends \PHPUnit\Framework\TestCase
{
    public function test_returns_1_on_swe_date()
    {
        $this->assertEquals(1.0, SweDate::swe_date());
    }
}