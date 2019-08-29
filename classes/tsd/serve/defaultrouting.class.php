<?php

namespace tsd\serve;

/**
 * @Default
 */
class DefaultRouting extends RoutingStrategy
{
    function createRoute (string $host, string $method, string $path, Factory $factory, array $plugins)
    {
        $parts = explode('/', $path);
     
        $cutoff = 1;

        $name = count($parts) > 1 ? $parts[1] : 'default';
        
        if ($name == 'admin')
        {
            $name = $parts[2];
            $cutoff += 2;
            
            $c = $this->createController($name, $factory, App::PLUGINS."/.{$plugin}");
        }

        if (in_array($name, $plugins))
        {
            $plugin = $name;
            $name = count($parts) > 2 ? $parts[2] : 'default';
            $cutoff++;

            if ($name == '') $name = 'default';
            
            $c = $this->createController($name, $factory, App::PLUGINS."/{$plugin}");
        }
        else
        {
            $c = $this->createController($name, $factory);//, $base);
        }
        if (!$c)  $c = $this->createController('default', $factory);//, $base);
             
        $params = [];
        $methodName = $method == 'POST' ? 'do' : $method == 'GET' ? 'show' : $method;
         
        for ($i = 0; $i < $cutoff; $i ++) \array_shift($parts);
        $methodPath = \implode('/', $parts);

echo "<br>MethodPath: <br>";
var_dump ($methodPath);

        $params = [];

        if ($methodPath == '') 
        {
            $methodName .= 'index';
        }
 
        $rc = new \ReflectionClass($c);
        $m = $rc->getMethods(\ReflectionMethod::IS_PUBLIC);
        $n = strtolower($methodName);

        echo "<br>guessing methodName: $m";

        foreach ($m as $mi) 
        {
            if (strtolower($mi->name) == $n)
                break;
                
            $mi = false;
        }

        if (!$mi)
        {
            $alternatives = [];
            //$methodName = Router::getMethodName($methodPath, $prefix, $params, $alternatives);
 
            foreach ($alternatives as $a) 
            {
                echo "<br>checking alternative: $a";

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

        foreach ($parts as $p) 
        {
            var_dump($p);

            if (is_numeric($p)) 
            {
                $params[] = $p;
            } 
            else 
            {
                $sparts = explode('.', $p);
 
                foreach ($sparts as $sp) //echo "\nMethod $path\n";
                {
                    $params[] = $sp;
                }
            }
        } 

        echo "<br>Params: <br>";
        var_dump($params);
 
        return $method == 'POST' ? new PostRoute($c, $mi, $params) :
            $method == 'GET' ? new GetRoute($c, $mi, $params) : false;

    }

    private function createController(string $name, Factory $factory, string $path = '.')//, string $namespace = '')
    {
        $fileName = "$path/controller/$name.controller.php";
        $ctrlName = $name . 'Controller';
     
        if (!file_exists($fileName)) 
        {
            return false;
        }
 
        require_once $fileName;
 
        $c = $factory->create($ctrlName);
        $c->name = $name;
        $c->basePath = $path; 
 
        return $c;
    }
}