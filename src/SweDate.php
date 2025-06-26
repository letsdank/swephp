<?php

class SweDate extends SweModule
{

    public function __construct(SwePhp $base)
    {
        parent::__construct($base);
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

    public function swe_utc_time_zone(int $iyear, int $imonth, int $iday,
                                      int $ihour, int $imin, float $dsec, float $d_timezone,
                                      int &$iyear_out, int &$imonth_out, int &$iday_out,
                                      int &$ihour_out, int &$imin_out, float &$dsec_out): void
    {
        $d = 0.0;
        $have_leapsec = false;
        if ($dsec >= 60.0) {
            $have_leapsec = true;
            $dsec -= 1.0;
        }
        $dhour = ((float)$ihour) + ((float)$imin) / 60.0 + $dsec / 3600.0;
        $tjd = $this->swe_julday($iyear, $imonth, $iday, 0, self::SE_GREG_CAL);
        $dhour -= $d_timezone;
        if ($dhour < 0.0) {
            $tjd -= 1.0;
            $dhour += 24.0;
        }
        if ($dhour >= 24.0) {
            $tjd += 1.0;
            $dhour -= 24.0;
        }
        $this->swe_revjul($tjd + 0.001, self::SE_GREG_CAL, $iyear_out, $imonth_out, $iday_out, $d);
        $ihour_out = (int)$dhour;
        $d = ($dhour - (float)$ihour_out) * 60;
        $imin_out = (int)$d;
        $dsec_out = ($d - (float)$imin_out) * 60;
        if ($have_leapsec)
            $dsec_out += 1.0;
    }

    //
    // functions for the handling of UTC
    //

    // Leap seconds were inserted at the end of the following days:
    private const int NLEAP_SECONDS = 27;
    private const int NLEAP_SECONDS_SPACE = 100;
    static array $leap_seconds = [
        19720630,
        19721231,
        19731231,
        19741231,
        19751231,
        19761231,
        19771231,
        19781231,
        19791231,
        19810630,
        19820630,
        19830630,
        19850630,
        19871231,
        19891231,
        19901231,
        19920630,
        19930630,
        19940630,
        19951231,
        19970630,
        19981231,
        20051231,
        20081231,
        20120630,
        20150630,
        20161231,
        0  /* keep this 0 as end mark */
    ];
    private const float J1972 = 2441317.5;
    private const int NLEAP_INIT = 10;

    // Read additional leap second dates from external file, if given.
    //
    function init_leapsec(): int
    {
        $tabsiz = 0;
        if (!self::$init_leapseconds_done) {
            self::$init_leapseconds_done = true;
            $tabsiz = self::NLEAP_SECONDS;
            $ndat_last = self::$leap_seconds[self::NLEAP_SECONDS - 1];
            // no error message if file is missing
            if (($fp = $this->swePhp->sweph->swi_fopen(-1, "seleapsec.txt", $this->swePhp->sweph->swed->ephepath)) == null) {
                return self::NLEAP_SECONDS;
            }
            while (($s = fgets($fp, SweConst::AS_MAXCH)) != null) {
                $sp = $s;
                while ($sp[0] == " " || $sp[0] == "\t") $sp = substr($sp, 1);
                $sp = substr($sp, 1);
                if ($sp[0] == "#" || $sp[0] == "\n")
                    continue;
                $ndat = intval($s);
                if ($ndat <= $ndat_last)
                    continue;
                // table space is limited. no error msg, if exceeded
                if ($tabsiz >= self::NLEAP_SECONDS_SPACE)
                    return $tabsiz;
                self::$leap_seconds[$tabsiz] = $ndat;
                $tabsiz++;
            }
            if ($tabsiz > self::NLEAP_SECONDS) self::$leap_seconds[$tabsiz] = 0; // end mark
            fclose($fp);
            return $tabsiz;
        }
        // find table size
        $tabsiz = 0;
        for ($i = 0; $i < self::NLEAP_SECONDS_SPACE; $i++) {
            if (self::$leap_seconds[$i] == 0) break;
            else $tabsiz++;
        }
        return $tabsiz;
    }

    public function swe_utc_to_jd(int   $iyear, int $imonth, int $iday, int $ihour, int $imin,
                                  float $dsec, int $gregflag, array &$dret, ?string &$serr = null): int
    {
        if ($gregflag != self::SE_GREG_CAL && $gregflag != self::SE_JUL_CAL)
            throw new ValueError(sprintf("Invalid calendar (%d)", $gregflag));
        $iyear2 = 0;
        $imonth2 = 0;
        $iday2 = 0;
        $d = 0;
        //
        // error handling: invalid iyear etc.
        //
        $tjd_ut1 = $this->swe_julday($iyear, $imonth, $iday, 0, $gregflag);
        $this->swe_revjul($tjd_ut1, $gregflag, $iyear2, $imonth2, $iday2, $d);
        if ($iyear != $iyear2 || $imonth != $imonth2 || $iday != $iday2) {
            if ($serr)
                $serr = sprintf("invalid date: year = %d, month = %d, day = %d", $iyear, $imonth, $iday);
            return SweConst::ERR;
        }
        if ($ihour < 0 || $ihour > 23 ||
            $imin < 0 || $imin > 59 ||
            $dsec < 0 || $dsec >= 61 ||
            ($dsec >= 60 && ($imin < 59 || $ihour < 23 || $tjd_ut1 < self::J1972))) {
            if ($serr)
                $serr = sprintf("invalid time: %d:%d:%.2f", $ihour, $imin, $dsec);
            return SweConst::ERR;
        }
        $dhour = (float)$ihour + ((float)$imin) / 60.0 + $dsec / 3600.0;
        //
        // before 1972, we treat input date as UT1
        //
        if ($tjd_ut1 < self::J1972) {
            $dret[1] = $this->swe_julday($iyear, $imonth, $iday, $dhour, $gregflag);
            $dret[0] = $dret[1] + $this->swePhp->swephLib->swe_deltat_ex($dret[1], -1);
            return SweConst::OK;
        }
        //
        // if gregflag = Julian calendar, convert to gregorian calendar
        //
        if ($gregflag == self::SE_JUL_CAL) {
            $gregflag = self::SE_GREG_CAL;
            $this->swe_revjul($tjd_ut1, $gregflag, $iyear, $imonth, $iday, $d);
        }
        //
        // number of leap seconds since 1972:
        //
        $tabsiz_nleap = $this->init_leapsec();
        $nleap = self::NLEAP_INIT; // initial difference between UTC and TAI in 1972
        $ndat = $iyear * 10000 + $imonth * 100 + $iday;
        for ($i = 0; $i < $tabsiz_nleap; $i++) {
            if ($ndat < self::$leap_seconds[$i])
                break;
            $nleap++;
        }
        //
        // For input dates > today:
        // If leap seconds table is not up-to-date, we'd better interpret the
        // input time as UT1, not as UTC. How do we find out?
        // Check, if delta_t - nleap - 32.184 > 0.9
        //
        $d = $this->swePhp->swephLib->swe_deltat_ex($tjd_ut1, -1) * 86400.0;
        if ($d - (float)$nleap - 32.184 >= 1.0) {
            $dret[1] = $tjd_ut1 + $dhour / 24.0;
            $dret[0] = $dret[1] + $this->swePhp->swephLib->swe_deltat_ex($dret[1], -1);
            return SweConst::OK;
        }
        //
        // if input second is 60: is it a valid leap second?
        //
        if ($dsec >= 60) {
            $j = 0;
            for ($i = 0; $i < $tabsiz_nleap; $i++) {
                if ($ndat == self::$leap_seconds[$i]) {
                    $j = 1;
                    break;
                }
            }
            if ($j != 1) {
                if ($serr)
                    $serr = sprintf("invalid time (no leap second!): %d:%d:%.2f", $ihour, $imin, $dsec);
                return SweConst::ERR;
            }
        }
        //
        // Convert UTC to ET and UT1
        //

        // the number of days between input date and 1 jan 1972:
        $d = $tjd_ut1 - self::J1972;
        // SI time since 1972, ignoring leap seconds:
        $d += (float)$ihour / 24.0 + (float)$imin / 1440.0 + $dsec / 86400.0;
        // ET (TT)
        $tjd_et_1972 = self::J1972 + (32.184 + self::NLEAP_INIT) / 86400.0;
        $tjd_et = $tjd_et_1972 + $d + ((float)($nleap - self::NLEAP_INIT)) / 86400.0;
        $d = $this->swePhp->swephLib->swe_deltat_ex($tjd_et, -1);
        $tjd_ut1 = $tjd_et - $this->swePhp->swephLib->swe_deltat_ex($tjd_et - $d, -1);
        $tjd_ut1 = $tjd_et - $this->swePhp->swephLib->swe_deltat_ex($tjd_ut1, -1);
        $dret[0] = $tjd_et;
        $dret[1] = $tjd_ut1;
        return SweConst::OK;
    }

    public function swe_jdet_to_utc(float $tjd_et, int $gregflag, int &$iyear, int &$imonth, int &$iday,
                                    int   &$ihour, int &$imin, float &$dsec): void
    {
        $iyear2 = 0;
        $imonth2 = 0;
        $iday2 = 0;
        $dret = [];
        $second_60 = 0;
        //
        // if tjd_et is before 1 jan 1972 UTC, return UT1
        //
        $tjd_et_1972 = self::J1972 + (32.184 + self::NLEAP_INIT) / 86400.0;
        $d = $this->swePhp->swephLib->swe_deltat_ex($tjd_et, -1);
        $tjd_ut = $tjd_et - $this->swePhp->swephLib->swe_deltat_ex($tjd_et - $d, -1);
        $tjd_ut = $tjd_et - $this->swePhp->swephLib->swe_deltat_ex($tjd_ut, -1);
        if ($tjd_et < $tjd_et_1972) {
            $this->swe_revjul($tjd_ut, $gregflag, $iyear, $imonth, $iday, $d);
            $ihour = (int)$d;
            $d -= (float)$ihour;
            $d *= 60;
            $imin = (int)$d;
            $dsec = ($d - (float)$imin) * 60.0;
            return;
        }
        //
        // minimum number of leap seconds since 1972; we may be missing one leap
        // second
        //
        $tabsiz_nleap = $this->init_leapsec();
        $this->swe_revjul($tjd_ut - 1, self::SE_GREG_CAL, $iyear2, $imonth2,
            $iday2, $d);
        $ndat = $iyear2 * 10000 + $imonth2 * 100 + $iday2;
        $nleap = 0;
        for ($i = 0; $i < $tabsiz_nleap; $i++) {
            if ($ndat <= self::$leap_seconds[$i])
                break;
            $nleap++;
        }
        // date of potentially missing leapsecond
        if ($nleap < $tabsiz_nleap) {
            $i = self::$leap_seconds[$nleap];
            $iyear2 = $i / 10000;
            $imonth2 = ($i % 10000) / 100;
            $iday2 = $i % 100;
            $tjd = $this->swe_julday($iyear2, $imonth2, $iday2, 0, self::SE_GREG_CAL);
            $this->swe_revjul($tjd + 1, self::SE_GREG_CAL, $iyear2, $imonth2, $iday2, $d);
            $this->swe_utc_to_jd($iyear2, $imonth2, $iday, 0, 0, 0, self::SE_GREG_CAL, $dret);
            $d = $tjd_et - ($dret[0] ?? 0.0);
            if ($d >= 0) {
                $nleap++;
            } else if ($d > -1.0 / 86400.0) {
                $second_60 = 1;
            }
        }
        //
        // UTC, still unsure about one leap second
        //
        $tjd = self::J1972 + ($tjd_et - $tjd_et_1972 - ((float)$nleap + $second_60) / 86400.0);
        $this->swe_revjul($tjd, self::SE_GREG_CAL, $iyear, $imonth, $iday, $d);
        $ihour = (int)$d;
        $d -= (float)$ihour;
        $d *= 60;
        $imin = (int)$d;
        $dsec = ($d - (float)$imin) * 60.0 + $second_60;
        //
        // For input dates > today:
        // If leap seconds table is not up-to-date, we'd better interpret the
        // input time as UT1, not as UTC. How do we find out?
        // Check, if delta_t - nleap - 32.184 > 0.9
        //
        $d = $this->swePhp->swephLib->swe_deltat_ex($tjd_et, -1);
        $d = $this->swePhp->swephLib->swe_deltat_ex($tjd_et_1972 - $d, -1);
        if ($d * 86400.0 - (float)($nleap + self::NLEAP_INIT) - 32.184 >= 1.0) {
            $this->swe_revjul($tjd_et - $d, self::SE_GREG_CAL, $iyear, $imonth, $iday, $d);
            $ihour = (int)$d;
            $d -= (float)$ihour;
            $d *= 60;
            $imin = (int)$d;
            $dsec = ($d - (float)$imin) * 60.0;
        }
        if ($gregflag == self::SE_JUL_CAL) {
            $tjd = $this->swe_julday($iyear, $imonth, $iday, 0, self::SE_GREG_CAL);
            $this->swe_revjul($tjd, $gregflag, $iyear, $imonth, $iday, $d);
        }
    }

    public function swe_jdut1_to_utc(float $tjd_ut, int $gregflag, int &$iyear, int &$imonth, int &$iday,
                                     int   &$ihour, int &$imin, float &$dsec): void
    {
        $tjd_et = $tjd_ut + $this->swePhp->swephLib->swe_deltat_ex($tjd_ut, -1);
        $this->swe_jdet_to_utc($tjd_et, $gregflag, $iyear, $imonth, $iday,
            $ihour, $imin, $dsec);
    }
}