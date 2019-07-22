<?php

use tsd\serve\Controller;

class defaultController extends Controller
{
  function showIndex ()
  {
    return $this->view (['user' => 'Toni Schranz']);
  }

  function show (array $names)
  {
    echo "--- DUMP --- \n";
    
    if (count($names) > 0)
      var_dump($names);
    
    return $this->view (['user' => $names[0]],'index');
  }
}
