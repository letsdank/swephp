<?php

namespace Structs;

use Enums\SweTidalAccel;

class swe_data
{
    // TODO: Remove default values

    public bool $ephe_path_is_set;
    public bool $jpl_file_is_open = false;
    public string $ephepath = "sweph/ephe/";
    public string $jplfnam = ""; // TODO:
    public int $jpldenum = 0;
    public int $last_epheflag;
    public bool $geopos_is_set;
    public bool $ayana_is_set;
    public bool $is_old_starfile;
    public float $eop_tjd_beg;
    public float $eop_tjd_beg_horizons;
    public float $eop_tjd_end;
    public float $eop_tjd_end_add;
    public int $eop_dpsi_loaded;

    // delta t/tidal acceleration variables
    public float $tid_acc = SweTidalAccel::SE_TIDAL_DEFAULT;
    public bool $is_tid_acc_manual = false;
    public bool $init_dt_done = false;
    public bool $swed_is_initialized;
    public bool $delta_t_userdef_is_set = false;
    public float $delta_t_userdef;
    public array $dpsi;
    public array $deps;

    public array $astro_models = [];
    public bool $do_interpolate_nut = false;
    public interpol $interpol;
    public array $fidat = [];
    public sid_data $sidd;

    public function __construct()
    {
        $this->interpol = new interpol();
        $this->sidd = new sid_data();
    }
}