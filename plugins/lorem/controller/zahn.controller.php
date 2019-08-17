<?php

use tsd\serve\Controller;

class zahnController extends Controller
{
  function showIndex ()
  {
    return $this->view (['text' => 'Es isch e mau Maa gsi, dä het ä hohlä Zahn gha, i dä Zahn hets es Briefli gha u da isch gstandä: Es isch e mau Maa gsi, dä het ä hohlä Zahn gha, i dä Zahn hets es Briefli gha u da isch gstandä: Es isch e mau Maa gsi, dä het ä hohlä Zahn gha, i dä Zahn hets es Briefli gha u da isch gstandä: ... ']);
  }
}