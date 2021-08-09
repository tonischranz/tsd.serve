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
    //public $basePath;

    /*  function __construct ()
  {

  }*/

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

        return new ViewResult($this->basePath . '/views/' . $this->name . "/$view", $data, $ctx);
    }

    protected function message(string $message, ?string $url = null)
    {
        $ctx = new ViewContext();
        return new MessageResult($ctx, "views/info", $message, $url);
    }

    protected function redirect($url)
    {
        return new RedirectResult($url);
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
    private $_view;
    private $_ctx;

    function __construct(string $view, $data, ViewContext $ctx = null, $statuscode = 200)
    {
        parent::__construct($data, $statuscode);
        $this->_ctx = $ctx ?? new ViewContext();
        $this->_view = $view;
    }

    function view()
    {
        return $this->_view;
    }

    function ctx()
    {
        return $this->_ctx;
    }
}

class MessageResult extends ViewResult
{
    function __construct(ViewContext $ctx, $type, $message, $code = 200, $url = null)
    {
        parent::__construct($type, ["message" => $message, "url" => $url], $ctx, $code);
    }
}

class ErrorResult extends MessageResult
{
    function __construct(ViewContext $ctx, $message, int $code = 500)
    {
        parent::__construct($ctx, 'views/error', $message, $code);
    }
}

class SucessResult extends MessageResult
{
    function __construct(ViewContext $ctx, $message, $url = null)
    {
        parent::__construct($ctx, 'views/sucess', $message, 200, $url);
    }
}

class DataResult extends ResultBase
{
    function __construct($data)
    {
        parent::__construct($data, 200);
    }
}
