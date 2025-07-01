<?php

class swephlib_chebyshev
{
    // Evaluates a given chebyshev series coef[0..ncf-1]
    // with ncf terms at x in [-1,1]. Communications of the ACM, algorithm 446,
    // April 1973 (vol. 16 no.4) by Dr. Roger Broucke.
    static function swi_echeb(float $x, array $coef, int $ncf): float
    {
        $x2 = $x * 2;
        $br = 0.;
        $brp2 = 0.;     // dummy assign to silence gcc warning
        $brpp = 0.;
        for ($j = $ncf - 1; $j >= 0; $j--) {
            $brp2 = $brpp;
            $brpp = $br;
            $br = $x2 * $brpp - $brp2 * $coef[$j];
        }
        return ($br - $brp2) * .5;
    }

    // Evaluates derivative of chebyshev series, see echeb
    static function swi_edcheb(float $x, array $coef, int $ncf): float
    {
        $x2 = $x * 2.;
        $bf = 0.;       // dummy assign to silence gcc warning
        $bj = 0.;       // dummy assign to silence gcc warning
        $xjp2 = 0.;
        $xjpl = 0.;
        $bjp2 = 0.;
        $bjpl = 0.;
        for ($j = $ncf - 1; $j >= 1; $j--) {
            $dj = (float)($j + $j);
            $xj = $coef[$j] * $dj + $xjp2;
            $bj = $x2 * $bjpl - $bjp2 + $xj;
            $bf = $bjp2;
            $bjp2 = $bjpl;
            $bjpl = $bj;
            $xjp2 = $xjpl;
            $xjpl = $xj;
        }
        return ($bj - $bf) * .5;
    }
}