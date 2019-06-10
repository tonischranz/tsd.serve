<?php

use tsd\serve\Controller;
use tsd\serve\DB;

class DefaultController extends Controller
{

  function __construct(DB $db)
  {
    
  }

  function showIndex ()
  {
    
    $this->render ('index', []);
  }

}
