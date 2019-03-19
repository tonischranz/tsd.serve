<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace tsd\serve;

/**
 * Description of layout
 *
 * @author tonti
 */
class Layout extends View
{

  public function __construct ()
  {
    parent::__construct ('./views/layout.html');
  }

  public function render (array $data)
  {
    $this->load ();
    $this->localize ();
    $this->compile ();

    $view = $this->getCompiled ();

    Layout::renderInt ($view, $data);
  }

  private static function renderInt ($view, $data)
  {
    $d = $data;

    eval ($view);

    unset ($d);
  }

}
