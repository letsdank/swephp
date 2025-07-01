<?php

namespace Structs;

// the following data are read from file only once, immediately after
// file has been opened
class plan_data
{

    // internal body number
    public int $ibdy = 0;
    // contains several bit flags describing the data:
    // * SEI_FLG_HELIO: true if helio, false if bary
    // * SEI_FLG_ROTATE: TRUE if coefficients are referred to
    //       coordinate system of orbital plane
    // * SEI_FLG_ELLIPSE: TRUE if reference ellipse
    public int $iflg = 0;
    // # of coefficients of ephemeris polynomial, is polynomial order + 1
    // where is the segment index on the file
    public int $ncoe = 0;
    // file position of begin of planet's index
    public int $lndx0 = 0;
    // number of index entries on file: computed
    public int $nndx = 0;
    // file contains ephemeris for tfstart thru tfend
    // for this particular planet !!!
    public float $tfstart = 0, $tfend = 0;
    // segment size (days covered by a polynomial)
    public float $dseg = 0;

    // orbital elements:
    public float $telem = 0;        // epoch of elements
    public float $prot = 0;
    public float $qrot = 0;
    public float $dprot = 0;
    public float $dqrot = 0;
    // normalisation factor of cheby coefficients
    // in addition, if reference ellipse is used:
    public float $rmax = 0;
    public float $peri = 0;
    public float $dperi = 0;
    // pointer to cheby coeffs of reference ellipse,
    // size of data is 2 x ncoe
    public $refep;

    // unpacked segment information, only updated when a segment is read:

    // start and end jd of current segment
    public float $tseg0 = 0, $tseg1 = 0;
    // pointer to unpacked cheby coeffs of segment;
    // the size is 3 x ncoe
    public array $segp = [];
    // how many coefficients to evaluate. this may
    // be less than ncoe
    public int $neval = 0;

    // result of most recent data evaluation for this body:

    // time for which previous computation was made
    public float $teval = 0;
    // which ephemeris was used
    public int $iephe = 0;
    // position and speed vectors equatorial J2000
    public array $x = [0., 0., 0., 0., 0., 0.,];
    // hel., light-time, aberr., prec. flags etc.
    public int $xflgs = 0;
    // return positions:
    // xreturn+0    ecliptic polar coordinates
    // xreturn+6    ecliptic cartesian coordinates
    // xreturn+12   equatorial polar coordinates
    // xretrun+18   equatorial cartesian coordinates
    public array $xreturn = [
        0., 0., 0., 0., 0., 0.,
        0., 0., 0., 0., 0., 0.,
        0., 0., 0., 0., 0., 0.,
        0., 0., 0., 0., 0., 0.,
    ];
}