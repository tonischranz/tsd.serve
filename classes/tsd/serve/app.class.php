<?php

namespace tsd\serve;

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

        echo "hallo";
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
        
        try { $result = $this->getResult($route, $data); }
        catch (Exception $e) { $result = $e; }
        
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

/**
 * @Implementation tsd\serve\ServeViewEngine
 */
abstract class ViewEngine
{
    function render($result, $accept)
    {
        if ($result instanceof tsd\serve\AccessDeniedException) $result = new ErrorResult (403, $result);
        if ($result instanceof tsd\serve\NotFoundException) $result = new ErrorResult (404, $result);
        if ($result instanceof \Exception) $result = new ErrorResult (500, $result->getMessage());
        if (!($result instanceof Result)) $result = new DataResult ($result);

        http_response_code($result->getStatusCode());
        $headers = $result->getHeaders();
        foreach ($headers as $h)
        {
            header($h);
        }

        //todo: better
        if ($accept == 'application/json') $this->renderJson($result);
        if ($accept == 'text/xml') $this->renderXml($result);

        if ($result instanceof ViewResult) 
        {
            $this->renderView($result);
        }
    }

    private function renderJson(Result $result)
    {
        ob_clean();        
        echo json_encode($result->getData());
    }

    private function renderXml(Result $result)
    {
        ob_clean();
        echo $result->getData()->asXML();
    }

    protected abstract function renderView (ViewResult $result);
}

/**
 * @Default
 */
class ServeViewEngine extends ViewEngine
{
    const VIEWS = './views';

    function renderView(ViewResult $result)
    {
        $v = new View (ServeViewEngine::VIEWS.'/'.$result->getView());
        $v->render ($result->getData());        
    }
}