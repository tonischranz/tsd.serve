<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace tsd\serve;

class JSONLabels implements Label
{

  private $root;
  private $data;

  public function __construct(string $path, JSONLabels $root = null)
  {
    if ($root)
      $this->root = $root;

    $file = $path . '/labels.json';

    if (file_exists($file))
      $this->data = json_decode(file_get_contents($file), true);
  }

  function getLabel(string $name)
  {
    $lang = 'de';

    if (!$name)
      return false;
    if ($name[0] == '/')
    {
      if ($this->root)
      {
        return $this->root->getLabel(substr($name, 1));
      }
    }
    if (!$this->data || !array_key_exists($name, $this->data))
      return "[not found|$name]";
    if (!array_key_exists($lang, $this->data[$name]))
      return "[not $lang|$name]";
    return $this->data[$name][$lang];
  }

}
