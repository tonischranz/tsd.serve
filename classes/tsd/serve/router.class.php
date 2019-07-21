<?php
namespace tsd\serve;

class Router
{
    private $factory;

    function __construct($factory, $plugins)
    {
        $this->factory = $factory;
    }

    function getData ($method, $host, $path, $get_data, $post_data, $files_data, $cookie_data)
    {
        $controller = Router::getController($path);

        $data = [];

        $this->serve($controller, $method, $path, $data);


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


  /*function serveGET (string $path, array $data)
  {
    $this->serve ($path, 'show', $data);
  }

  function servePOST (string $path, array $data)
  {
    $this->serve ($path, 'do', $data);
  }*/


  /**
   *
   * @param string $path
   * @param string $prefix
   * @param array $data
   */
  protected function serve (Controller $c, string $method, string $path, array $data)
  {

    $prefix = $method == 'POST' ? 'do' : $method == 'GET' ? 'show' : $method;
        
    //extract method name and parameters
    $params = [];
    $methodPath = $this->getMethodPath ($path);

    /*if (!$this->basePath)
    {
      $this->setBasePath ($this->buildBasePath ($path));
    }*/

    $methodName = Router::getMethodName ($methodPath, $prefix, $params);

    // find suitable Method

    $mi = Router::getMethodInfo ($c, $methodName);
    if (!$mi)
    {
      $alternatives = [];
      $methodName = Router::getMethodName ($methodPath, $prefix, $params, $alternatives);

      foreach ($alternatives as $a)
      {
        $mi = Router::getMethodInfo ($c, $a['methodName']);

        if ($mi)
        {
          $params = [$a['params']];
          break;
        }
      }
      if (!$mi)
      {
        //Controller::error (404, "Not Found", "Keine passende Methode ($methodName) gefunden.");
      }
    }

    // check permission
    $mem = $this->factory->create('\tsd\serve\Membership', 'member');
    $doc = $mi->getDocComment ();
    $matches = [];
    $authorized = true;

    if (preg_match('#@SecurityUser#', $doc))
    {
        $authorized = !$mem->isAnonymous();
    }

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
    }

    if (!$authorized)
    {
      if ($mem->isAnonymous ())
      {
        $this->redirect ('/_member/login');
      }
      else
      {
        $this->error (403, 'Forbidden');
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

    $mi->invokeArgs ($c, $params);
  }

    function getController(string $path)
    {
        //$instance = App::getInstance();
        $parts = explode('/', $path);

        $name = count($parts) > 1 ? $parts[1] : 'default';
        $c = $this->loadController($name);

        return $c ? $c : $this->loadController('default');
    }

    static function getPluginController(string $name, string $path, string $namespace)
    {
        $instance = App::getInstance();
        return $instance->createController($name, $path, $namespace);
    }

    private function loadController(string $name)
    {
        echo "Load Controller $name";

        if ($name && file_exists("./plugins/$name/")) {
            try {
                return new PluginController($name);
            } catch (Exception $e) {
                echo $e->getTraceAsString();
                throw new Exception("Loading Plugin $name failed");
            }
        }

        return $this->createController($name);
    }


    private function createController(string $name, string $path = 'controller', string $namespace = '')
    {
        echo "try create Controller $name ";

        $fileName = "$path/$name.controller.php";
        $ctrlName = ($namespace ? '\\' : '') . $namespace . '\\' . $name . 'Controller';

        if (!file_exists($fileName)) {
            return false;
        }

        require_once $fileName;

        echo "File included, trying to instanciate";

        $c = $this->factory->create($ctrlName);
        $c->name = $name;

        return $c;
    }


  private static function getMethodInfo (Controller $c, string $name)
  {
    $rc = new \ReflectionClass ($c);
    $m = $rc->getMethods (\ReflectionMethod::IS_PUBLIC);
    $n = strtolower ($name);

    foreach ($m as $mi)
    {
      if (strtolower ($mi->name) == $n)
        return $mi;
    }

    return false;
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
}