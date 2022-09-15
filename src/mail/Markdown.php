<?php

namespace yzh52521\mail;

use cebe\markdown\GithubMarkdown;

class Markdown extends GithubMarkdown
{

    protected function identifyCode($line)
    {
        return false;
    }
}