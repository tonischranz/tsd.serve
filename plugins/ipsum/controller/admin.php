<?php 

use tsd\serve\Controller;

class adminController extends Controller
{
    #[SecurityGroup('admin')]
    #[SecurityGroup('editor')]
    function showIndex ()
    {
        return $this->view();
    }
}