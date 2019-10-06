<?php

use tsd\serve\App;

/* ⚒ tsd.serve
 * Main PHP File
 */

spl_autoload_register ();

// Having issues? wanna check your php env?
//phpinfo();

// wanna see errors?
ini_set('display_errors','On');

//function trace_autoload($name) {var_dump ($name);}
//spl_autoload_register('trace_autoload', true, true);

function fail_autoload($name){echo "Not Found: $name <br />";}
spl_autoload_register('fail_autoload');


App::serve();