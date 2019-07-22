<?php

namespace tsd\serve;

class Router
{
  private $factory;
  private $plugins;

  function __construct(Factory $factory, array $plugins)
  {
    $this->factory = $factory;
    $this->plugins = $plugins;
  }

  function getData(string $method, string $host, string $path, array $data)
  {
    $controller = $this->getController($host, $path);    
    return Router::serve($controller, $method, $path, $data);
  }

  function route(string $host, string $method, string $path)
  {
    $controller = $this->getController($host, $path);
    
        //extract method name and parameters
        $params = [];
        $prefix = $method == 'POST' ? 'do' : $method == 'GET' ? 'show' : $method;
    
        $methodPath = Router::getMethodPath($controller->name, $path);
        $methodName = Router::getMethodName($methodPath, $prefix, $params);
    
        // find suitable Method
    
        $mi = Router::getMethodInfo($controller, $methodName);
        if (!$mi) {
          $alternatives = [];
          $methodName = Router::getMethodName($methodPath, $prefix, $params, $alternatives);
    
          foreach ($alternatives as $a) {
            $mi = Router::getMethodInfo($controller, $a['methodName']);
    
            if ($mi) {
              $params = [$a['params']];
              break;
            }
          }
          if (!$mi) {
            //Controller::error (404, "Not Found", "Keine passende Methode ($methodName) gefunden.");
          }
        }

        return $method == 'POST' ? new PostRoute($controller, $mi) : 
          $method == 'GET' ? new GetRoute($controller, $mi) : false;
  }

  private static function getMethodName(string $methodPath, string $prefix, array &$params, array &$pathAlternatives = null)
  {
    $parts = explode('/', $methodPath);
    $methodName = $prefix;
    $params = [];

    if ($methodPath == '/') {
      $methodName .= 'index';
    }

    foreach ($parts as $p) {
      if (is_numeric($p)) {
        $params[] = $p;
      } else {
        $sparts = explode('.', $p);

        foreach ($sparts as $sp) {
          if (is_numeric($sp)) {
            $params[] = $sp;
          } else if (is_array($pathAlternatives) && $sp) {
            $params[] = $sp;
          } else {
            $methodName .= $sp;
          }
        }
      }
    }

    if (is_array($pathAlternatives)) {

      foreach ($params as $p) {
        $a = ['methodName' => $prefix, 'params' => []];
        $x = 0;

        foreach ($params as $p2) {
          if ($x > count($pathAlternatives)) {
            $a['methodName'] .= $p2;
          } else {
            $a['params'][] = $p2;
            $x++;
          }
        }

        $pathAlternatives[] = $a;
      }

      $params = [$params];
      return count($pathAlternatives);
    }

    return $methodName;
  }

  /**
   *
   * @param string $path
   * @param string $prefix
   * @param array $data
   */
  private function serve(Controller $c, string $method, string $path, array $data)
  {
    //extract method name and parameters
    $params = [];
    $prefix = $method == 'POST' ? 'do' : $method == 'GET' ? 'show' : $method;

    $methodPath = Router::getMethodPath($c->name, $path);
    $methodName = Router::getMethodName($methodPath, $prefix, $params);

    // find suitable Method

    $mi = Router::getMethodInfo($c, $methodName);
    if (!$mi) {
      $alternatives = [];
      $methodName = Router::getMethodName($methodPath, $prefix, $params, $alternatives);

      foreach ($alternatives as $a) {
        $mi = Router::getMethodInfo($c, $a['methodName']);

        if ($mi) {
          $params = [$a['params']];
          break;
        }
      }
      if (!$mi) {
        //Controller::error (404, "Not Found", "Keine passende Methode ($methodName) gefunden.");
      }
    }

    


    // invoke
    $pinfos = $mi->getParameters();
    $n = 0;

    foreach ($pinfos as $pi) {
      if (count($params) <= $n) {
        $params[] = $data[$pi->name];
      }

      $n++;
    }

    $mi->invokeArgs($c, $params);
  }

  function getController(string $path)
  {
    $parts = explode('/', $path);

    $name = count($parts) > 1 ? $parts[1] : 'default';
    $c = $this->loadController($name);

    return $c ? $c : $this->loadController('default');
  }

  /*static function getPluginController(string $name, string $path, string $namespace)
  {
    $instance = App::getInstance();
    return $instance->createController($name, $path, $namespace);
  }*/

  private function loadController(string $name)
  {
    echo "Load Controller $name";

    if (in_array($name, $this->plugins))
      return $this->createController('', App::PLUGINS."/{$name}/controller");
    /*try {
        return new PluginController($name);
      } catch (Exception $e) {
        echo $e->getTraceAsString();
        throw new Exception("Loading Plugin $name failed");
      }*/

    return $this->createController($name);
  }


  private function createController(string $name, string $path = 'controller', string $namespace = '')
  {
    echo "try create Controller '$name'\n";

    $fileName = "$path/$name.controller.php";
    $ctrlName = ($namespace ? '\\' : '') . $namespace . '\\' . $name . 'Controller';

    if (!file_exists($fileName)) {
      return false;
    }

    require_once $fileName;

    echo "File included, trying to instanciate.\n";

    $c = $this->factory->create($ctrlName);
    $c->name = $name;

    return $c;
  }


  private static function getMethodInfo(Controller $c, string $name)
  {
    $rc = new \ReflectionClass($c);
    $m = $rc->getMethods(\ReflectionMethod::IS_PUBLIC);
    $n = strtolower($name);

    foreach ($m as $mi) {
      if (strtolower($mi->name) == $n)
        return $mi;
    }

    return false;
  }

  private static function getMethodPath(string $name, string $path)
  {
    $parts = explode('/', $path);
    $mp = '/';

    $start = ($name == 'default' && (count($parts) > 1 && $parts[1] != 'default')) ? 1 : 2;

    for ($i = $start; $i < count($parts); $i++) {
      if ($parts[$i] != '') {
        $mp .= "$parts[$i]/";
      }
    }

    return $mp;
  }

  protected function buildBasePath($path)
  {
    $parts = explode('/', $path);
    $mp = '/';

    $start = ($this->name == 'default' && (count($parts) > 1 && $parts[1] != 'default')) ? 1 : 2;

    for ($i = 0; $i <= $start && $i < count($parts); $i++) {
      if ($parts[$i] != '') {
        $mp .= "$parts[$i]/";
      }
    }

    return $mp;
  }
}
class GetRoute extends Route
{
    function __construct(Controller $c, \ReflectionMethod $mi) 
    {
        parent::__construct($c, $mi);
    }

    function fill(array $data)
    {
        $this->data = array_merge($data['_GET'], $data);
    }
}

class PostRoute extends Route
{
    function __construct(Controller $c, \ReflectionMethod $mi) 
    {
        parent::__construct($c, $mi);
    }

    function fill(array $data)
    {
        $this->data = array_merge($data['_POST'], $data);
    }
}

abstract class Route
{
    private $controlller;
    private $methodInfo;
    protected $data;

    function __construct(Controller $controlller, \ReflectionMethod $methodInfo)
    {
        $this->controlller = $controlller;
        $this->methodInfo = $methodInfo;
    }

    abstract function fill(array $data);
    
    function follow()
    {
        // invoke
        $pinfos = $this->methodInfo->getParameters();
        $n = 0;
        $params = [];

        foreach ($pinfos as $pi) {
            if (count($params) <= $n) {
                $params[] = $this->data[$pi->name];
            }
         $n++;
        }

        $this->methodInfo->invokeArgs($this->controlller, $params);
    }

    function checkPermission(Membership $member)
    {
        // check permission
    
    $doc = $this->methodInfo->getDocComment();
    $matches = [];
    $authorized = true;

    if (preg_match('#@SecurityUser#', $doc)) {
      $authorized = !$member->isAnonymous();
    }

    if (preg_match_all('#@SecurityGroup\s(\w+)#', $doc, $matches) > 0) {
      $authorized = false;
      foreach ($matches[1] as $g) {
        if ($member->isInGroup($g)) {
          $authorized = true;
        }
      }
    }

    return $authorized;
    }
}
