<?php

class SwePhp
{
    // Main instance of the whole library
    static SwePhp $instance;


    public SweDate $sweDate;
    public Sweph $sweph;
    public SwephLib $swephLib;

    private function __construct()
    {
        $this->sweDate = new SweDate($this);
        $this->sweph = new Sweph($this);
        $this->swephLib = new SwephLib($this);
    }

    public static function getInstance(): SwePhp
    {
        if (!isset(self::$instance)) {
            self::$instance = new SwePhp();
        }

        return self::$instance;
    }
}
