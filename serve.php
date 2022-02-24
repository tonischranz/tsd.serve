<?php

use tsd\serve\App;

/* ⚒ tsd.serve
 * Main PHP File
 */

// wanna see errors?
ini_set('display_errors', 'On');

if (PHP_SAPI == 'cli') {
    if ($argc == 1) {
        shell_exec("\"" . PHP_BINARY . "\" -S localhost:8000 serve.php");
    } else {
        echo "Usage: php serve.php [<command>]\n";
        echo "\n";
    }
} else {

    spl_autoload_register(function($name){
        $parts = explode('\\', $name);
        if (count($parts) == 3 && $parts[0] == 'tsd' && $parts[1] == 'serve') include 'src' . DIRECTORY_SEPARATOR . $parts[2] . '.php';
    });

    $url = $_SERVER['REQUEST_URI'];

    if (file_exists('.' . urldecode($url)) && $url != '/') return false;

    App::serve();
}
