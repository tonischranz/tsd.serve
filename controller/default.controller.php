<?php

use tsd\serve\Controller;

class defaultController extends Controller
{

  function showIndex ()
  {
    $this->render ('index', ['user' => 'Toni Schranz']);
  }

  function show ($names)
  {
    if (count($names) > 0)
      var_dump($names);
    
    $this->render ('index', ['user' => $names[0]]);
  }

}
