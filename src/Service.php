<?php

namespace yzh52521;

class Service extends \think\Service
{
    public function boot()
    {
        $this->commands([
            \yzh52521\command\make\Mail::class,
        ]);
    }
}