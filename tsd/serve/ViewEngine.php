<?php

namespace tsd\serve;

/**
 * @Implementation tsd\serve\ServeViewEngine
 */
abstract class ViewEngine
{
    function render($result, $accept)
    {
        if ($result instanceof tsd\serve\AccessDeniedException) $result = new ErrorResult (403, $result);
        if ($result instanceof tsd\serve\NotFoundException) $result = new ErrorResult (404, $result);
        if ($result instanceof \Exception) $result = new ErrorResult (500, $result->getMessage());
        if (!($result instanceof Result)) $result = new DataResult ($result);

        http_response_code($result->getStatusCode());
        $headers = $result->getHeaders();
        foreach ($headers as $h)
        {
            header($h);
        }

        //todo: better
        if ($accept == 'application/json') $this->renderJson($result);
        if ($accept == 'text/xml') $this->renderXml($result);

        if ($result instanceof ViewResult) 
        {
            $this->renderView($result);
        }
    }

    private function renderJson(Result $result)
    {
        ob_clean();        
        echo json_encode($result->getData());
    }

    private function renderXml(Result $result)
    {
        ob_clean();
        echo $result->getData()->asXML();
    }

    protected abstract function renderView (ViewResult $result);
}

/**
 * @Default
 */
class ServeViewEngine extends ViewEngine
{
    const VIEWS = '.';

    function renderView(ViewResult $result)
    {
        $v = new View (ServeViewEngine::VIEWS.'/'.$result->getView());
        $v->render ($result->getData());        
    }
}

class View
{
    private $path;
      private $labels;

    function __construct(string $path)
    {
        $this->path   = $path.'.html';
        $this->labels = Labels::create($path);
    }

    function render($data)
    {
        $template = $this->compile($this->localize($this->load()));
        View::run($template, $data);
    }

      protected function compile($template)
    {
        return View::compileTemplate($template);
    }

    protected function load()
    {
        return View::loadTemplate($this->path);
    }

    protected function localize($template)
    {
        return View::localizeTemplate($template, $this->labels);
    }

    private static function loadTemplate($path)
    {
        return file_get_contents($path);
    }

    private static function localizeTemplate(string $template, Label $labels)
    {
        $patterns          = [
            '#\[(?<name>/?\w+\s*\([,\s\w]+\)):\s*\{(?<args>.*)\}\s*\]#' =>
            function ($m) use ($labels) 
            {
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

        return $o;
    }

    private static function compileExpression($exp)
    {
        $parts = explode('.', $exp);
        if (!$parts)
            return false;
        if (!$parts[0])
            return false;
        
        $o = $parts[0][0] == '$' ? $parts[0] : "\$d['$parts[0]']";
        array_shift($parts);
        foreach ($parts as $p) 
        {
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

    private static function compileLabel($label)
    {
        $pattern = '#\{(\$?\w+(\.\w+)*(\|\w+)*)\s*?\}#';

        return '?>' . preg_replace_callback($pattern, function ($m) 
        {
            $o = View::compileOutput($m[1]);
            return "<?php echo $o; ?>";
        }, $label);
    }

    private static function compileTemplate($template)
    {
        $patterns = [
            '#\{label\s*(?<params>[,\w\s]+)\s+with\s+(?<args>.*?)\}(?<label>.*?)\{/label\}#' => function ($m) 
            {
                $args   = [];
                $i      = 0;
                $params = explode(',', $m['params']);
                $arg_ex = explode(',', $m['args']);
                foreach ($arg_ex as $a) 
                {
                    $exp    = View::compileExpression($a);
                    $args[] = "'$params[$i]'=>$exp";
                    $i++;
                }
        
                $as       = implode(',', $args);
                $label    = addslashes(View::compileLabel($m['label']));
                return "<?php call_user_func(function(\$d){ eval('$label'); }, [$as]); ?>";
            },
            '#\{foreach\s*(?<var>\$\w+)\s+in\s+(?<arg>.*?)\}(?<inner>.*)\{/foreach\}#ms' => function ($m) 
            {
                $inner = View::compileTemplate($m['inner']);
                $arg   = View::compileExpression($m['arg']);
                return "<?php foreach($arg as $m[var]) {\n$inner\n} ?>";
            },
            '#\{(\$?\w+(\.\w+)*(\|\w+)*)\s*?\}#' => function ($m) 
            {
                $o = View::compileOutput($m[1]);
                return "<?php echo $o; ?>";
            }
        ];

        $o = preg_replace_callback_array($patterns, $template, -1);

        return '?>' . $o . '<?php ';
    }

    private static function run(string $view, array $data)
    {
        $d     = $data;//['viewData'];
        $debug = ob_get_contents();
        ob_clean();
        ob_start();
        eval($view);
        $html  = ob_get_contents();
        ob_end_clean();

        $m   = [];
        $exp = '#<\s*title.*?>(?<title>.*)<\s*/title.*?>(?<head>.*)<\s*/head.*?>.*<\s*body.*?>(?<content>.*)<\s*/body.*>#ims';

        if (preg_match($exp, $html, $m)) 
        {
            $layoutData = [
                'title'   => $m['title'], 'head'    => $m['head'],
                'content' => $m['content'],
                'debug'   => $debug
            ];

            $layout = new Layout();
            $layout->render(array_merge($data, $layoutData));
        }
        else 
        {
            echo 'Regex Content Lookup failed';
        }
    }
}

class Layout extends View
{

  public function __construct ()
  {
    parent::__construct ('./views/layout');
  }

  public function render ($data)
  {
    $template = $this->compile ($this->localize ($this->load ()));

    Layout::renderInt ($template, $data);
  }
  
  private static function renderInt ($view, $data)
  {
    $d = $data;

    eval ($view);

    unset ($d);
  }
}


interface Label
{

  /**
   *
   * @param string $name
   * @return string
   */
  function getLabel (string $name);
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
    if ($name[0] == '/')
    {
      if ($this->root)
      {
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