<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace tsd\serve;

interface Label
{

  /**
   *
   * @param string $name
   * @return string
   */
  function getLabel (string $name);
}
