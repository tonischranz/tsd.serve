<?php

namespace tsd\serve;

/**
 * The Application
 * 
 * @author Toni Schranz
 */
class App
{
    /**
     * Config file name
     */
    const CONFIG = '.config.json';

    /**
     * Plugins directory name
     */
    const PLUGINS = 'plugins';
 
    /** 
     * the Router
     * @var Router $router
     */
    private $router;
    
    /**
     * the View Engine
     * @var ViewEngine $view_engine 
     */
    private $view_engine;

    /** 
     * the Membership Provider
     * @var Membership $member 
     */
    private $member;

    /**
     * Creates a new Instance
     * 
     * @param array $config optional alternative Configuration values to use
     */
    function __construct(array $config = null)
    {
        if ($config == null && file_exists(App::CONFIG))
            $config = json_decode (file_get_contents (App::CONFIG), true);
        else
            $config = [];

        $plugins = scandir(App::PLUGINS);

        echo "hallo";
        $factory = new Factory($config, preg_grep('/^\.\w/',$plugins));
        
        $this->router = new Router($factory, $factory->create('tsd\serve\RoutingStrategy', 'routing'), preg_grep('/^[^\._]\w/', $plugins));
        $this->member = $factory->create('tsd\serve\Membership', 'member');
        $this->view_engine = $factory->create('tsd\serve\ViewEngine', 'views');
    }

    /**
     * serves a HTTP Request
     */
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

    /**
     * gets the Route, Evaluates the Result and renders it with the View Engine
     * 
     * @param string $method the HTTP Method
     * @param string $host the Hostname
     * @param string $path the Path of the requested Resource
     * @param array $data data sent along with the Request
     * 
     * @internal
     */
    protected function serveRequest(string $method, string $host, string $path, array $data, $accept)
    {
        $route = $this->router->route($host, $method, $path);
        
        try { $result = $this->getResult($route, $data); }
        catch (Exception $e) { $result = $e; }

        echo $accept;
        
        $this->view_engine->render($result, $accept);
    }

    /**
     * evaluates the Result for a given Route
     * 
     * @param Route $route the Route
     * @param array $data data
     * 
     * @internal
     */
    private function getResult(Route $route, array $data)
    {
        if (!$route->checkPermission($this->member))
            throw new AccessDeniedException($route);
        
        $route->fill($data);

        return $route->follow();
    }
}