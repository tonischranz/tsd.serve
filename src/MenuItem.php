<?php

namespace tsd\serve;

use \Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class MenuItem
{  
    function __construct(public string $name)
    {
    }
}