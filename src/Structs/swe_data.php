<?php

namespace Structs;

use Enums\SweTidalAccel;

class swe_data
{
    // Default values should be set (according to sweph.c)

    public bool $ephe_path_is_set = false;
    public bool $jpl_file_is_open = false;
    public $fixfp;      // fixed stars file pointer
    public string $ephepath = "";
    public string $jplfnam = "";
    public int $jpldenum = 0;
    public int $last_epheflag = 0;
    public bool $geopos_is_set = false;
    public bool $ayana_is_set = false;
    public bool $is_old_starfile = false;
    public float $eop_tjd_beg = 0.0;
    public float $eop_tjd_beg_horizons = 0.0;
    public float $eop_tjd_end = 0.0;
    public float $eop_tjd_end_add = 0.0;
    public int $eop_dpsi_loaded = 0;

    // delta t/tidal acceleration variables
    public float $tid_acc = 0;
    public bool $is_tid_acc_manual = false;
    public bool $init_dt_done = false;
    public bool $swed_is_initialized = false;
    public bool $delta_t_userdef_is_set = false;
    public float $delta_t_userdef = 0.0;
    public float $ast_G = 0.0;
    public float $ast_H = 0.0;
    public float $ast_diam = 0.0;
    public string $astelem = "";
    public int $i_saved_planet_name = 0;
    public string $saved_planet_name = "";
    public ?array $dpsi = null;
    public ?array $deps = null;
    public int $timeout = 0;
    public array $astro_models = [0, 0, 0, 0, 0, 0, 0, 0,];
    public bool $do_interpolate_nut = false;
    public interpol $interpol;
    public array $fidat = [];
    public array $pldat = [];
    public array $nddat = [];
    public array $savedat = [];
    public epsilon $oec;
    public epsilon $oec2000;
    public nut $nut;
    public nut $nut2000;
    public nut $nutv;
    public topo_data $topd;
    public sid_data $sidd;
    public int $n_fixstars_real; // real number of fixed stars in sefstars.txt
    public int $n_fixstars_named; // number of fixed stars with traditional name
    public int $n_fixstars_records; // number of fixed stars with records in fixed_stars
    public ?array $fixed_stars = [];

    public function __construct()
    {
        $this->interpol = new interpol();
        $this->oec = new epsilon();
        $this->oec2000 = new epsilon();
        $this->nut = new nut();
        $this->nut2000 = new nut();
        $this->nutv = new nut();
        $this->topd = new topo_data();
        $this->sidd = new sid_data();
    }
}