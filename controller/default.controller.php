<?php

use tsd\serve\Controller;

class defaultController extends Controller
{

  function showIndex ()
  {
    return $this->view (['user' => 'Toni Schranz']);
  }

  function showBar (float $amt)
  {
      return $this->view (['amt' => $amt * 2]);
  }

  function showInfo()
  {
    phpinfo();
    return $this->message("phpinfo()");
  }

  function show (array $names)
  {
    echo "<br>--- DUMP --- \n";
    
    if (count($names) > 0)
      var_dump($names);
    
    return $this->view (['user' => $names[0]],'index');
  }
}
