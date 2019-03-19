<?php

use tsd\serve\Controller;

class defaultController extends Controller
{

  function showIndex ()
  {
    $this->render ('index', ['user' => 'Toni Schranz']);
  }

  function show ($name)
  {
    var_dump($name);
    
    $this->render ('index', ['user' => $name[0]]);
  }

}
