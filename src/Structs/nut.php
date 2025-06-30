<?php

namespace Structs;

// nutation
class nut
{
    public float $tnut = 0;
    public array $nutlo = [];           // nutation in longitude and obliquity
    public float $snut = 0, $cnut = 0;  // sine and cosine of nutation in obliquity
    public array $matrix = [];          // matrix[3][3]
}