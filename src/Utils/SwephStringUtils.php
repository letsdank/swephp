<?php

namespace Utils;

class SwephStringUtils
{
    public static function swe_cs2timestr(int $t, string $sep, bool $suppressZero): string
    {
        $a = "        ";
        $a[2] = $a[5] = $sep;
        $t = (($t + 50) / 100) % (24 * 3600); // round to seconds
        $s = $t % 60;
        $m = ($t / 60) % 60;
        $h = $t / 3600 % 100;
        if ($s == 0 && $suppressZero)
            $a = substr($a, 0, 5);
        else {
            $a[6] = strval($s / 10);
            $a[7] = strval($s % 10);
        }
        $a[0] = strval((int)($h / 10));
        $a[1] = strval($h % 10);
        $a[3] = strval((int)($m / 10));
        $a[4] = strval($m % 10);
        return $a;
    }

    public static function swe_cs2lonlatstr(int $t, string $pchar, string $mchar): string
    {
        $a = "      '  ";
        // mask    dddEmm'ss
        if ($t < 0) $pchar = $mchar;
        $t = (abs($t) + 50) / 100; // round to seconds
        $s = $t % 60;
        $m = $t / 60 % 60;
        $h = $t / 3600 % 1000;
        if ($s == 0)
            $a = substr($a, 0, 6);
        else {
            $a[7] = strval((int)($s / 10));
            $a[8] = strval($s % 10);
        }
        $a[3] = $pchar;
        if ($h > 99) $a[0] = strval((int)($h / 100));
        if ($h > 9) $a[1] = strval((int)($h % 100 / 10));
        $a[2] = strval($h % 10);
        $a[4] = strval((int)($m / 10));
        $a[5] = strval($m % 10);
        return $a;
    }

    public static function swe_cs2degstr(int $t): string
        // does suppress leading zeros in degrees
    {
        $t = $t / 100 % (30 * 3600);    // truncate to seconds
        $s = $t % 60;
        $m = $t / 60 % 60;
        $h = $t / 3600 % 100;           // only 0.99 degrees
        return sprintf("%2d%s%02d'%02d", $h, "°", $m, $s);
    }
}