<?php

namespace tsd\serve;

/**
 * @Implementation \tsd\serve\InstallMembership
 * @Implementation \tsd\serve\OpenIDMembership
 */
abstract class Membership
{
  abstract function isAnonymous ();
  abstract function isInGroup ($group);
}
