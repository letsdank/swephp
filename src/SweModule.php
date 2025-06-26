<?php

class SweModule
{
    protected SwePhp $swePhp;

    public function __construct(SwePhp $base)
    {
        $this->swePhp = $base;
    }
}