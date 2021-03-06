<?php

namespace tsd\serve;

use function PHPSTORM_META\type;

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


    private Router $router;

    private ViewEngine $view_engine;

    private Membership $member;

    /**
     * Creates a new Instance
     * 
     * @param array $config optional alternative Configuration values to use
     */
    function __construct(array $config = null)
    {
        if ($config == null && file_exists(App::CONFIG))
            $config = json_decode(file_get_contents(App::CONFIG), true);
        else
            $config = [];

        $plugins = scandir(App::PLUGINS);

        $factory = new Factory($config, $plugins);

        $this->router = new Router($factory, $plugins);
        $this->member = $factory->create('tsd\serve\Membership', 'member');
        $this->view_engine = $factory->create('tsd\serve\ViewEngine', 'views');
    }

    /**
     * serves a HTTP Request
     */
    static function serve()
    {
        $url = key_exists('REDIRECT_URL', $_SERVER) ?
            $_SERVER['REDIRECT_URL'] :
            urldecode($_SERVER['REQUEST_URI']);

        if (\stripos($url, '/static/') === 0)
            return false;

        ob_start();

        try {
            $app = new App();

            $app->serveRequest(
                $_SERVER['REQUEST_METHOD'],
                $_SERVER['HTTP_HOST'],
                $url,
                [
                    '_GET' => $_GET, '_COOKIE' => $_COOKIE,
                    '_POST' => $_POST, '_FILES' => $_FILES
                ],
                $_SERVER['HTTP_ACCEPT']
            );

            ob_flush();
        } catch (\Exception $e) {
            echo "Error $e->message";
            ob_flush();
        }
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
        try {
            $i = \strpos($path, '?');
            $route = $this->router->getRoute($host, $method, \substr($path, 0, $i > 0 ? $i : \strlen($path)));

            $result = $this->getResult($route, $data);
        } catch (\Exception $e) {
            $result = $e;
        } catch (\Error $e) {
            $result = $e;
        }

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

class Exception extends \Exception
{
    function __construct($m)
    {
        parent::__construct($m);
    }
}

class AccessDeniedException extends \Exception
{
    function __construct()
    {
        parent::__construct("Zugriff verweigert!");
    }
}

class NotFoundException extends \Exception
{
    function __construct()
    {
        parent::__construct("Nicht gefunden!");
    }
}
