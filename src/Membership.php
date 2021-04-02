<?php

namespace tsd\serve;

interface Membership
{
  function isAnonymous(): bool;
  function isInGroup(string $group): bool;
  public function login(string $username, string $password) : bool;
}

/**
 * @Default
 */
class DefaulMembership implements Membership
{
  private Session $_session;

  public function isAnonymous(): bool
  {
    return !$this->_session->get('logged_in');
  }

  public function isInGroup($group): bool
  {
    if ($this->isAnonymous()) return false;
    if ($group == 'developer') return true;
    return false;
  }

  public function login(string $username, string $password) : bool
  {
    if ($username == 'install' && $password == $this->password)
    {
      $this->_session->set('logged_in', true);
      return true;
    }
    return false;
  }

  public function logout()
  {
    $this->_session->reset();
  }
}

class Session
{
  function get(string $key)
  {
    if (!session_id()) session_start();
    return @$_SESSION[$key];
  }

  function set(string $key, $value)
  {
    if (!session_id()) session_start();
    $_SESSION[$key] = $value;
  }

  function reset()
  {
    session_destroy();
  }
}
