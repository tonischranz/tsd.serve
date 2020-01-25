<?php

namespace tsd\serve;

/**
 * @Implementation tsd\serve\InstallMembership
 * @Implementation tsd\serve\OpenIDMembership
 */
interface Membership
{
  function isAnonymous () : bool;
  function isInGroup (string $group):bool;
}

/**
 * @Default
 * @Mode install
 */
class InstallMembership implements Membership
{
  private $password;

  function __construct (array $config)
  {
    if (isset($config['password']))
      $this->password = $config['password'];
  }

  public function isAnonymous () : bool
  {
    return !isset($_SESSION['logged_in']);
  }

  public function isInGroup ($group) : bool
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