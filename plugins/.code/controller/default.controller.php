<?php

use tsd\serve\Controller;

class DefaultController extends Controller
{
  function __construct()
  {        
  }

  /**
   * @SecurityGroup developer
   */
  function showIndex ()
  {    
    return $this->view();
  }

}
