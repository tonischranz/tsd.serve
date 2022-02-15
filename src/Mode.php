<?php

namespace tsd\serve;

use \Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Mode
{  
    function __construct(public string $name)
    {
    }
}