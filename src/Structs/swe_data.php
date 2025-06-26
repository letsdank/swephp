<?php

namespace Structs;

class swe_data
{
    // TODO: Remove default values

    public bool $ephe_path_is_set;
    public bool $jpl_file_is_open;
    public string $ephepath = "sweph/ephe/";
    public string $jplfnam = ""; // TODO:
    public int $jpldenum;

    // delta t/tidal acceleration variables
    public float $tid_acc;
    public bool $is_tid_acc_manual = false;
    public bool $init_dt_done = false;
    public bool $swed_is_initialized;
    public bool $delta_t_userdef_is_set = false;
    public float $delta_t_userdef;

    public array $astro_models = [];
    public array $fidat = [];
}