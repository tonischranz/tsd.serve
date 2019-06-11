<?php

namespace tsd\serve;

class PluginController extends Controller
{

  private $data;

  function __construct (string $name)
  {
    parent::__construct ();

    $this->name = $name;

    //sdkj,fhgskdjlfg

    //ToDo: Plugins in phar-archives

    $filename = "./plugins/$name/plugin.json";
    $classPath = "./plugins/$name/classes";

    if (file_exists ($filename))
    {
      $this->data = json_decode (file_get_contents ($filename), true);
    }

    $p = get_include_path () . PATH_SEPARATOR . $classPath;
    set_include_path ($p);
    spl_autoload_register ();
  }

  function serve (string $path, string $prefix, array $data)
  {
    $parts = explode ('/', $path);

    $name = (count ($parts) > 2 && $parts[2]) ? $parts [2] : 'default';
    $name_plugin = $this->name;
    $d = $this->data;

    array_shift ($parts);
    $parts[0] = '';

    $p = implode ('/', $parts);

    if (!$d)
    {
      $d = [];
    }
    if (!isset($d['namespace']))
    {
      $nps = explode ('.', $name_plugin);

      if (count ($nps) == 1)
      {
        $nps = [];
      }

      if ($nps && !$nps[0])
      {
        $nps[0] = 'serve';
        array_unshift ($nps, 'tsd');
      }

      $d['namespace'] = implode ('\\', $nps);
    }

    $c = App::getPluginController ($name, "plugins/$name_plugin/controller", $d['namespace']);

    if (!$c)
    {
      $c = App::getPluginController ('default', "plugins/$name_plugin/controller", $d['namespace']);
    }

    $c->setViewsPath ("./plugins/$name_plugin/views");
    $c->setBasePath ($c->buildBasePath ($path));
    $c->serve ($p, $prefix, $data);
  }

}
