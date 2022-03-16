<?php 
namespace tsd\serve\ipsum;

use tsd\serve\SecurityGroup;
use tsd\serve\MenuItem;
use tsd\serve\admin\AdminControllerBase;

class adminController extends AdminControllerBase
{
    #[SecurityGroup('admin')]
    #[SecurityGroup('editor')]
    #[MenuItem("Ipsum Admin")]
    function showIndex ()
    {
        return $this->view();
    }

    #[SecurityGroup('editor')]
    #[MenuItem("Ipsum Foo")]
    function showFoo()
    {
        return $this->view();
    }

    #[MenuItem("Bar Ipsum")]
    function showBar(int $n = 1000)
    {
        return $this->view(['n'=>$n]);
    }
}