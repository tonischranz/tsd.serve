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
    protected Membership $_member;

    protected function view($data = null, string $view = null)
    {
        if ($view == null) {
            $backtrace = debug_backtrace();
            $view = $backtrace[1]['function'];

            if (\preg_match('/^show/', $view) == 1) $view = \strtolower(\substr($view, 4));
            else if (\preg_match('/^do/', $view) == 1) $view = \strtolower(\substr($view, 2));
        }

        return new ViewResult($this->_name . DIRECTORY_SEPARATOR . $view, $data, $this->_plugin);
    }

    public function prepare()
    {}

    static function message(string $message, ?string $url = null)
    {
        return new MessageResult('info', $message, url: $url);
    }

    static function success(string $message, ?string $url = null)
    {
        return new SuccessResult($message, $url);
    }

    static function redirect($url)
    {
        return new RedirectResult($url);
    }

    static function error($result, $code)
    {
        return new ErrorResult($result, $code);
    }

    static function data($result)
    {
        return new DataResult($result);
    }

    function name(): string
    {
        return $this->_name;
    }
    function plugin(): string
    {
        return $this->_plugin;
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
        parent::__construct($location, 302, ["Location: $location"]);
    }
}

class ViewResult extends ResultBase implements IViewResult
{
    private string $_view;
    private string $_plugin;

    function __construct(string $view, $data, string $plugin = '', $statuscode = 200)
    {
        parent::__construct($data, $statuscode);
        $this->_view = $view;
        $this->_plugin = $plugin;
    }

    function view(): string
    {
        return $this->_view;
    }

    function plugin(): string
    {
        return $this->_plugin;
    }
}

class MessageResult extends ViewResult
{
    function __construct($type, $message, $code = 200, $url = null)
    {
        parent::__construct($type, ["message" => $message, "url" => $url], '', $code);
    }
}

class ErrorResult extends MessageResult
{
    function __construct($message, int $code = 500)
    {
        parent::__construct('error', $message, $code);
    }
}

class SuccessResult extends MessageResult
{
    function __construct($message, $url = null)
    {
        parent::__construct('success', $message, 200, $url);
    }
}

class DataResult extends ResultBase
{
    function __construct($data)
    {
        parent::__construct($data, 200);
    }
}

class FileResult extends ResultBase
{
    function __construct(string $url, string $mimetype = '')
    {
        if ($mimetype) parent::__construct($url, 200, ["Content-Type: $mimetype"]);
        else parent::__construct($url, 200);
    }
}

class Base64FileResult extends FileResult
{
    function __construct(string $data, string $mimetype = '')
    {
        parent::__construct('data:text/plain;base64,' . $data, $mimetype);
    }
}

class TextResult extends ResultBase
{
    function __construct(string $data, string $mimetype = '')
    {
        if ($mimetype) parent::__construct($data, 200, ["Content-Type: $mimetype"]);
        else parent::__construct($data, 200);
    }
}