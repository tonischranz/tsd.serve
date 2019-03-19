<?php

namespace tsd\serve;

class InstallMembership extends Membership
{

  private $password;

  protected function __construct ($password)
  {
    $this->password = $password;
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
