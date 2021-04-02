<?php

namespace tsd\serve\config;

use tsd\serve\Controller;
use tsd\serve\config\Config;

class defaultController extends Controller
{

  private Config $cfg;

  function showIndex ()
  {
    $c = $this->cfg;

    $db = $c->getDBConfig ();
    $lang = $c->getLanguages ();

    $this->view (['db' => $db, 'lang' => $lang]);
  }

  /**
   * 
   * @param type $host
   * @param type $username
   * @param type $password
   * @param type $db
   */
  function doSetDB ($host, $username, $password, $db)
  {
    $c = $this->cfg;

    $c->setDBConfig ($host, $username, $password, $db);
    $this->message ('[success_db]');
  }

  function doAddLanguage ($lang)
  {
    $c = $this->cfg;
    $l = $c->getLanguages ();

    $l[] = $lang;
    $c->setLanguages ($l);

    $this->message ("");
  }

  function doRemoveLanguage ($lang)
  {
    $c = $this->cfg;
    $l = $c->getLanguages ();

    $c->setLanguages (array_diff ($l, [$lang]));
  }

  function doMoveLanguageUp ($lang)
  {
    $c = $this->cfg;
    $l = $c->getLanguages ();

    $x = array_search ($lang, $l);

    $p = $l[$x - 1];
    $l[$x - 1] = $l[$x];
    $l[$x] = $p;
    $c->setLanguages ($l);

    $this->message ("");
  }

  function doMoveLanguageDown ($lang)
  {
    $c = $this->cfg;
    $l = $c->getLanguages ();

    $x = array_search ($lang, $l);

    if (!$x)
      $this->error ("", 200);
    if ($x > (count ($l) - 2))
      $this->error ("", 500);

    $p = $l[$x + 1];
    $l[$x + 1] = $l[$x];
    $l[$x] = $p;
    $c->setLanguages ($l);

    $this->message ("");
  }

}
