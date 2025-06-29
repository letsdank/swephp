<?php

namespace Structs;

/* Ayanamsas
 * For each ayanamsa, there are the following values:
 * t0       epoch of ayanamsa, TDT (can be ET or UT)
 * ayan_t0  ayanamsa value at epoch
 * t0_is_UT true, if t0 is UT
 * prec_offset is the precession model for which the ayanamsha
 *          has to be corrected by adding/subtracting a constant offset.
 *          0, if no correction is needed
 *          -1, if correction is unclear or has not been investigated
 *              and therefore is not applied
 */

class aya_init
{
    public function __construct(
        public float $t0,
        public float $ayan_t0,
        public bool  $t0_is_UT,
        public int   $prec_offset,
    )
    {
    }
}