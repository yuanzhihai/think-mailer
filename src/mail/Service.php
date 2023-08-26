<?php

namespace yzh52521\mail;

use yzh52521\MailManager;

class Service extends \think\Service
{
    public function boot()
    {
        $this->commands(\yzh52521\command\make\Mail::class);
    }

    public function register()
    {
        $this->app->bind('mail.manager', function () {
            return new MailManager($this->app);
        });

        $this->app->bind('mailer', function () {
            return $this->app->make('mail.manager')->mailer();
        });
    }
}