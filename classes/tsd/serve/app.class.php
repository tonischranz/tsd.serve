<?php

namespace tsd\serve;


class App
{
    const CONFIG = '.config.json';
    private static $instance;

    private $factory;

    function __construct(array $config = null)
    {
        if ($config == null && file_exists(App::CONFIG))
            $config = json_decode (file_get_contents (App::CONFIG), true);
        else
            $config = [];

        //$plugins = //list plugin directory names
		$plugins = [];
        
        $this->factory = new Factory($config, $plugins);
    }
    
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
    
    static function getController(string $path)
    {
        $instance = App::getInstance();
        $parts = explode('/', $path);

        $name = count($parts) > 1 ? $parts[1] : 'default';
        $c = $instance->loadController($name);

        return $c ? $c : $instance->loadController('default');
    }

    static function getPluginController(string $name, string $path, string $namespace)
    {
        $instance = App::getInstance();
        return $instance->createController($name, $path, $namespace);
    }

    private function loadController(string $name)
    {
        if ($name && file_exists("./plugins/$name/")) {
            try {
                return new PluginController($name);
            } catch (Exception $e) {
                echo $e->getTraceAsString();
                throw new Exception("Loading Plugin $name failed");
            }
        }

        return $this->createController($name);
    }


    private function createController(string $name, string $path = 'controller', string $namespace = '')
    {
        $fileName = "$path/$name.controller.php";
        $ctrlName = ($namespace ? '\\' : '') . $namespace . '\\' . $name . 'Controller';

        if (!file_exists($fileName)) {
            return false;
        }

        require_once $fileName;

        $c = $this->factory->create($ctrlName, $name);
        $c->name = $name;

        return $c;
    }
}
