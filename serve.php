<?php

use tsd\serve\App;

/* ⚒ tsd.serve
 * Main PHP File
 */

// Autoload with default settings
spl_autoload_register();

// Autoload from Composer
# include 'vendor/autoload.php';



// Having issues? wanna check your php env?
# phpinfo();

// wanna see errors?
ini_set('display_errors', 'On');

// type autoloading issues?
# function trace_autoload($name) {var_dump ($name);}
# spl_autoload_register('trace_autoload', true, true);

# function fail_autoload($name){echo "Not Found: $name <br />";}
# spl_autoload_register('fail_autoload');

if (PHP_SAPI == 'cli') {
    if ($argc == 1) {
        shell_exec(PHP_BINARY . " -S localhost:8000 serve.php");
    } else {
        echo "Usage: php serve.php [<command>]\n";
        echo "\n";
    }
} else {

    $url = $_SERVER['REQUEST_URI'];

    if (file_exists('.' . urldecode($url)) && $url != '/') return false;

    App::serve();
}
