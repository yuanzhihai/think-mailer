<?php

namespace yzh52521\command\make;

use think\console\command\Make;

class Mail extends Make
{
    protected $type = "Mail";

    protected function configure()
    {
        parent::configure();
        $this->setName('make:mail')
            ->setDescription('Create a new mailable class');
    }

    protected function getStub(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'stubs' . DIRECTORY_SEPARATOR . 'mailable.stub';
    }

    protected function getNamespace(string $app): string
    {
        return parent::getNamespace($app) . '\\mail';
    }
}
