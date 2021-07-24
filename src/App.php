<?php

namespace tsd\serve;

use DateTime;
use DateTimeZone;

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

    public static array $plugins = [];

    private Router $router;

    private ViewEngine $view_engine;

    private Membership $member;

    private Time $time;

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

        $stats = '';
        $pdirs = glob('.' . App::PLUGINS . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        foreach ($pdirs as $pd) $stats .= stat($pd)['mtime'];
        $pfiles = glob('.' . App::PLUGINS . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'plugin.json');
        foreach ($pfiles as $pf) $stats .= stat($pf)['mtime'];
        $md5 = md5($stats);

        $cache_file = ".cached_plugins.$md5.php";
        if (file_exists($cache_file)) include $cache_file;
        else {
            foreach ($pdirs as $pd) App::$plugins[basename($pd)] = true;
            foreach ($pfiles as $pf) {
                $i = json_decode(file_get_contents($pf), true);
                $n = basename(dirname($pf));
                App::$plugins[$n] = [];
                if (@$i['namespace']) App::$plugins[$n]['namespace'] = $i['namespace'];
                if (@$i['forceLayout']) App::$plugins[$n]['forceLayout'] = $i['forceLayout'];
                if (@$i['usePrefix']) App::$plugins[$n]['usePrefix'] = $i['usePrefix'];
            }

            array_map('unlink', glob(".cached_plugins.*.php"));
            file_put_contents($cache_file, ["<?php\n", "use tsd\serve\App;\n", 'App::$plugins = [']);

            foreach (App::$plugins as $k => $v) {
                file_put_contents($cache_file, "'$k'=>", FILE_APPEND);
                if (is_bool($v)) file_put_contents($cache_file, "$v,", FILE_APPEND);
                else {
                    file_put_contents($cache_file, '[', FILE_APPEND);
                    if (@$v['namespace']) file_put_contents($cache_file, "'namespace'=>'" . $v['namespace'] . "',", FILE_APPEND);
                    if (@$v['forceLayout']) file_put_contents($cache_file, "'forceLayout'=>" . $v['forceLayout'] . ',', FILE_APPEND);
                    if (@$v['usePrefix']) file_put_contents($cache_file, "'usePrefix'=>" . $v['usePrefix'] . ',', FILE_APPEND);
                    file_put_contents($cache_file, '],', FILE_APPEND);
                }
            }

            file_put_contents($cache_file, '];', FILE_APPEND);
        }


        $factory = new Factory($config);

        $this->time = $factory->createSingleton('tsd\serve\Time', 'time');
        $this->member = $factory->createSingleton('tsd\serve\Membership', 'member');

        $this->router = $factory->create('tsd\serve\Router', 'router');        
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

        ob_start(null, 0, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_REMOVABLE | PHP_OUTPUT_HANDLER_FLUSHABLE);

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
        } catch (\Exception $e) {
            ob_end_clean();
            echo "Error: $e";            
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
        } catch (AccessDeniedException $e)
        {
            $result = $this->member->isAnonymous() ? Controller::redirect('/_login?returnUrl=' . urlencode($path)) : $e;
        } catch (\Exception $e) {
            $result = $e;
        } catch (\Error $e) {
            $result = $e;
        }

        $this->view_engine->render($result, $route->ctx(), $accept);
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

        $route->ctx()->groups = $this->member->getGroups();
        $route->fill($data);

        return $route->follow();
    }
}

class ViewContext
{
    public array $menu;
    public array $member;
    public array $groups;
    public string $layoutPlugin = '';
    public string $pluginRoot = '';
    public string $debug;
    public array $data;
}

class Time
{
    private DateTimeZone $tz;

    function __construct(string $timezone = "Europe/Zurich", private string $locale = "de_CH.UTF-8")
    {
        $this->tz = new DateTimeZone($timezone);
        date_default_timezone_set($timezone);
        
        setlocale(LC_TIME, $locale);
    }

    function offset(int $d) : int
    {
        return $this->tz->getOffset(new DateTime("@$d"));
    }

    function lc() : string
    {
        return $this->locale;
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
        parent::__construct("access denied!");
    }
}

class NotFoundException extends \Exception
{
    function __construct()
    {
        parent::__construct("not found!");
    }
}
