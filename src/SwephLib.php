<?php

use Enums\SweModel;
use Enums\SweModelJPLHorizon;
use Enums\SweModelPrecession;
use Enums\SweTidalAccel;

class SwephLib extends SweModule
{
    const int SEFLG_EPHMASK = (SweConst::SEFLG_JPLEPH | SweConst::SEFLG_SWIEPH | SweConst::SEFLG_MOSEPH);
    const float SE_DELTAT_AUTOMATIC = -1E-10;

    private swephlib_deltat $deltat;
    private swephlib_cotrans $cotrans;

    public function __construct(SwePhp $base)
    {
        parent::__construct($base);
        $this->deltat = new swephlib_deltat($this);
        $this->cotrans = new swephlib_cotrans($this);
    }

    function getSwePhp(): SwePhp
    {
        return $this->swePhp;
    }

    /**
     * Normalization of any degree number to the range [0;360].
     *
     * @param float $x
     * @return float
     */
    public function swe_degnorm(float $x): float
    {
        $y = fmod($x, 360.0);
        if (abs($y) < 1e-13) $y = 0;
        if ($y < 0.0) $y += 360.0;
        return $y;
    }

    /**
     * Normalization of any radian number to the range [0;2*pi].
     *
     * @param float $x
     * @return float
     */
    public function swe_radnorm(float $x): float
    {
        $y = fmod($x, SweConst::TWOPI);
        if (abs($y) < 1e-13) $y = 0;
        if ($y < 0.0) $y += SweConst::TWOPI;
        return $y;
    }

    /**
     * Calculate midpoint (in degrees).
     *
     * @param float $x1
     * @param float $x0
     * @return float
     */
    public function swe_deg_midp(float $x1, float $x0): float
    {
        $d = $this->swe_difdeg2n($x1, $x0);     // arc from x0 to x1
        $y = $this->swe_degnorm($x0 + $d / 2);
        return $y;
    }

    /**
     * Calculate midpoint (in radians).
     *
     * @param float $x1
     * @param float $x0
     * @return float
     */
    public function swe_rad_midp(float $x1, float $x0): float
    {
        return SweConst::DEGTORAD * $this->swe_deg_midp(
                $x1 * SweConst::RADTODEG, $x0 * SweConst::RADTODEG);
    }

    // Reduce x modulo 2*PI
    function swi_mod2PI(float $x): float
    {
        $y = fmod($x, SweConst::TWOPI);
        if ($y < 0.0) $y += SweConst::TWOPI;
        return $y;
    }

    function swi_angnorm(float $x): float
    {
        if ($x < 0.0) return $x + SweConst::TWOPI;
        else if ($x >= SweConst::TWOPI) return $x - SweConst::TWOPI;
        return $x;
    }

    function swi_cross_prod(array $a, array $b, array &$x): void
    {
        $x[0] = $a[1] * $b[2] - $a[2] * $b[1];
        $x[1] = $a[2] * $b[0] - $a[0] * $b[2];
        $x[2] = $a[0] * $b[1] - $a[1] * $b[0];
    }

    // Evaluates a given chebyshev series coef[0..ncf-1]
    // with ncf terms at x in [-1,1]. Communications of the ACM, algorithm 446,
    // April 1973 (vol. 16 no.4) by Dr. Roger Broucke.
    function swi_echeb(float $x, array $coef, int $ncf): float
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
    function swi_edcheb(float $x, array $coef, int $ncf): float
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

    /**
     * Coordinate transformation from ecliptic to the equator or vice-versa.
     *
     * @param array $xpo Array of 3 float for coordinates:
     *  - 0: longitude
     *  - 1: latitude
     *  - 2: distance (unchanged, can be set to 1)
     * @param array $xpn Return array of 3 float with values:
     *  - 0: converted longitude
     *  - 1: converted latitude
     *  - 2: converted distance
     * @param float $eps Obliquity of ecliptic, in degrees
     * @return void
     *
     * @note For equatorial to ecliptical, obliquity must be positive. From ecliptical to
     * equatorial, obliquity must be negative. Longitude, latitude and obliquity
     * are in positive degrees.
     */
    public function swe_cotrans(array $xpo, array &$xpn, float $eps): void
    {
        $e = $eps * SweConst::DEGTORAD;
        for ($i = 0; $i <= 1; $i++)
            $x[$i] = $xpo[$i];
        $x[0] *= SweConst::DEGTORAD;
        $x[1] *= SweConst::DEGTORAD;
        $x[2] = 1;
        for ($i = 3; $i <= 5; $i++)
            $x[$i] = 0;
        $this->cotrans->swi_polcart($x, $x);
        $this->cotrans->swi_coortrf($x, $x, $e);
        $this->cotrans->swi_cartpol($x, $x);
        $xpn[0] = $x[0] * SweConst::RADTODEG;
        $xpn[1] = $x[1] * SweConst::RADTODEG;
        $xpn[2] = $xpo[2];
    }

    /**
     * Coordinate transformation of position and speed, from ecliptic to the equator
     * or vice-versa.
     *
     * @param array $xpo Array of 6 float for coordinates:
     *  - 0: longitude
     *  - 1: latitude
     *  - 2: distance
     *  - 3: longitude speed
     *  - 4: latitude speed
     *  - 5: distance speed
     * @param array $xpn Return array of 6 float with values:
     *  - 0: converted longitude
     *  - 1: converted longitude speed
     *  - 2: converted latitude
     *  - 3: converted latitude speed
     *  - 4: converted distance
     *  - 5: converted distance speed
     * @param float $eps Obliquity of ecliptic, in degrees
     * @return void
     *
     * @note For equatorial to ecliptical, obliquity must be positive. From ecliptical to
     * equatorial, obliquity must be negative. Longitude, latitude, their speeds
     * and obliquity are in positive degrees.
     */
    public function swe_cotrans_sp(array $xpo, array &$xpn, float $eps): void
    {
        $e = $eps * SweConst::DEGTORAD;
        for ($i = 0; $i <= 5; $i++)
            $x[$i] = $xpo[$i];
        $x[0] *= SweConst::DEGTORAD;
        $x[1] *= SweConst::DEGTORAD;
        $x[2] = 1;          // avoids problems with polcart(), if x[2] = 0
        $x[3] *= SweConst::DEGTORAD;
        $x[4] *= SweConst::DEGTORAD;
        $this->cotrans->swi_polcart_sp($x, $x);
        $this->cotrans->swi_coortrf($x, $x, $e);
        $xsp = [$x[3], $x[4], $x[5]];
        $this->cotrans->swi_coortrf($xsp, $xsp, $e);
        $x[3] = $xsp[0];
        $x[4] = $xsp[1];
        $x[5] = $xsp[2];
        unset($xsp);
        $this->cotrans->swi_cartpol_sp($x, $xpn);
        $xpn[0] *= SweConst::RADTODEG;
        $xpn[1] *= SweConst::RADTODEG;
        $xpn[2] = $xpo[2];
        $xpn[3] *= SweConst::RADTODEG;
        $xpn[4] *= SweConst::RADTODEG;
        $xpn[5] = $xpo[5];
    }

    function swi_dot_prod_unit(array $x, array $y): float
    {
        $dop = $x[0] * $y[0] + $x[1] * $y[1] + $x[2] * $y[2];
        $e1 = sqrt($x[0] * $x[0] + $x[1] * $x[1] + $x[2] * $x[2]);
        $e2 = sqrt($y[0] * $y[0] + $y[1] * $y[1] + $y[2] * $y[2]);
        $dop /= $e1;
        $dop /= $e2;
        if ($dop > 1) $dop = 1;
        if ($dop < -1) $dop = -1;
        return $dop;
    }

    // functions for precession and ecliptic obliquity to Vondrák et alii, 2011
    const float AS2R = (SweConst::DEGTORAD / 3600.0);
    const float D2PI = SweConst::TWOPI;
    const float EPS0 = (84381.406 * self::AS2R);
    const int NPOL_PEPS = 4;
    const int NPER_PEPS = 10;
    const int NPOL_PECL = 4;
    const int NPER_PECL = 8;
    const int NPOL_PEQU = 4;
    const int NPER_PEQU = 14;

    // for pre_peps():
    // polynomials
    const array pepol = [
        [+8134.017132, +84028.206305],
        [+5043.0520035, +0.3624445],
        [-0.00710733, -0.00004039],
        [+0.000000271, -0.000000110],
    ];

    // periodics
    const array peper = [
        [+409.90, +396.15, +537.22, +402.90, +417.15, +288.92, +4043.00, +306.00, +277.00, +203.00],
        [-6908.287473, -3198.706291, +1453.674527, -857.748557, +1173.231614, -156.981465, +371.836550, -216.619040, +193.691479, +11.891524],
        [+753.872780, -247.805823, +379.471484, -53.880558, -90.109153, -353.600190, -63.115353, -28.248187, +17.703387, +38.911307],
        [-2845.175469, +449.844989, -1255.915323, +886.736783, +418.887514, +997.912441, -240.979710, +76.541307, -36.788069, -170.964086],
        [-1704.720302, -862.308358, +447.832178, -889.571909, +190.402846, -56.564991, -296.222622, -75.859952, +67.473503, +3.014055],
    ];

    // for pre_pecl():
    // polynomials
    const array pqpol = [
        [+5851.607687, -1600.886300],
        [-0.1189000, +1.1689818],
        [-0.00028913, -0.00000020],
        [+0.000000101, -0.000000437],
    ];

    // periodics
    const array pqper = [
        [708.15, 2309, 1620, 492.2, 1183, 622, 882, 547],
        [-5486.751211, -17.127623, -617.517403, 413.44294, 78.614193, -180.732815, -87.676083, 46.140315],
        // original publication    A&A 534, A22 (2011):
        //{-684.66156, 2446.28388, 399.671049, -356.652376, -186.387003, -316.80007, 198.296071, 101.135679},
        // typo fixed according to A&A 541, C1 (2012)
        [-684.66156, 2446.28388, 399.671049, -356.652376, -186.387003, -316.80007, 198.296701, 101.135679],
        [667.66673, -2354.886252, -428.152441, 376.202861, 184.778874, 335.321713, -185.138669, -120.97283],
        [-5523.863691, -549.74745, -310.998056, 421.535876, -36.776172, -145.278396, -34.74445, 22.885731],
    ];

    // for pre_pequ():
    // polynomials
    const array xypol = [
        [+5453.282155, -73750.930350],
        [+0.4252841, -0.7675452],
        [-0.00037173, -0.00018725],
        [-0.000000152, +0.000000231],
    ];

    // periodics
    const array xyper = [
        [256.75, 708.15, 274.2, 241.45, 2309, 492.2, 396.1, 288.9, 231.1, 1610, 620, 157.87, 220.3, 1200],
        [-819.940624, -8444.676815, 2600.009459, 2755.17563, -167.659835, 871.855056, 44.769698, -512.313065, -819.415595, -538.071099, -189.793622, -402.922932, 179.516345, -9.814756],
        [75004.344875, 624.033993, 1251.136893, -1102.212834, -2660.66498, 699.291817, 153.16722, -950.865637, 499.754645, -145.18821, 558.116553, -23.923029, -165.405086, 9.344131],
        [81491.287984, 787.163481, 1251.296102, -1257.950837, -2966.79973, 639.744522, 131.600209, -445.040117, 584.522874, -89.756563, 524.42963, -13.549067, -210.157124, -44.919798],
        [1558.515853, 7774.939698, -2219.534038, -2523.969396, 247.850422, -846.485643, -1393.124055, 368.526116, 749.045012, 444.704518, 235.934465, 374.049623, -171.33018, -22.899655],
    ];

    function swi_ldp_peps(float $tjd, ?float &$dpre = null, ?float &$deps = null): void
    {
        $npol = self::NPOL_PEPS;
        $nper = self::NPER_PEPS;
        $t = ($tjd - Sweph::J2000) / 36525.0;
        $p = 0;
        $q = 0;
        // periodic terms
        for ($i = 0; $i < $nper; $i++) {
            $w = self::D2PI * $t;
            $a = $w / self::peper[0][$i];
            $s = sin($a);
            $c = cos($a);
            $p += $c * self::peper[1][$i] + $s * self::peper[3][$i];
            $q += $c * self::peper[2][$i] + $s * self::peper[4][$i];
        }
        // polynomial terms
        $w = 1;
        for ($i = 0; $i < $npol; $i++) {
            $p += self::pepol[$i][0] * $w;
            $q += self::pepol[$i][1] * $w;
            $w *= $t;
        }
        // both to radians
        $p *= self::AS2R;
        $q *= self::AS2R;
        // return
        if ($dpre)
            $dpre = $p;
        if ($deps)
            $deps = $q;
    }

    //
    // Long term high precision precession,
    // according to Vondrak/Capitaine/Wallace, "New precession expressions, valid
    // for long time intervals", in A&A 534, A22(2011).
    //

    // precession of the ecliptic
    function pre_pecl(float $tjd, array &$vec): void
    {
        $npol = self::NPOL_PECL;
        $nper = self::NPER_PECL;
        $t = ($tjd / Sweph::J2000) / 36525.0;
        $p = 0;
        $q = 0;
        // periodic terms
        for ($i = 0; $i < $nper; $i++) {
            $w = self::D2PI * $t;
            $a = $w / self::pqper[0][$i];
            $s = sin($a);
            $c = cos($a);
            $p += $c * self::pqper[1][$i] + $s * self::pqper[3][$i];
            $q += $c * self::pqper[2][$i] + $s * self::pqper[4][$i];
        }
        // polynomial terms
        $w = 1;
        for ($i = 0; $i < $npol; $i++) {
            $p += self::pqpol[$i][0] * $w;
            $q += self::pqpol[$i][1] * $w;
            $w *= $t;
        }
        // both to radians
        $p *= self::AS2R;
        $q *= self::AS2R;
        // ecliptic pole vector
        $z = 1 - $p * $p - $q * $q;
        if ($z < 0)
            $z = 0;
        else
            $z = sqrt($z);
        $s = sin(self::EPS0);
        $c = cos(self::EPS0);
        $vec[0] = $p;
        $vec[1] = -$q * $c - $z * $s;
        $vec[2] = -$q * $s + $z * $c;
    }

    // precession of the equator
    function pre_pequ(float $tjd, array &$veq): void
    {
        $npol = self::NPOL_PEQU;
        $nper = self::NPER_PEQU;
        $t = ($tjd / Sweph::J2000) / 36525.0;
        $x = 0;
        $y = 0;
        for ($i = 0; $i < $nper; $i++) {
            $w = self::D2PI * $t;
            $a = $w / self::xyper[0][$i];
            $s = sin($a);
            $c = cos($a);
            $x += $c * self::xyper[1][$i] + $s * self::xyper[3][$i];
            $y += $c * self::xyper[2][$i] + $s * self::xyper[4][$i];
        }
        // polynomial terms
        $w = 1;
        for ($i = 0; $i < $npol; $i++) {
            $x += self::xypol[$i][0] * $w;
            $y += self::xypol[$i][0] * $w;
            $w *= $t;
        }
        $x *= self::AS2R;
        $y *= self::AS2R;
        // equator pole vector
        $veq[0] = $x;
        $veq[1] = $y;
        $w = $x * $x + $y * $y;
        if ($w < 1)
            $veq[2] = sqrt(1 - $w);
        else
            $veq[2] = 0;
    }

    // precession matrix
    function pre_pmat(float $tjd, array &$rp): void
    {
        $peqr = [];
        $pecl = [];
        $v = [];
        $eqx = [];
        // equator pole
        $this->pre_pequ($tjd, $peqr);
        // ecliptic pole
        $this->pre_pecl($tjd, $pecl);
        // equinox
        $this->swi_cross_prod($peqr, $pecl, $v);
        $w = sqrt($v[0] * $v[0] + $v[1] * $v[1] + $v[2] * $v[2]);
        $eqx[0] = $v[0] / $w;
        $eqx[1] = $v[1] / $w;
        $eqx[2] = $v[2] / $w;
        $this->swi_cross_prod($peqr, $eqx, $v);
        $rp[0] = $eqx[0];
        $rp[1] = $eqx[1];
        $rp[2] = $eqx[2];
        $rp[3] = $v[0];
        $rp[4] = $v[1];
        $rp[5] = $v[2];
        $rp[6] = $peqr[0];
        $rp[7] = $peqr[1];
        $rp[8] = $peqr[2];
    }

    // precession according to Owen 1990:
    // Owen, William M., Jr., (JPL) "A Theory of the Earth's Precession
    // Relative to the Invariable Plane of the Solar System", Ph.D.
    // Dissertation, University of Florida, 1990.
    // Implemented for time range -18000 to 14000.
    //
    /*
     * p. 177: central time Tc = -160, covering time span -200 <= T <= -120
     * i.e. -14000 +- 40 centuries
     * p. 178: central time Tc = -80, covering time span -120 <= T <= -40
     * i.e. -6000 +- 40 centuries
     * p. 179: central time Tc = 0, covering time span -40 <= T <= +40
     * i.e. 2000 +- 40 centuries
     * p. 180: central time Tc = 80, covering time span 40 <= T <= 120
     * i.e. 10000 +- 40 centuries
     * p. 181: central time Tc = 160, covering time span 120 <= T <= 200
     * i.e. 10000 +- 40 centuries
     */
    const array owen_eps0_coef = [
        [23.699391439256386, 5.2330816033981775e-1, -5.6259493384864815e-2, -8.2033318431602032e-3, 6.6774163554156385e-4, 2.4931584012812606e-5, -3.1313623302407878e-6, 2.0343814827951515e-7, 2.9182026615852936e-8, -4.1118760893281951e-9,],
        [24.124759551704588, -1.2094875596566286e-1, -8.3914869653015218e-2, 3.5357075322387405e-3, 6.4557467824807032e-4, -2.5092064378707704e-5, -1.7631607274450848e-6, 1.3363622791424094e-7, 1.5577817511054047e-8, -2.4613907093017122e-9,],
        [23.439103144206208, -4.9386077073143590e-1, -2.3965445283267805e-4, 8.6637485629656489e-3, -5.2828151901367600e-5, -4.3951004595359217e-5, -1.1058785949914705e-6, 6.2431490022621172e-8, 3.4725376218710764e-8, 1.3658853127005757e-9,],
        [22.724671295125046, -1.6041813558650337e-1, 7.0646783888132504e-2, 1.4967806745062837e-3, -6.6857270989190734e-4, 5.7578378071604775e-6, 3.3738508454638728e-6, -2.2917813537654764e-7, -2.1019907929218137e-8, 4.3139832091694682e-9,],
        [22.914636050333696, 3.2123508304962416e-1, 3.6633220173792710e-2, -5.9228324767696043e-3, -1.882379107379328e-4, 3.2274552870236244e-5, 4.9052463646336507e-7, -5.9064298731578425e-8, -2.0485712675098837e-8, -6.2163304813908160e-10,],
    ];

    const array owen_psia_coef = [
        [-218.57864954903122, 51.752257487741612, 1.3304715765661958e-1, 9.2048123521890745e-2, -6.0877528127241278e-3, -7.0013893644531700e-5, -4.9217728385458495e-5, -1.8578234189053723e-6, 7.4396426162029877e-7, -5.9157528981843864e-9,],
        [-111.94350527506128, 55.175558131675861, 4.7366115762797613e-1, -4.7701750975398538e-2, -9.2445765329325809e-3, 7.0962838707454917e-4, 1.5140455277814658e-4, -7.7813159018954928e-7, -2.4729402281953378e-6, -1.0898887008726418e-7,],
        [-2.041452011529441e-1, 55.969995858494106, -1.9295093699770936e-1, -5.6819574830421158e-3, 1.1073687302518981e-2, -9.0868489896815619e-5, -1.1999773777895820e-4, 9.9748697306154409e-6, 5.7911493603430550e-7, -2.3647526839778175e-7,],
        [111.61366860604471, 56.404525305162447, 4.4403302410703782e-1, 7.1490030578883907e-2, -4.9184559079790816e-3, -1.3912698949042046e-3, -6.8490613661884005e-5, 1.2394328562905297e-6, 1.7719847841480384e-6, 2.4889095220628068e-7,],
        [228.40683531269390, 60.056143904919826, 2.9583200718478960e-2, -1.5710838319490748e-1, -7.0017356811600801e-3, 3.3009615142224537e-3, 2.0318123852537664e-4, -6.5840216067828310e-5, -5.9077673352976155e-6, 1.3983942185303064e-6,],
    ];

    const array owen_oma_coef = [
        [25.541291140949806, 2.377889511272162e-1, -3.7337334723142133e-1, 2.4579295485161534e-2, 4.3840999514263623e-3, -3.1126873333599556e-4, -9.8443045771748915e-6, -7.9403103080496923e-7, 1.0840116743893556e-9, 9.2865105216887919e-9,],
        [24.429357654237926, -9.5205745947740161e-1, 8.6738296270534816e-2, 3.0061543426062955e-2, -4.1532480523019988e-3, -3.7920928393860939e-4, 3.5117012399609737e-5, 4.6811877283079217e-6, -8.1836046585546861e-8, -6.1803706664211173e-8,],
        [23.450465062489337, -9.7259278279739817e-2, 1.1082286925130981e-2, -3.1469883339372219e-2, -1.0041906996819648e-4, 5.6455168475133958e-4, -8.4403910211030209e-6, -3.8269157371098435e-6, 3.1422585261198437e-7, 9.3481729116773404e-9,],
        [22.581778052947806, -8.7069701538602037e-1, -9.8140710050197307e-2, 2.6025931340678079e-2, 4.8165322168786755e-3, -1.906558772193363e-4, -4.6838759635421777e-5, -1.6608525315998471e-6, -3.2347811293516124e-8, 2.8104728109642000e-9,],
        [21.518861835737142, 2.0494789509441385e-1, 3.5193604846503161e-1, 1.5305977982348925e-2, -7.5015367726336455e-3, -4.0322553186065610e-4, 1.0655320434844041e-4, 7.1792339586935752e-6, -1.603874697543020e-6, -1.613563462813512e-7,],
    ];

    const array owen_chia_coef = [
        [8.2378850337329404e-1, -3.7443109739678667, 4.0143936898854026e-1, 8.1822830214590811e-2, -8.5978790792656293e-3, -2.8350488448426132e-5, -4.2474671728156727e-5, -1.6214840884656678e-6, 7.8560442001953050e-7, -1.032016641696707e-8,],
        [-2.1726062070318606, 7.8470515033132925e-1, 4.4044931004195718e-1, -8.0671247169971653e-2, -8.9672662444325007e-3, 9.2248978383109719e-4, 1.5143472266372874e-4, -1.6387009056475679e-6, -2.4405558979328144e-6, -1.0148113464009015e-7,],
        [-4.8518673570735556e-1, 1.0016737299946743e-1, -4.7074888613099918e-1, -5.8604054305076092e-3, 1.4300208240553435e-2, -6.7127991650300028e-5, -1.3703764889645475e-4, 9.0505213684444634e-6, 6.0368690647808607e-7, -2.2135404747652171e-7,],
        [-2.0950740076326087, -9.4447359463206877e-1, 4.0940512860493755e-1, 1.0261699700263508e-1, -5.3133241571955160e-3, -1.6634631550720911e-3, -5.9477519536647907e-5, 2.9651387319208926e-6, 1.6434499452070584e-6, 2.3720647656961084e-7,],
        [6.3315163285678715e-1, 3.5241082918420464, 2.1223076605364606e-1, -1.5648122502767368e-1, -9.1964075390801980e-3, 3.3896161239812411e-3, 2.1485178626085787e-4, -6.6261759864793735e-5, -5.9257969712852667e-6, 1.3918759086160525e-6,],
    ];

    function get_owen_t0_icof(float $tjd, float &$t0, int &$icof): void
    {
        $j = 0;
        $t0s = [-3392455.5, -470455.5, 2451544.5, 5373544.5, 8295544.5,];
        $t0 = $t0s[0];
        for ($i = 1; $i < 5; $i++) {
            if ($tjd >= ($t0s[$i - 1] + $t0s[$i]) / 2) {
                $t0 = $t0s[$i];
                $j++;
            }
        }
        $icof = $j;
    }

    // precession matrix Owen 1990
    function owen_pre_matrix(float $tjd, array &$rp, int $iflag)
    {
        $icof = 0;
        $eps0 = 0;
        $chia = 0;
        $psia = 0;
        $oma = 0;
        $t0 = 0.0;
        $this->get_owen_t0_icof($tjd, $t0, $icof);
        $tau[0] = 0;
        $tau[1] = ($tjd - $t0) / 36525.0 / 40.0;
        for ($i = 2; $i <= 9; $i++) {
            $tau[$i] = $tau[1] * $tau[$i - 1];
        }
        $k[0] = 1;
        $k[1] = $tau[1];
        $k[2] = 2 * $tau[2] - 1;
        $k[3] = 4 * $tau[3] - 3 * $tau[1];
        $k[4] = 8 * $tau[4] - 8 * $tau[2] + 1;
        $k[5] = 16 * $tau[5] - 20 * $tau[3] + 5 * $tau[1];
        $k[6] = 32 * $tau[6] - 48 * $tau[4] + 18 * $tau[2] - 1;
        $k[7] = 64 * $tau[7] - 112 * $tau[5] + 56 * $tau[3] - 7 * $tau[1];
        $k[8] = 128 * $tau[8] - 256 * $tau[6] + 160 * $tau[4] - 32 * $tau[2] + 1;
        $k[9] = 256 * $tau[9] - 576 * $tau[7] + 432 * $tau[5] - 120 * $tau[3] + 9 * $tau[1];
        for ($i = 0; $i < 10; $i++) {
            $psia += ($k[$i] * self::owen_psia_coef[$icof][$i]);
            $oma += ($k[$i] * self::owen_oma_coef[$icof][$i]);
            $chia += ($k[$i] * self::owen_chia_coef[$icof][$i]);
        }
        if ($iflag & (SweConst::SEFLG_JPLHOR | SweConst::SEFLG_JPLHOR_APPROX)) {
            //
            // In comparison with JPL Horizons we have an almost constant offset
            // almost constant offset in ecl. longitude of about -0.000019 deg.
            // We fix this as follows:
            $psia += -0.000018560;
        }
        $eps0 = 84381.448 / 3600.0;
        $eps0 *= SweConst::DEGTORAD;
        $psia *= SweConst::DEGTORAD;
        $chia *= SweConst::DEGTORAD;
        $oma *= SweConst::DEGTORAD;
        $coseps0 = cos($eps0);
        $sineps0 = sin($eps0);
        $coschia = cos($chia);
        $sinchia = sin($chia);
        $cospsia = cos($psia);
        $sinpsia = sin($psia);
        $cosoma = cos($oma);
        $sinoma = sin($oma);
        $rp[0] = $coschia * $cospsia + $sinchia * $cosoma * $sinpsia;
        $rp[1] = (-$coschia * $sinpsia + $sinchia * $cosoma * $cospsia) * $coseps0 + $sinchia * $sinoma * $sineps0;
        $rp[2] = (-$coschia * $sinpsia + $sinchia * $cosoma * $cospsia) * $sineps0 - $sinchia * $sinoma * $coseps0;
        $rp[3] = -$sinchia * $cospsia + $coschia * $cosoma * $sinpsia;
        $rp[4] = ($sinchia * $sinpsia + $coschia * $cosoma * $cospsia) * $coseps0 + $coschia * $sinoma * $sineps0;
        $rp[5] = ($sinchia * $sinpsia + $coschia * $cosoma * $cospsia) * $sineps0 - $coschia * $sinoma * $coseps0;
        $rp[6] = $sinoma * $sinpsia;
        $rp[7] = $sinoma * $cospsia * $coseps0 - $cosoma * $sineps0;
        $rp[8] = $sinoma * $cospsia * $sineps0 + $cosoma * $coseps0;
    }

    function epsiln_owen_1986(float $tjd, float &$eps): void
    {
        $t0 = 0.0;
        $icof = 0;
        $this->get_owen_t0_icof($tjd, $t0, $icof);
        $eps = 0;
        $tau[0] = 0;
        $tau[1] = ($tjd - $t0) / 36525.0 / 40.0;
        for ($i = 2; $i <= 9; $i++) {
            $tau[$i] = $tau[1] * $tau[$i - 1];
        }
        $k[0] = 1;
        $k[1] = $tau[1];
        $k[2] = 2 * $tau[2] - 1;
        $k[3] = 4 * $tau[3] - 3 * $tau[1];
        $k[4] = 8 * $tau[4] - 8 * $tau[2] + 1;
        $k[5] = 16 * $tau[5] - 20 * $tau[3] + 5 * $tau[1];
        $k[6] = 32 * $tau[6] - 48 * $tau[4] + 18 * $tau[2] - 1;
        $k[7] = 64 * $tau[7] - 112 * $tau[5] + 56 * $tau[3] - 7 * $tau[1];
        $k[8] = 128 * $tau[8] - 256 * $tau[6] + 160 * $tau[4] - 32 * $tau[2] + 1;
        $k[9] = 256 * $tau[9] - 576 * $tau[7] + 432 * $tau[5] - 120 * $tau[3] + 9 * $tau[1];
        for ($i = 0; $i < 10; $i++) {
            $eps += ($k[$i] * self::owen_eps0_coef[$icof][$i]);
        }
    }

    /* Obliquity of the ecliptic at Julian date J
     *
     * IAU Coefficients are from:
     * J. H. Lieske, T. Lederle, W. Fricke, and B. Morando,
     * "Expressions for the Precession Quantities Based upon the IAU
     * (1976) System of Astronomical Constants,"  Astronomy and Astrophysics
     * 58, 1-16 (1977).
     *
     * Before or after 200 years from J2000, the formula used is from:
     * J. Laskar, "Secular terms of classical planetary theories
     * using the results of general theory," Astronomy and Astrophysics
     * 157, 59070 (1986).
     *
     * Bretagnon, P. et al.: 2003, "Expressions for Precession Consistent with
     * the IAU 2000A Model". A&A 400,785
     *B03  	84381.4088  	-46.836051*t  	-1667*10-7*t2  	+199911*10-8*t3  	-523*10-9*t4  	-248*10-10*t5  	-3*10-11*t6
     *C03   84381.406  	-46.836769*t  	-1831*10-7*t2  	+20034*10-7*t3  	-576*10-9*t4  	-434*10-10*t5
     *
     *  See precess and page B18 of the Astronomical Almanac.
     */
    const float OFFSET_EPS_JPLHORIZONS = 35.95;
    const float DCOR_EPS_JPL_TJD0 = 2437846.5;

    //////////////////////////////////////////////////////////
    // from header
    //////////////////////////////////////////////////////////

    const float PREC_IAU_1976_CTIES = 2.0;          // J2000 +/- two centuries
    const float PREC_IAU_2000_CTIES = 2.0;          // J2000 +/- two centuries
    // we use P03 for whole ephemeris
    const float PREC_IAU_2006_CTIES = 75.0;         // J2000 +/- 75 centuries


    /* For reproducing JPL Horizons to 2 mas (SEFLG_JPLHOR):
     * The user has to keep the following files up to date which contain
     * the earth orientation parameters related to the IAU 1980 nutation
     * theory.
     * Download the file
     * datacenter.iers.org/eop/-/somos/5Rgv/document/tx13iers.u24/eopc04_08.62-now
     * and rename it as eop_1962_today.txt. For current data and estimations for
     * the near future, also download maia.usno.navy.mil/ser7/finals.all and
     * rename it as eop_finals.txt */
    const string DPSI_DEPS_IAU1980_FILE_EOPC04 = "eop_1962_today.txt";
    const string DPSI_DEPS_IAU1980_FILE_FINALS = "eop_finals.txt";
    const float DPSI_DEPS_IAU1980_TJD0_HORIZONS = 2437684.5;
    const float HORIZONS_TJD0_DPSI_DEPS_IAU1980 = 2437684.5;
    const float DPSI_IAU1980_TJD0 = (64.284 / 1000.0);      // arcsec
    const float DEPS_IAU1980_TJD0 = (6.151 / 1000.0);       // arcsec

    const int NDCOR_EPS_JPL = 51;
    private array $dcor_eps_jpl = [
        36.726, 36.627, 36.595, 36.578, 36.640, 36.659, 36.731, 36.765,
        36.662, 36.555, 36.335, 36.321, 36.354, 36.227, 36.289, 36.348, 36.257, 36.163,
        35.979, 35.896, 35.842, 35.825, 35.912, 35.950, 36.093, 36.191, 36.009, 35.943,
        35.875, 35.771, 35.788, 35.753, 35.822, 35.866, 35.771, 35.732, 35.543, 35.498,
        35.449, 35.409, 35.497, 35.556, 35.672, 35.760, 35.596, 35.565, 35.510, 35.394,
        35.385, 35.375, 35.415,
    ];

    function swi_epsiln(float $J, int $iflag): float
    {
        $eps = 0.;
        $prec_model = $this->swePhp->sweph->swed->astro_models[SweModel::MODEL_PREC_LONGTERM->value];
        $prec_model_short = $this->swePhp->sweph->swed->astro_models[SweModel::MODEL_PREC_SHORTTERM->value];
        $jplhora_model = $this->swePhp->sweph->swed->astro_models[SweModel::MODEL_JPLHORA_MODE->value];
        $is_jplhor = false;
        if ($prec_model == 0) $prec_model = SweModelPrecession::default();
        if ($prec_model_short == 0) $prec_model_short = SweModelPrecession::defaultShort();
        if ($jplhora_model == 0) $jplhora_model = SweModelJPLHorizon::default();
        if ($iflag & self::SEFLG_EPHMASK)
            $is_jplhor = true;
        if (($iflag & SweConst::SEFLG_JPLHOR_APPROX) &&
            $jplhora_model == SweModelJPLHorizon::MOD_JPLHORA_3 &&
            $J <= self::HORIZONS_TJD0_DPSI_DEPS_IAU1980)
            $is_jplhor = true;
        $T = ($J - 2451545.0) / 36525.0;
        if ($is_jplhor) {
            if ($J > 2378131.5 && $J < 2525323.5) { // between 1.1.1799 and 1.1.2202
                $eps = (((1.813e-3 * $T - 5.9e-4) * $T - 46.8150) * $T + 84381.448) * SweConst::DEGTORAD / 3600;
            } else {
                $this->epsiln_owen_1986($J, $eps);
                $eps *= SweConst::DEGTORAD;
            }
        } else if (($iflag & SweConst::SEFLG_JPLHOR_APPROX) && $jplhora_model == SweModelJPLHorizon::MOD_JPLHORA_2) {
            $eps = (((1.813e-3 * $T - 5.9e-4) * $T - 46.8150) * $T + 84381.448) * SweConst::DEGTORAD / 3600;
        } else if ($prec_model_short == SweModelPrecession::MOD_PREC_IAU_1976 && abs($T) <= self::PREC_IAU_1976_CTIES) {
            $eps = (((1.813e-3 * $T - 5.9e-4) * $T - 46.8150) * $T + 84381.448) * SweConst::DEGTORAD / 3600;
        } else if ($prec_model == SweModelPrecession::MOD_PREC_IAU_1976) {
            $eps = (((1.813e-3 * $T - 5.9e-4) * $T - 46.8150) * $T + 84381.448) * SweConst::DEGTORAD / 3600;
        } else if ($prec_model_short == SweModelPrecession::MOD_PREC_IAU_2000 && abs($T) <= self::PREC_IAU_2000_CTIES) {
            $eps = (((1.813e-3 * $T - 5.9e-4) * $T - 46.84024) * $T + 84381.406) * SweConst::DEGTORAD / 3600;
        } else if ($prec_model == SweModelPrecession::MOD_PREC_IAU_2000) {
            $eps = (((1.813e-3 * $T - 5.9e-4) * $T - 46.84024) * $T + 84381.406) * SweConst::DEGTORAD / 3600;
        } else if ($prec_model_short == SweModelPrecession::MOD_PREC_IAU_2006 && abs($T) <= self::PREC_IAU_2006_CTIES) {
            $eps = (((((-4.34e-8 * $T - 5.76e-7) * $T + 2.0034e-3) * $T - 1.831e-4) * $T - 46.834769) * $T + 84381.406) * SweConst::DEGTORAD / 3600.0;
        } else if ($prec_model == SweModelPrecession::MOD_PREC_NEWCOMB) {
            $Tn = ($J - 2396758.0) / 36525.0;
            $eps = (0.0017 * $Tn * $Tn * $Tn - 0.0085 * $Tn * $Tn - 46.837 * $Tn + 84451.68) * SweConst::DEGTORAD / 3600.0;
        } else if ($prec_model == SweModelPrecession::MOD_PREC_IAU_2006) {
            $eps = (((((-4.34e-8 * $T - 5.76e-7) * $T + 2.0034e-3) * $T - 1.831e-4) * $T - 46.836769) * $T + 84381.406) * SweConst::DEGTORAD / 3600.0;
        } else if ($prec_model == SweModelPrecession::MOD_PREC_BRETAGNON_2003) {
            $eps = ((((((-3e-11 * $T - 2.48e-8) * $T - 5.23e-7) * $T + 1.99911e-3) * $T - 1.667e-4) * $T - 46.834051) * $T + 84381.40880) * SweConst::DEGTORAD / 3600;
        } else if ($prec_model == SweModelPrecession::MOD_PREC_SIMON_1994) {
            $eps = (((((2.5e-8 * $T - 5.1e-7) * $T + 1.9989e-3) * $T - 1.52e-4) * $T - 46.80927) * $T + 84381.412) * SweConst::DEGTORAD / 3600.0;
        } else if ($prec_model == SweModelPrecession::MOD_PREC_WILLIAMS_1994) {
            $eps = ((((-1.0e-6 * $T + 2.0e-3) * $T - 1.74e-4) * $T - 46.833960) * $T + 84381.409) * SweConst::DEGTORAD / 3600.0;
        } else if ($prec_model == SweModelPrecession::MOD_PREC_LASKAR_1986 || $prec_model == SweModelPrecession::MOD_PREC_WILL_EPS_LASK) {
            $T /= 10.0;
            $eps = (((((((((2.45e-10 * $T + 5.79e-9) * $T + 2.787e-7) * $T
                                            + 7.12e-7) * $T - 3.905e-5) * $T - 2.4967e-3) * $T
                                - 5.138e-3) * $T + 1.99925) * $T - 0.0155) * $T - 468.093) * $T
                + 84381.448;
            $eps *= SweConst::DEGTORAD / 3600.0;
        } else if ($prec_model == SweModelPrecession::MOD_PREC_OWEN_1990) {
            $this->epsiln_owen_1986($J, $eps);
            $eps *= SweConst::DEGTORAD;
        } else {
            $this->swi_ldp_peps($J, deps: $eps);
            if (($iflag & SweConst::SEFLG_JPLHOR_APPROX) && $jplhora_model != SweModelJPLHorizon::MOD_JPLHORA_2) {
                $tofs = ($J - self::DCOR_EPS_JPL_TJD0) / 365.25;
                $dofs = self::OFFSET_EPS_JPLHORIZONS;
                if ($dofs < 0) {
                    $tofs = 0;
                    $dofs = $this->dcor_eps_jpl[0];
                } else if ($tofs >= self::NDCOR_EPS_JPL - 1) {
                    $tofs = self::NDCOR_EPS_JPL;
                    $dofs = $this->dcor_eps_jpl[self::NDCOR_EPS_JPL - 1];
                } else {
                    $t0 = (int)$tofs;
                    $t1 = $t0 + 1;
                    $dofs = $this->dcor_eps_jpl[$t0];
                    $dofs = ($tofs - $t0) * ($this->dcor_eps_jpl[$t0] - $this->dcor_eps_jpl[$t1]) + $this->dcor_eps_jpl[$t0];
                }
                $dofs /= (1000.0 * 3600.0);
                $eps += $dofs * SweConst::DEGTORAD;
            }
        }
        return $eps;
    }

    /* Precession of the equinox and ecliptic
     * from epoch Julian date J to or from J2000.0
     *
     * Original program by Steve Moshier.
     * Changes in program structure and implementation of IAU 2003 (P03) and
     * Vondrak 2011 by Dieter Koch.
     *
     * SEMOD_PREC_VONDRAK_2011
     * J. Vondrák, N. Capitaine, and P. Wallace, "New precession expressions,
     * valid for long time intervals", A&A 534, A22 (2011)
     *
     * SEMOD_PREC_IAU_2006
     * N. Capitaine, P.T. Wallace, and J. Chapront, "Expressions for IAU 2000
     * precession quantities", 2003, A&A 412, 567-586 (2003).
     * This is a "short" term model, that can be combined with other models
     *
     * SEMOD_PREC_WILLIAMS_1994
     * James G. Williams, "Contributions to the Earth's obliquity rate,
     * precession, and nutation,"  Astron. J. 108, 711-724 (1994).
     *
     * SEMOD_PREC_SIMON_1994
     * J. L. Simon, P. Bretagnon, J. Chapront, M. Chapront-Touze', G. Francou,
     * and J. Laskar, "Numerical Expressions for precession formulae and
     * mean elements for the Moon and the planets," Astronomy and Astrophysics
     * 282, 663-683 (1994).
     *
     * SEMOD_PREC_IAU_1976
     * IAU Coefficients are from:
     * J. H. Lieske, T. Lederle, W. Fricke, and B. Morando,
     * "Expressions for the Precession Quantities Based upon the IAU
     * (1976) System of Astronomical Constants,"  Astronomy and
     * Astrophysics 58, 1-16 (1977).
     * This is a "short" term model, that can be combined with other models
     *
     * SEMOD_PREC_LASKAR_1986
     * Newer formulas that cover a much longer time span are from:
     * J. Laskar, "Secular terms of classical planetary theories
     * using the results of general theory," Astronomy and Astrophysics
     * 157, 59070 (1986).
     *
     * See also:
     * P. Bretagnon and G. Francou, "Planetary theories in rectangular
     * and spherical variables. VSOP87 solutions," Astronomy and
     * Astrophysics 202, 309-315 (1988).
     *
     * Bretagnon and Francou's expansions for the node and inclination
     * of the ecliptic were derived from Laskar's data but were truncated
     * after the term in T**6. I have recomputed these expansions from
     * Laskar's data, retaining powers up to T**10 in the result.
     *
     */
    function precess_1(array &$R, float $J, int $direction, SweModelPrecession $prec_method): int
    {
        $z = 0;
        $TH = 0;
        if ($J == Sweph::J2000)
            return 0;
        $T = ($J - Sweph::J2000) / 36525.0;
        if ($prec_method == SweModelPrecession::MOD_PREC_IAU_1976) {
            $Z = ((0.017998 * $T + 0.30188) * $T + 2306.2181) * $T * SweConst::DEGTORAD / 3600;
            $z = ((0.018203 * $T + 1.09468) * $T + 2306.2181) * $T * SweConst::DEGTORAD / 3600;
            $TH = ((-0.041833 * $T - 0.42665) * $T + 2004.3109) * $T * SweConst::DEGTORAD / 3600;
            //
            // precession relative to ecliptic of start epoch is:
            // pn = (5029.0966 + 2.22226*T-0.000042*T*T) * t + (1.11161-0.000127*T) * t * t - 0.000113*t*t*t;
            // with: t = (tstart - tdate) / 36525.0
            //       T = (tstart - J2000) / 36525.0
            //
        } else if ($prec_method == SweModelPrecession::MOD_PREC_IAU_2000) {
            // AA 2006 B28:
            $Z = (((((-0.0000002 * $T - 0.0000327) * $T + 0.0179663) * $T + 0.3019015) * $T + 2306.0809506) * $T + 2.5976176) * SweConst::DEGTORAD / 3600;
            $z = (((((-0.0000003 * $T - 0.000047) * $T + 0.0182237) * $T + 1.0947790) * $T + 2306.0803226) * $T - 2.5976176) * SweConst::DEGTORAD / 3600;
            $TH = ((((-0.0000001 * $T - 0.0000601) * $T - 0.0418251) * $T - 0.4269353) * $T + 2004.1917476) * $T * SweConst::DEGTORAD / 3600;
        } else if ($prec_method == SweModelPrecession::MOD_PREC_IAU_2006) {
            $T = ($J - Sweph::J2000) / 36525.0;
            $Z = (((((-0.0000003173 * $T - 0.000005971) * $T + 0.01801828) * $T + 0.2988499) * $T + 2306.083227) * $T + 2.650545) * SweConst::DEGTORAD / 3600;
            $z = (((((-0.0000002904 * $T - 0.000028596) * $T + 0.01826837) * $T + 1.0927348) * $T + 2306.077181) * $T - 2.650545) * SweConst::DEGTORAD / 3600;
            $TH = ((((-0.00000011274 * $T - 0.000007089) * $T - 0.04182264) * $T - 0.4294934) * $T + 2004.191903) * $T * SweConst::DEGTORAD / 3600;
        } else if ($prec_method == SweModelPrecession::MOD_PREC_BRETAGNON_2003) {
            $Z = ((((((-0.00000000013 * $T - 0.0000003040) * $T - 0.000005708) * $T + 0.01801752) * $T + 0.3023262) * $T + 2306.080472) * $T + 2.72767) * SweConst::DEGTORAD / 3600;
            $z = ((((((-0.00000000005 * $T - 0.0000002486) * $T - 0.000028276) * $T + 0.01826676) * $T + 1.0956768) * $T + 2306.076070) * $T - 2.72767) * SweConst::DEGTORAD / 3600;
            $TH = ((((((0.000000000009 * $T + 0.00000000036) * $T - 0.0000001127) * $T - 0.000007291) * $T - 0.04182364) * $T - 0.4266980) * $T + 2004.190936) * $T * SweConst::DEGTORAD / 3600;
        } else if ($prec_method == SweModelPrecession::MOD_PREC_NEWCOMB) {
            // Newcomb according to Kinoshita 1975, very close to ExplSuppl/Andoyer;
            // one additional digit.
            $millis = 365242.198782; // trop. millennia
            $t1 = (Sweph::J2000 - Sweph::B1850) / $millis;
            $t2 = ($J - Sweph::B1850) / $millis;
            $T = $t2 - $t1;
            $T2 = $T * $T;
            $T3 = $T2 * $T;
            $Z1 = 23035.5548 + 139.720 * $t1 + 0.069 * $t1 * $t1;
            $Z = $Z1 * $T + (30.242 - 0.269 * $t1) * $T2 + 17.996 * $T3;
            $z = $Z1 * $T + (109.478 - 0.387 * $t1) * $T2 + 18.324 * $T3;
            $TH = (20051.125 - 85.294 * $t1 - 0.365 * $t1 * $t1) * $T + (-42.647 - 0.365 * $t1) * $T2 - 41.802 * $T3;
            $Z *= (SweConst::DEGTORAD / 3600.0);
            $z *= (SweConst::DEGTORAD / 3600.0);
            $TH *= (SweConst::DEGTORAD / 3600.0);
        } else {
            return 0;
        }
        $sinth = sin($TH);
        $costh = cos($TH);
        $sinZ = sin($Z);
        $cosZ = cos($Z);
        $sinz = sin($z);
        $cosz = cos($z);
        $A = $cosZ * $costh;
        $B = $sinz * $costh;
        if ($direction < 0) { // From J2000.0 to J
            $x[0] = ($A * $cosz - $sinZ * $sinz) * $R[0]
                - ($B * $cosz + $cosZ * $sinz) * $R[1]
                - $sinth * $cosz * $R[2];
            $x[1] = ($A * $sinz + $sinZ * $cosz) * $R[0]
                - ($B * $sinz - $cosZ * $cosz) * $R[1]
                - $sinth * $sinz * $R[2];
            $x[2] = $cosz * $sinth * $R[0]
                - $sinZ * $sinth * $R[1]
                + $costh * $R[2];
        } else { // From J to J2000.0
            $x[0] = ($A * $cosz - $sinZ * $sinz) * $R[0]
                + ($A * $sinz + $sinZ * $cosz) * $R[1]
                + $cosZ * $sinth * $R[2];
            $x[1] = -($B * $cosz + $cosZ * $sinz) * $R[0]
                - ($B * $sinz - $cosZ * $cosz) * $R[1]
                - $sinZ * $sinth * $R[2];
            $x[2] = -$sinth * $cosz * $R[0]
                - $sinth * $sinz * $R[1]
                + $costh * $R[2];
        }
        for ($i = 0; $i < 3; $i++)
            $R[$i] = $x[$i];
        return 0;
    }

    /* In WILLIAMS and SIMON, Laskar's terms of order higher than t^4
       have been retained, because Simon et al mention that the solution
       is the same except for the lower order terms.  */

    // SEMOD_PREC_WILLIAMS_1994
    const array pAcof_williams = [
        -8.66e-10, -4.759e-8, 2.424e-7, 1.3095e-5, 1.7451e-4, -1.8055e-3,
        -0.235316, 0.076, 110.5407, 50287.70000,
    ];
    const array nodecof_williams = [
        6.6402e-16, -2.69151e-15, -1.547021e-12, 7.521313e-12, 1.9e-10,
        -3.54e-9, -1.8103e-7, 1.26e-7, 7.436169e-5,
        -0.04207794833, 3.052115282424,
    ];
    const array inclcof_williams = [
        1.2147e-16, 7.3759e-17, -8.26287e-14, 2.503410e-13, 2.4650839e-11,
        -5.4000441e-11, 1.32115526e-9, -6.012e-7, -1.62442e-5,
        0.00227850649, 0.0,
    ];

    // SEMOD_PREC_SIMON_1994
    // Precession coefficients from Simon et al:
    const array pAcof_simon = [
        -8.66e-10, -4.759e-8, 2.424e-7, 1.3095e-5, 1.7451e-4, -1.8055e-3,
        -0.235316, 0.07732, 111.2022, 50288.200,
    ];
    const array nodecof_simon = [
        6.6402e-16, -2.69151e-15, -1.547021e-12, 7.521313e-12, 1.9e-10,
        -3.54e-9, -1.8103e-7, 2.579e-8, 7.4379679e-5,
        -0.0420782900, 3.0521126906,
    ];
    const array inclcof_simon = [
        1.2147e-16, 7.3759e-17, -8.26287e-14, 2.503410e-13, 2.4650839e-11,
        -5.4000441e-11, 1.32115526e-9, -5.99908e-7, -1.624383e-5,
        0.002278492868, 0.0,
    ];

    // SEMOD_PREC_LASKAR_1986
    // Precession coefficients taken from Laskar's paper:
    const array pAcof_laskar = [
        -8.66e-10, -4.759e-8, 2.424e-7, 1.3095e-5, 1.7451e-4, -1.8055e-3,
        -0.235316, 0.07732, 111.1971, 50290.966,
    ];
    // Node and inclination of the earth's orbit computed from
    // Laskar's data as done in Bretagnon and Francou's paper.
    // Units are radians.
    //
    const array nodecof_laskar = [
        6.6402e-16, -2.69151e-15, -1.547021e-12, 7.521313e-12, 6.3190131e-10,
        -3.48388152e-9, -1.813065896e-7, 2.75036225e-8, 7.4394531426e-5,
        -0.042078604317, 3.052112654975,
    ];
    const array inclcof_laskar = [
        1.2147e-16, 7.3759e-17, -8.26287e-14, 2.503410e-13, 2.4650839e-11,
        -5.4000441e-11, 1.32115526e-9, -5.998737027e-7, -1.6242797091e-5,
        0.002278495537, 0.0,
    ];

    function precess_2(array &$R, float $J, int $iflag, int $direction, SweModelPrecession $prec_method): int
    {
        if ($J == Sweph::J2000)
            return 0;
        if ($prec_method == SweModelPrecession::MOD_PREC_LASKAR_1986) {
            $pAcof = self::pAcof_laskar;
            $nodecof = self::nodecof_laskar;
            $inclcof = self::inclcof_laskar;
        } else if ($prec_method == SweModelPrecession::MOD_PREC_SIMON_1994) {
            $pAcof = self::pAcof_simon;
            $nodecof = self::nodecof_simon;
            $inclcof = self::inclcof_simon;
        } else if ($prec_method == SweModelPrecession::MOD_PREC_WILLIAMS_1994) {
            $pAcof = self::pAcof_williams;
            $nodecof = self::nodecof_williams;
            $inclcof = self::inclcof_williams;
        } else { // default, to satisfy compiler
            $pAcof = self::pAcof_laskar;
            $nodecof = self::nodecof_laskar;
            $inclcof = self::inclcof_laskar;
        }
        $T = ($J - Sweph::J2000) / 36525.0;
        // Implementation by elementary rotations using Laskar's expansions.
        // First rotate about the x axis from the initial equator
        // to the ecliptic. (The input is equatorial.)
        //
        if ($direction == 1)
            $eps = $this->swi_epsiln($J, $iflag);   // To J2000
        else
            $eps = $this->swi_epsiln(Sweph::J2000, $iflag); // From J2000
        $sineps = sin($eps);
        $coseps = cos($eps);
        $x[0] = $R[0];
        $z = $coseps * $R[1] + $sineps * $R[2];
        $x[2] = -$sineps * $R[1] + $coseps * $R[2];
        $x[1] = $z;
        // Precession in longitude
        $T /= 10.0; // thousands of years
        $pA = $pAcof[0];
        for ($i = 1; $i < 10; $i++) {
            $pA = $pA * $T + $pAcof[$i];
        }
        $pA *= SweConst::DEGTORAD / 3600 * $T;
        // Node of the moving ecliptic on the J2000 ecliptic.
        //
        $W = $nodecof[0];
        for ($i = 1; $i < 11; $i++) {
            $W = $W * $T + $nodecof[$i];
        }
        // Rotate about z axis to the node.
        //
        if ($direction == 1)
            $z = $W + $pA;
        else
            $z = $W;
        $B = cos($z);
        $A = sin($z);
        $z = $B * $x[0] + $A * $x[1];
        $x[1] = -$A * $x[0] + $B * $x[1];
        $x[0] = $z;
        // Rotate about new x axis by the inclination of the moving
        // ecliptic on the J2000 ecliptic.
        //
        $z = $inclcof[0];
        for ($i = 1; $i < 11; $i++)
            $z = $z * $T + $inclcof[$i];
        if ($direction == 1)
            $z = -$z;
        $B = cos($z);
        $A = sin($z);
        $z = $B * $x[1] + $A * $x[2];
        $x[2] = -$A * $x[1] + $B * $x[2];
        $x[1] = $z;
        // Rotate about new z axis back from the node.
        //
        if ($direction == 1)
            $z = -$W;
        else
            $z = -$W - $pA;
        $B = cos($z);
        $a = sin($z);
        $z = $B * $x[0] + $A * $x[1];
        $x[2] = $sineps * $x[1] + $coseps * $x[2];
        $x[1] = $z;
        for ($i = 0; $i < 3; $i++)
            $R[$i] = $x[$i];
        return 0;
    }

    function precess_3(array &$R, float $J, int $direction, int $iflag, SweModelPrecession $prec_meth): int
    {
        $x = [];
        $pmat = [];
        if ($J == Sweph::J2000)
            return 0;
        // Each precession angle is specified by a polynomial in
        // T = Julian centuries from J2000.0.  See AA page B18.
        //
        if ($prec_meth == SweModelPrecession::MOD_PREC_OWEN_1990)
            $this->owen_pre_matrix($J, $pmat, $iflag);
        else
            $this->pre_pmat($J, $pmat);
        if ($direction == -1) {
            for ($i = 0, $j = 0; $i <= 2; $i++, $j = $i * 3) {
                $x[$i] = $R[0] * $pmat[$j] +
                    $R[1] * $pmat[$j + 1] +
                    $R[2] * $pmat[$j + 2];
            }
        } else {
            for ($i = 0; $i <= 2; $i++) {
                $x[$i] = $R[0] * $pmat[$i] +
                    $R[1] * $pmat[$i + 3] +
                    $R[2] * $pmat[$i + 6];
            }
        }
        for ($i = 0; $i < 3; $i++)
            $R[$i] = $x[$i];
        return 0;
    }

    /* Subroutine arguments:
     *
     * R = rectangular equatorial coordinate vector to be precessed.
     *     The result is written back into the input vector.
     * J = Julian date
     * direction =
     *      Precess from J to J2000: direction = 1
     *      Precess from J2000 to J: direction = -1
     * Note that if you want to precess from J1 to J2, you would
     * first go from J1 to J2000, then call the program again
     * to go from J2000 to J2.
     */
    function swi_precess(array &$R, float $J, int $iflag, int $direction): int
    {
        $T = ($J - Sweph::J2000) / 36525.0;
        $prec_model = $this->swePhp->sweph->swed->astro_models[SweModel::MODEL_PREC_LONGTERM->value];
        $prec_model_short = $this->swePhp->sweph->swed->astro_models[SweModel::MODEL_PREC_SHORTTERM->value];
        $jplhora_model = $this->swePhp->sweph->swed->astro_models[SweModel::MODEL_JPLHORA_MODE->value];
        $is_jplhor = false;
        if ($prec_model == 0) $prec_model = SweModelPrecession::default();
        if ($prec_model_short == 0) $prec_model_short = SweModelPrecession::defaultShort();
        if ($jplhora_model == 0) $jplhora_model = SweModelJPLHorizon::default();
        if ($iflag & SweConst::SEFLG_JPLHOR)
            $is_jplhor = true;
        if (($iflag & SweConst::SEFLG_JPLHOR_APPROX) &&
            $jplhora_model == SweModelJPLHorizon::MOD_JPLHORA_3 &&
            $J <= self::HORIZONS_TJD0_DPSI_DEPS_IAU1980)
            $is_jplhor = true;
        // JPL Horizons uses precession IAU 1976 and nutation IAU 1980 plus
        // some correction to nutation, arriving at extremely high precision
        if ($is_jplhor) {
            if ($J >= 2378131.5 && $J < 2525323.5) { // between 1.1.1799 and 1.1.2202
                return $this->precess_1($R, $J, $direction, SweModelPrecession::MOD_PREC_IAU_1976);
            } else {
                return $this->precess_3($R, $J, $direction, $iflag, SweModelPrecession::MOD_PREC_OWEN_1990);
            }
        }
        // Use IAU 1976 formula for a few centuries.
        else if ($prec_model_short == SweModelPrecession::MOD_PREC_IAU_1976 && abs($T) <= self::PREC_IAU_1976_CTIES) {
            return $this->precess_1($R, $J, $direction, SweModelPrecession::MOD_PREC_IAU_1976);
        } else if ($prec_model == SweModelPrecession::MOD_PREC_IAU_1976) {
            return $this->precess_1($R, $J, $direction, SweModelPrecession::MOD_PREC_IAU_1976);
        }
        // Use IAU 2000 formula for a few centuries.
        else if ($prec_model_short == SweModelPrecession::MOD_PREC_IAU_2000 && abs($T) <= self::PREC_IAU_2006_CTIES) {
            return $this->precess_1($R, $J, $direction, SweModelPrecession::MOD_PREC_IAU_2000);
        } else if ($prec_model == SweModelPrecession::MOD_PREC_IAU_2000) {
            return $this->precess_1($R, $J, $direction, SweModelPrecession::MOD_PREC_IAU_2000);
        }
        // Use IAU 2006 formula for a few centuries.
        else if ($prec_model_short == SweModelPrecession::MOD_PREC_IAU_2006 && abs($T) <= self::PREC_IAU_2006_CTIES) {
            return $this->precess_1($R, $J, $direction, SweModelPrecession::MOD_PREC_IAU_2006);
        } else if ($prec_model == SweModelPrecession::MOD_PREC_IAU_2006) {
            return $this->precess_1($R, $J, $direction, SweModelPrecession::MOD_PREC_IAU_2006);
        } else if ($prec_model == SweModelPrecession::MOD_PREC_BRETAGNON_2003) {
            return $this->precess_1($R, $J, $direction, SweModelPrecession::MOD_PREC_BRETAGNON_2003);
        } else if ($prec_model == SweModelPrecession::MOD_PREC_NEWCOMB) {
            return $this->precess_1($R, $J, $direction, SweModelPrecession::MOD_PREC_NEWCOMB);
        } else if ($prec_model == SweModelPrecession::MOD_PREC_LASKAR_1986) {
            return $this->precess_2($R, $J, $iflag, $direction, SweModelPrecession::MOD_PREC_LASKAR_1986);
        } else if ($prec_model == SweModelPrecession::MOD_PREC_SIMON_1994) {
            return $this->precess_2($R, $J, $iflag, $direction, SweModelPrecession::MOD_PREC_SIMON_1994);
        } else if ($prec_model == SweModelPrecession::MOD_PREC_WILLIAMS_1994 || $prec_model == SweModelPrecession::MOD_PREC_WILL_EPS_LASK) {
            return $this->precess_2($R, $J, $iflag, $direction, SweModelPrecession::MOD_PREC_WILLIAMS_1994);
        } else if ($prec_model == SweModelPrecession::MOD_PREC_OWEN_1990) {
            return $this->precess_2($R, $J, $iflag, $direction, SweModelPrecession::MOD_PREC_OWEN_1990);
        } else { // SEMOD_PREC_VONDRAK_2011
            return $this->precess_3($R, $J, $direction, $iflag, SweModelPrecession::MOD_PREC_VONDRAK_2011);
        }
    }


    public function swe_deltat_ex(float $tjd, int $iflag, ?string &$serr = null): float
    {
        $deltat = 0.0;
        if ($this->swePhp->sweph->swed->delta_t_userdef_is_set)
            return $this->swePhp->sweph->swed->delta_t_userdef;
        if ($serr)
            $serr[0] = "\0";
        $this->deltat->calc_deltat($tjd, $iflag, $deltat, $serr);
        return $deltat;
    }

    public function swe_deltat(float $tjd): float
    {
        $iflag = $this->deltat->swi_guess_ephe_flag();
        return $this->swe_deltat_ex($tjd, $iflag); // with default tidal acceleration/default ephemeris
    }

    // returns tidal acceleration used in swe_deltat() and swe_deltat_ex()
    public function swe_get_tid_acc(): float
    {
        return $this->swePhp->sweph->swed->tid_acc;
    }

    /* function sets tidal acceleration of the Moon.
     * t_acc can be either
     * - the value of the tidal acceleration in arcsec/cty^2
     *   of the Moon will be set consistent with that ephemeris.
     * - SE_TIDAL_AUTOMATIC,
     */
    public function swe_set_tid_acc(float $t_acc): void
    {
        if ($t_acc == SweTidalAccel::SE_TIDAL_AUTOMATIC) {
            $this->swePhp->sweph->swed->tid_acc = SweTidalAccel::SE_TIDAL_DEFAULT;
            $this->swePhp->sweph->swed->is_tid_acc_manual = false;
            return;
        }
        $this->swePhp->sweph->swed->tid_acc = $t_acc;
        $this->swePhp->sweph->swed->is_tid_acc_manual = true;
    }

    public function swe_set_delta_t_userdef(float $dt): void
    {
        if ($dt == self::SE_DELTAT_AUTOMATIC) {
            $this->swePhp->sweph->swed->delta_t_userdef_is_set = false;
        } else {
            $this->swePhp->sweph->swed->delta_t_userdef_is_set = true;
            $this->swePhp->sweph->swed->delta_t_userdef = $dt;
        }
    }
}