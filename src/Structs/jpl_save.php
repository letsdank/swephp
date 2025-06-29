<?php

namespace Structs;

class jpl_save
{
    public string $jplfname;
    public string $jplfpath;
    public $jplfptr;
    public int $do_reorder;
    public array $eh_cval = [];
    public array $eh_ss = [];
    public float $eh_au, $eh_emrat;
    public int $eh_denum, $eh_ncon;
    public array $eh_ipt = [];
    public string $ch_cnam;
    public array $pv = [];
    public array $pvsun = [];
    public array $buf = [];
    public array $pc = [], $vc = [], $ac = [], $jc = [];
    public int $do_km;
}