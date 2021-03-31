<?php

namespace tsd\serve;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;
use SimpleXMLElement;

/**
 * @Implementation tsd\serve\ServeViewEngine
 */
abstract class ViewEngine
{
    function render($result, $accept)
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
            $this->renderView($result);
        }
    }

    private function renderJson(Result $result)
    {
        ob_clean();
        echo json_encode($result->data());
    }

    private function renderXml(Result $result)
    {
        ob_clean();
        echo $result->data()->asXML();
    }

    protected abstract function renderView(IViewResult $result);
}

interface IViewResult
{
    function view() : string;
    function plugin() : string;
    function ctx() : ViewContext;
    function data();
}

class ViewContext
{
    public $menu;
    public $error;
    public $member;
    public $title;
}



/**
 * @Default
 */
class ServeViewEngine extends ViewEngine
{
    const VIEWS = 'views';

    function renderView(IViewResult $result)
    {
        $v = new View($result->view(), $result->plugin());
        $v->render($result->data(), $result->ctx());
    }
}

class View
{
    private $path;
    private $plugin;
    private $labels;

    function __construct(string $path, string $plugin = '')
    {
        $this->path   = $path . '.html';
        $this->plugin = $plugin;
        $this->labels = Labels::create($path);
    }

    function render($data, ViewContext $ctx)
    {
        $template = $this->compile();
        $layout = new Layout($this->plugin);
        $layoutTemplate = $layout->compile();

        $t = new DOMDocument;
        $o = new DOMDocument;
        //$s = new SimpleXMLElement($template);

        //$t->strictErrorChecking = false;
        libxml_use_internal_errors(true);
        $t->loadHTML($template);
        $o->loadHTML($layoutTemplate);

        $title = $t->getElementsByTagName('title')[0]->textContent;
        $x = new DOMXPath($t);
        $xL = new DOMXPath($o);
        $links = $x->query('head/link');
        $styles = $x->query('head/style');
        $scripts = $x->query('head/script');
        $main = $x->query('body/main')[0];

        $lBody = $o->getElementsByTagName('body')[0];
        $lOldMain = $xL->query('body/main')[0];
        $lMain = $o->importNode($main, true);
        $lBody->replaceChild($lMain, $lOldMain);

        $lHead = $o->getElementsByTagName('head')[0];

        foreach($links as $h) $lHead->appendChild($o->importNode($h, true));
        foreach($styles as $h) $lHead->appendChild($o->importNode($h, true));
        foreach($scripts as $h) $lHead->appendChild($o->importNode($h, true));
        

        $ctx->title = $title;
        
        View::run(preg_replace('/PUBLIC.*/', '>', $o->saveHTML(), 1), $data, $ctx);
    }

    public function compile()
    {
        return View::compileTemplate($this->localize($this->load()));
    }

    protected function load()
    {
        return View::loadTemplate($this->path, $this->plugin);
    }

    protected function localize($template)
    {
        return View::localizeTemplate($template, $this->labels);
    }

    private static function loadTemplate($path, $plugin)    
    {
        $basePath = $plugin ? '.' . App::PLUGINS . DIRECTORY_SEPARATOR . $plugin . DIRECTORY_SEPARATOR . ServeViewEngine::VIEWS : '.' . ServeViewEngine::VIEWS;
        $alternateBasePath = $plugin ? '.' . ServeViewEngine::VIEWS . DIRECTORY_SEPARATOR . App::PLUGINS . DIRECTORY_SEPARATOR . $plugin : '';

        $viewPath = $alternateBasePath ? $alternateBasePath . DIRECTORY_SEPARATOR . $path : $basePath . DIRECTORY_SEPARATOR . $path;

        if (!file_exists($viewPath) && $alternateBasePath) $viewPath = $basePath . DIRECTORY_SEPARATOR . $path;

        if (!file_exists($viewPath))
        {
            if ($path == 'error.html') return <<<'EOError'
            <!DOCTYPE html>
            <html>
              <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                <title>error</title>
                <style type="text/css"></style>
              </head>
            
              <body>
                <main>
                  <h1>💥 error</h1>
                  <p>{message}</p>
                </main>
              </body>
            </html>
            EOError;

            if ($path == 'layout.html') return <<<'EOLayout'
            <!doctype html>
            <html>
            
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                <meta name="viewport" content="width=device-width, initial-scale=1.0" />

                <title>{title} - tsd.serve</title>

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

                {head}
            </head>

            <body>
                <header>
                <nav>
                </nav>
                </header>
                <main>
                {content}
                <footer class="debug">
                    {debug}
                </footer>
                </main>
                <footer>
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
        /*$patterns          = [
            '#\[(?<name>/?\w+\s*\([,\s\w]+\)):\s*\{(?<args>.*)\}\s*\]#' =>
            function ($m) use ($labels) {
                $params_pattern = '#\((.*?)\)#';
                $params_matches = [];
                if (!preg_match($params_pattern, $m['name'], $params_matches))
                    return false;

                $params         = $params_matches[1];
                $o = "{label $params with $m[args]}" . $labels->getLabel($m['name']) . '{/label}';
                return $o;
            },
            '#\[(/?\w+)\s*\]#' => function ($m) use ($labels) {
                return $labels->getLabel($m[1]);
            }
        ];

        $o = preg_replace_callback_array($patterns, $template, -1);

        return $o;*/

        $t = new DOMDocument;
        $o = new DOMDocument;
        //$s = new SimpleXMLElement($template);

        //$t->strictErrorChecking = false;
        libxml_use_internal_errors(true);
        $t->loadHTML($template);

        View::copyNode($t, $o, $o, $labels);

        return $o->saveHTML();
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

        $o = $parts[0][0] == '@' ? "\$$name" : "\$d['$parts[0]']";
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

    /*private static function compileLabel($label)
    {
        $pattern = '#\{(\$?\w+(\.\w+)*(\|\w+)*)\s*?\}#';

        return '?>' . preg_replace_callback($pattern, function ($m) {
            $o = View::compileOutput($m[1]);
            return "<?php echo $o; ?>";
        }, $label);
    }*/

    private static function compileTemplate($template) : string
    {
        $patterns = [
            /*'/\{label\s*(?<params>[,\w\s]+)\s+with\s+(?<args>.*?)\}(?<label>.*?)\{\/label\}/' => function ($m) {
                $args   = [];
                $i      = 0;
                $params = explode(',', $m['params']);
                $arg_ex = explode(',', $m['args']);
                foreach ($arg_ex as $a) {
                    $exp    = View::compileExpression($a);
                    $args[] = "'$params[$i]'=>$exp";
                    $i++;
                }

                $as       = implode(',', $args);
                $label    = addslashes(View::compileLabel($m['label']));
                return "<?php call_user_func(function(\$d){ eval('$label'); }, [$as]); ?>";
            },*/
            '/\{each\s+(?<arg>\@?\w[\.\|\w]*)\s*\}(?<inner>((?:(?!\{\/?each).)|(?R))*)(\{else\}(?<else>((?:(?!\{\/?each).)|(?R))*))?\{\/each\}/ms' => function ($m) {
                $inner = View::compileTemplate($m['inner']);
                $arg   = View::compileExpression($m['arg']);
                return "<?php if (isset($arg) && $arg) { array_push(\$s, \$d); foreach($arg as \$d) { ?>$inner<?php } \$d=array_pop(\$s); } ?>";
            },
            '/\{if\s+(?<arg>\@?\w[\.\|\w]*)\s*\}(?<inner>((?:(?!\{\/?if).)|(?R))*)(\{else\}(?<else>((?:(?!\{\/?if).)|(?R))*))?\{\/if\}/ms' => function ($m) {
                $inner = View::compileTemplate($m['inner']);
                $arg   = View::compileExpression($m['arg']);
                return "<?php if (isset($arg) && $arg) { ?>$inner<?php } ?>";
            },
            '/\{with\s+(?<arg>\@?\w[\.\|\w]*)\s*\}(?<inner>((?:(?!\{\/?if).)|(?R))*)\{\/with\}/ms' => function ($m) {
                $inner = View::compileTemplate($m['inner']);
                $arg   = View::compileExpression($m['arg']);
                return "<?php if (isset($arg) && $arg && array_push(\$s, $arg)) { ?>$inner<?php } \$d=array_pop(\$s) ?>";
            },
            '/\{((\@?[a-zA-Z_]\w*(\.\w+)*(\|\w+)*)|\.)\s*\}/' => function ($m) {
                $o = View::compileOutput($m[1]);
                return "<?php if (isset($o)) echo $o; ?>";
            },
        ];

        return preg_replace_callback_array($patterns, $template, -1);        
    }

    private static function copyNode(DOMNode $t, DOMDocument $o, DOMNode $p, Label $l)
    {
        switch ($t->nodeType)
        {
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
                View::copyText($t, $o, $p, $l);
            case XML_TEXT_NODE:
                View::copyText($t, $o, $p, $l);
                break;
        }
    }

    private static function localizeAttributes(DOMElement $e, Label $l)
    {
        foreach ($e->attributes as $a)
        {
            if ($e->nodeName == 'input' && $a->name == 'placeholder') $a->value = View::localizeText($a->value, $l);
            if ($e->nodeName == 'img' && $a->name == 'alt') $a->value = View::localizeText($a->value, $l);
        }
    }

    private static function copyText(DOMText $t, DOMDocument $o, DOMElement $p, Label $l)
    {
        if ($t->isElementContentWhitespace()) return;
        if ($p->nodeName == 'style' || $p->nodeName == 'script')
        {
            $p->appendChild($o->createCDATASection($t->data));
        }
        else
        {
            //todo: MD
            $p->appendChild($o->createTextNode(View::localizeText($t->wholeText, $l)));
        }
    }

    private static function localizeText (string $s, Label $l)
    {
        return $s;
        //todo: localize!
        //return $l->getLabel($s);
    }

    private static function run(string $view, array $data, ViewContext $ctx)
    {
        $d     = $data;
        $model = $data;
        $s  = [];
        foreach ($ctx as $k => $v) $$k = $v;

        $debug = ob_get_contents();

        eval('?>' . $view . '<?php');

        /*$debug = ob_get_contents();
        ob_clean();
        ob_start();
        eval('?>' . $view . '<?php');
        $html  = ob_get_contents();
        ob_end_clean();

        $m   = [];*/
        $exp = '#<\s*title.*?>(?<title>.*)<\s*/title.*?>(?<head>.*)<\s*/head.*?>.*<\s*body.*?>.*<\s*main.*?>(?<content>.*)<\s*/main.*>.*<\s*/body.*>#ims';

        /*if (preg_match($exp, $html, $m)) {
            $layoutData = [
                'title'   => $m['title'], 'head'    => $m['head'],
                'content' => $m['content'],
                'debug'   => $debug
            ];

            $layout = new Layout();
            $layout->render(array_merge($data, $layoutData), $ctx);
        } else {
            echo 'Regex Content Lookup failed';
            echo $html;
            echo $debug;
        }*/
    }
}

class Layout extends View
{

    public function __construct(string $plugin = '')
    {
        parent::__construct('layout', $plugin);
    }

    /*public function render($data, $ctx)
    {
        $template = $this->compile($this->localize($this->load()));

        Layout::renderInt($template, $data, $ctx);
    }

    private static function renderInt($view, $data, ViewContext $ctx)
    {
        $d = $data;
        $s = [];
        foreach ($ctx as $k => $v) $$k = $v;

//        echo $view;
        eval('?>' . $view . '<?php');

        unset($d);
    }*/
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
