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
  }

  public function isAnonymous ()
  {
    return true;
  }

  public function isInGroup ($group)
  {
    return false;
  }

}
