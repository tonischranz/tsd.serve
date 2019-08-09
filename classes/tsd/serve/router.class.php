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

    function route(string $host, string $method, string $path)
    {
        //echo "\nMethod $path\n";
        $base = '';

        $controller = $this->getController($host, $path, $base);
        
        //extract method name and parameters
        $params = [];
        $prefix = $method == 'POST' ? 'do' : $method == 'GET' ? 'show' : $method;
        
        echo " CN $controller->name";
        $methodPath = Router::getMethodPath($base, $controller->name, $path);
        echo " MP $methodPath ";
        $methodName = Router::getMethodName($methodPath, $prefix, $params);
        
        // find suitable Method        
        $mi = Router::getMethodInfo($controller, $methodName);
        if (!$mi)
        {
            $alternatives = [];
            $methodName = Router::getMethodName($methodPath, $prefix, $params, $alternatives);

            foreach ($alternatives as $a) 
            {
                $mi = Router::getMethodInfo($controller, $a['methodName']);
            
                if ($mi) 
                {
                    $params = [$a['params']];
                    break;
                }
            }
        
            if (!$mi) 
            {
                //Controller::error (404, "Not Found", "Keine passende Methode ($methodName) gefunden.");
                //return false;
            }
        }

        return $method == 'POST' ? new PostRoute($controller, $mi, $params) :
            $method == 'GET' ? new GetRoute($controller, $mi, $params) : false;
    }

    private static function getMethodName(string $methodPath, string $prefix, array &$params, array &$pathAlternatives = null)
    {
        $parts = explode('/', $methodPath);
        $methodName = $prefix;
        $params = [];

        if ($methodPath == '/') 
        {
            $methodName .= 'index';
        }

        foreach ($parts as $p) 
        {
            if (is_numeric($p)) 
            {
                $params[] = $p;
            } 
            else 
            {
                $sparts = explode('.', $p);

                foreach ($sparts as $sp) 
                {
                    if (is_numeric($sp)) 
                    {
                        $params[] = $sp;
                    } 
                    else if (is_array($pathAlternatives) && $sp) 
                    {
                        $params[] = $sp;
                    } else 
                    {
                        $methodName .= $sp;
                    }
                }
            }
        }

        if (is_array($pathAlternatives)) 
        {
            foreach ($params as $p) 
            {
                $a = ['methodName' => $prefix, 'params' => []];
                $x = 0;

                foreach ($params as $p2) 
                {
                    if ($x > count($pathAlternatives))
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
            return count($pathAlternatives);
        }

        return $methodName;
    }

    /*private function serve(Controller $c, string $method, string $path, array $data)
    {
        //extract method name and parameters
        $params = [];
        $prefix = $method == 'POST' ? 'do' : $method == 'GET' ? 'show' : $method;

        $methodPath = Router::getMethodPath($c->name, $path);
        $methodName = Router::getMethodName($methodPath, $prefix, $params);

        // find suitable Method

        $mi = Router::getMethodInfo($c, $methodName);
        if (!$mi) 
        {
            $alternatives = [];
            $methodName = Router::getMethodName($methodPath, $prefix, $params, $alternatives);

            foreach ($alternatives as $a) 
            {
                $mi = Router::getMethodInfo($c, $a['methodName']);

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
        
        // invoke
        $pinfos = $mi->getParameters();
        $n = 0;

        foreach ($pinfos as $pi) 
        {
            if (count($params) <= $n) 
            {
                $params[] = $data[$pi->name];
            }

            $n++;
        }

        $mi->invokeArgs($c, $params);
    }*/

    function getController(string $host, string $path, string &$base)
    {
        //var_dump($path);        

        $parts = explode('/', $path);

        //var_dump($parts);

        $name = count($parts) > 1 ? $parts[1] : 'default';
        $c = $this->loadController($name, $base);

        return $c ? $c : $this->loadController('default', $base);
    }

    private function loadController(string $name, string &$base)
    {
        //var_dump($this->plugins);

        if (in_array($name, $this->plugins))
        {
            $base = $name;
            return $this->createController('default', App::PLUGINS."/{$name}/controller");
        }
        
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
        echo "trying to create Controller '$name' from $path";
        $fileName = "$path/$name.controller.php";
        $ctrlName = ($namespace ? '\\' : '') . $namespace . '\\' . $name . 'Controller';

        if (!file_exists($fileName)) 
        {
            return false;
        }

        require_once $fileName;

        $c = $this->factory->create($ctrlName);
        $c->name = $name;

        return $c;
    }

    private static function getMethodInfo(Controller $c, string $name)
    {
        $rc = new \ReflectionClass($c);
        $m = $rc->getMethods(\ReflectionMethod::IS_PUBLIC);
        $n = strtolower($name);

        foreach ($m as $mi) 
        {
            if (strtolower($mi->name) == $n)
                return $mi;
        }

        return false;
    }

    private static function getMethodPath(string $base, string $name, string $path)
    {
        $parts = explode('/', $path);
        $mp = '/';
        $bs = $base ? 1 : 0;

        $start = $bs + ($name == 'default' && (count($parts) > 1 && $parts[1] != 'default')) ? 1 : 2;

        for ($i = $start; $i < count($parts); $i++) 
        {
            if ($parts[$i] != '') 
            {
                $mp .= "$parts[$i]/";
            }
        }

        return $mp;
    }

  /*protected function buildBasePath($path)
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
  }*/
}

abstract class Route
{
    private $controlller;
    private $methodInfo;
    protected $data;

    function __construct(Controller $controlller, \ReflectionMethod $methodInfo, array $data)
    {
        $this->controlller = $controlller;
        $this->methodInfo = $methodInfo;
        $this->data = $data;
    }

    abstract function fill(array $data);
    
    function follow()
    {
        $pinfos = $this->methodInfo->getParameters();
        $n = 0;
        $params = [];

        foreach ($pinfos as $pi) 
        {
            if (count($params) <= $n) 
            {
                //todo: Model validation
                $params[] = key_exists($pi->name, $this->data) ? $this->data[$pi->name]:$this->data[$n];
            }
    
            $n++;
        }

        return $this->methodInfo->invokeArgs($this->controlller, $params);
    }

    function checkPermission(Membership $member)
    {
        $doc = $this->methodInfo->getDocComment();
        $matches = [];
        $authorized = true;

        if (preg_match('#@SecurityUser#', $doc)) 
        {
            $authorized = !$member->isAnonymous();
        }

        if (preg_match_all('#@SecurityGroup\s(\w+)#', $doc, $matches) > 0) 
        {
            $authorized = false;
            foreach ($matches[1] as $g) 
            {
                if ($member->isInGroup($g)) 
                {
                $authorized = true;
                }
            }
        }

        return $authorized;
    }
}

class GetRoute extends Route
{
    function __construct(Controller $c, \ReflectionMethod $mi, array $data) 
    {
        parent::__construct($c, $mi, $data);
    }

    function fill(array $data)
    {
        $d = array_merge($this->data, $data['_GET']); 
        $this->data = array_merge($d, $data);
    }
}

class PostRoute extends Route
{
    function __construct(Controller $c, \ReflectionMethod $mi, array $params) 
    {
        parent::__construct($c, $mi, $params);
    }

    function fill(array $data)
    {
        $this->data = array_merge($this->data, $data['_POST'], $data);
    }
}
