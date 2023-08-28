<?php

namespace yzh52521\mail;

class ModelIdentifier
{
    public $class;

    public $id;

    public function __construct($class, $id)
    {
        $this->id    = $id;
        $this->class = $class;
    }
}