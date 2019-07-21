<?php

namespace tsd\serve;


class App
{
    const CONFIG = '.config.json';
    const PLUGINS = 'plugins';
 
    //private $factory;
    private $router;
    private $view_engine;

    function __construct(array $config = null)
    {
        if ($config == null && file_exists(App::CONFIG))
            $config = json_decode (file_get_contents (App::CONFIG), true);
        else
            $config = [];

        $plugins = scandir(App::PLUGINS);

        var_dump($plugins, $config);

        $factory = new Factory($config, preg_grep('/^\.\w/',$plugins));        
        $this->router = new Router($factory, preg_grep('/^[^\._]\w/', $plugins));
        $this->view_engine = $factory->create('tsd\serve\ViewEngine', 'views');
    }

    static function serve()
    {
        $app = new App();
        $app->serveRequest(
            $_SERVER['REQUEST_METHOD'], 
            $_SERVER['HTTP_HOST'],
            key_exists('REDIRECT_URL', $_SERVER) ? 
                $_SERVER['REDIRECT_URL']:$_SERVER['REQUEST_URI'], 
            $_GET, $_POST, $_FILES, $_COOKIE, $_SERVER['HTTP_ACCEPT'] );
    }

    protected function serveRequest($method, $host, $path, $get_data, $post_data, $files_data, $cookie_data, $accept)
    {
        $result = $this->router->getData($method, $host, $path, $get_data, $post_data, $files_data, $cookie_data);
        
        var_dump($result);
        
        $this->view_engine->render($result, $accept);
    }
    
    /*
    private static function getInstance()
    {
        if (!App::$instance) App::$instance = new App();
        return App::$instance;
    }

    static function create($type, $name)
    {
        $instance = App::getInstance();
        return $instance->factory->create($type, $name);
    }
    */
    
}



class ViewEngine
{
    function render($data)
    {
        
    }
}