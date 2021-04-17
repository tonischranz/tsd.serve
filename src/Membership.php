<?php

namespace tsd\serve;

interface Membership
{
  function isAnonymous(): bool;
  function isInGroup(string $group): bool;
  function getGroups(): array;
  function login(string $username, string $password) : bool;
  function logout();
  function getName() : string;
  function getFullName() : string;
  function getEMail() : string;
  function setFullName(string $value);
  function setEMail(string $value);
  function setPassword(string $value);
  function save();
}

/**
 * @Default
 */
class DefaulMembership implements Membership
{
  private Session $_session;
  private array $users;

  public function getName(): string
  {
    if ($this->isAnonymous()) return '';

    $username = $this->_session->get('logged_in');
    return $username ?? '';
  }

  public function getGroups():array
  {
    if ($this->isAnonymous()) return [];

    $username = $this->getName();
    
    if (array_key_exists('groups', $this->users[$username])) 
      return $this->users[$username]['groups'];
    return [];
  }

  public function getFullName(): string
  {
    if ($this->isAnonymous()) return '';

    $username = $this->getName();
    
    if (array_key_exists('fullname', $this->users[$username])) 
      return $this->users[$username]['fullname'];
    return '';
  }

  public function getEMail(): string
  {
    if ($this->isAnonymous()) return '';
    
    $username = $this->getName();
    
    if (array_key_exists('email', $this->users[$username])) 
      return $this->users[$username]['email'];
    return '';
  }

  public function setFullName(string $value)
  {
    if ($this->isAnonymous()) return;

    $username = $this->getName();
    
    $this->users[$username]['fullname'] = $value;    
  }

  public function setEMail(string $value)
  {
    if ($this->isAnonymous()) return;

    $username = $this->getName();
    
    $this->users[$username]['email'] = $value;
  }

  public function setPassword(string $value)
  {
    if ($this->isAnonymous()) return;

    $username = $this->getName();
    
    $this->users[$username]['password'] = password_hash($value, PASSWORD_DEFAULT);
  }

  public function save()
  {
    if ($this->isAnonymous()) return;

    $username = $this->getName();

    //todo: lock
    $cfg = json_decode(file_get_contents(App::CONFIG), true);
    $cfg['member']['users'][$username] = $this->users[$username];
    file_put_contents(App::CONFIG, json_encode($cfg, JSON_PRETTY_PRINT));
  }  

  public function isAnonymous(): bool
  {
    return !$this->_session->get('logged_in');
  }

  public function isInGroup($group): bool
  {
    if ($this->isAnonymous()) return false;

    $username = $this->getName();
    if (array_key_exists('groups', $this->users[$username]))
      if (in_array($group, $this->users[$username]['groups'])) return true;  

    return false;
  }

  public function login(string $username, string $password) : bool
  {
    if (array_key_exists($username, $this->users))
    {
      if (array_key_exists('password', $this->users[$username]))
      {
        $this->_session->set('logged_in', $username);
        return password_verify($password, $this->users[$username]['password']);
      }
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
