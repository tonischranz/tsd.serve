<?php

namespace tsd\serve;

interface Membership
{
  function isAnonymous(): bool;
  function isInGroup(string $group): bool;
  public function login(string $username, string $password) : bool;
  public function logout();
}

/**
 * @Default
 */
class DefaulMembership implements Membership
{
  private Session $_session;
  private array $users;

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
    if (array_key_exists($username, $this->users))
    {
      if (array_key_exists('password', $this->users[$username]))
        return password_verify($password, $this->users[$username]['password']);            
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
