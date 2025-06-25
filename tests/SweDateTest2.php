<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

class SweDateTest2 extends TestCase
{
    public function test_not_equals()
    {
        $this->assertNotEquals(1.3, \SweDate::swe_date());
    }
}