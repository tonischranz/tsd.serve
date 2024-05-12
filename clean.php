<?php

////¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨|
///  tsd.serve ⚒ clean.php                                                |
// ♫ Toni Schranz                                                         |
// -----------------------------------------------------------------------|
// This file helps you to setup your new application based on the         |
// [tsd.serve] framework. It also acts as router-script/FallbackResource. |
// If called via CLI it starts a development webserver and browser.      /
// _____________________________________________________________________/

namespace tsd\serve;

////¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨|
/// ⚒ paths                                                              /
//______________________________________________________________________/

const SERVE_HOST = 'github.com';
const SERVE_BASE = 'tonischranz';
const SERVE_REPO = 'tsd.serve';
const SERVE_BRANCH = 'next';

const CONFIG_FILE = '.htconfig.json';
const EXTENSIONS_SERVE = ['dom', 'session'];

$serve_file = '.' . SERVE_REPO . '.php';
$no_cfg = !file_exists(CONFIG_FILE);
$ext = get_loaded_extensions();

$filename = basename(__FILE__);
$dirname = getenv('__DN__') ? getenv('__DN__') : basename(__DIR__);
$username = getenv('__UN__') ? getenv('__UN__') : get_current_user();

////¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨|
/// CLI ♫ launch dev webserver and browser                               /
//______________________________________________________________________/

if (PHP_SAPI == 'cli') {

    function launchBrowser (string $url)
    {
        echo "Launching browser on $url\n";
        if(!str_starts_with($url, 'http')) 
            $url = "https://$url";
        
        if (PHP_OS == 'WINNT') {
            echo "Launching browser on Windows with start\n";
            shell_exec("start '$url'");
        }
        else if (PHP_OS == 'Linux') {
            echo "Launching browser on Linux with xdg-open\n";
            shell_exec("xdg-open '$url'");
        }            
        else if (PHP_OS == 'FreeBSD') {
            echo "Launching browser on FreeBSD with xdg-open\n";
            shell_exec("xdg-open '$url'");
        }
        else
            echo "Unknown PHP_OS " . PHP_OS . "\n";
            
         //ToDo: macOS
    }


    function launchserver (string $hostname='localhost', int $port=8000) : callable
    {
        echo "Launching server on $hostname:$port\n";
        global $dirname;
        global $filename;
        global $username;

        $docker = shell_exec('which docker');
        $dir = __DIR__;
        
        if ($docker) {            
            $df = file_exists('Dockerfile');
            $in = $df ? "debug-$dirname":'php:8.3-apache';
            $bo = false;

            echo "Docker CLI found\n";

            if($df) {
                echo "Building docker image debug-$dirname\n";
                $bo = shell_exec("docker build -t $in . && echo built");
            }

            if(!$df || $bo)
            {
                echo "Running docker image $in\n";
                
                shell_exec("chmod 777 .");
                $rid = strtok(shell_exec("docker run -d -v $dir:/var/www/html:z -e __DN__=\"$dirname\"  -e __UN__=\"$username\" -e XDEBUG_CONFIG=\"client_host=`hostname -I | cut -d \" \" -f 1`\" -p $port:80 $in"), "\n");
                                
                if ($rid)
                {
                    echo "Container $rid is running.\n Go to http://$hostname:$port\n";
                    echo "\nPress ENTER to shut down\n\n";
                    
                    return fn() => shell_exec("docker stop $rid");
                
                    exit(0);
                }
            }
            else
                echo "Docker build failed\n";
        }
        
        echo "Launching dev webserver\n";

        $h = popen(PHP_BINARY . " -S $hostname:$port -t $dir -dextension=zip $filename\n", 'r');

        echo "\nPress CTRL+C to shut down\n\n";
        
        return fn() => pclose($h);
    }

    // CLI entry point
    $hn = gethostname();
    if ($hn == 'penguin')
        $hn = 'localhost';

    $lu = $no_cfg ? "http://$hn:8000/$filename" : "http://$hn:8000/";

    if ($argc == 1) {
        $stop = launchserver($hn,8000);
        launchBrowser($lu);
        readline("...");
        echo "Shutting down\n";
        $stop();
    }
    elseif ($argv[1] == 'debug') {
        launchIDE();        
    }
    elseif ($argv[1] == 'info') {
        phpinfo();
    }
    else {
        echo "Usage: php $filename [info|debug]\n";
        var_dump($argv);
        echo "\n";
    }
    exit(0);
}

////¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨|
/// ☮ router script ⚒ load and execute the app                           /
//______________________________________________________________________/

$url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($url != "/$filename")
{
    // static files
    if (PHP_SAPI == 'cli-server') {
        if ($url == '/' . CONFIG_FILE)
        {
            header("Location: /$filename");
            exit(0);
        }
            
        if (is_file(__DIR__ . $url))
            return false;
    }

    // heartbeat
    if ($url == '/_heartbeat')
    {
        echo \time();
        exit(0);
    }

    // in composer directory
    // if ($url == "/vendor/tonischranz/tsd.serve/$filename")
    // {
    //     copy (__FILE__, '.');
    // }

    // no config
    if (!file_exists(CONFIG_FILE))
    {
        header("Location: /$filename");
        exit(0);
    }

    // extensions check    
    foreach ( EXTENSIONS_SERVE as $ex)
        if (!in_array($ex, $ext))
        {
            header("Location: /$filename");
            exit(0);
        }

    // composer
    if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php' ))
        include __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
    
    // standalone
    elseif (file_exists(__DIR__ . DIRECTORY_SEPARATOR . $serve_file))
        include __DIR__ . DIRECTORY_SEPARATOR . $serve_file;

    // dev
    elseif (file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'App.php'))
    {
        $ds = DIRECTORY_SEPARATOR;
        spl_autoload_register(function($name) use ($ds)
        {
            $parts = explode('\\', $name);
            if (count($parts) == 3 && $parts[0] == 'tsd' && $parts[1] == 'serve') 
                include __DIR__ . $ds . 'src' . $ds . $parts[2] . '.php';
        });
    }

    // setup
    else
    {
        header("Location: /$filename");
        exit(0);
    }

    // ⚒ serve ⚒ //
    App::serve();
    exit(0);
}

////¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨|
/// 🧽 clean.php ⚒ install and setup your tsd.serve application          /
//______________________________________________________________________/


const EXTENSIONS_STANDALONE = ['openssl', 'session', 'zip'];
const EXTENSIONS_COMPOSER = ['filter', 'mbstring', 'phar'];

const MINVER = "8.0.0";

$serve_url = 'https://' . SERVE_HOST . '/' . SERVE_BASE . '/' . SERVE_REPO . '/archive/' . SERVE_BRANCH . '.zip';
//$admin_url = SERVE_BASE . '/' . ADMIN_REPO . '/archive/' . ADMIN_BRANCH . '.zip';


////¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨|
/// lib functions                                                        /
//______________________________________________________________________/

function rrmdir($dir)
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $object) && !is_link($dir . "/" . $object))
                    rrmdir($dir . DIRECTORY_SEPARATOR . $object);
                else
                    unlink($dir . DIRECTORY_SEPARATOR . $object);
            }
        }
        rmdir($dir);
    }
}

function rcopy($src, $dest)
{
    if (!is_dir($src)) return false;
    if (!is_dir($dest)) mkdir($dest);

    $objects = scandir($src);
    foreach ($objects as $object) {
        if ($object != "." && $object != "..") {
            if (is_dir($src . DIRECTORY_SEPARATOR . $object) && !is_link($src . "/" . $object))
                rcopy($src . DIRECTORY_SEPARATOR . $object, $dest . DIRECTORY_SEPARATOR . $object);
            else
                copy($src . DIRECTORY_SEPARATOR . $object, $dest . DIRECTORY_SEPARATOR . $object);
        }
    }

    return true;
}

////¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨|
/// install functions                                                    /
//______________________________________________________________________/

function create_config(string $username, string $pw, string $name, string $email)
{
    // $key = str_replace('+', '_', base64_encode(random_bytes(128)));
    $config = [
        // 'clean' =>  ['key' => $key],
        'member' =>  ['users' => ["$username" => [
            'password' => password_hash($pw, PASSWORD_DEFAULT),
            'name' => $name,
            'email' => $email,
            'groups' => ['admin', 'developer']
        ]]]
    ];
    file_put_contents(CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
}

function create_github_config(string $clientid, string $secret){
    $config = [
        // 'clean' =>  ['key' => $key],
        'member' =>  [
            'github_clientid' => $clientid,
            'github_secret' => $secret
        ]
    ];
    file_put_contents(CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
}

function register_user(string $id, string $name, string $email) {
    $config = json_decode(file_get_contents(CONFIG_FILE));

    if (@!$config['member']['users'])
    {
        $config['member']['users'] = [
            "github@$id" => [                
                'name' => $name,
                'email' => $email,
                'groups' => ['admin', 'developer']
            ]
        ];        
    }
    else {
        $config['member']['users'] = [
            "github@$id" => [                
                'name' => $name,
                'email' => $email,
                'groups' => []
            ]
        ];        
    }

    file_put_contents(CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
}
// function update_config()
// {
//     $cfg = json_decode(file_get_contents(CONFIG_FILE), true);

//     if (!@$cfg['clean']['key']) {
//         $key = str_replace('+', '_', base64_encode(random_bytes(128)));
//         $cfg['clean']['key'] = $key;
//         file_put_contents(CONFIG_FILE, json_encode($cfg, JSON_PRETTY_PRINT));
//     }
// }

function install_composer()
{

}

function install_serve($modules = [])
{
    get_serve();

    if (!is_dir('plugins')) mkdir('plugins');
    if (!is_dir('views')) mkdir('views');

    //install modules
    if (in_array('admin', $modules)) {
        get_admin();
    }
}

function get_admin()
{
    global $admin_url;

    $md5 = md5_file($admin_url);

    $z = "admin.$md5.zip";
    $h = fopen($admin_url, 'rb');
    $o = fopen($z, 'wb');

    while ($h && $o && !feof($h)) {
        fwrite($o, fread($h, 4096));
    }

    fclose($h);
    fclose($o);

    $zip = new ZipArchive;

    $zip->open($z);
    $zip->extractTo("admin.$md5");
    $zip->close();

    $dir = "admin.$md5/" . ADMIN_REPO . '-' . ADMIN_BRANCH;

    rcopy($dir, 'plugins'.DIRECTORY_SEPARATOR.'admin');

    $cfg = json_decode(file_get_contents(CONFIG_FILE), true);
    $cfg['clean']['admin_md5'] = $md5;
    file_put_contents(CONFIG_FILE, json_encode($cfg, JSON_PRETTY_PRINT));

    rrmdir("admin.$md5");
    unlink("admin.$md5.zip");
}

function get_serve()
{
    global $serve_url;
    global $serve_file;

    $md5 = md5_file($serve_url);

    $z = "serve.$md5.zip";
    $h = fopen($serve_url, 'rb');
    $o = fopen($z, 'wb');

    while ($h && $o && !feof($h)) {
        fwrite($o, fread($h, 4096));
    }

    fclose($h);
    fclose($o);

    $zip = new ZipArchive;

    $zip->open($z);
    $zip->extractTo("serve.$md5");
    $zip->close();

    $dir = "serve.$md5/" . SERVE_REPO . '-' . SERVE_BRANCH . '/src/';

    $files = glob($dir . DIRECTORY_SEPARATOR . '*.php');

    file_put_contents($serve_file, ["<?php\n", "namespace tsd\serve;\n"]);
    $uses = array();
    foreach ($files as $f) {
        $lines = file($f);
        $before_class = true;
        foreach ($lines as $l) {
            if ($before_class) {
                if (preg_match('/^<\?php/', $l)) continue;
                if (preg_match('/^\s*namespace\s/', $l)) continue;
                if (preg_match('/^\s*use\s/', $l)) {
                    $lt = trim($l);
                    if (in_array($lt, $uses)) continue;
                    else $uses[] = $lt;
                }
                if (preg_match('/^\s*(abstract\s)?class\s/', $l)) $before_class = false;
                if (preg_match('/^\s*interface\s/', $l)) $before_class = false;
            }
            if (preg_match('/^\s*$/', $l)) continue;

            file_put_contents($serve_file, $l, FILE_APPEND);
        }
    }

    //file_put_contents($serve_file, 'App::serve();', FILE_APPEND);
    
    $cfg = json_decode(file_get_contents(CONFIG_FILE), true);
    $cfg['clean']['serve_md5'] = $md5;
    file_put_contents(CONFIG_FILE, json_encode($cfg, JSON_PRETTY_PRINT));

    rrmdir("serve.$md5");
    unlink("serve.$md5.zip");
}

////¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨|
/// clean.php entry point                                                /
//______________________________________________________________________/



//$cfg = load_config();
$hostname = $_SERVER['HTTP_HOST'];
$no_user = !@$cfg['member']['user'];
$auth = false;
$login = false;
$not_installed = false;
$update_available = false;
$admin_update_available = false;
$config_no_key = false;
$extensions_ok = false;
$missing_extensions = [];
$missing_standalone = [];
$minver = false;

$ext= get_loaded_extensions();
            
foreach (EXTENSIONS_SERVE as $et)
{
    if (!in_array($et, $ext))
    $missing_extensions[]=$et;
}

foreach (EXTENSIONS_STANDALONE as $et)
{
    if (!in_array($et, $ext))
    $missing_standalone[]=$et;
}

foreach (EXTENSIONS_SERVE as $et)
{
    if (!in_array($et, $ext))
    $missing_extensions[]=$et;
}

$write_ok = @file_put_contents('.flag', time());

if ($write_ok)
    unlink('.flag');

$exec_ok = shell_exec('which sh');

$minver = version_compare(\PHP_VERSION, MINVER) >= 0;

if ($no_cfg) {
    if (@$_POST['action'] == 'install') {
        $valid = true;

        if (@!preg_match('/\w+/', $_POST['username'])) $valid = false;
        if (@!preg_match('/\w+/', $_POST['pw1'])) $valid = false;
        if (@!preg_match('/\w+/', $_POST['pw2'])) $valid = false;
        if ($valid && $_POST['pw1'] != $_POST['pw2']) $valid = false;

        if ($valid) {
            create_config($_POST['username'], $_POST['pw1']);
            install_serve(@$_POST['module'] ? $_POST['module'] : []);

            header('Location: /admin'); //ToDo if admin not installed
        }
    }
    
} else {
    $config = json_decode(file_get_contents(CONFIG_FILE), true);
    $appname = @$config['name'];

    if (@$config['member']['users']) {
        if (@$_POST['username'] && @$_POST['pw']) {
            $username = $_POST['username'];

            if (@$config['member']['users'][$username]) {
                if (password_verify($_POST['pw'], $config['member']['users'][$username]['password'])) {
                    if (@$config['member']['users'][$username]['groups']) {
                        if (in_array('admin', $config['member']['users'][$username]['groups'])) $auth = true;
                        else $error_insufficient_permissions = true;
                    }
                } else $error_bad_password = true;
            } else $error_bad_user = true;
        }
    } else $config_no_user = true;

    if (!in_array('session', $missing_extensions)){
    	session_start();

	if (@$_SESSION['auth']) {
            $auth = true;
        }
    }

    if (@$auth) {
        $_SESSION['auth'] = true;

        $cfg = json_decode(file_get_contents(CONFIG_FILE), true);
        
        if (file_exists($serve_file))
        {
            if (@$cfg['clean']['serve_md5']) 
            {
                $md5 = md5_file($serve_url);
                $update_available = $cfg['clean']['serve_md5'] != $md5;
            }
            if (@$cfg['clean']['admin_md5']) 
            {
                $md5 = md5_file($admin_url);
                $admin_update_available = $cfg['clean']['admin_md5'] != $md5;
            }
        } else {
            $not_installed = true;
        }

        if (@$_POST['action']) {
            if ($_POST['action'] == 'update') {
                if ($update_available) get_serve();
                if ($admin_update_available) get_admin();
                $update_available = false;
                $admin_update_available = false;
            } else if ($_POST['action'] == 'install') {
                // if ($config_no_key) update_config();
                install_serve(@$_POST['module'] ? $_POST['module'] : []);
            }
        }
    } else $login = true;
}

?>

<!doctype html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>🧽 <?=$filename?></title>

    <style type="text/css">
        body {
            color: #ddd;
            background-color: #222;
            font-family: sans-serif;
        }

        body.ro {
            color: #600;
        }

        body.ne {
            color: #006;
        }

        body.ro.ne {
            color: #606;
        }

        a,
        a:visited {
            text-decoration: none;
            color: #088;
        }

        a:active,
        a:hover {
            text-decoration: #ddd underline;
        }

        button {
            border: thin solid #888;
            background-color: #000;
            background-image: radial-gradient(farthest-corner at -10% -10%, #000, #000, #111, #444);
            color: #ddd;
            font-weight: bold;
            font-size: 2em;
            border-radius: .5em;
            padding: .25em 1em;
            outline: none;
        }

        button:hover {
            border: thin solid #888;
            background-image: radial-gradient(farthest-corner at 110% 110%, #000, #111, #222, #888);
        }

        button:active {
            background-image: radial-gradient(farthest-corner at -10% -10%, #000, #000, #111, #444);
        }

        h1 {
            font-size: 4rem;
        }

        h2 {
            margin-top: 3em;
        }

        h3 {
            margin-top: 1em;
        }

        div {
            margin-bottom: 1em;
        }

        div.gap {
            height: 2em;
        }

        div#content {
            margin: auto;
            width: 32em;
        }
        
        @media screen and (max-width: 32rem) {
            div#content {
            margin: .5em;
            width: auto;
          }
        }

        input,
        input:focus {
            color: #ddd;
            background-color: #222;
            border-style: solid;
            border-radius: .5em;
            padding: .25em;
            font-size: 1.5em;
            width: 100%;
            outline: none;
            text-align: right;
            padding-right: 1em;
        }

        input[disabled] {
            color: #888;
            background-color: #444;
        }

        input::placeholder {
            text-align: left;
            font-size: .8em;
        }

        input:focus::placeholder {
            font-size: .6em;
        }

        input[type=checkbox] {            
            width: auto;
            margin-right: .7em;
        }

        .r {
            text-align: right;
        }

        .ff {
            display:flex;
        }
        .ff>* {
            width:50%;
        }
        .ff>button>img {
            width:100%;
        }

        .e {
            color: #a00;
        }

        div {
            margin-top: .5em;
        }

        label.checkbox {
            display:block;
        }
        .c {
            text-align: center;
        }

        ul.n {
            padding-left: .25em;
        }

        ul.n>li {
            list-style-type: none;
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.7.1.slim.min.js" integrity="sha256-kmHvs0B+OpCW5GVHUNjv9rOmY0IvSIRcf7zGUDTDQM8=" crossorigin="anonymous"></script>

    <script>
        function updateOAuthCreateLink ()
        {
            let murl = <?=json_encode((@$_SERVER['HTTPS'] ? 'https://' : 'http://') . $hostname)?>;
            let aurl = <?=json_encode((@$_SERVER['HTTPS'] ? 'https://' : 'http://') . $hostname . $url)?>;
            let name = $('form.install input[name=appname]').val();
                        
            let e_name = encodeURIComponent(name);
            let e_url = encodeURIComponent(murl);
            let e_aurl = encodeURIComponent(aurl);

            let url = `https://github.com/settings/applications/new?oauth_application[name]=${e_name}&oauth_application[url]=${e_url}&oauth_application[callback_url]=${e_aurl}`;

            console.log('updating link');

            $('form.install #oauth').prop('href',url);
        }

        $(() => {

            $('form.install input[name=github_clientid]').change(e => {
                let i = $(e.currentTarget);
                if (i.val()) {
                    $('form.install input[name=username]').prop('disabled', true);
                    $('form.install input[name=pw1]').prop('disabled', true);
                    $('form.install input[name=pw2]').prop('disabled', true);

                    $('form.install input[name=github_secret]').prop('disabled', false);
                }
                else {
                    $('form.install input[name=username]').prop('disabled', false);
                    $('form.install input[name=pw1]').prop('disabled', false);
                    $('form.install input[name=pw2]').prop('disabled', false);

                    $('form.install input[name=github_secret]').prop('disabled', true);
                }

            });
            
            $('form.install input[name=name]').change(e => {
                updateOAuthCreateLink();
            });

            $('form.install input[type=password]').change(() => {
                $('#err_pwd_mismatch').hide();
            });

            $('form.install').submit( e => {
                if ($('input[name=pw1]').val() != $('input[name=pw2]').val()) {
                    $('#err_pwd_mismatch').show();
                    e.preventDefault();
                }
            });

            updateOAuthCreateLink();
        });
    </script>

</head>

<body <?=!$write_ok?'class="ro"':''?> <?=!$exec_ok?'class="ne"':''?>>
    <header></header>

    <div id="content">
        <?php $eu = (@$_SERVER['HTTPS'] ? 'https://' : 'http://') . $hostname . $url; ?>
        <?php if ($hostname != 'localhost:8000') : ?>
            <div class="gap"></div>
            <div class="c">            
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=<?=urlencode($eu)?>" alt="QR-Code"?>
            </div>
        <?php endif ?>
    
        <h1> 🧽 <?=$filename?> </h1>
        
        <div class="r"><?=$appname??"$dirname on $hostname"?></div>
        
        <?php if ($missing_extensions) : ?>
            <div>
                <h2>extensions</h2>
                <p>the following php extensions are needed by the framework</p>
                <ul>
                    <?php foreach ($missing_extensions as $me) { ?>
                        <li><?=$me; ?></li>
                    <?php } ?>                    
                </ul>
            </div>
        <?php elseif (!$minver): ?>
            <div>
                <h2>PHP version</h2>
                <p>the framework requires at least PHP <?=MINVER; ?></p>
            </div>
        <?php endif ?>


        <?php if ($no_cfg) : ?>


            
           
                <!-- <form method="post" action="<?=$filename?>" class="install">
                    <div>
                        <input type="email" name="email" placeholder="email" required autocomplete="email"/>
                    </div>
                    <div>
                        <input type="name" name="name" placeholder="name" autocomplete="name"/>
                    </div>
                </form> -->
                
            

                <form method="post" action="<?=$filename?>" class="install">
                    <h2>about you and your app</h2>
                    <div>
                        <input type="email" name="email" placeholder="your email" required autocomplete="email"/>
                    </div>
                    <div>
                        <input type="name" name="name" placeholder="your name" autocomplete="name"/>
                    </div>
                    <div>
                        <input type="name" name="appname" placeholder="name of your app" autocomplete="on" value="<?="$dirname on $hostname"?>" required />
                    </div>                    

                    <h2>secure your app</h2>

                    <p>
                        choose your favorite authentication method.
                    </p>
                    <h3>GitHub OAuth</h3>
                    <p><a id="oauth" href="#" target="_blank">register a new OAuth application</a></p>
                    <div>
                        <input type="text" name="github_clientid" placeholder="client id" />
                    </div>
                    <div>
                        <input type="text" name="github_secret" placeholder="secret" disabled />
                    </div>
                    <h3>username / password</h3>
                    <div>
                        <input type="text" name="username" placeholder="username" required autocomplete="username" value="<?=$username?>"/>
                    </div>
                    <div>
                        <input type="password" name="pw1" placeholder="password" autocomplete="new-password" required />
                    </div>
                    <div>
                        <input type="password" name="pw2" placeholder="repeat password" autocomplete="new-password" required />
                    </div>
                    <div>
                        <span class="e" style="display:none;" id="err_pwd_mismatch">passwords do not match</span>
                    </div>                    
                    <h2>install</h2>
                    <h3>additional modules</h3>
                    <div>
                        <label class="checkbox">
                            <input id="admin" type="checkbox" name="module[]" value="admin" checked>
                            install serve.admin as well
                        </label>
                        <label class="checkbox">
                            <input id="admin" type="checkbox" name="module[]" value="pages">
                            install serve.pages
                        </label>
                    </div>                    
                    <h3>choose your method</h2>
                    <div class="ff">
                        <button type="submit" name="action" value="install"><img  alt="install standalone" src="http://tsd.ovh/%E2%92%B6.svg" /></button>
                        <button type="submit" name="action" value="composer"><img  alt="install with composer" src="https://getcomposer.org/img/logo-composer-transparent.png" /></button>
                    </div>
                </form>

        <?php endif ?>

        <?php if ($login) : ?>

            <h2>login</h2>
            <div class="gap"></div>
            <form method="post" action="<?=$filename ?>">
                <div>
                    <input type="text" name="username" placeholder="username" autocomplete="username" required />
                </div>
                <div>
                    <input type="password" name="pw" placeholder="password" autocomplete="current-password" required />
                </div>
                <div class="r">
                    <button type="submit" name="action" value="login">login</button>
                </div>
                <div>
                    <?php if (@$error_bad_key) : ?><span class="e">bad key, please check it again or use username/password</span><?php endif; ?>
                    <?php if (@$error_bad_password) : ?><span class="e">bad username/password, please check it again</span><?php endif; ?>
                    <?php if (@$error_insufficient_permissions) : ?><span class="e">you were logged in successfully, but don't have enough permissions</span><?php endif; ?>
                </div>
            </form>

        <?php endif ?>

        <?php if ($auth) : ?>

            <?php if ($not_installed) : ?>
                <?php if ($missing_extensions) : ?>
                    <h2>extensions</h2>
                    <p>the following php extensions are needed by the framework</p>
                    <ul>
                    <?php foreach ($missing_extensions as $me) { ?>
                        <li><?=$me; ?></li>
                    <?php } ?>                    
                    </ul>
                <?php else : ?>
                    <h2>clean install available</h2>
                    <p>
                        Looks like you don't have installed tsd.serve with clean yet. But you do have a config file, maybe from a development environment?
                    </p>
                    <p>
                        You can install it for production purposes with this tool now, if you want to.
                    </p>
                    <form method="post" action="<?=$url?>">
                        <div class="gap"></div>
                        <div class="ff">
                            <button type="submit" name="action" value="install">

                                <img  alt="install standalone" src="http://tsd.ovh/%E2%92%B6.svg" />
                            </button>
                            <button type="submit" name="action" value="composer"><img  alt="install with composer" src="https://getcomposer.org/img/logo-composer-transparent.png" /></button>
                        </div>
                    </form>
                <?php endif; ?>
            <?php elseif ($update_available || $admin_update_available) : ?>
                <h2>update available</h2>
                <?php if ($update_available) : ?>
                <p>
                    There is a new version of tsd.serve available.
                </p>
                <?php endif; ?>
                <?php if ($admin_update_available) : ?>
                <p>
                    There is a new version of tsd.serve.admin available.
                </p>
                <?php endif; ?>
                <form method="post" action="<?=$url?>">
                    <div class="gap"></div>
                    <div class="r">
                        <button type="submit" name="action" value="update">install</button>
                    </div>
                </form>

            <?php else : ?>
                <h2>tsd.serve is up to date</h2>
                <p>
                    There is nothing to do.
                </p>

            <?php endif ?>

        <?php endif ?>

        <?php if ($no_cfg || $auth) : ?>
            <h2>your files and directories</h2>
            <ul class="n">
                <?php foreach (scandir('.') as $f) { ?>
                    <li>
                        <a href="<?=$f?>"><?=$f?></a>
                    </li>
                <?php } ?>
            </ul>
        <?php endif ?>
    </div>
    <footer>
        <pre></pre>
    </footer>
</body>

</html>
