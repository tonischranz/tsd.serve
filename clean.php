<?php

////¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨/
/// paths                                                                    /
//__________________________________________________________________________/

const CONFIG_FILE = '.config.json';
const SERVE_FILE = '.php';

const SERVE_BASE = 'https://github.com/tonischranz';
const SERVE_REPO = 'tsd.serve';
const SERVE_BRANCH = 'next';
const ADMIN_REPO = 'serve.admin';
const ADMIN_BRANCH = 'main';

const EXTENSIONS = ['dom', 'openssl', 'session', 'zip' /*, 'mysqli'*/];

$serve_url = SERVE_BASE . '/' . SERVE_REPO . '/archive/' . SERVE_BRANCH . '.zip';
$admin_url = SERVE_BASE . '/' . ADMIN_REPO . '/archive/' . ADMIN_BRANCH . '.zip';

////¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨/
/// functions                                                                /
//__________________________________________________________________________/

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

function create_config($username, $pw)
{
    $key = str_replace('+', '_', base64_encode(random_bytes(128)));
    $config = [
        'clean' =>  ['key' => $key],
        'member' =>  ['users' => ["$username" => [
            'password' => password_hash($pw, PASSWORD_DEFAULT),
            'groups' => ['admin', 'developer']
        ]]]
    ];
    file_put_contents(CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
}

function update_config()
{
    $cfg = json_decode(file_get_contents(CONFIG_FILE), true);

    if (!@$cfg['clean']['key']) {
        $key = str_replace('+', '_', base64_encode(random_bytes(128)));
        $cfg['clean']['key'] = $key;
        file_put_contents(CONFIG_FILE, json_encode($cfg, JSON_PRETTY_PRINT));
    }
}

function install_serve($modules = [])
{
    get_serve();

    /*if (file_exists('index.php')) rename('index.php', 'index.php.orig');
    file_put_contents('index.php', ["<?php\n", 'include \'' . SERVE_FILE . "';\n", "use tsd\serve\App;\n", "return App::serve();\n"]);*/

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

    file_put_contents(SERVE_FILE, ["<?php\n", "namespace tsd\serve;\n"]);
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

            file_put_contents(SERVE_FILE, $l, FILE_APPEND);
        }
    }

    file_put_contents(SERVE_FILE, 'App::serve();', FILE_APPEND);
    
    $cfg = json_decode(file_get_contents(CONFIG_FILE), true);
    $cfg['clean']['serve_md5'] = $md5;
    file_put_contents(CONFIG_FILE, json_encode($cfg, JSON_PRETTY_PRINT));

    rrmdir("serve.$md5");
    unlink("serve.$md5.zip");
}

////¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨/
/// entry points                                                             /
//__________________________________________________________________________/

if (PHP_SAPI == 'cli') {
    if ($argc == 1) {
        shell_exec(PHP_BINARY . " -S localhost:8000 clean.php");
    } else {
        echo "Usage: php clean.php\n";
        echo "\n";
    }
    exit(0);
}

$url = $_SERVER['REQUEST_URI'];

if ($url != '/clean.php')
{
    if (file_exists('.' . urldecode($url)) && $url != '/') return false;

    if (file_exists('.php'))
    {
        include '.php';
        exit(0);
    }

    if (file_exists('./src/App.php'))
    {
        spl_autoload_register(function($name){
            $parts = explode('\\', $name);
            if (count($parts) == 3 && $parts[0] == 'tsd' && $parts[1] == 'serve') include 'src' . DIRECTORY_SEPARATOR . $parts[2] . '.php';
        });
        \tsd\serve\App::serve();
        exit(0);
    }
}

////¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨¨/
/// clean install                                                            /
//__________________________________________________________________________/

$fresh = !file_exists(CONFIG_FILE);
$auth = false;
$login = false;
$not_installed = false;
$update_available = false;
$admin_update_available = false;
$config_no_key = false;
$extensions_ok = false;
$missing_extensions = [];

$ext= get_loaded_extensions();
            
foreach (EXTENSIONS as $et)
{
    if (!in_array($et, $ext))
    $missing_extensions[]=$et;
}

if ($fresh) {
    if (@$_POST['action'] == 'install') {
        $valid = true;

        if (@!preg_match('/\w+/', $_POST['username'])) $valid = false;
        if (@!preg_match('/\w+/', $_POST['pw1'])) $valid = false;
        if (@!preg_match('/\w+/', $_POST['pw2'])) $valid = false;
        if ($valid && $_POST['pw1'] != $_POST['pw2']) $valid = false;

        if ($valid) {
            create_config($_POST['username'], $_POST['pw1']);
            install_serve(@$_POST['module'] ? $_POST['module'] : []);

            header('Location: /admin');
        }
    }
} else {
    $config = json_decode(file_get_contents(CONFIG_FILE), true);

    if (@$config['clean']['key']) {
        if (@$_POST['key']) {
            if ($_POST['key'] == $config['clean']['key']) $auth = true;
            else $error_bad_key = true;
        }
    } else $config_no_key = true;

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
        
        if (file_exists(SERVE_FILE))
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
                if ($config_no_key) update_config();
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

    <title>🧽 clean</title>

    <style type="text/css">
        body {
            color: #ddd;
            background-color: #222;
            font-family: sans-serif;
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
            font-size: 5rem;
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

        div.right {
            text-align: right;
        }

        span.error {
            color: #a00;
        }

        div {
            margin-top: .5em;
        }
    </style>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>

    <script>
        $(function() {

            $('form.install input[type=password]').change(function() {
                $('#err_pwd_mismatch').hide();
            });

            $('form.install').submit(function(e) {
                if ($('input[name=pw1]').val() != $('input[name=pw2]').val()) {
                    $('#err_pwd_mismatch').show();
                    e.preventDefault();
                }
            });
        });
    </script>

</head>

<body>
    <header></header>

    <div id="content">
        <h1>🧽 clean</h1>
        <div style="text-align: right; font-size:.7em;">by&nbsp;&nbsp;&nbsp;&nbsp;Δ@✞εℕᚹⅤᚢᛕ</div>
        
        <div class="gap"></div>


        <?php if ($fresh) : ?>

            <h2>fresh install</h2>
            <p>
                Looks like you don't have installed tsd.serve yet.
            </p>

            <div class="gap"></div>

            <?php if ($missing_extensions) : ?>
                <div>
                    <h2>extensions</h2>
                    <p>the following php extensions are needed by the framework</p>
                    <ul>
                        <?php foreach ($missing_extensions as $me) { ?>
                            <li><?php echo $me; ?></li>
                        <?php } ?>                    
                    </ul>
                </div>
            <?php else : ?>

                <form method="post" action="<?php echo $_SERVER['PHP_SELF'] ?>" class="install">
                    <h3>Master Account</h3>
                    <div>
                        <input type="text" name="username" placeholder="username" required />
                    </div>
                    <div>
                        <input type="password" name="pw1" placeholder="password" required />
                    </div>
                    <div>
                        <input type="password" name="pw2" placeholder="repeat password" required />
                    </div>
                    <div>
                        <span class="error" style="display:none;" id="err_pwd_mismatch">passwords do not match</span>
                    </div>
                    <div class="gap"></div>
                    <h3>Additional Modules</h3>
                    <div>
                        <label class="checkbox">
                            <input id="admin" type="checkbox" name="module[]" value="admin" checked>
                            Install the serve.admin administration UI as well
                        </label>
                    </div>
                    <div class="gap"></div>
                    <div class="right">
                        <button type="submit" name="action" value="install">install</button>
                    </div>                   
                </form>
            <?php endif ?>

        <?php endif ?>

        <?php if ($login) : ?>

            <h2>login</h2>
            <div class="gap"></div>
            <form method="post" action="<?php echo $_SERVER['PHP_SELF'] ?>">
                <div>
                    <input type="text" name="username" placeholder="username" required />
                </div>
                <div>
                    <input type="password" name="pw" placeholder="password" />
                </div>
                <div class="right">
                    <button type="submit" name="action" value="login">login</button>
                </div>
                <div>
                    <?php if (@$error_bad_key) : ?><span class="error">bad key, please check it again or use username/password</span><?php endif; ?>
                    <?php if (@$error_bad_password) : ?><span class="error">bad username/password, please check it again</span><?php endif; ?>
                    <?php if (@$error_insufficient_permissions) : ?><span class="error">you were logged in successfully, but don't have enough permissions</span><?php endif; ?>
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
                        <li><?php echo $me; ?></li>
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
                    <form method="post" action="<?php echo $_SERVER['PHP_SELF'] ?>">
                        <div class="gap"></div>
                        <div class="right">
                            <button type="submit" name="action" value="install">install</button>
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
                <form method="post" action="<?php echo $_SERVER['PHP_SELF'] ?>">
                    <div class="gap"></div>
                    <div class="right">
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

    </div>
    <footer>
        <pre></pre>
    </footer>
</body>

</html>
