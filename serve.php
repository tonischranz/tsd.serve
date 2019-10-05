<?php

use tsd\serve\App;

/* ⚒ tsd.serve
 * Main PHP File
 */

// Having issues? wanna check your php env?
//phpinfo();

// wanna see errors?
ini_set('display_errors','On');

set_include_path ('./classes');
spl_autoload_extensions ('.class.php');
spl_autoload_register ();

App::serve();