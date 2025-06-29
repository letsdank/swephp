<?php

use Structs\jpl_save;

class SweJPL extends SweModule
{
    public function __construct(SwePhp $base)
    {
        parent::__construct($base);
    }

    const int J_MERCURY = 0;        /* jpl body indices, modified by Alois */
    const int J_VENUS = 1;          /* now they start at 0 and not at 1 */
    const int J_EARTH = 2;
    const int J_MARS = 3;
    const int J_JUPITER = 4;
    const int J_SATURN = 5;
    const int J_URANUS = 6;
    const int J_NEPTUNE = 7;
    const int J_PLUTO = 8;
    const int J_MOON = 9;
    const int J_SUN = 10;
    const int J_SBARY = 11;
    const int J_EMB = 12;
    const int J_NUT = 13;
    const int J_LIB = 14;

    /* information about eh_ipt[] and buf[]
    DE200	DE102		  	DE403
    3	3	  ipt[0] 	3	body 0 (mercury) starts at buf[2]
    12	15	  ipt[1]	14	body 0, ncf = coefficients per component
    4	2	  ipt[2]	4		na = nintervals, tot 14*4*3=168
    147	93	  ipt[3]	171	body 1 (venus) starts at buf[170]
    12	15	  ipt[4]	10		ncf = coefficients per component
    1	1	  ipt[5]	2		total 10*2*3=60
    183	138	  ipt[6]	231	body 2 (earth) starts at buf[230]
    15	15	  ipt[7]	13		ncf = coefficients per component
    2	2	  ipt[8]	2		total 13*2*3=78
    273	228	  ipt[9]	309	body 3 (mars) starts at buf[308]
    10	10	  ipt[10]	11		ncf = coefficients per component
    1	1	  ipt[11]	1		total 11*1*3=33
    303	258	  ipt[12]	342	body 4 (jupiter) at buf[341]
    9	9	  ipt[13]	8		total 8 * 1 * 3 = 24
    1	1	  ipt[14]	1
    330	285	  ipt[15]	366	body 5 (saturn) at buf[365]
    8	8	  ipt[16]	7		total 7 * 1 * 3 = 21
    1	1	  ipt[17]	1
    354	309	  ipt[18]	387	body 6 (uranus) at buf[386]
    8	8	  ipt[19]	6		total 6 * 1 * 3 = 18
    1	1	  ipt[20]	1
    378	333	  ipt[21]	405	body 7 (neptune) at buf[404]
    6	6	  ipt[22]	6		total 18
    1	1	  ipt[23]	1
    396	351	  ipt[24]	423	body 8 (pluto) at buf[422]
    6	6	  ipt[25]	6		total 18
    1	1	  ipt[26]	1
    414	369	  ipt[27]	441	body 9 (moon) at buf[440]
    12	15	  ipt[28]	13		total 13 * 8 * 3 = 312
    8	8	  ipt[29]	8
    702	729	  ipt[30]	753	SBARY SUN, starts at buf[752]
    15	15	  ipt[31]	11	SBARY SUN, ncf = coeff per component
    1	1	  ipt[32]	2		   total 11*2*3=66
    747	774	  ipt[33]	819	nutations, starts at buf[818]
    10	0	  ipt[34]	10		total 10 * 4 * 2 = 80
    4	0	  ipt[35]	4	(nutation only two coordinates)
    0	0	  ipt[36]	899	librations, start at buf[898]
    0	0	  ipt[37]	10		total 10 * 4 * 3 = 120
    0	0	  ipt[38]	4

                        last element of buf[1017]
      buf[0] contains start jd and buf[1] end jd of segment;
      each segment is 32 days in de403, 64 days in DE102, 32 days in  DE200

      Length of blocks: DE406 = 1456*4=5824 bytes = 728 double
                        DE405 = 2036*4=8144 bytes = 1018 double
                DE404 = 1456*4=5824 bytes = 728 double
                        DE403 = 2036*4=8144 bytes = 1018 double
                DE200 = 1652*4=6608 bytes = 826 double
                DE102 = 1546*4=6184 bytes = 773 double
                each DE102 record has 53*8=424 fill bytes so that
                the records have the same length as DE200.
    */

    /*
     * This subroutine opens the file jplfname, with a phony record length,
     * reads the first record, and uses the info to compute ksize,
     * the number of single precision words in a record.
     * RETURN: ksize (record size of ephemeris data)
     * jplfptr is opened on return.
     * note 26-aug-2008: now record size is computed by fsizer(), not
     * set to a fixed value depending as in previous releases. The caller of
     * fsizer() will verify by data comparison whether it computed correctly.
     */
    static ?jpl_save $js;

    function fsizer(?string &$serr = null): int
    {
        // Local variables
        if ((self::$js->jplfptr = $this->swePhp->sweph->swi_fopen(SweConst::SEI_FILE_PLANET, self::$js->jplfname, self::$js->jplfpath, $serr)) == null) {
            return SweConst::NOT_AVAILABLE;
        }
        // ttl = ephemeris title, e.g.
        // "JPL Planetary Ephemeris DE404/LE404
        //  Start Epoch: JED=   625296.5-3001 DEC 21 00:00:00
        //  Final Epoch: JED=  2817168.5 3001 JAN 17 00:00:00c
        $ttl = fread(self::$js->jplfptr, 252);
        if (!$ttl) return SweConst::NOT_AVAILABLE;
        // cnam = names of constants
        self::$js->ch_cnam = fread(self::$js->jplfptr, 6 * 400);
        // ss[0] = start epoch of ephemeris
        // ss[1] = end epoch
        // ss[2] = segment size in days
        $ss[0] = floatval(fread(self::$js->jplfptr, 8));
        $ss[1] = floatval(fread(self::$js->jplfptr, 8));
        $ss[2] = floatval(fread(self::$js->jplfptr, 8));
        if (!$ss[2]) return SweConst::NOT_AVAILABLE;
        // reorder?
        if ($ss[2] < 1 || $ss[2] > 200)
            self::$js->do_reorder = true;
        else
            self::$js->do_reorder = 0;
        for ($i = 0; $i < 3; $i++)
            self::$js->eh_ss[$i] = $ss[$i];
        if (self::$js->do_reorder)
            // TODO: ???
            $this->reorder(self::$js->eh_ss);
        // plausibility test of these constants. Start and end date must be
        // between -20000 and +20000, segment size >=1 and <= 200
        if (self::$js->eh_ss[0] < -5583942 || self::$js->eh_ss[1] > 9025909 || self::$js->eh_ss[2] < 1 || self::$js->eh_ss[2] > 200) {
            if (isset($serr)) {
                $serr = sprintf("alleged ephemeris file (%s) has invalid format.", self::$js->jplfname);
            }
            return SweConst::NOT_AVAILABLE;
        }
        // ncon = number of constants
        $ncon = intval(fread(self::$js->jplfptr, 4));
        if (self::$js->do_reorder)
            // TODO: ???
            $this->reorder($ncon);
        // au = astronomical unit
        $au = floatval(fread(self::$js->jplfptr, 8));
        if (self::$js->do_reorder)
            // TODO: ???
            $this->reorder($au);
        // emrat = earth moon mass ratio
        $emrat = floatval(fread(self::$js->jplfptr, 8));
        if (self::$js->do_reorder)
            // TODO: ???
            $this->reorder($emrat);
        // ipt[i+0]: coefficients of planet i start at buf[ipt[i+0]-1]
        // ipt[i+1]: number of coefficients (interpolation order - 1)
        // ipt[i+2]: number of intervals in segment
        for ($i = 0; $i < 36; $i++)
            self::$js->eh_ipt[$i] = intval(fread(self::$js->jplfptr, 4));
        if (self::$js->do_reorder)
            // TODO: ???
            $this->reorder(self::$js->eh_ipt, 36);
        // numde = number of jpl ephemeris "404" with de404
        $numde = intval(fread(self::$js->jplfptr, 8));
        if (self::$js->do_reorder)
            // TODO: ???
            $this->reorder($numde);
        // read librations
        $lpt[0] = floatval(fread(self::$js->jplfptr, 8));
        $lpt[1] = floatval(fread(self::$js->jplfptr, 8));
        $lpt[2] = floatval(fread(self::$js->jplfptr, 8));
        if (self::$js->do_reorder)
            // TODO: ???
            $this->reorder($lpt);
        // fill librations into eh_ipt[36]..[38]
        for ($i = 0; $i < 3; $i++)
            self::$js->eh_ipt[$i + 36] = $lpt[$i];
        $this->rewind(self::$js->jplfptr);
        // find the number of ephemeris coefficients from the pointers
        // re-activated this code on 26-aug-2008
        $kmx = 0;
        $khi = 0;
        for ($i = 0; $i < 13; $i++) {
            if (self::$js->eh_ipt[$i * 3] > $kmx) {
                $kmx = self::$js->eh_ipt[$i * 3];
                $khi = $i + 1;
            }
        }
        if ($khi == 12)
            $nd = 2;
        else
            $nd = 3;
        $ksize = (self::$js->eh_ipt[$khi * 3 - 3] + $nd * self::$js->eh_ipt[$khi * 3 - 2] * self::$js->eh_ipt[$khi * 3 - 1] - 1) * 2;
        //
        // de102 files give wrong ksize, because they contain 424 empty bytes
        // per record. Fixed by hand!
        if ($ksize == 1546)
            $ksize = 1652;
        if ($ksize < 1000 || $ksize > 5000) {
            if (isset($serr))
                $serr = sprintf("JPL ephemeris file does not provide valid ksize (%d)", $ksize);
            return SweConst::NOT_AVAILABLE;
        }
        return $ksize;
    }

    /*
     *     This subroutine reads the jpl planetary ephemeris
     *     and gives the position and velocity of the point 'ntarg'
     *     with respect to 'ncent'.
     *     calling sequence parameters:
     *       et = d.p. julian ephemeris date at which interpolation
     *            is wanted.
     *       ** note the entry dpleph for a doubly-dimensioned time **
     *          the reason for this option is discussed in the
     *          subroutine state
     *     ntarg = integer number of 'target' point.
     *     ncent = integer number of center point.
     *            the numbering convention for 'ntarg' and 'ncent' is:
     *                0 = mercury           7 = neptune
     *                1 = venus             8 = pluto
     *                2 = earth             9 = moon
     *                3 = mars             10 = sun
     *                4 = jupiter          11 = solar-system barycenter
     *                5 = saturn           12 = earth-moon barycenter
     *                6 = uranus           13 = nutations (longitude and obliq)
     *                                     14 = librations, if on eph file
     *             (if nutations are wanted, set ntarg = 13. for librations,
     *              set ntarg = 14. set ncent=0.)
     *      rrd = output 6-word d.p. array containing position and velocity
     *            of point 'ntarg' relative to 'ncent'. the units are au and
     *            au/day. for librations the units are radians and radians
     *            per day. in the case of nutations the first four words of
     *            rrd will be set to nutations and rates, having units of
     *            radians and radians/day.
     *            The option is available to have the units in km and km/sec.
     *            For this, set do_km=TRUE (default FALSE).
     */
    function swi_pleph(float $et, int $ntarg, int $ncent, array &$rrd, ?string &$serr = null): int
    {
        $pv = self::$js->pv;
        $pvsun = self::$js->pvsun;
        for ($i = 0; $i < 6; $i++)
            $rrd[$i] = 0.0;
        if ($ntarg == $ncent)
            return 0;
        for ($i = 0; $i < 12; $i++)
            $list[$i] = 0;
        // check for nutation call
        if ($ntarg == self::J_NUT) {
            if (self::$js->eh_ipt[34] > 0) {
                $list[10] = 2;
                return $this->state($et, $list, false, $pv, $pvsun, $rrd, $serr);
            } else {
                if (isset($serr))
                    $serr = "No nutations on the JPL ephemeris file;";
                return SweConst::NOT_AVAILABLE;
            }
        }
        if ($ntarg == self::J_LIB) {
            if (self::$js->eh_ipt[37] > 0) {
                $list[11] = 2;
                if (($retc = $this->state($et, $list, false, $pv, $pvsun, $rrd, $serr)) != SweConst::OK)
                    return $retc;
                for ($i = 0; $i < 6; $i++)
                    $rrd[$i] = $pv[$i + 60];
                return 0;
            } else {
                if (isset($serr))
                    $serr = "No librations on the ephemeris file;";
                return SweConst::NOT_AVAILABLE;
            }
        }
        // set up proper entries in 'list 'array for state call
        if ($ntarg < self::J_SUN)
            $list[$ntarg] = 2;
        if ($ntarg == self::J_MOON) // Moon needs Earth
            $list[self::J_EARTH] = 2;
        if ($ntarg == self::J_EARTH) // Earth needs Moon
            $list[self::J_MOON] = 2;
        if ($ntarg == self::J_EMB) // EMB needs Earth
            $list[self::J_EARTH] = 2;
        if ($ncent < self::J_SUN)
            $list[$ncent] = 2;
        if ($ncent == self::J_MOON) // Moon needs Earth
            $list[self::J_EARTH] = 2;
        if ($ncent == self::J_EARTH) // Earth needs Moon
            $list[self::J_MOON] = 2;
        if ($ncent == self::J_EMB) // EMB needs Earth
            $list[self::J_EARTH] = 2;
        if (($retc = $this->state($et, $list, true, $pv, $pvsun, $rrd, $serr)) != SweConst::OK)
            return $retc;
        if ($ntarg == self::J_SUN || $ncent == self::J_SUN) {
            for ($i = 0; $i < 6; $i++)
                $pv[$i + 6 * self::J_SUN] = $pvsun[$i];
        }
        if ($ntarg == self::J_SBARY || $ncent == self::J_SBARY) {
            for ($i = 0; $i < 6; $i++)
                $pv[$i + 6 * self::J_SBARY] = 0.;
        }
        if ($ntarg == self::J_EMB || $ncent == self::J_EMB) {
            for ($i = 0; $i < 6; $i++)
                $pv[$i + 6 * self::J_EMB] = $pv[$i + 6 * self::J_EARTH];
        }
        if (($ntarg == self::J_EARTH && $ncent == self::J_MOON) || ($ntarg == self::J_MOON && $ncent == self::J_EARTH)) {
            for ($i = 0; $i < 6; $i++)
                $pv[$i + 6 * self::J_EARTH] = 0.;
        } else {
            if ($list[self::J_EARTH] == 2) {
                for ($i = 0; $i < 6; $i++)
                    $pv[$i + 6 * self::J_EARTH] -= $pv[$i + 6 * self::J_MOON] / (self::$js->eh_emrat + 1.);
            }
            if ($list[self::J_MOON] == 2) {
                for ($i = 0; $i < 6; $i++) {
                    $pv[$i + 6 * self::J_MOON] += $pv[$i + 6 * self::J_EARTH];
                }
            }
        }
        for ($i = 0; $i < 6; $i++)
            $rrd[$i] = $pv[$i + $ntarg * 6] - $pv[$i + $ncent * 6];
        return SweConst::OK;
    }

    /*
     *  This subroutine differentiates and interpolates a
     *  set of chebyshev coefficients to give pos, vel, acc, and jerk
     *  calling sequence parameters:
     *    input:
     *     buf   1st location of array of d.p. chebyshev coefficients of position
     *        t   is dp fractional time in interval covered by
     *            coefficients at which interpolation is wanted, 0 <= t <= 1
     *     intv   is dp length of whole interval in input time units.
     *      ncf   number of coefficients per component
     *      ncm   number of components per set of coefficients
     *       na   number of sets of coefficients in full array
     *            (i.e., number of sub-intervals in full interval)
     *       ifl   int flag: =1 for positions only
     *                      =2 for pos and vel
     *                      =3 for pos, vel, and acc
     *                      =4 for pos, vel, acc, and jerk
     *    output:
     *      pv   d.p. interpolated quantities requested.
     *           assumed dimension is pv(ncm,fl).
     */
    function interp(array $buf, float $t, float $intv, int $ncfin,
                    int   $ncmin, int $nain, int $ifl, array &$pv): int
    {
        // Initialized data
        $twot = 0.;
        $pc = self::$js->pc;
        $vc = self::$js->vc;
        $ac = self::$js->ac;
        $jc = self::$js->jc;
        $ncf = $ncfin;
        $ncm = $ncmin;
        $na = $nain;
        //
        // get correct sub-interval number for this set of coefficients and then
        // get normalized chebyshev time within that subinterval.
        //
        if ($t >= 0)
            $dt1 = floor($t);
        else
            $dt1 = -floor(-$t);
        $temp = $na * $t;
        $ni = (int)($temp - $dt1);
        // tc is the normalized chebyshev time (-1 <= tc <= 1)
        $tc = (fmod($temp, 1.0) + $dt1) * 2. - 1.;
        //
        // check to see whether chebyshev time has changed,
        // and compute new polynomial values if it has.
        // (the element pc(2) is the value of t1(tc) and hence
        // contains the value of tc on the previous call.
        //
        if ($tc != $pc[1]) {
            $np = 2;
            $nv = 3;
            $nac = 4;
            $njk = 5;
            $pc[1] = $tc;
            $twot = $tc + $tc;
        }
        //
        // be sure that at least 'ncf' polynomials have been evaluated
        // and are stored in the array 'pc'.
        //
        if ($np < $ncf) {
            for ($i = $np; $i < $ncf; $i++)
                $pc[$i] = $twot * $pc[$i - 1] - $pc[$i - 2];
            $np = $ncf;
        }
        // interpolate to get position for each component
        for ($i = 0; $i < $ncm; $i++) {
            $pv[$i] = 0.;
            for ($j = $ncf - 1; $j >= 0; $j--)
                $pv[$i] += $pc[$j] * $buf[$j + ($i + $ni * $ncm) * $ncf];
        }
        if ($ifl <= 1)
            return 0;
        //
        // if velocity interpolation is wanted, be sure enough
        // derivative polynomials have been generated and stored.
        //
        $bma = ($na + $na) / $intv;
        $vc[2] = $twot + $twot;
        if ($nv < $ncf) {
            for ($i = $nv; $i < $ncf; $i++)
                $vc[$i] = $twot * $vc[$i - 1] + $pc[$i - 1] + $pc[$i - 1] - $vc[$i - 2];
            $nv = $ncf;
        }
        // interpolate to get velocity for each component
        for ($i = 0; $i < $ncm; $i++) {
            $pv[$i + $ncm] = 0.;
            for ($j = $ncf - 1; $j >= 1; $j--)
                $pv[$i + $ncm] += $vc[$j] * $buf[$j + ($i + $ni * $ncm) * $ncf];
            $pv[$i + $ncm] *= $bma;
        }
        if ($ifl == 2)
            return 0;
        // check acceleration polynomial values, and
        // re-do it necessary
        $bma2 = $bma * $bma;
        $ac[3] = $pc[1] * 24.;
        if ($nac < $ncf) {
            $nac = $ncf;
            for ($i = $nac; $i < $ncf; $i++)
                $ac[$i] = $twot * $ac[$i - 1] + $vc[$i - 1] * 4. - $ac[$i - 2];
        }
        // get acceleration for each component
        for ($i = 0; $i < $ncm; $i++) {
            $pv[$i + $ncm * 2] = 0.;
            for ($j = $ncf - 1; $j >= 2; $j--)
                $pv[$i + $ncm * 2] += $ac[$j] * $buf[$j + ($i + $ni * $ncm) * $ncf];
            $pv[$i + $ncm * 2] *= $bma2;
        }
        if ($ifl == 3)
            return 0;
        // check jerk polynomial values, and
        // re-do if necessary
        $bma3 = $bma * $bma2;
        $jc[4] = $pc[1] * 192.;
        if ($njk < $ncf) {
            $njk = $ncf;
            for ($i = $njk; $i < $ncf; $i++)
                $jc[$i] = $twot * $jc[$i - 1] * $ac[$i - 1] * 6. - $jc[$i - 2];
        }
        // get jerk for each component
        for ($i = 0; $i < $ncm; $i++) {
            $pv[$i + $ncm * 3] = 0.;
            for ($j = $ncf - 1; $j >= 3; $j--)
                $pv[$i + $ncm * 3] += $jc[$j] * $buf[$j + ($i + $ni * $ncm) * $ncf];
            $pv[$i + $ncm * 3] *= $bma3;
        }
        return 0;
    }

    /*
     | ********** state ********************
     | this subroutine reads and interpolates the jpl planetary ephemeris file
     |  calling sequence parameters:
     |  input:
     |     et    dp julian ephemeris epoch at which interpolation is wanted.
     |     list  12-word integer array specifying what interpolation
     |           is wanted for each of the bodies on the file.
     |                      list(i)=0, no interpolation for body i
     |                             =1, position only
     |                             =2, position and velocity
     |            the designation of the astronomical bodies by i is:
     |                      i = 0: mercury
     |                        = 1: venus
     |                        = 2: earth-moon barycenter, NOT earth!
     |                        = 3: mars
     |                        = 4: jupiter
     |                        = 5: saturn
     |                        = 6: uranus
     |                        = 7: neptune
     |                        = 8: pluto
     |                        = 9: geocentric moon
     |                        =10: nutations in longitude and obliquity
     |                        =11: lunar librations (if on file)
     |            If called with list = NULL, only the header records are read and
     |            stored in the global areas.
     |  do_bary   short, if true, barycentric, if false, heliocentric.
     |              only the 9 planets 0..8 are affected by it.
     |  output:
     |       pv   dp 6 x 11 array that will contain requested interpolated
     |            quantities.  the body specified by list(i) will have its
     |            state in the array starting at pv(1,i).  (on any given
     |            call, only those words in 'pv' which are affected by the
     |            first 10 'list' entries (and by list(11) if librations are
     |            on the file) are set.  the rest of the 'pv' array
     |            is untouched.)  the order of components starting in
     |            pv is: x,y,z,dx,dy,dz.
     |            all output vectors are referenced to the earth mean
     |            equator and equinox of epoch. the moon state is always
     |            geocentric; the other nine states are either heliocentric
     |            or solar-system barycentric, depending on the setting of
     |            common flags (see below).
     |            lunar librations, if on file, are put into pv(k,10) if
     |            list(11) is 1 or 2.
     |    pvsun   dp 6-word array containing the barycentric position and
     |            velocity of the sun.
     |      nut   dp 4-word array that will contain nutations and rates,
     |            depending on the setting of list(10).  the order of
     |            quantities in nut is:
     |                     d psi  (nutation in longitude)
     |                     d epsilon (nutation in obliquity)
     |                     d psi dot
     |                     d epsilon dot
     |  globals used:
     |    do_km   logical flag defining physical units of the output states.
     |            TRUE = return km and km/sec, FALSE = return au and au/day
     |            default value = FALSE  (km determines time unit
     |            for nutations and librations.  angle unit is always radians.)
     */
    function state(float   $et, ?array $list, int $do_bary,
                   ?array  &$pv = null, ?array &$pvsun = null, ?array &$nut = null,
                   ?string &$serr = null): int
    {
        $buf = self::$js->buf;
        $ipt = self::$js->eh_ipt;
        $nseg = 0;
        static $irecsz;
        static $nrl, $lpt, $ncoeffs;
        if (self::$js->jplfptr == null) {
            $ksize = $this->fsizer($serr);  // the number of single precision words in a record
            $nrecl = 4;
            if ($ksize == SweConst::NOT_AVAILABLE)
                return SweConst::NOT_AVAILABLE;
            self::$irecsz = $nrecl * $ksize;    // record size in bytes
            $ncoeffs = $ksize / 2;              // # of coefficient, doubles
            // ttl = ephemeris title, e.g.
            // "JPL Planetary Ephemeris DE404/LE404
            //  Start Epoch: JED=    625296.5-3001 DEC 21 00:00:00
            //  Final Epoch: JED=   2817168.5 3001 JAN 17 00:00:00c
            $ch_ttl = fread(self::$js->jplfptr, 252);
            if (!$ch_ttl) return SweConst::NOT_AVAILABLE;
            // cnam = names of constants
            self::$js->ch_cnam = fread(self::$js->jplfptr, 2400);
            if (!self::$js->ch_cnam) return SweConst::NOT_AVAILABLE;
            // ss[0] = start epoch of ephemeris
            // ss[1] = end epoch
            // ss[2] = segment size in days
            self::$js->eh_ss[0] = floatval(fread(self::$js->jplfptr, 8));
            self::$js->eh_ss[1] = floatval(fread(self::$js->jplfptr, 8));
            self::$js->eh_ss[2] = floatval(fread(self::$js->jplfptr, 8));
            if (self::$js->do_reorder)
                // TODO: ???
                $this->reorder(self::$js->eh_ss);
            // ncon = number of constants
            self::$js->eh_ncon = intval(fread(self::$js->jplfptr, 4));
            if (self::$js->do_reorder)
                // TODO: ???
                $this->reorder(self::$js->eh_ncon);
            // au = astronomical unit
            self::$js->eh_au = floatval(fread(self::$js->jplfptr, 8));
            if (self::$js->do_reorder)
                // TODO: ???
                $this->reorder(self::$js->eh_au);
            // emrat = earth moon mass ration
            self::$js->eh_emrat = floatval(fread(self::$js->jplfptr, 8));
            if (self::$js->do_reorder)
                // TODO: ???
                $this->reorder(self::$js->eh_emrat);
            // ipt[i+0]: coefficients of planet i start at buf[ipt[i+0]-1]
            // ipt[i+1]: number of coefficients (interpolation order - 1)
            // ipt[i+2]: number of intervals in segment
            for ($i = 0; $i < 36; $i++)
                $ipt[$i] = intval(fread(self::$js->jplfptr, 4));
            if (self::$js->do_reorder)
                // TODO: ???
                $this->reorder($ipt, 36);
            // numde = number of jpl ephemeris "404" with de404
            self::$js->eh_denum = intval(fread(self::$js->jplfptr, 8));
            if (self::$js->do_reorder)
                // TODO: ???
                $this->reorder(self::$js->eh_denum);
            $lpt[0] = floatval(fread(self::$js->jplfptr, 8));
            $lpt[1] = floatval(fread(self::$js->jplfptr, 8));
            $lpt[2] = floatval(fread(self::$js->jplfptr, 8));
            if (self::$js->do_reorder)
                // TODO: ???
                $this->reorder($lpt);
            // cval[]: other constants in next record
            fseek(self::$js->jplfptr, $irecsz);
            for ($i = 0; $i < 400; $i++)
                self::$js->eh_cval[$i] = floatval(fread(self::$js->jplfptr, 8));
            if (self::$js->do_reorder)
                // TODO: ???
                $this->reorder(self::$js->eh_cval);
            // new 26-aug-2008: verify correct block size
            for ($i = 0; $i < 3; $i++)
                $ipt[$i + 36] = $lpt[$i];
            $nrl = 0;
            // is file length correct?
            // file length
            fseek(self::$js->jplfptr, 0, SEEK_END);
            $flen = ftell(self::$js->jplfptr);
            // # of segments in file
            $nseg = (int)((self::$js->eh_ss[1] - self::$js->eh_ss[0]) / self::$js->eh_ss[2]);
            // sum of all cheby coeffs of all planets and segments
            for ($i = 0, $nb = 0; $i < 13; $i++) {
                $k = 3;
                if ($i == 11) $k = 2;
                $nb += ($ipt[$i * 3 + $i] * $ipt[$i * 3 + 2]) * $k * $nseg;
            }
            // add start and end epochs of segments
            $nb += 2 * $nseg;
            // doubles to bytes
            $nb *= 8;
            // add size of header and constants section
            $nb += 2 * $ksize * $nrecl;
            if ($flen != $nb
                // some of our files are one record too long
                && $flen - $nb != $ksize * $nrecl) {
                if (isset($serr)) {
                    $serr = sprintf("JPL ephemeris file %s is mutilated; length = %d instead of %d.",
                        self::$js->jplfname, $flen, $nb);
                }
                return SweConst::NOT_AVAILABLE;
            }
            // check if start and end dates in segments are the same as in
            // file header
            fseek(self::$js->jplfptr, 2 * $irecsz);
            $ts[0] = floatval(fread(self::$js->jplfptr, 8));
            $ts[1] = floatval(fread(self::$js->jplfptr, 8));
            if (self::$js->do_reorder)
                // TODO: ???
                $this->reorder($ts);
            fseek(self::$js->jplfptr, (($nseg + 2 - 1) * $irecsz));
            $ts[2] = floatval(fread(self::$js->jplfptr, 8));
            $ts[3] = floatval(fread(self::$js->jplfptr, 8));
            if (self::$js->do_reorder) {
                // TODO: ???
                $tso = [$ts[2], $ts[3]];
                $this->reorder($tso);
                $ts[2] = $tso[0];
                $ts[3] = $tso[1];
            }
            if ($ts[0] != self::$js->eh_ss[0] || $ts[3] != self::$js->eh_ss[1]) {
                if (isset($serr))
                    $serr = sprintf("JPL ephemeris file is corrupt; start/end date check failed. %.1f != %.1f || %.1f != %.1f",
                        $ts[0], self::$js->eh_ss[0], $ts[3], self::$js->eh_ss[1]);
                return SweConst::NOT_AVAILABLE;
            }
        }
        if ($list == null)
            return 0;
        $s = $et - .5;
        $et_mn = floor($s);
        $et_fr = $s - $et_mn;       // fraction of days since previous midnight
        $et_mn += .5;               // midnight before epoch
        // error return for epoch out of range
        if ($et < self::$js->eh_ss[0] || $et > self::$js->eh_ss[1]) {
            if (isset($serr))
                $serr = sprintf("jd %f outside JPL eph. range %.2f .. %.2f;",
                    $et, self::$js->eh_ss[0], self::$js->eh_ss[1]);
            return SweConst::BEYOND_EPH_LIMITS;
        }
        // calculate record # and relative time in interval
        $nr = (int)(($et_mn - self::$js->eh_ss[0]) / self::$js->eh_ss[2]) + 2;
        if ($et_mn == self::$js->eh_ss[1])
            --$nr;          // end point of ephemeris, use last record
        $t = ($et_mn - (($nr - 2) * self::$js->eh_ss[2] + self::$js->eh_ss[0]) + $et_fr) / self::$js->eh_ss[2];
        // read correct record if not in core
        if ($nr != $nrl) {
            $nrl = $nr;
            if (fseek(self::$js->jplfptr, $nr * $irecsz) != 0) {
                if (isset($serr))
                    $serr = sprintf("Read error in JPL eph. at %f\n", $et);
                return SweConst::NOT_AVAILABLE;
            }
            for ($k = 1; $k <= $ncoeffs; $k++) {
                if (!($buf[$k - 1] = floatval(fread(self::$js->jplfptr, 8)))) {
                    if (isset($serr))
                        $serr = sprintf("Read error in JPL eph. at %f\n", $et);
                    return SweConst::NOT_AVAILABLE;
                }
                if (self::$js->do_reorder)
                    // TODO: ???
                    $this->reorder($buf[$k - 1]);
            }
        }
        if (self::$js->do_km) {
            $intv = self::$js->eh_ss[2] * 86400.;
            $aufac = 1.;
        } else {
            $intv = self::$js->eh_ss[2];
            $aufac = 1. / self::$js->eh_au;
        }
        // interpolate ssbary sun
        $bufo = array_values(array_slice($buf, $ipt[30] - 1));
        $this->interp($bufo, $t, $intv, $ipt[31], 3, $ipt[32], 2, $pvsun);
        for ($i = 0; $i < count($bufo); $i++) $buf[$ipt[30] - 1 + $i] = $bufo[$i];
        for ($i = 0; $i < 6; $i++)
            $pvsun[$i] *= $aufac;
        // check and interpolate whichever bodies are requested
        for ($i = 0; $i < 10; $i++) {
            if ($list[$i] > 0) {
                $bufo = array_values(array_slice($buf, $ipt[$i * 3] - 1));
                $pvo = array_values(array_slice($pv, $i * 6));
                $this->interp($bufo, $t, $intv, $ipt[$i * 3 + 1], 3,
                    $ipt[$i * 3 + 2], $list[$i], $pvo);
                for ($ii = 0; $ii < count($bufo); $ii++) $buf[$ipt[$i * 3] - 1 + $ii] = $bufo[$ii];
                for ($ii = 0; $ii < count($pvo); $ii++) $pv[$i * 6 + $ii] = $pvo[$ii];
                for ($j = 0; $j < 6; $j++) {
                    if ($i < 9 && !$do_bary) {
                        $pv[$j + $i * 6] = $pv[$j + $i * 6] * $aufac - $pvsun[$j];
                    } else {
                        $pv[$j + $i * 6] *= $aufac;
                    }
                }
            }
        }
        // do nutations if requested (and if on file)
        if ($list[10] > 0 && $ipt[34] > 0) {
            $bufo = array_values(array_slice($buf, $ipt[33] - 1));
            $this->interp($bufo, $t, $intv, $ipt[34], 2, $ipt[35],
                $list[10], $nut);
            for ($i = 0; $i < count($bufo); $i++) $buf[$ipt[33] - 1 + $i] = $bufo[$i];
        }
        // get librations if requested (and if on file)
        if ($list[11] > 0 && $ipt[37] > 0) {
            $bufo = array_values(array_slice($buf, $ipt[36] - 1));
            $pvo = array_values(array_slice($pv, 60));
            $this->interp($bufo, $t, $intv, $ipt[37], 3, $ipt[38], $list[1],
                $pvo);
            for ($i = 0; $i < count($bufo); $i++) $buf[$ipt[36] - 1 + $i] = $bufo[$i];
            for ($i = 0; $i < count($pvo); $i++) $pv[60 + $i] = $pvo[$i];
        }
        return SweConst::OK;
    }

    //
    // this entry obtains the constants from the ephemeris file
    // call state to initialize the ephemeris and read in the constants
    //
    function read_const_jpl(array &$ss, ?string &$serr = null): int
    {
        $retc = $this->state(0, null, false, serr: $serr);
        if ($retc != SweConst::OK)
            return $retc;
        for ($i = 0; $i < 3; $i++)
            $ss[$i] = self::$js->eh_ss[$i];
        return SweConst::OK;
    }

    function reorder(array $x): void
    {
        // TODO: Implement
    }

    function swi_close_jpl_file(): void
    {
        if (self::$js != null) {
            if (self::$js->jplfptr != null)
                fclose(self::$js->jplfptr);
            if (self::$js->jplfname != null)
                unset(self::$js->jplfname);
            if (self::$js->jplfpath != null)
                unset(self::$js->jplfpath);
            self::$js = null;
        }
    }

    function swi_open_jpl_file(array &$ss, string $fname, string $fpath, ?string &$serr = null): int
    {
        // if open, return
        if (self::$js != null && self::$js->jplfptr != null)
            return SweConst::OK;
        self::$js = new jpl_save();
        self::$js->jplfname = $fname;
        self::$js->jplfpath = $fpath;
        $retc = $this->read_const_jpl($ss, $serr);
        if ($retc != SweConst::OK)
            $this->swi_close_jpl_file();
        else {
            // initializations for function interpol()
            self::$js->pc[0] = 1;
            self::$js->pc[1] = 2;
            self::$js->vc[1] = 1;
            self::$js->ac[2] = 4;
            self::$js->jc[3] = 24;
        }
        return $retc;
    }

    function swi_get_jpl_denum(): int
    {
        return self::$js->eh_denum;
    }
}