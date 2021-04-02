<?php

namespace tsd\serve;

use ReflectionMethod;
use ReflectionClass;
/**
 * The Router
 * 
 * @author Toni Schranz
 */
class Router
{
    /**
     * Controller directory name
     */
    const CONTROLLER = 'controller';

    private $factory;
    private $_plugins;
    
    function __construct(Factory $factory, array $_plugins)
    {
        $this->factory = $factory;
        $this->_plugins = $_plugins;
    }

    function getRoute(string $host, string $method, string $path)
    {
        $ctx = new ViewContext;
        $parts = explode('/', $path);

        $cutoff = 1;

        $name = count($parts) > 1 ? $parts[1] : 'default';


        /*if ($name == 'admin') {
            $plugin = $parts[2];
            $name = count($parts) > 3 ? $parts[3] : 'default';
            $cutoff += 3;

            if (!$name) {
                $name = 'default';
                $cutoff--;
            }

            $c = $this->createController($name, $plugin);
        } else */

        //todo:check for domains, set layoutplugin, plugin
        //todo:check for plugin/plugin stuff

        if (in_array($name, $this->_plugins)) {
            $plugin = $name;
            $name = count($parts) > 2 ? $parts[2] : 'default';
            $cutoff += 2;

            if ($name == '') {
                $name = 'default';
                $cutoff--;
            }

            $c = $this->createController($name, $plugin);

            if (!$c) {
                $c = $this->createController('default', $plugin);
                $cutoff--;
            }
        } else {
            $c = $this->createController($name);
        }

        if (!$c) {
            $c = $this->createController('default');
        }

        if (!$c) {
            return new NoRoute($ctx);
        }

        for ($i = 0; $i < $cutoff; $i++) array_shift($parts);
        $methodPath = implode('/', $parts);

        $params = [];
        $prefix = $method == 'POST' ? 'do' : ($method == 'GET' ? 'show' : $method);

        $methodName = $this->getMethodName($methodPath, $prefix, $params);

        $rc = new ReflectionClass($c);

        $mi = $this->getMethodInfo($rc, $methodName);

        if (!$mi) {
            $alternatives = [];
            $methodName = $this->getMethodName($methodPath, $prefix, $params, $alternatives);

            foreach ($alternatives as $a) {
                $mi = $this->getMethodInfo($rc, $a['methodName']);

                if ($mi) {
                    $params = [$a['params']];
                    break;
                }
            }
            if (!$mi) {
                echo "No method found for $methodPath";
                //Controller::error (404, "Not Found", "Keine passende Methode ($methodName) gefunden.");
            }
        }

        foreach ($parts as $p) {
            if (is_numeric($p)) {
                $params[] = $p;
            } else {
                $sparts = explode('.', $p);

                foreach ($sparts as $sp) //echo "\nMethod $path\n";
                {
                    $params[] = $sp;
                }
            }
        }

        return $method == 'POST' ? new PostRoute($c, $mi, $ctx, $params) : ($method == 'GET' ? new GetRoute($c, $mi, $ctx, $params) : false);
    }

    public static function getMethodName(string $methodPath, string $prefix, array &$params, array &$pathAlternatives = null): string
    {
        $parts = explode('/', $methodPath);
        $methodName = $prefix;
        $params = [];

        if ($methodPath == '') {
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

    private static function getMethodInfo(ReflectionClass $rc, string $name)
    {
        $m = $rc->getMethods(ReflectionMethod::IS_PUBLIC);
        $n = strtolower($name);

        foreach ($m as $mi) {
            if (strtolower($mi->name) == $n)
                return $mi;
        }

        return false;
    }

    private function createController(string $name, string $plugin = '')
    {
        $path = $plugin ? '.' . App::PLUGINS . DIRECTORY_SEPARATOR . $plugin . DIRECTORY_SEPARATOR . Router::CONTROLLER : '.' . Router::CONTROLLER;

        $fileName = $path . DIRECTORY_SEPARATOR . $name . '.php';
        $ctrlName = $name . 'Controller';

        if (!file_exists($fileName)) {
            return false;
        }

        require_once $fileName;

        $ctx = new InjectionContext();
        $ctx->name = 'serve';
        $ctx->fullname = "tsd.serve";
        $ctx->plugin = $plugin;

        $c = $this->factory->create($ctrlName, $name, $ctx);

        return $c;
    }
}


abstract class Route
{
    private Controller $controller;
    private ReflectionMethod $methodInfo;
    private ViewContext $ctx;
    protected $data;

    function __construct($controller, $methodInfo, ViewContext $ctx, array $data)
    {
        $this->ctx = $ctx;
        $this->controller = $controller;
        $this->methodInfo = $methodInfo;
        $this->data = $data;
    }

    abstract function fill(array $data);

    function follow()
    {
        $pinfos = $this->methodInfo->getParameters();
        $n = 0;
        $params = [];

        foreach ($pinfos as $pi) {
            if (count($params) <= $n) {
                //todo: Model validation
                //todo: param with default value
                $params[] = key_exists($pi->name, $this->data) ? $this->data[$pi->name] : $this->data[$n];
            }

            $n++;
        }

        return $this->methodInfo->invokeArgs($this->controller, $params);
    }

    function checkPermission(Membership $member)
    {
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

    function ctx() : ViewContext
    {
        return $this->ctx;
    }
}

class GetRoute extends Route
{
    function __construct(Controller $c, ReflectionMethod $mi, ViewContext $ctx, array $data)
    {
        parent::__construct($c, $mi, $ctx, $data);
    }

    function fill(array $data)
    {
        $d = array_merge($this->data, $data['_GET']);
        $this->data = array_merge($d, $data);
    }
}

class PostRoute extends Route
{
    function __construct(Controller $c, ReflectionMethod $mi, ViewContext $ctx, array $params)
    {
        parent::__construct($c, $mi, $ctx, $params);
    }

    function fill(array $data)
    {
        $this->data = array_merge($this->data, $data['_POST'], $data);
    }
}

class NoRoute extends Route
{
    function __construct(ViewContext $ctx)
    {
        parent::__construct(null, null, $ctx, array());
    }

    function fill(array $data)
    {
    }

    function follow()
    {
        throw new NotFoundException();
    }

    function checkPermission(Membership $member)
    {
        return true;
    }
}
