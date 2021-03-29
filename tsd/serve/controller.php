<?php

namespace tsd\serve;

/**
 * Controller baseclass
 *
 * @author Toni Schranz
 */
class Controller
{

    protected string $_name;
    protected string $_plugin;

    protected function view($data = null, string $view = null)
    {
        if ($view == null) {
            $backtrace = debug_backtrace();
            $view = $backtrace[1]['function'];

            if (\preg_match('/^show/', $view) == 1) $view = \strtolower(\substr($view, 4));
            else if (\preg_match('/^do/', $view) == 1) $view = \strtolower(\substr($view, 2));
        }

        $ctx = new ViewContext();
        $ctx->menu = [
            ['url' => '#', 'title' => 'Hash', 'active' => true],
            ['url' => '~', 'title' => 'Tilde', 'tags' => ['home', 'userdir', 'private'], 'emblems' => ['~']],
            ['url' => 'info', 'title' => '⚒Info', 'emblems' => ['ℹ']],
             ['url' => '#', 'title' => 'Hash', 'active' => true],
               ['url' => '#', 'title' => 'Hash', 'active' => true],
                ['url' => '#', 'title' => 'Hash', 'active' => true],
                 ['url' => '#', 'title' => 'Hash', 'active' => true],
                 ['url' => '#', 'title' => '# Hash', 'active' => true],
                 ['url' => '#', 'title' => '#Hash', 'active' => true, 'menu'=>[
                    ['url' => '#', 'title' => 'Hash', 'active' => true],
                    ['url' => '#', 'title' => '# Hash', 'active' => true],
                    ['url' => '#', 'title' => '#Hash', 'active' => true],
                 ]],
                 ['url' => '#', 'title' => 'Hash', 'active' => true],
        ];

        // _plugin?App::PLUGINS : ....

        return new ViewResult($this->_name . DIRECTORY_SEPARATOR . $view, $data, $this->_plugin, $ctx);

        //return new ViewResult($this->basePath . '/views/' . $this->name . "/$view", $data, $ctx);
    }

    protected function message(string $message, ?string $url = null)
    {
        $ctx = new ViewContext();
        return new MessageResult($ctx, "info", $message, $url);
    }

    protected function redirect($url)
    {
        return new RedirectResult($url);
    }

    static function error($result, $code)
    {
        $ctx = new ViewContext();
        
        return new ErrorResult($ctx, $result, $code);
    }

    static function data($result)
    {
        return new DataResult($result);
    }
}

interface Result
{
    function data();
    function getStatusCode();
    function getHeaders();
}

class ResultBase implements Result
{
    private $statuscode;
    private $data;
    private $headers;

    function __construct($data, $statuscode, $headers = [])
    {
        $this->statuscode = $statuscode;
        $this->data = $data;
        $this->headers = $headers;
    }

    function data()
    {
        return $this->data;
    }

    function getStatusCode()
    {
        return $this->statuscode;
    }

    function getHeaders()
    {
        return $this->headers;
    }
}

class RedirectResult extends ResultBase
{
    function __construct($location)
    {
        parent::__construct($location, 302);
    }
}

class ViewResult extends ResultBase implements IViewResult
{
    private string $_view;
    private ViewContext $_ctx;
    private string $_plugin;

    function __construct(string $view, $data, string $plugin = '', ViewContext $ctx = null, $statuscode = 200)
    {
        parent::__construct($data, $statuscode);
        $this->_ctx = $ctx ?? new ViewContext();
        $this->_view = $view;
        $this->_plugin = $plugin;
    }

    function view() : string
    {
        return $this->_view;
    }

    function plugin() : string
    {
        return $this->_plugin;
    }

    function ctx() : ViewContext
    {
        return $this->_ctx;
    }
}

class MessageResult extends ViewResult
{
    function __construct(ViewContext $ctx, $type, $message, $code = 200, $url = null)
    {
        parent::__construct($type, ["message" => $message, "url" => $url], '', $ctx, $code);
    }
}

class ErrorResult extends MessageResult
{
    function __construct(ViewContext $ctx, $message, int $code = 500)
    {
        parent::__construct($ctx, 'error', $message, $code);
    }
}

class SucessResult extends MessageResult
{
    function __construct(ViewContext $ctx, $message, $url = null)
    {
        parent::__construct($ctx, 'sucess', $message, 200, $url);
    }
}

class DataResult extends ResultBase
{
    function __construct($data)
    {
        parent::__construct($data, 200);
    }
}
