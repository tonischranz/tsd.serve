<?php

namespace tsd\serve;

use \DOMDocument;
use \DOMElement;
use \DOMNode;
use \DOMText;
use \DOMXPath;

abstract class ViewEngine
{
    function render($result, ViewContext $ctx, string $accept)
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

        //todo: better
        if ($accept == 'application/json') $this->renderJson($result);
        if ($accept == 'text/xml') $this->renderXml($result);

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
    }

    private function renderJson(Result $result)
    {
        ob_clean();
        echo json_encode($result->data(), JSON_PRETTY_PRINT);
    }

    private function renderXml(Result $result)
    {
        ob_clean();
        echo $result->data()->asXML();
    }

    protected abstract function renderView(IViewResult $result, ViewContext $ctx);
}

/**
 * @Default
 */
class ServeViewEngine extends ViewEngine
{
    const CACHED_VIEWS = '.cached_views.php';
    const CACHED_DIR = '.cached_views';
    const CACHE_DURATION = 30;
    const VIEWS = 'views';

    public static array $cached_views = array();

    function __construct()
    {
        if (file_exists(ServeViewEngine::CACHED_VIEWS)) include ServeViewEngine::CACHED_VIEWS;
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
            
            $template = $v->compile();
            $layout = new Layout($layoutPlugin);
            $layoutTemplate = $layout->compile();

            $t = new DOMDocument;
            $o = new DOMDocument;

            libxml_use_internal_errors(true);
            $t->loadHTML($template);
            $o->loadHTML($layoutTemplate);

            $title = $t->getElementsByTagName('title')[0]->C14N();
            $title = str_replace(['<title>', '</title>'], '', $title);
            $title = str_replace('??>', '?>', $title);
            $x = new DOMXPath($t);
            $xL = new DOMXPath($o);
            $links = $x->query('head/link');
            $styles = $x->query('head/style');
            $scripts = $x->query('head/script');
            $main = $x->query('body/main')[0];

            $lBody = $o->getElementsByTagName('body')[0];
            $lOldMain = $xL->query('//main')[0];
            $lMain = $o->importNode($main, true);
            $lBody->replaceChild($lMain, $lOldMain);

            $lHead = $o->getElementsByTagName('head')[0];

            foreach ($links as $h) $lHead->appendChild($o->importNode($h, true));
            foreach ($styles as $h) $lHead->appendChild($o->importNode($h, true));
            foreach ($scripts as $h) $lHead->appendChild($o->importNode($h, true));


            /*$ctx->title = $title;*/
            $to = $o->saveHTML();
            $to = preg_replace('/\{#title\}/', $title, $to);
            
            $to = preg_replace('/\&lt;\?php/', '<?php', $to);
            $to = preg_replace('/\?\&gt;/', '?>', $to);
            $to = preg_replace('/%20/', ' ', $to);
            $to = preg_replace('/%24/', '$', $to);
            $to = preg_replace('/%5B/', '[', $to);
            $to = preg_replace('/%5D/', ']', $to);
            $to = preg_replace('/PUBLIC.*/', '>', $to, 1);

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
        ob_clean();

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
    private string $template;
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

    public function compile()
    {
        return View::compileTemplate($this->localize($this->template));
    }

    protected function localize($template)
    {
        return View::localizeTemplate($template, $this->labels);
    }

    private static function loadTemplate($path, $plugin)
    {
        $noPluginBasePath = '.' . ServeViewEngine::VIEWS;
        $basePath = $plugin ? '.' . App::PLUGINS . DIRECTORY_SEPARATOR . $plugin . DIRECTORY_SEPARATOR . ServeViewEngine::VIEWS : $noPluginBasePath;
        $alternateBasePath = $plugin ? '.' . ServeViewEngine::VIEWS . DIRECTORY_SEPARATOR . App::PLUGINS . DIRECTORY_SEPARATOR . $plugin : '';

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
                  <p>{message}</p>
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
                <main>
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
                      <button type="submit">go</button>
                    </div
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
                        <button type="submit">yes</button>
                    </div
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
                    <a href="/_login?returnUrl={.}">login again</a>
                  {else}
                    <a href="/">return to home</a>
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
                      <button type="submit">save</button>
                    </div
                    <div>
                        <a href="password">change password</a>
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
                      <button type="submit">change</button>
                    </div
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
                tsd.serve
                </footer>
            </body>
            </html>
            EOLayout;
        }

        return file_get_contents($viewPath);
    }

    private static function localizeTemplate(string $template, Label $labels)
    {
        $t = new DOMDocument;
        $o = new DOMDocument;
        libxml_use_internal_errors(true);
        $t->loadHTML($template);
        View::copyNode($t, $o, $o, $labels);
        $to = $o->saveHTML();

        $to = preg_replace('/%7B/', '{', $to);
        $to = preg_replace('/%7D/', '}', $to);
        
        return $to;
    }

    private static function compileExpression($exp)
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

    private static function compileOutput($output)
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

    private static function compileTemplate($template): string
    {
        $patterns = [
            '/\{each\s+(?<arg>\@?\w[\.\|\w]*)\s*\}(?<inner>((?:(?!(\{\/?each|\{else)).)|(?R))*)(\{else\}(?<else>((?:(?!\{\/each).)|(?R))*))?\{\/each\}/ms' => function ($m) {
                $inner = View::compileTemplate($m['inner']);
                $arg   = View::compileExpression($m['arg']);
                if (key_exists('else', $m))
                {
                    $else = View::compileTemplate($m['else']);
                    return "<?php if (@$arg) { array_push(\$s, \$d); foreach($arg as \$d) { array_push(\$s, \$d);  ?>$inner<?php array_pop(\$s); } array_pop(\$s); \$d=end(\$s); } else { ?>$else<?php } ?>";
                }
                return "<?php if (@$arg) { array_push(\$s, \$d); foreach($arg as \$d) { array_push(\$s, \$d);  ?>$inner<?php array_pop(\$s); } array_pop(\$s); \$d=end(\$s); } ?>";
            },
            '/\{if\s+(?<arg>\@?\w[\.\|\w]*)\s*\}(?<inner>((?:(?!(\{\/?if|\{else)).)|(?R))*)(\{else\}(?<else>((?:(?!\{\/if).)|(?R))*))?\{\/if\}/ms' => function ($m) {
                $inner = View::compileTemplate($m['inner']);
                $arg   = View::compileExpression($m['arg']);
                if (key_exists('else', $m))
                {
                    $else = View::compileTemplate($m['else']);
                    return "<?php if (@$arg) { ?>$inner<?php } else { ?>$else<?php } ?>";
                }
                return "<?php if (@$arg) { ?>$inner<?php } ?>";
            },
            '/\{with\s+(?<arg>\@?\w[\.\|\w]*)\s*\}(?<inner>((?:(?!(\{\/with|\{else)).)|(?R))*)(\{else\}(?<else>((?:(?!\{\/with).)|(?R))*))?\{\/with\}/ms' => function ($m) {
                $inner = View::compileTemplate($m['inner']);
                $arg   = View::compileExpression($m['arg']);
                if (key_exists('else', $m))
                {
                    $else = View::compileTemplate($m['else']);
                    return "<?php if (@$arg) { array_push(\$s, $arg); \$d=$arg; ?>$inner<?php array_pop(\$s); \$d=end(\$s); } else { ?>$else<?php } ?>";
                }
                else return "<?php if (@$arg) { array_push(\$s, $arg); \$d=$arg; ?>$inner<?php array_pop(\$s); \$d=end(\$s); } ?>";
            },
            '/\{((\@?[a-zA-Z_]\w*(\.\w+)*(\|\w+)*)|\.)\s*\}/' => function ($m) {
                $o = View::compileOutput($m[1]);
                return "<?php echo @$o; ?>";                
            },
        ];

        return preg_replace_callback_array($patterns, $template, -1);
    }

    private static function copyNode(DOMNode $t, DOMDocument $o, DOMNode $p, Label $l)
    {
        switch ($t->nodeType) {
            case XML_HTML_DOCUMENT_NODE:
                View::copyNode($t->documentElement, $o, $o, $l);
                break;
            case XML_ELEMENT_NODE:
                $n = $o->importNode($t);
                $n = $p->appendChild($n);
                View::localizeAttributes($n, $l);
                foreach ($t->childNodes as $c) View::copyNode($c, $o, $n, $l);
                break;
            case XML_CDATA_SECTION_NODE:
                View::copyCData($t, $o, $p);
            case XML_TEXT_NODE:
                View::copyText($t, $o, $p, $l);
                break;
        }
    }

    private static function localizeAttributes(DOMElement $e, Label $l)
    {
        foreach ($e->attributes as $a) {
            if ($e->nodeName == 'input' && $a->name == 'placeholder') $a->value = View::localizeText($a->value, $l);
            if ($e->nodeName == 'img' && $a->name == 'alt') $a->value = View::localizeText($a->value, $l);
        }
    }

    private static function copyCData(DOMText $t, DOMDocument $o, DOMElement $p)
    {
        $p->appendChild($o->createCDATASection($t->data));
    }

    private static function copyText(DOMText $t, DOMDocument $o, DOMElement $p, Label $l)
    {
        if ($t->isElementContentWhitespace()) return;
        if ($p->nodeName == 'style' || $p->nodeName == 'script') return;
        else {
            //todo: MD
            $p->appendChild($o->createTextNode(View::localizeText($t->wholeText, $l)));
        }
    }

    private static function localizeText(string $s, Label $l)
    {
        return $s;
        //todo: localize!
        //return $l->getLabel($s);
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

    private $root;
    private $data;

    public function __construct(string $path, JSONLabels $root = null)
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
