<?php

const CONFIG_FILE = '.config.json';
const SERVE_FILE = '.tsd.serve.php';
const SERVE_URL = 'https://github.com/tonischranz/tsd.serve/archive/master.zip'; // https://tsd.ovh/serve
const ADMIN_URL = 'https://github.com/tonischranz/serve.admin/archive/master.zip'; // https://tsd.ovh/serve.admin

function rrmdir($dir) { 
    if (is_dir($dir)) { 
      $objects = scandir($dir);
      foreach ($objects as $object) { 
        if ($object != "." && $object != "..") { 
          if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
            rrmdir($dir. DIRECTORY_SEPARATOR .$object);
          else
            unlink($dir. DIRECTORY_SEPARATOR .$object); 
        } 
      }
      rmdir($dir); 
    } 
}

function create_config($username, $pw)
{
    $key = str_replace('+', '_', base64_encode(random_bytes(128)));
    $config = [ 'clean' =>  [   'key'=>$key], 
                'member'=>  [   'users'=>["$username"=>['password'=>password_hash($pw, PASSWORD_DEFAULT), 
                                                    'groups'=>['admin', 'clean']]
                                        ]
                            ]
                ];
    file_put_contents(CONFIG_FILE, json_encode($config));
}

function install_serve($modules)
{
    get_serve();

    file_put_contents('index.php', ["<?php\n", 'include \'' . SERVE_FILE . "';\n", "use tsd\serve\App;\n", "return App::serve();\n"]);

    mkdir('.plugins');
    mkdir('.views');
    
    //install modules
}

function get_serve()
{
    $md5 = md5_file(SERVE_URL);

    $z = "serve.$md5.zip";
    $h = fopen(SERVE_URL, 'rb');
    $o = fopen($z, 'wb');

    while ($h && $o && !feof($h))
    {
        fwrite($o, fread($h, 4096));
    }

    fclose($h);
    fclose($o);

    $zip = new ZipArchive;

    $zip->open($z);
    $zip->extractTo("serve.$md5");
    $zip->close();

    $files = array_slice(scandir("serve.$md5/tsd.serve-master/tsd/serve/"),2);

    file_put_contents(SERVE_FILE, ["<?php\n", "namespace tsd\serve;\n"]);
    foreach ($files as $f)
    {
        file_put_contents(SERVE_FILE, array_slice(file("serve.$md5/tsd.serve-master/tsd/serve/$f"), 4), FILE_APPEND);
    }

    $cfg = json_decode(file_get_contents(CONFIG_FILE), true);
    $cfg['clean']['serve_md5'] = $md5;
    file_put_contents(CONFIG_FILE, json_encode($cfg));

    rrmdir("serve.$md5");
    unlink("serve.$md5.zip");
}



$fresh = !file_exists(CONFIG_FILE);
$auth = false;
$login = false;

if ($fresh)
{
    if (@$_POST['action'] == 'install')
    {
        $valid=true;
        
        if (@!preg_match('/\w+/', $_POST['username'])) $valid = false;
        if (@!preg_match('/\w+/', $_POST['pw1'])) $valid = false;
        if (@!preg_match('/\w+/', $_POST['pw2'])) $valid = false;
        if ($valid && $_POST['pw1'] != $_POST['pw2']) $valid = false;
        
        if ($valid)
        {
            create_config($_POST['username'], $_POST['pw1']);
            install_serve(array_key_exists('module[]', $_POST) ? $_POST['module[]'] : []);
        
            header('Location: /admin');
        }
    }
}
else
{
    $config = json_decode(file_get_contents(CONFIG_FILE), true);

    if (@$config['clean']['key'])
    {
        if (@$_POST['key'])
        {
            if ($_POST['key'] == $config['clean']['key']) $auth = true;
            else $error_bad_key = true;
        }
    }
    else $config_no_key = true;
    
    if (@$config['member']['users'])
    {
        if (@$_POST['username'] && @$_POST['pw'])
        {
            $username = $_POST['username'];            
            
            if (@$config['member']['users']["$username"])
            {
                if (password_verify($_POST['pw'], $config['member']['users']["$username"]['password'])) $auth = true;
                else $error_bad_password = true;
            }
            else $error_bad_user = true;            
        }
    }
    else $config_no_user = true;

    session_start();
    if (@$_SESSION['auth'])
    {
        $auth = true;
    }

    if (@$auth)
    {
        $_SESSION['auth'] = true;

        if (@$_POST['action'])
        {
            if ($_POST['action'] == 'update')
            {

            }
        }
        else
        {
            //check md5
        }
    }
    else $login = true;
}

?>

<!doctype html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    
    <title>🧽 clean</title>
    
    <style type="text/css">
        body { color:#ddd; background-color:#222; font-family: sans-serif; }
        a, a:visited { text-decoration: none; color:#088; }
        a:active, a:hover { text-decoration:#ddd underline; }        
        button {  border: thin solid #888; background-color: #000; background-image: radial-gradient(farthest-corner at -10% -10%, #000, #000, #111, #444); color: #ddd; font-weight: bold; font-size: 2em; border-radius: .5em; padding:.25em 1em; outline:none; }
        button:hover { border: thin solid #888; background-image: radial-gradient(farthest-corner at 110% 110%, #000, #111, #222, #888); }
        button:active { background-image: radial-gradient(farthest-corner at -10% -10%, #000, #000, #111, #444); }
        h1 { font-size: 5rem; }
        div.gap { height: 2em; }
        div#content { margin:auto; width:32em; }
        input, input:focus { color:#ddd; background-color:#222; border-style:solid; border-radius: .5em; padding:.25em; font-size: 1.5em; width:100%; outline:none; text-align:right; padding-right: 1em;}
        input::placeholder { text-align:left;font-size:.8em; }
        input:focus::placeholder {font-size:.6em; }
        input[type=checkbox] {width:auto; margin-right:.7em;}
        div.right {text-align: right;}
        span.error {color:#a00;}
        div {margin-top: .5em;}
    </style>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>

    <script>
    $(function(){

        $('form.install input[type=password]').change(function()
        {
            $('#err_pwd_mismatch').hide();
        });

        $('form.install').submit(function (e){
            if ($('input[name=pw1]').val() != $('input[name=pw2]').val())
            {
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
        
        <?php if ($fresh): ?>

        <h2>fresh install</h2>
        <p>
            Looks like you don't have installed tsd.serve yet.
        </p>
        <div class="gap"></div>
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
                <input id="admin" type="checkbox" name="module[]" value="admin" checked>
                <label for="admin">Install the serve.admin administration UI as well</label>
            </div>
            <div class="gap"></div>
            <div class="right">
                <button type="submit" name="action" value="install">install</button>
            </div>
        </form>

        <?php endif ?>

        <?php if ($login): ?>

        <h2>login</h2>
        <div class="gap"></div>
        <form method="post" action="<?php echo $_SERVER['PHP_SELF'] ?>">
            <div>
                <input type="text" name="username" placeholder="username" required />
            </div>
            <div>
                <input type="password" name="pw" placeholder="password" required />
            </div>
            <div class="right">
                <button type="submit" name="action" value="login">login</button>
            </div>
        </form>

        <?php endif ?>

        <?php if ($auth): ?>

        logged in

        <?php endif ?>

    </div>
    <footer>
    <pre></pre>
  </footer>
</body>

</html>
