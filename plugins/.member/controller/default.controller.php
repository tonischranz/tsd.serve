<?php

use tsd\serve\Controller;
use tsd\serve\Membership;

class DefaultController extends Controller
{
  private $member;

  function __construct(Membership $member)
  {
    $this->member = $member;
  }

  function showlogin ()
  {
    $this->render('login', ['username'=>'', 'password'=>'', 'error'=>['username'=>'', 'password'=>'']]);
  }

  /**
   * @SecurityUser
   */
  function showLogout ()
  {
    $this->render('logout', []);
  }

  /**
   * @SecurityUser
   */
  function showIndex ()
  {    
    $this->render ('index', []);
  }

  function doLogin ($username, $password, $redirect_url=false)
  {
    $this->member->login($username, $password);    
  }

  /**
   * @SecurityUser
   */
  function doLogout ()
  {
    $this->member->logout();
  }
}
