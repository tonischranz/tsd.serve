<?php

use tsd\serve\Controller;

class DefaultController extends Controller
{
  

  function showlogin ()
  {
    $this->render('login', ['username'=>'', 'password'=>'', 'error'=>['username'=>'', 'password'=>'']]);
  }

  function showIndex ()
  {    
    $this->render ('index', []);
  }

  function doLogin ($username, $password, $redirect_url=false)
  {
    $this->member->login($username, $password);
    
  }
}
