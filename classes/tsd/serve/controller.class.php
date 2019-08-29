<?php

namespace tsd\serve;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/*    setlocale(LC_ALL, "de_CH.UTF-8");
  session_start(); */

/**
 * Description of Controller
 *
 * @author tonti
 */
class Controller
{

  public $name;
  public $basePath;
  
  function __construct ()
  {

  }

  protected function view ($data = null, string $view = null)
  {
    if ($view == null)
    {
      $backtrace = debug_backtrace();
      //var_dump($backtrace);
      $view = $backtrace[1]['function'];
      echo "<br>";
      echo "<br>";
      echo "$view";
      echo "<br>";
      echo "<br>";
      $view = str_replace('showI', 'i', $view);
    }

    return new ViewResult($this->basePath.'/'.$this->name."/$view", $data);
  }

  protected function redirect ($url)
  {
    return new RedirectResult($url);
  }

  /*protected function setBasePath ($path)
  {
    $this->basePath = $path;
  }

  protected function getBasePath ()
  {
    return $this->basePath;
  }*/

}


interface Result
{
    function getData();
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

    function getData()
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

class RedirectResult
{
    function __construct($location)
    {
        parent::__construct($location, 302);
    }
}

class ViewResult extends ResultBase
{   
    private $view;

    function __construct(string $view, $data, $statuscode = 200)
    {
      parent::__construct($data,$statuscode);
      $this->view = $view;
    }

    function getView()
    {
      return $this->view;
    }
}

class MessageResult extends ViewResult
{
    function __construct($code=200, $type, $massage, $url=null) 
    {
        parent::__construct($type, ["message"=>$massage, "url"=>$url], $code);
    }
}

class ErrorResult extends MessageResult
{
    function __construct(int $code=500,$message)
    {
        parent::__construct($code, 'error', $message);
    }
}

class SucessResult extends MessageResult
{
    function __construct($message, $url = null)
    {
        parent::__construct('sucess', $message, $url);
    }
}

class DataResult extends ViewResult
{
    function __construct($data)
    {
        parent::__construct('data', $data, 200);
    }
}
