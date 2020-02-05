<?php

use tsd\serve\Controller;
use tsd\serve\DB;

class defaultController extends Controller
{
  private DB $db;

  function showIndex ()
  {
    return $this->view (['user' => 'Toni Schranz']);
  }

  function showBar (float $amt)
  {
      return $this->view (['amt' => $amt * 2]);
  }

  function show (array $names)
  {
    echo "<br>--- DUMP --- \n";
    
    if (count($names) > 0)
      var_dump($names);
    
    return $this->view (['user' => $names[0]],'index');
  }
}
