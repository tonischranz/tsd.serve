<?php

namespace tsd\serve;

use \ReflectionMethod;
use \ReflectionClass;
use \ReflectionParameter;
use \ReflectionNamedType;

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

    private Factory $factory;
    private array $domains = [];


    function getRoute(string $host, string $method, string $path)
    {
        $plugin = '';
        $layoutPlugin = '';
        $hostPlugin = '';
        $oldPlugin = '';
        $overrideName = '';
        $pluginRoot = '';
        $oldPluginRoot = '';
        $c = null;
        $ctx = $this->factory->createSingleton('tsd\\serve\\ViewContext');

        $parts = explode('/', $path);

        $cutoff = 1;

        $name = count($parts) > 1 ? $parts[1] : 'default';

        if (array_key_exists($host, $this->domains) && array_key_exists($this->domains[$host], App::$plugins)) {
            $hostPlugin = $this->domains[$host];

            $plugin = $hostPlugin;
            $layoutPlugin = $hostPlugin;
            $oldPlugin = $hostPlugin;
        }

        if ($name == '_login') {
            $c = $this->injectController('tsd\\serve\\LoginController', '_login');
            $cutoff ++;
        }

        if ($name == '_static') {
            $c = $this->injectController('tsd\\serve\\StaticController', '_static');
            $cutoff ++;
        }

        if ($name == '_info') {
            $c = $this->injectController('tsd\\serve\\InfoController', '_info');
            $cutoff ++;
        }

        if ($name == "favicon.ico") {
            $c = $this->injectController('tsd\\serve\\StaticController', '_static');            
        }

        if (!$c) {

            if (array_key_exists($name, App::$plugins)) {
                $plugin = $name;
                $pluginRoot = "/$name";
                $oldPluginRoot = $pluginRoot;

                if ($hostPlugin && @App::$plugins[$hostPlugin]['overrideController']) {
                    $overrideName = $hostPlugin;
                    $pluginRoot = '';
                }

                $name = count($parts) > 2 ? $parts[2] : 'default';

                $cutoff += 2;

                if ($name == '') {
                    $name = 'default';
                    $cutoff--;
                }

                if (!$layoutPlugin || @App::$plugins[$plugin]['forceLayout']) {
                    $layoutPlugin = $plugin;
                }

                if (array_key_exists($name, App::$plugins)) {
                    $tmp = App::$plugins;
                    $oldPlugin = $plugin;
                    $plugin = $name;

                    if ($oldPlugin && @App::$plugins[$oldPlugin]['overrideController']) {
                        $overrideName = $oldPlugin;
                    }

                    $name = count($parts) > 3 ? $parts[3] : 'default';

                    $cutoff++;

                    if ($name == '') {
                        $name = 'default';
                        $cutoff--;
                    }

                    if (!$layoutPlugin || @App::$plugins[$plugin]['forceLayout']) {
                        $layoutPlugin = $plugin;
                    }
                }

                if ($overrideName) {
                    $c = $this->createController($overrideName, $plugin);

                    if (!$c) {
                        $c = $this->createController('default', $oldPlugin);
                        $pluginRoot = $oldPluginRoot;
                        $cutoff--;
                    }
                    $cutoff--;
                } else {
                    $c = $this->createController($name, $plugin);
                    if (!$c) {
                        $c = $this->createController('default', $plugin);
                        $cutoff--;
                    }
                }

                if (!$c) $plugin = '';
            } else if ($plugin) {
                $c = $this->createController($name, $plugin);
                $cutoff++;
                if (!$c) {
                    $c = $this->createController('default', $plugin);
                    $cutoff--;
                }
            } else {
                $c = $this->createController($name);
            }
            if (!$c) {
                $c = $this->createController('default');
                $pluginRoot = '';
            }
        }

        $ctx->layoutPlugin = $layoutPlugin;
        $ctx->pluginRoot = $pluginRoot;
        
        if (!$c) {
            return new NoRoute($ctx);
        }

        for ($i = 0; $i < $cutoff; $i++) array_shift($parts);
        $methodPath = implode('/', $parts);

        $params = [];
        $prefix = $method == 'POST' ? 'do' : ($method == 'GET' ? 'show' : $method);

        $rc = new ReflectionClass($c);

            $alternatives = Router::getAlternatives(preg_split('/\/|\./',$methodPath), $prefix);

            foreach ($alternatives as $a) {
                $mi = $this->getMethodInfo($rc, $a['name']);

                if ($mi) {
                    $params = $a['params'] ? [$a['params']] : [];
                    break;
                }
            }
            if (!$mi) {
                return new NoRoute($ctx);
            }

        return $method == 'POST' ? new PostRoute($c, $mi, $ctx, $params) : ($method == 'GET' ? new GetRoute($c, $mi, $ctx, $params) : false);
    }

    private static function getAlternatives(array $path, string $name = '', array $params = [])
    {
        if (count($path) > 1)
        {
            $newPath = $path;
            $newPart = array_shift($newPath);
            $chalt = Router::getAlternatives($newPath,$name.$newPart, $params);
            $chalt2 = Router::getAlternatives($newPath,$name, array_merge($params, [$newPart]));

            foreach ($chalt as $ca) yield $ca;
            foreach ($chalt2 as $ca2) yield $ca2;
        }
        else
        {
            if ($path[0] == '') yield ['name'=>$name.'index', 'params' => $params];
            else
            {
                yield ['name'=>$name.$path[0], 'params' => $params];
                yield ['name'=>$name, 'params'=>array_merge($params, [$path[0]]) ];
            }
        }
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
        $path = $plugin ? App::PLUGINS . DIRECTORY_SEPARATOR . $plugin . DIRECTORY_SEPARATOR . Router::CONTROLLER : Router::CONTROLLER;

        $fileName = $path . DIRECTORY_SEPARATOR . $name . '.php';
        $ctrlName = $name . 'Controller';

        $namespace = @App::$plugins[$plugin]['namespace'];

        if ($namespace) $ctrlName = "$namespace\\$ctrlName";

        if (!file_exists($fileName)) {
            return false;
        }

        require_once $fileName;

        return $this->injectController($ctrlName, $name, $plugin);
    }

    private function injectController($cname, $name, $plugin = '')
    {
        $ctx = new InjectionContext();
        $ctx->name = 'serve';
        $ctx->fullname = "tsd.serve";
        $ctx->plugin = $plugin;

        $c = $this->factory->create($cname, $name, $ctx);

        return $c;
    }
}


abstract class Route
{
    private ?Controller $controller;
    private ?ReflectionMethod $methodInfo;
    private ViewContext $ctx;
    protected array $data = [];

    function __construct($controller, $methodInfo, ViewContext $ctx, array $data = [])
    {
        $this->ctx = $ctx;
        $this->controller = $controller;
        $this->methodInfo = $methodInfo;
        $this->data = $data;
    }

    abstract function fill(array $data);

    function follow()
    {
        $this->controller->prepare();
        
        $pinfos = $this->methodInfo->getParameters();
        $n = 0;
        $params = [];

        foreach ($pinfos as $pi) {
            /*if (count($params) <= $n) {*/
                //todo: Model validation
                if ($this->isModelParam($pi)) $params[] = $this->injectModel($pi);
                else if (key_exists($pi->name, $this->data)) $params[] = $this->data[$pi->name];
                //todo: 
                else if (($pi->isVariadic()) && key_exists(0, $this->data)) foreach ($this->data[0] as $d) $params[] = $d;
                else if (Route::declaresArray($pi) && key_exists(0, $this->data))$params[] = $this->data[0];
                else if (key_exists(0, $this->data) && key_exists($n, $this->data[0])) { $params[] = $this->data[0][$n]; $n++;}
                else if ($pi->isDefaultValueAvailable()) $params[] = $pi->getDefaultValue();
            /*}*/

            //$n++;
        }

        return $this->methodInfo->invokeArgs($this->controller, $params);
    }

    static function declaresArray(ReflectionParameter $reflectionParameter): bool
    {
        $reflectionType = $reflectionParameter->getType();

        if (!$reflectionType) return false;

        $types = $reflectionType instanceof ReflectionUnionType
            ? $reflectionType->getTypes()
            : [$reflectionType];

        return in_array('array', array_map(fn(ReflectionNamedType $t) => $t->getName(), $types));
    }

    function isModelParam(ReflectionParameter $pi)
    {
        if (!$pi->hasType()) return false;
        $t = $pi->getType();
        if (!$t instanceof ReflectionNamedType) return false;
        return !$t->isBuiltin();
    }

    function injectModel(ReflectionParameter $pi)
    {
        $t = $pi->getType();
        $c = new ReflectionClass($t->getName());
        $obj = $c->newInstance();

        foreach ($c->getProperties() as $pi)
        {
            if(key_exists($pi->name, $this->data)) $pi->setValue($obj, $this->data[$pi->name]); 
        }
        return $obj;
    }

    function checkPermission(Membership $member)
    {
        $att = $this->methodInfo->getAttributes();
        $authorized = true;

        foreach ($att as $a)
        {
            if ($a->getName() == 'tsd\serve\SecurityUser') return !$member->isAnonymous();
            if ($a->getName() == 'tsd\serve\SecurityGroup')
            {
                $authorized = false;
                if ($member->isInGroup($a->getArguments()[0])) return true;
            }
        }

        return $authorized;
    }

    function ctx(): ViewContext
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
        $this->data = array_merge($this->data, $data['_GET'], $data['_POST'], $data);
    }
}

class NoRoute extends Route
{
    function __construct(ViewContext $ctx)
    {
        parent::__construct(null, null, $ctx);
    }

    function fill(array $data)
    {
    }

    function follow()
    {
        throw new NotFoundException('Route');
    }

    function checkPermission(Membership $member)
    {
        return true;
    }
}
