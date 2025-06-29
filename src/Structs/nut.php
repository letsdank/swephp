<?php

namespace Structs;

// nutation
class nut
{
    public float $tnut;
    public array $nutlo;        // nutation in longitude and obliquity
    public float $snut, $cnut;  // sine and cosine of nutation in obliquity
    public array $matrix;       // matrix[3][3]
}