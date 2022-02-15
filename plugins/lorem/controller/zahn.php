<?php

use tsd\serve\Controller;

class zahnController extends Controller
{
  function showIndex ()
  {
    var_dump($this);
    return $this->view (['text' => 'Es isch e mau Maa gsi, dä het ä hohlä Zahn gha, i dä Zahn hets es Briefli gha u da isch gstandä: Es isch e mau Maa gsi, dä het ä hohlä Zahn gha, i dä Zahn hets es Briefli gha u da isch gstandä: Es isch e mau Maa gsi, dä het ä hohlä Zahn gha, i dä Zahn hets es Briefli gha u da isch gstandä: ... ',
    'code'=>'<html>{bla}<bod><textarea>asdf&lt;/textarea></body></html>']);
  }

}