<?php

namespace tsd\serve;

abstract class ViewEngine
{
    function render(mixed $result, ViewContext $ctx, string $accept)
    {
        if ($result instanceof AccessDeniedException) $result = Controller::error($result, 403);
        if ($result instanceof NotFoundException) $result = Controller::error($result, 404);
        if ($result instanceof \Exception) $result = Controller::error($result->getMessage(), 500);
        if ($result instanceof \Error) $result = Controller::error($result->getMessage(), 500);
        if (!($result instanceof Result)) $result = Controller::data($result);

        http_response_code($result->getStatusCode());
        $headers = $result->getHeaders();
        foreach ($headers as $h) {
            header($h);
        }

        if ($result instanceof ViewResult) {
            try {
                $this->renderView($result, $ctx);
            } catch (\Exception $e) {
                http_response_code(500);
                $this->renderView(Controller::error($e->getMessage(), 500), $ctx);
            } catch (\Error $e) {
                http_response_code(500);
                $this->renderView(Controller::error($e->getMessage(), 500), $ctx);
            }
        }
        else if ($result instanceof FileResult) {
          readfile($result->data());
        }
        else if ($result instanceof TextResult) {
          echo $result->data();
        }
        else if (strstr($accept,'application/json') || strstr($accept,'*/*'))
        {
          $this->renderJson($result);
        } 
        else if (strstr($accept,'text/xml'))
        {
          $this->renderXml($result);
        }
        else if (ob_get_length())
        {
          ob_end_flush(); 
        }
        else {
          //default to json
          $this->renderJson($result);
        }
    }

    private function renderJson(Result $result)
    {
        ob_end_clean();
        echo json_encode($result->data(), JSON_PRETTY_PRINT);
    }

    private function renderXml(Result $result)
    {
        ob_end_clean();
        echo $result->data()->asXML();
    }

    protected abstract function renderView(IViewResult $result, ViewContext $ctx);
}

/**
 * Default View Engine for tsd.serve. It compiles the Views and Layouts into PHP Files and caches them for later use.
 * The Views are written in a simple Template Syntax and support basic Control Structures like if, each
 */
#[DefaultMode]
class ServeViewEngine extends ViewEngine
{
    const CACHED_VIEWS = '.cached_views.php';
    const CACHED_DIR = '.cached_views';
    const CACHE_DURATION = 30;
    const VIEWS = 'views';

    public static array $cached_views = array();

    function __construct()
    {
        if (file_exists(ServeViewEngine::CACHED_VIEWS)) 
        {
          try {
          include ServeViewEngine::CACHED_VIEWS;
          }
          catch (\Error){}
        }
        if (!is_dir(ServeViewEngine::CACHED_DIR)) mkdir(ServeViewEngine::CACHED_DIR);
    }

    private static function writeCacheFile()
    {
        file_put_contents(ServeViewEngine::CACHED_VIEWS, ["<?php\n", "use tsd\serve\ServeViewEngine;\n", 'ServeViewEngine::$cached_views = [']);

        foreach (ServeViewEngine::$cached_views as $k => $v)
            file_put_contents(ServeViewEngine::CACHED_VIEWS, "'$k'=>['$v[0]','$v[1]'],", FILE_APPEND);

        file_put_contents(ServeViewEngine::CACHED_VIEWS, '];', FILE_APPEND);
    }

    function renderView(IViewResult $result, ViewContext $ctx)
    {
        $plugin = $result->plugin();
        $view = $result->view();
        $layoutPlugin = $ctx->layoutPlugin;
        $key = "$layoutPlugin-$plugin-" . str_replace('/', '.', $view);
        $cached_view = '';
        $view_file = '';
        $v = null;
        
        if (array_key_exists($key, ServeViewEngine::$cached_views)) {
            $md5 = ServeViewEngine::$cached_views[$key][0];
            $timestamp = ServeViewEngine::$cached_views[$key][1];

            if ($timestamp + ServeViewEngine::CACHE_DURATION < time()) {
                $v = new View($view, $plugin);
                $md5 = $v->md5();
            }
            $cached_view = "$key.$md5.php";
        }

        if ($cached_view && file_exists(ServeViewEngine::CACHED_VIEWS . DIRECTORY_SEPARATOR . $cached_view)) {
            $view_file = ServeViewEngine::CACHED_VIEWS . DIRECTORY_SEPARATOR . $cached_view;
        } else {
            if (!$v) $v = new View($view, $plugin);
            
            $layout = new Layout($layoutPlugin);

            $t = \Dom\HTMLDocument::createFromString(View::escapeTemplate($v->template));
            $o = \Dom\HTMLDocument::createFromString(View::escapeTemplate($layout->template));

            $title = $t->head->getElementsByTagName('title')->item(0);
            $links = $t->head->getElementsByTagName('link');
            $styles = $t->head->getElementsByTagName('style');
            $scripts = $t->head->getElementsByTagName('script');
            $main = $t->body->getElementsByTagName('main')->item(0);

            $lBody = $o->getElementsByTagName('body')->item(0);
            $lOldMain = $o->body->getElementsByTagName('main')->item(0);
            $lMain = $o->importNode($main, true);
            $lBody->replaceChild($lMain, $lOldMain);

            $lHead = $o->getElementsByTagName('head')->item(0);

            foreach ($links as $h) $lHead->appendChild($o->importNode($h, true));
            foreach ($styles as $h) $lHead->appendChild($o->importNode($h, true));
            foreach ($scripts as $h) $lHead->appendChild($o->importNode($h, true));

            $lTitle = $o->head->getElementsByTagName('title')->item(0);
            $lTitle->textContent = $title->textContent;

            $to = View::compileTemplate($o->saveHTML());

            //cache
            $md5 = $v->md5();
            $view_file = ServeViewEngine::CACHED_DIR . DIRECTORY_SEPARATOR . "$key.$md5.php";
            array_map('unlink', glob(ServeViewEngine::CACHED_DIR . DIRECTORY_SEPARATOR . "$key.*.php"));
            file_put_contents($view_file, $to);
            ServeViewEngine::$cached_views[$key] = [$md5, time()];
            ServeViewEngine::writeCacheFile();
        }

        ServeViewEngine::run($view_file, $result->data(), $ctx);
    }

    private static function run(string $view, ?array $data, ViewContext $ctx)
    {
        $debug = ob_get_contents();
        ob_end_clean();

        $ctx->debug = $debug;
        $c = (array)$ctx;

        $d     = $data;
        $s  = [$d];

        include $view;
    }
}

class View
{
    private Label $labels;
    public string $template;
    private string $md5;

    function __construct(string $path, string $plugin = '')
    {
        $this->labels = Labels::create($path);

        $this->template = View::loadTemplate($path . '.html', $plugin);
        $this->md5 = md5($this->template);
    }

    public function md5(): string
    {
        return $this->md5;
    }


    private static function loadTemplate(string $path, string $plugin)
    {
        $noPluginBasePath = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . '.' . ServeViewEngine::VIEWS;
        $basePath = $plugin ? $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . App::PLUGINS . DIRECTORY_SEPARATOR . $plugin . DIRECTORY_SEPARATOR . ServeViewEngine::VIEWS : $noPluginBasePath;
        $alternateBasePath = $plugin ? $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . '.' . ServeViewEngine::VIEWS . DIRECTORY_SEPARATOR . App::PLUGINS . DIRECTORY_SEPARATOR . $plugin : '';

        $viewPath = $alternateBasePath ? $alternateBasePath . DIRECTORY_SEPARATOR . $path : $basePath . DIRECTORY_SEPARATOR . $path;

        if (!file_exists($viewPath) && $alternateBasePath) $viewPath = $basePath . DIRECTORY_SEPARATOR . $path;

        if (!file_exists($viewPath)) $viewPath = $noPluginBasePath . DIRECTORY_SEPARATOR . $path;

        if (!file_exists($viewPath)) {
            if ($path == 'error.html') return <<<'EOError'
            <!DOCTYPE html>
            <html>
              <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                <title>error</title>
              </head>
            
              <body>
                <main>
                  <h1>💥 error</h1>
                  <pre>{message}</pre>
                </main>
              </body>
            </html>
            EOError;

            if ($path == 'info.html') return <<<'EOInfo'
            <!DOCTYPE html>
            <html>
              <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                <title>info</title>
              </head>
            
              <body>
                <main>
                  <h1>🛈 info</h1>
                  <p>{message}</p>
                </main>
              </body>
            </html>
            EOInfo;

            if ($path == 'success.html') return <<<'EOSuccess'
            <!DOCTYPE html>
            <html>
              <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                <title>success</title>
              </head>
            
              <body>
                <main class="success">
                  <h1>🛈 success</h1>
                  <p>{message}</p>
                </main>
              </body>
            </html>
            EOSuccess;

            if ($path == 'login.html') return <<<'EOLogin'
            <!DOCTYPE html>
            <html>
              <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                <title>login</title>
              </head>
            
              <body>
                <main>
                  <h1>🔑 login</h1>
                  <form method="post" action="/_login">
                    {with returnUrl}<input type="hidden" name="returnUrl" value="{.}" />{/with}
                    <div>
                      <input type="text" name="username" placeholder="username" />
                    </div>
                    <div>
                      <input type="password" name="password" placeholder="password" />
                    </div>
                    <div class="gap">
                      {if error}
                        <span class="error">wrong username / password</span>
                      {/if}
                    </div>
                    <div class="right">
                      <input type="submit" value="go" />
                    </div>
                  </form>
                </main>
              </body>
            </html>
            EOLogin;

            if ($path == 'logout.html') return <<<'EOLogout'
            <!DOCTYPE html>
            <html>
              <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                <title>logout</title>
              </head>
            
              <body>
                <main>
                  <h1>🔒 logout</h1>
                  <p>do you really want to logout?</p>
                  <form method="post" action="/_login/logout">
                  {with returnUrl}<input type="hidden" name="returnUrl" value="{.}" />{/with}
                    <div class="right">
                        <input type="submit" value="yes" />
                    </div>
                  </form>
                </main>
              </body>
            </html>
            EOLogout;

            if ($path == 'loggedout.html') return <<<'EOLoggedout'
            <!DOCTYPE html>
            <html>
              <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                <title>logged out</title>
              </head>
            
              <body>
                <main>
                  <h1>🔒 logged out</h1>
                  <p>you have successfully logged out</p>
                  {with returnUrl}
                    <a class="nopopup" href="/_login?returnUrl={.}">login again</a>
                  {without}
                    <a class="nopopup" href="/">return to home</a>
                  {/with}
                </main>
              </body>
            </html>
            EOLoggedout;

            if ($path == 'profile.html') return <<<'EOProfile'
            <!DOCTYPE html>
            <html>
              <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                <title>{with fullname}{.}{else}{username}{/with}'s profile</title>
              </head>
            
              <body>
                <main>
                  <h1>⚐ user profile</h1>
                  <form method="post" action="profile">
                    <div>
                        username: {username}
                    </div>
                    <div>
                      <input type="text" name="fullname" placeholder="full name" value="{fullname}" />
                    </div>
                    <div>
                      <input type="email" name="email" placeholder="email" value="{email}" />
                    </div>                   
                    <div class="right">
                      <input type="submit" value="save" />
                    </div>
                    <div>
                        <a class="nopopup" href="password">change password</a>
                    </div>
                  </form>
                </main>
              </body>
            </html>
            EOProfile;

            if ($path == 'password.html') return <<<'EOPassword'
            <!DOCTYPE html>
            <html>
              <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                <title>change password</title>
                <script>
                $(function() {
        
                    $('form.password input[type=password]').change(function() {
                        $('#err_pwd_mismatch').hide();
                    });
        
                    $('form.password').submit(function(e) {
                        if ($('input[name=pw1]').val() != $('input[name=pw2]').val()) {
                            $('#err_pwd_mismatch').show();
                            e.preventDefault();
                        }
                    });
                });
            </script>
              </head>
            
              <body>
                <main>
                  <h1>🔑 change password</h1>
                  <form method="post" action="password" class="password">
                    <div>
                      <input type="password" name="old_password" placeholder="old password" required />
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
                    <div class="right">
                      <input type="submit" value="change" />
                    </div>
                  </form>
                </main>
              </body>
            </html>
            EOPassword;

            if ($path == 'layout.html') return <<<'EOLayout'
            <!doctype html>
            <html>
            
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                <meta name="viewport" content="width=device-width, initial-scale=1.0" />

                <title>{#title} - tsd.serve</title>

                <link rel="icon" type="image/svg+xml" href="/_static/favicon.svg" sizes="any" />
                <link rel="stylesheet" href="/_static/style.css" />

                <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>

            </head>

            <body>
                <header>
                <nav>
                <ul>
                <li><a href="/">⚒</a></li>
                </ul>
                </nav>
                </header>
                <main>
                </main>
                <footer class="debug">
                    {@debug}
                </footer>
                <footer class="sticky">
                <span style="color:#040">tsd.serve</span>  
                <div style="text-align: right;">by&nbsp;&nbsp;&nbsp;&nbsp;Δ@✞εℕᚹⅤᚢᛕ</div>                
                </footer>
            </body>
            </html>
            EOLayout;
        }

        return file_get_contents($viewPath);
    }


    private static function compileExpression(string $exp)
    {
        if ($exp == '.') return '$d';

        $parts = explode('.', $exp);
        if (!$parts)
            return false;
        if (!$parts[0])
            return false;

        $name = substr($parts[0], 1);

        $o = str_split($parts[0])[0] == '@' ? "\$c['$name']" : "\$d['$parts[0]']";
        array_shift($parts);
        foreach ($parts as $p) {
            $o .= "['$p']";
        }

        return $o;
    }

    private static function compileOutput(string $output)
    {
        $parts = explode('|', $output);
        if (!$parts)
            return false;

        if (!$parts[0])
            return false;

        $o     = View::compileExpression($parts[0]);
        //array_shift($parts);
        //foreach formatter append
        return $o;
    }

    static function escapeTemplate(string $template) : string
    {
      return preg_replace(
        ['/\{each ([^\}]+)\}/', '/\{\/each\}/', '/\{none\}/',
         '/\{if ([^\}]+)\}/', '/\{else\}/', '/\{\/if\}/',
         '/\{with ([^\}]+)\}/', '/\{\/with\}/', '/\{without\}/'
        ],
        ['<!--{each $1}-->', '<!--{/each}-->', '<!--{none}-->',
         '<!--{if $1}-->', '<!--{else}-->', '<!--{/if}-->',
         '<!--{with $1}-->', '<!--{/with}-->', '<!--{without}-->'
        ],
        $template
      );
    }

    static function compileTemplate(string $template): string
    {
        $patterns = [
            '/<!--\{if\s+(?<arg>\@?\w[\.\|\w]*)\s*\}-->(?<inner>((?:(?!(<!--\{\/?if|<!--\{else)).)|(?R))*)(<!--\{else\}-->(?<else>((?:(?!<!--\{\/if).)|(?R))*))?<!--\{\/if\}-->/ms' => function ($m) {
                $inner = View::compileTemplate($m['inner']);
                $arg   = View::compileExpression($m['arg']);
                if (key_exists('else', $m))
                {
                    $else = View::compileTemplate($m['else']);
                    return "<?php if (@$arg) { ?>$inner<?php } else { ?>$else<?php } ?>";
                }
                return "<?php if (@$arg) { ?>$inner<?php } ?>";
            },
            '/<!--\{with\s+(?<arg>\@?\w[\.\|\w]*)\s*\}-->(?<inner>((?:(?!(<!--\{\/?with|<!--\{without)).)|(?R))*)(<!--\{without\}-->(?<else>((?:(?!<!--\{\/with).)|(?R))*))?<!--\{\/with\}-->/ms' => function ($m) {
                $inner = View::compileTemplate($m['inner']);
                $arg   = View::compileExpression($m['arg']);
                if (key_exists('else', $m))
                {
                    $else = View::compileTemplate($m['else']);
                    return "<?php if (@$arg) { array_push(\$s, $arg); \$d=$arg; ?>$inner<?php array_pop(\$s); \$d=end(\$s); } else { ?>$else<?php } ?>";
                }
                else return "<?php if (@$arg) { array_push(\$s, $arg); \$d=$arg; ?>$inner<?php array_pop(\$s); \$d=end(\$s); } ?>";
            },
            '/<!--\{each\s+(?<arg>\@?\w[\.\|\w]*)\s*\}-->(?<inner>((?:(?!(<!--\{\/?each|<!--\{none)).)|(?R))*)(<!--\{none\}-->(?<else>((?:(?!<!--\{\/each).)|(?R))*))?<!--\{\/each\}-->/ms' => function ($m) {
              $inner = View::compileTemplate($m['inner']);
              $arg   = View::compileExpression($m['arg']);
              if (key_exists('else', $m))
              {
                  $else = View::compileTemplate($m['else']);
                  return "<?php if (@$arg) { array_push(\$s, \$d); foreach($arg as \$d) { array_push(\$s, \$d);  ?>$inner<?php array_pop(\$s); } array_pop(\$s); \$d=end(\$s); } else { ?>$else<?php } ?>";
              }
              return "<?php if (@$arg) { array_push(\$s, \$d); foreach($arg as \$d) { array_push(\$s, \$d);  ?>$inner<?php array_pop(\$s); } array_pop(\$s); \$d=end(\$s); } ?>";
            },
            '/\{~\}/' => function ($m) {
              $o = View::compileOutput('@pluginRoot');
              return "<?php echo @$o; ?>";
            },
            '/\{\{\{((\@?[a-zA-Z_]\w*(\.\w+)*(\|\w+)*)|\.)\s*\}\}\}/' => function ($m) {
                $o = View::compileOutput($m[1]);
                return "<?php echo @$o; ?>";
            },
            '/\{((\@?[a-zA-Z_]\w*(\.\w+)*(\|\w+)*)|\.)\s*\}/' => function ($m) {
                $o = View::compileOutput($m[1]);
                return "<?php echo htmlspecialchars((is_string(@$o) ? @$o : json_encode(@$o)), ENT_QUOTES, 'UTF-8'); ?>";
            },
        ];

        return preg_replace_callback_array($patterns, $template, -1);
    }

}

class Layout extends View
{

    public function __construct(string $plugin = '')
    {
        parent::__construct('layout', $plugin);
    }
}


interface Label
{

    /**
     *
     * @param string $name
     * @return string
     */
    function getLabel(string $name);
}


class JSONLabels implements Label
{

    private ?JSONLabels $root;
    private ?array $data;

    public function __construct(string $path, ?JSONLabels $root = null)
    {
        if ($root)
            $this->root = $root;

        $file = $path . '/labels.json';

        if (file_exists($file))
            $this->data = json_decode(file_get_contents($file), true);
    }

    function getLabel(string $name)
    {
        $lang = 'de';

        if (!$name)
            return false;
        if ($name[0] == '/') {
            if ($this->root) {
                return $this->root->getLabel(substr($name, 1));
            }
        }
        if (!$this->data || !array_key_exists($name, $this->data))
            return "[not found|$name]";
        if (!array_key_exists($lang, $this->data[$name]))
            return "[not $lang|$name]";
        return $this->data[$name][$lang];
    }
}


class Labels
{

    /**
     *
     * @param string $path
     * @return \tsd\serve\Label
     */
    static function create(string $path)
    {
        $l = new JSONLabels(dirname($path), new JSONLabels('./views'));
        return $l;
    }
}
