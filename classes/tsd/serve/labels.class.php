<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace tsd\serve;

/**
 * Description of Labels
 *
 * @author tonti
 */
class Labels
{

  /**
   *
   * @param string $path
   * @return \tsd\serve\Label
   */
  static function create(string $path)
  {
    $l = new JSONLabels(dirname($path), new JSONLabels('./views'));
    return $l;
  }

}
