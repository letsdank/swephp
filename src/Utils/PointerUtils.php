<?php

namespace Utils;

class PointerUtils
{
    public static function pointerFn(array &$arr, int $offset, callable $fn, ?int $size = null)
    {
        $ao = array_values(array_slice($arr, $offset, $size));
        $result = $fn($ao);
        for ($i = 0; $i < sizeof($ao); $i++)
            $arr[$i + $offset] = $ao[$i];
        return $result;
    }

    public static function pointer2Fn(array &$arr1, array &$arr2, int $offset1, int $offset2, callable $fn,
                                      ?int  $size1 = null, ?int $size2 = null)
    {
        $ao1 = array_values(array_slice($arr1, $offset1, $size1));
        $ao2 = array_values(array_slice($arr2, $offset2, $size2));
        $result = $fn($ao1, $ao2);
        for ($i = 0; $i < sizeof($ao1); $i++)
            $arr1[$i + $offset1] = $ao1[$i];
        for ($i = 0; $i < sizeof($ao2); $i++)
            $arr2[$i + $offset2] = $ao2[$i];
        return $result;
    }
}