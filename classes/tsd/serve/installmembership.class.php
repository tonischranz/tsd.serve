<?php

namespace tsd\serve;


/**
 * @Mode install
 */
class InstallMembership extends Membership
{
  private $password;

  function __construct (array $config)
  {
    $this->password = $config['password'];
    //session_start();
    //var_dump($config);
  }

  public function isAnonymous ()
  {
    return !isset($_SESSION['logged_in']);
  }

  public function isInGroup ($group)
  {
    if ($this->isAnonymous()) return false;
    if ($group == 'developer') return true;
    return false;
  }

  public function login (string $username, string $password)
  {
    if ($username == 'install' && $password == $this->password)
      $_SESSION['logged_in']=true;
  }

  public function logout ()
  {
    session_destroy();
  }
}
