<?php

use tsd\serve\Controller;

class DefaultController extends Controller
{
  /**
    @SecurityGroup developer
   */
  function showIndex ()
  {    
    $this->render ('index', []);
  }

}
