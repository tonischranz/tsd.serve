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
        
        echo "<br>Path parts: <br>";
        var_dump ($parts);

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
            $c = $this->createController($name, $factory);
        }
        if (!$c)  $c = $this->createController('default', $factory);
             
         
        for ($i = 0; $i < $cutoff; $i ++) \array_shift($parts);
        $methodPath = \implode('/', $parts);
        
        echo "<br>MethodPath: <br>";
        var_dump ($methodPath);

        $params = [];
        $prefix = $method == 'POST' ? 'do' : $method == 'GET' ? 'show' : $method;
            
        $methodName = $this->getMethodName ($methodPath, $prefix, $params);

        var_dump($c);

        $rc = new \ReflectionClass ($c);
     
        $mi = $this->getMethodInfo ($rc, $methodName);

        if (!$mi)
        {
            $alternatives = [];
            $methodName = $this->getMethodName ($methodPath, $prefix, $params, $alternatives);

            foreach ($alternatives as $a)
            {
                $mi = $this->getMethodInfo ($rc, $a['methodName']);

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

    private static function getMethodName (string $methodPath, string $prefix, array &$params, array &$pathAlternatives = null)
    {
        $parts = explode ('/', $methodPath);
        $methodName = $prefix;
        $params = [];

        if ($methodPath == '')
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

    private static function getMethodInfo (\ReflectionClass $rc, string $name)
    {
        $m = $rc->getMethods (\ReflectionMethod::IS_PUBLIC);
        $n = strtolower ($name);

        foreach ($m as $mi)
        {
            if (strtolower ($mi->name) == $n)
            return $mi;
        }

        return false;
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