<?php

namespace Utils;

class ArrayUtils
{
    public static function createArray2D(int $dim1, int $dim2): array
    {
        $result = [];
        for ($i = 0; $i < $dim1; $i++) {
            $result[$i] = array_fill(0, $dim2, null);
        }
        return $result;
    }
}