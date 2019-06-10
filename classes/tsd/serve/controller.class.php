<?php

namespace tsd\serve;

use tsd\serve\model\Config;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/*    setlocale(LC_ALL, "de_CH.UTF-8");
  session_start(); */

/**
 * Description of Controller
 *
 * @author tonti
 */
class Controller
{

  protected $name;
  private $config;
  private $membership;
  private $viewsPath;
  private $basePath;

  function __construct ()
  {
    //$this->membership = new Membership();
    $c = new Config();
    $this->config = $c;
    $m = $c->getMembershipConfig();
    $this->membership = Membership::create($m['mode'], $m);
    $this->viewsPath = './views';

    set_include_path (get_include_path () . PATH_SEPARATOR . './lib');
    spl_autoload_register ();
  }

  private function getMethodPath (string $path)
  {
    $parts = explode ('/', $path);
    $mp = '/';

    $start = ($this->name == 'default' && (count ($parts) > 1 && $parts[1] != 'default')) ? 1 : 2;

    for ($i = $start; $i < count ($parts); $i++)
    {
      if ($parts[$i] != '')
      {
        $mp.="$parts[$i]/";
      }
    }

    return $mp;
  }

  protected function buildBasePath ($path)
  {
    $parts = explode ('/', $path);
    $mp = '/';

    $start = ($this->name == 'default' && (count ($parts) > 1 && $parts[1] != 'default')) ? 1 : 2;

    for ($i = 0; $i <= $start && $i < count ($parts); $i++)
    {
      if ($parts[$i] != '')
      {
        $mp.="$parts[$i]/";
      }
    }

    return $mp;
  }

  private function getMethodInfo (string $name)
  {
    $rc = new \ReflectionClass ($this);
    $m = $rc->getMethods (\ReflectionMethod::IS_PUBLIC);
    $n = strtolower ($name);

    foreach ($m as $mi)
    {
      if (strtolower ($mi->name) == $n)
        return $mi;
    }

    return false;
  }

  /**
   *
   * @param string $path
   * @param string $prefix
   * @param array $data
   */
  protected function serve (string $path, string $prefix, array $data)
  {
    //extract method name and parameters
    $params = [];
    $methodPath = $this->getMethodPath ($path);

    if (!$this->basePath)
    {
      $this->setBasePath ($this->buildBasePath ($path));
    }

    $methodName = Controller::getMethodName ($methodPath, $prefix, $params);

    // find suitable Method

    $mi = $this->getMethodInfo ($methodName);
    if (!$mi)
    {
      $alternatives = [];
      $methodName = Controller::getMethodName ($methodPath, $prefix, $params, $alternatives);

      foreach ($alternatives as $a)
      {
        $mi = $this->getMethodInfo ($a['methodName']);

        if ($mi)
        {
          $params = [$a['params']];
          break;
        }
      }
      if (!$mi)
      {
        Controller::error (404, "Not Found", "Keine passende Methode ($methodName) gefunden.");
      }
    }

    // check permission
    $mem = $this->getMembership ();
    $doc = $mi->getDocComment ();
    $matches = [];

    if (preg_match_all ('#@SecurityGroup\s(\w+)#', $doc, $matches) > 0)
    {
      $authorized = false;
      foreach ($matches[1] as $g)
      {
        if ($mem->isInGroup ($g))
        {
          $authorized = true;
        }
      }
      if (!$authorized)
      {
        if ($mem->isAnonymous ())
        {
          $this->redirect ('/member/login');
        }
        else
        {
          $this->error (403, 'Forbidden');
        }
      }
    }


    // invoke
    $pinfos = $mi->getParameters ();
    $n = 0;

    foreach ($pinfos as $pi)
    {
      if (count ($params) <= $n)
      {
        $params[] = $data[$pi->name];
      }

      $n++;
    }

    $mi->invokeArgs ($this, $params);
  }

  /**
   *
   * @return Membership
   */
  protected function getMembership ()
  {
    return $this->membership;
  }

  protected function render (string $view, array $data = null)
  {
    $template = $this->getTemplatePath ($view);


    if (file_exists ($template))
    {
      Controller::renderTemplate ($template, ['viewData' => $data, 'basePath' => $this->basePath]);
    }
    else
    {
      Controller::error (404, 'Not Found', 'No suitable view could be found.');
    }
  }

  protected function getTemplatePath (string $view)
  {
    return "$this->viewsPath/$this->name/$view.html";
  }

  protected function setViewsPath (string $path)
  {
    $this->viewsPath = $path;
  }

  protected function redirect (string $path)
  {
    header ("Location: $path");
    exit;
  }

  function serveGET (string $path, array $data)
  {
    $this->serve ($path, 'show', $data);
  }

  function servePOST (string $path, array $data)
  {
    $this->serve ($path, 'do', $data);
  }

  protected function buildMenu ()
  {
    /* $mem = $this->getMembership();

      $menu = [];

      if ($mem->isInGroup('user') || $mem->isInGroup('editor'))
      {
      $menu [] = [url => '/', name => 'Offerten'];
      }
      if ($mem->isInGroup('editor'))
      {
      $menu [] = [url => '/drafts', name => 'Entwürfe'];
      }
      if ($mem->isInGroup('user') || $mem->isInGroup('editor'))
      {
      $menu [] = [url => '/archive', name => 'Archiv'];
      }
      if ($mem->isInGroup('admin'))
      {
      $menu [] = [url => '/member/users', name => 'Benutzerverwaltung'];
      }

      return $menu; */
  }

  protected function buildUserMenu ()
  {
    /*
      $mem = $this->getMembership();

      $userMenu = [];


      if (!$mem->isAnonymous())
      {
      $u          = $mem->getCurrentUser();
      $userMenu[] = [url => '/member', name => $u['fullname'], icon => 'fa-user'];
      $userMenu[] = [url => '/member/logout', name => 'abmelden', icon => 'fa-power-off'];
      }

      return $userMenu;
     *
     */
  }

  protected function renderPDF ($view, $viewData)
  {
    /*
      global $pdf;
      global $data;

      $pdf  = new ArenaPDF();
      $data = $viewData;

      $template = $this->name . '/' . $view . '.php';

      if (file_exists("./views/$template"))
      require "./views/$template";
      else
      Controller::error(500, 'Internal Error');

      $pdf->Output();
     *
     */
    Controller::error (501, 'Not Implemented', 'PDF Rendering is not yet implemented');
  }

  static function getController (string $path)
  {
    $parts = explode ('/', $path);

    $name = count ($parts) > 1 ? $parts [1] : 'default';
    $c = Controller::loadController ($name);

    if (!$c)
    {
      $c = Controller::loadController ('default');
    }

    return $c;
  }

  private static function renderTemplate (string $view, array $data)
  {
    $v = new View ($view);
    $v->render ($data);
  }

  /**
   *
   * @param string $name
   * @param string $path
   * @param string $namespace
   * @return boolean|\tsd\serve\Controller
   */
  protected static function loadControllerInt (string $name, string $path = 'controller', string $namespace = '')
  {
    $fileName = "$path/$name.controller.php";
    $ctrlName = ($namespace ? '\\' : '') . $namespace . '\\' . $name . 'Controller';

    if (!file_exists ($fileName))
    {
      return false;
    }

    require_once $fileName;


    $c= Controller::create($ctrlName);
    //$c = new $ctrlName();
    $c->name = $name;

    return $c;
  }

  private static function create($name, $pname='')
{
	$t = new \ReflectionClass($name);
	$con = $t->getConstructor();
  $par = $con?$con->getParameters():[];
  $args = [];
	
	foreach ($par as $p)
	{
    if ($p->isArray() && $p->name == 'config' && $pname) 
      $args[]=Config::getConfig($pname);
    else if ($p->hasType() && !$p->isArray())
      $args[]=Controller::create($p->getType()->getName(), $p->getName());
    else if ($p->isArray())
      $args[]=[];
    else 
      $args[]=null;
  }
  
  return $con?$args?$t->newInstanceArgs($args):new $name():new $name();
}

  private static function getMethodName (string $methodPath, string $prefix, array &$params, array &$pathAlternatives = null)
  {
    $parts = explode ('/', $methodPath);
    $methodName = $prefix;
    $params = [];

    if ($methodPath == '/')
    {
      $methodName .= 'index';
    }

    foreach ($parts as $p)
    {
      if (is_numeric ($p))
      {
        $params[] = $p;
      }
      else
      {
        $sparts = explode ('.', $p);

        foreach ($sparts as $sp)
        {
          if (is_numeric ($sp))
          {
            $params[] = $sp;
          }
          else if (is_array ($pathAlternatives) && $sp)
          {
            $params[] = $sp;
          }
          else
          {
            $methodName .= $sp;
          }
        }
      }
    }

    if (is_array ($pathAlternatives))
    {

      foreach ($params as $p)
      {
        $a = ['methodName' => $prefix, 'params' => []];
        $x = 0;

        foreach ($params as $p2)
        {
          if ($x > count ($pathAlternatives))
          {
            $a['methodName'] .= $p2;
          }
          else
          {
            $a['params'][] = $p2;
            $x++;
          }
        }

        $pathAlternatives[] = $a;
      }

      $params = [$params];
      return count ($pathAlternatives);
    }

    return $methodName;
  }

  private static function loadController (string $name)
  {
    //ToDo: Namespaces & Plugins
    if ($name && file_exists ("./plugins/$name/"))
    {
      try
      {
        return new PluginController ($name);
      }
      catch (Exception $e)
      {
        echo $e->getTraceAsString ();
        Controller::error (500, "Loading Plugin $name failed");
        return false;
      }
    }

    return Controller::loadControllerInt ($name);
  }

  private static function error (int $code, string $message, string $description = '')
  {
    echo "Error: $code<br />";
    echo "$message<br />";
    echo "$description<br />";
    //header ("HTTP/1.0 $code $message");
    //Controller::renderInt('error.php', [code=>$code, message=>$message, description=>$description]);
    exit;
  }

  /**
   *
   * @param string $template
   * @param array $viewData
   * @param array $layoutData
   *
   * @deprecated since version 16.12.0
   */
  private static function renderInt (string $template, array $viewData, array $layoutData = [])
  {
    Controller::error (501, 'Internal Error', 'Direct .php File rendering not supported anymore');
  }

  protected function setBasePath ($path)
  {
    $this->basePath = $path;
  }

  protected function getBasePath ()
  {
    return $this->basePath;
  }

}
