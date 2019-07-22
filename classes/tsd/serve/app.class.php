<?php

namespace tsd\serve;

use ReflectionMethod;

class App
{
    const CONFIG = '.config.json';
    const PLUGINS = 'plugins';
 
    private $router;
    private $view_engine;
    private $member;

    function __construct(array $config = null)
    {
        if ($config == null && file_exists(App::CONFIG))
            $config = json_decode (file_get_contents (App::CONFIG), true);
        else
            $config = [];

        $plugins = scandir(App::PLUGINS);
        $factory = new Factory($config, preg_grep('/^\.\w/',$plugins));
        
        $this->router = new Router($factory, preg_grep('/^[^\._]\w/', $plugins));
        $this->member = $factory->create('tsd\serve\Membership', 'member');
        $this->view_engine = $factory->create('tsd\serve\ViewEngine', 'views');
    }

    static function serve()
    {
        ob_start ();

        $app = new App();
        $app->serveRequest(
            $_SERVER['REQUEST_METHOD'], 
            $_SERVER['HTTP_HOST'],
            key_exists('REDIRECT_URL', $_SERVER) ? 
                $_SERVER['REDIRECT_URL']:$_SERVER['REQUEST_URI'], 
            [
                '_GET' => $_GET,'_COOKIE' => $_COOKIE, 
                '_POST' => $_POST,'_FILES' => $_FILES 
            ],         
            $_SERVER['HTTP_ACCEPT'] );

        ob_flush();
    }

    protected function serveRequest($method, $host, $path, $data, $accept)
    {
        $route = $this->router->route($host, $method, $path);
        var_dump($route);
        
        try { $result = $this->getResult($route, $data); }
        catch (Exception $e) { $result = $e; }
        var_dump($result);    
        
        $this->view_engine->render($result, $accept);
    }

    private function getResult(Route $route, array $data)
    {
        if (!$route->checkPermission($this->member))
            throw new AccessDeniedException($route);
        
        $route->fill($data);        
        return $route->follow();
    }   
}

class GetRoute extends Route
{
    function __construct(Controller $c, ReflectionMethod $mi) 
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
    function __construct(Controller $c, ReflectionMethod $mi) 
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

    function __construct(Controller $controlller, ReflectionMethod $methodInfo)
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

abstract class ViewEngine
{
    function render($result, $accept)
    {
        if (is_a($result, 'AccessDeniedException')) $result = new ErrorResult (401, $result);
        if (is_a($result, 'NotFoundException')) $result = new ErrorResult (404, $result);
        if (is_a($result, 'Exception')) $result = new ErrorResult (500, $result);
        if (!is_a($result, 'Result')) $result = new DataResult ($result);

        if ($accept == 'application/json') $this->renderJson($result);
        if ($accept == 'text/xml') $this->renderXml($result);

        $this->renderView($result);
    }

    private function renderJson(Result $result)
    {

    }

    private function renderXml(Result $result)
    {

    }

    protected abstract function renderView (Result $result);
}

class ServeViewEngine extends ViewEngine
{
    function renderView(Result $result)
    {
        if (is_a($result, 'ViewResult'));
        else if (is_a($result, 'DataResult'));
    }
}

interface Result
{
    function getData();
    function getStatusCode();
//    function 
}

class ResultBase implements Result
{
    private $statuscode;
    private $data;

    function __construct($data, $statuscode)
    {
        $this->statuscode = $statuscode;
        $this->data = $data;    
    }

    function getData()
    {
        return $this->data;
    }

    function getStatusCode()
    {
        return $this->statuscode;
    }

}

class RedirectResult
{
    function __construct($location)
    {
        parent::__construct($location, 302);
    }
}

class ViewResult
{
    function __construct(string $view, $data, $statuscode = 200)
    {

    }
}

class ErrorResult
{

}

class DataResult extends ResultBase
{
    function __construct($data)
    {
        parent::__construct($data, 200);
    }
}