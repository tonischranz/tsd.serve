<?php

namespace tsd\serve;

class InfoController extends Controller
{
    #[SecurityGroup('admin')]    
    #[SecurityGroup('developer')]
    function showIndex()
    {
        phpinfo();
        return $this->message("phpinfo();");
    }
}