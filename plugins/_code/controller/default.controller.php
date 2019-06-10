<?php

use tsd\serve\Controller;
use tsd\serve\DB;

class DefaultController extends Controller
{

  function __construct(DB $db)
  {
    
  }

  /**
    @SecurityGroup developer
   */
  function showIndex ()
  {    
    $this->render ('index', []);
  }

}
