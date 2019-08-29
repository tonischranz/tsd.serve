<?php

namespace tsd\serve;

/**
 * ⚒ 
 */
class Router
{
    private $factory;
    private $plugins;
    private $adminPlugins;
    private $routing;

    function __construct(Factory $factory, RoutingStrategy $routing, array $plugins)
    {
        $this->factory = $factory;
        $this->plugins = $plugins;
        $this->adminPlugins =  preg_grep('/^[^\._]\w/', $plugins);
        $this->routing = $routing;
    }

    function getRoute(string $host, string $method, string $path)
    {
        echo "Get Route for Path $path";
        return $this->routing->createRoute($host, $method, $path, $this->factory, $this->plugins);
    }
}

/**
 * @Mode simple
 */
class SimpleRouting extends RoutingStrategy
{
    function createRoute (string $host, string $method, string $path, Factory $factory, array $plugins)
    {
        throw new NotImplementedException();
    }
}

/**
 * 
 * @Mode www
 */
class WWWRouting extends RoutingStrategy
{
    function createRoute (string $host, string $method, string $path, Factory $factory, array $plugins)
    {
        throw new NotImplementedException();
    }
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
        $mi = $this->methodInfo;
        echo "MethodName $mi->name";

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
