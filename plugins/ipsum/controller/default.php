<?php
namespace tsd\serve\ipsum;

use tsd\serve\App;
use tsd\serve\Controller;
use tsd\serve\ViewContext;

class defaultController extends Controller
{
  protected ViewContext $ctx;

  function showIndex ()
  {
    return $this->view ();
  }

  function showFoo()
  {
    return $this->view (['text' => 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.']);      
  }

  function showBar(int $id)
  {
    var_dump($id);
    
    return $this->view(view:'index');
  }

  function showQwer(string $name)
  {
    var_dump($name);
    
    return $this->view(view:'index');
  }

  function showAsdf(string $name, string $foo)
  {
    var_dump($name);
    var_dump($foo);

    return $this->view(view:'index');
  }

  function showYxc(string $q, string $foo)
  {
    var_dump($q);
    var_dump($foo);

    return $this->view(view:'index');
  }
}