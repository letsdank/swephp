<?php

class SweDate extends SweModule
{

    public function __construct(SwePhp $base)
    {
        $this->swePhp = $base;
    }

    static bool $init_leapseconds_done = false;

    const int SE_JUL_CAL = 0;
    const int SE_GREG_CAL = 1;

    /**
     * Calculate Julian day number with check whether input date is correct.
     *
     * @param int $year Year
     * @param int $month Month
     * @param int $day Day of month
     * @param float $hour Input time, decimal with fraction
     * @param string $c Calendar type, gregorian ('g') or julian('j')
     * @param float $tjd Returned Julian day number
     * @return int SweConst::OK if the input date and time are legal, else SweConst::ERR
     */
    public function swe_date_conversion(int   $year, int $month, int $day,
                                        float $hour, string $c, float &$tjd): int
    {
        if ($c != 'g' && $c != 'j')
            throw new ValueError(sprintf("Invalid calendar '%s', must be 'g' or 'j'", $c));

        $rday = 0;
        $rmon = 0;
        $ryear = 0;
        $gregflag = self::SE_JUL_CAL;
        if ($c == 'g')
            $gregflag = self::SE_GREG_CAL;
        $rut = $hour;         // hours UT
        $jd = self::swe_julday($year, $month, $day, $rut, $gregflag);
        self::swe_revjul($jd, $gregflag, $ryear, $rmon, $rday, $rut);
        $tjd = $jd;
        if ($rmon == $month && $rday == $day && $ryear == $year) return SweConst::OK;
        return SweConst::ERR;
    }

    public function swe_julday(int $year, int $month, int $day, float $hour, int $gregflag): float
    {
        $u = $year;
        if ($month < 3) $u -= 1;
        $u0 = $u + 4712.0;
        $u1 = $month + 1.0;
        if ($u1 < 4) $u1 += 12.0;
        $jd = floor($u0 * 365.25)
            + floor(30.6 * $u1 + 0.000001)
            + $day + $hour / 24.0 - 63.5;
        if ($gregflag == self::SE_GREG_CAL) {
            $u2 = floor(abs($u) / 100) - floor(abs($u) / 400);
            if ($u < 0.0) $u2 = -$u2;
            $jd = $jd - $u2 + 2;
            if (($u < 0.0) && ($u / 100 == floor($u / 100)) && ($u / 400 != floor($u / 400)))
                $jd -= 1;
        }
        return $jd;
    }

    public function swe_revjul(float $jd, int $gregflag, int &$jyear, int &$jmon, int &$jday, float &$jut): void
    {
        $u0 = $jd + 32082.5;
        if ($gregflag == self::SE_GREG_CAL) {
            $u1 = $u0 + floor($u0 / 36525.0) - floor($u0 / 146100.0) - 38.0;
            if ($jd >= 1830691.5) $u1 += 1;
            $u0 = $u0 + floor($u1 / 36525.0) - floor($u1 / 146100.0) - 38.0;
        }
        $u2 = floor($u0 + 123.0);
        $u3 = floor(($u2 - 122.2) / 365.25);
        $u4 = floor(($u2 - floor(365.25 * $u3)) / 30.6001);
        $jmon = (int)($u4 - 1.0);
        if ($jmon > 12) $jmon -= 12;
        $jday = (int)($u2 - floor(365.25 * $u3) - floor(30.6001 * $u4));
        $jyear = (int)($u3 + floor(($u4 - 2.0) / 12.0) - 4800);
        $jut = ($jd - floor($jd + 0.5) + 0.5) * 24.0;
    }
}