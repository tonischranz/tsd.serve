<?php

use tsd\serve\App;
use tsd\serve\Controller;
use tsd\serve\ViewContext;

class defaultController extends Controller
{
  protected ViewContext $ctx;

  function showIndex ()
  {
    return $this->view (['text' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.']);
  }

  function showFoo ()
  {
    var_dump($_SERVER);
    var_dump($this);
    var_dump(App::$plugins);
    var_dump($this->ctx);
    $this->ctx->data['bla'] = "wtf";

      return $this->view (['text' => 'Foo']);
  }

  function doRegister (string $code, string $geb)
  {
    var_dump($code, $geb);
  }

  function doSomething(MyData $data)
  {

  }
}

class MyData
{
  public string $myStr;
  public int $myNr;
  public string $moreText;

}