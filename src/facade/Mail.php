<?php

namespace yzh52521\facade;

use think\Facade;

/**
 * Class Mail
 *
 * @package yzh52521\facade
 * @mixin \yzh52521\MailManager
 */
class Mail extends Facade
{
    protected static function getFacadeClass()
    {
        return \yzh52521\MailManager::class;
    }
}