<?php 

use tsd\serve\admin\AdminControllerBase;

class adminController extends AdminControllerBase
{
    #[SecurityGroup('admin')]
    #[SecurityGroup('editor')]
    function showIndex ()
    {
        return $this->view();
    }
}