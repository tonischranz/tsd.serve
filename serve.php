<?php

/*
 * Main PHP File
 */


set_include_path ('./classes');
spl_autoload_extensions ('.class.php');
spl_autoload_register ();
ob_start ();

if (!is_string ($_SERVER['REDIRECT_URL']))
{
  header ("HTTP/1.0 404 Not Found");
  exit;
}
else if (!is_string ($_SERVER['REQUEST_METHOD']))
{
  header ("HTTP/1.0 400 Bad Request");
  exit;
}
else
{
  $path = $_SERVER['REDIRECT_URL'];

  $c = tsd\serve\Controller::getController ($path);

  if ($_SERVER['REQUEST_METHOD'] == 'POST')
  {
    $c->servePOST ($path, $_POST);
  }
  else if ($_SERVER['REQUEST_METHOD'] == 'GET')
  {
    $c->serveGET ($path, $_GET);
  }
  else
  {
    header ("HTTP/1.0 501 Not Implemented");
    exit;
  }
}
