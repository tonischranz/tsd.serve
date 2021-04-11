<?php

namespace tsd\serve;

use \Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class SecurityGroup
{  
    function __construct(public string $name)
    {
    }
}