<?php

use tsd\serve\Controller;

class DefaultController extends Controller
{

  function showIndex ()
  {
    
    $this->render ('index', []);
  }

}
