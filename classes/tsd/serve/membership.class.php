<?php

namespace tsd\serve;

abstract class Membership
{

  
  //yasdfasdf
  abstract function isAnonymous ();

  abstract function isInGroup ($group);

  static function create (string $mode, array $config)
  {

    if ($mode == 'install')
    {
      return new InstallMembership ($config['password']);
    }
  }

}
