<?php

namespace yzh52521\command\make;

use think\console\Command;
use think\console\Input;
use think\console\Output;

class Mail extends Command
{

    protected function configure()
    {
        $this->setName('make:mail')
            ->setDescription('Create a new mailable class');
    }

    protected function execute(Input $input, Output $output)
    {
        echo '111';
    }

}
