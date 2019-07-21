<?php

use tsd\serve\App;

/* V0.1
 * Main PHP File
 */
//phpinfo();
//exit;
ob_start ();

ini_set('display_errors','On');

set_include_path ('./classes');
spl_autoload_extensions ('.class.php');
spl_autoload_register ();

App::serve();

//session_start();
/*
if (key_exists('REDIRECT_URL', $_SERVER))
{
	$path = $_SERVER['REDIRECT_URL'];
}
else if(is_string ($_SERVER['REQUEST_URI']))
{
	$path = $_SERVER['REQUEST_URI'];
}
else
{
	header ("HTTP/1.0 404 Not Found");
	exit;
}


if (!is_string ($_SERVER['REQUEST_METHOD']))
{
 	header ("HTTP/1.0 400 Bad Request");
 	exit;
}

$c = tsd\serve\App::getController ($path);

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

*/