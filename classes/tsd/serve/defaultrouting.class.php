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
        
        if (in_array($name, $plugins))
        {
            $plugin = $name;
            $name = count($parts) > 2 ? $parts[2] : 'default';
            $cutoff++;
            
            $c = $this->createController($name, $factory, App::PLUGINS."/{$plugin}/controller");
        }
        else
        {
            $c = $this->createController($name, $factory);//, $base);
        }
        if (!$c)  $c = $this->createController('default', $factory);//, $base);
             

        echo "Cutoff : $cutoff";
      
        //extract method name and parameters
        $params = [];
        $prefix = $method == 'POST' ? 'do' : $method == 'GET' ? 'show' : $method;
         
         echo " CN $c->name";

        for ($i = 0; $i < $cutoff; $i ++) \array_shift($parts);
        $methodPath = \implode('/', $parts);
         //$methodPath = Router::getMethodPath($base, $controller->name, $path);
         echo " MP $methodPath ";
         //$methodName = Router::getMethodName($methodPath, $prefix, $params);
         
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

    private function createController(string $name, Factory $factory, string $path = 'controller')//, string $namespace = '')
         {
             echo "trying to create Controller '$name' from $path";
             $fileName = "$path/$name.controller.php";
             $ctrlName = $name . 'Controller';
     
             if (!file_exists($fileName)) 
             {
                 return false;
             }
     
             require_once $fileName;
     
             $c = $factory->create($ctrlName);
             $c->name = $name;
     
             return $c;
         }
}
