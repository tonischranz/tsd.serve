<?php

namespace tsd\serve;

class View
{

  private $path;
  private $labels;
  private $template;
  private $localized;
  private $compiled;

  function __construct(string $path)
  {
    $this->path   = $path;
    $this->labels = Labels::create($path);
  }

  function render(array $data)
  {
    $this->load();
    $this->localize();
    $this->compile();

    View::renderInt($this->compiled, $data);
  }

  protected function getCompiled()
  {
    return $this->compiled;
  }

  protected function compile()
  {
    $this->compiled = View::compileTemplate($this->localized);
  }

  protected function load()
  {
    $this->template = View::loadTemplate($this->path);
  }

  protected function localize()
  {
    $this->localized = View::localizeTemplate($this->template, $this->labels);
  }

  private static function loadTemplate($path)
  {
    return file_get_contents($path);
  }

  private static function localizeTemplate(string $template, Label $labels)
  {
    $patterns          = ['#\[(?<name>/?\w+\s*\([,\s\w]+\)):\s*\{(?<args>.*)\}\s*\]#' => function ($m) use ($labels) {
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
        }];


            $o = preg_replace_callback_array($patterns, $template);

            return $o;
          }

          private static function compileExpression($exp)
          {
            $parts = explode('.', $exp);
            if (!$parts)
              return false;
            if (!$parts[0])
              return false;
            $o     = $parts[0][0] == '$' ? $parts[0] : "\$d['$parts[0]']";
            array_shift($parts);
            foreach ($parts as $p)
            {
              $o.="['$p']";
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

            return '?>' . preg_replace_callback($pattern, function ($m) {
                      $o = View::compileOutput($m[1]);
                      return "<?php echo $o; ?>";
                    }, $label);
          }

          private static function compileTemplate($template)
          {
            $patterns = ['#\{label\s*(?<params>[,\w\s]+)\s+with\s+(?<args>.*?)\}(?<label>.*?)\{/label\}#' => function ($m) {
                  $args   = [];
                  $i      = 0;
                  $params = explode(',', $m['params']);
                  foreach (explode(',', $m['args']) as $a)
                  {
                    $exp    = View::compileExpression($a);
                    $args[] = "'$params[$i]'=>$exp";
                    $i++;
                  }
                  $as       = implode(',', $args);
                  $label    = addslashes(View::compileLabel($m['label']));
                  return "<?php call_user_func(function(\$d){ eval('$label'); }, [$as]); ?>";
                },
                        '#\{foreach\s*(?<var>\$\w+)\s+in\s+(?<arg>.*?)\}(?<inner>.*)\{/foreach\}#ms' => function ($m) {
                  $inner = View::compileTemplate($m['inner']);
                  $arg   = View::compileExpression($m['arg']);
                  return "<?php foreach($arg as $m[var]) {\n$inner\n} ?>";
                },
                        '#\{(\$?\w+(\.\w+)*(\|\w+)*)\s*?\}#' => function ($m) {
                  $o = View::compileOutput($m[1]);
                  return "<?php echo $o; ?>";
                }
                    ];

                    $o = preg_replace_callback_array($patterns, $template);

                    return '?>' . $o . '<?php ';
                  }

                  private static function renderInt(string $view, array $data)
                  {
                    $d     = $data['viewData'];
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
                      $layoutData = ['title'   => $m['title'], 'head'    => $m['head'],
                          'content' => $m['content'],
                          'debug'   => $debug];

                      $layout = new Layout();
                      $layout->render(array_merge($data, $layoutData));
                    }
                    else
                    {
                      echo 'Regex Content Lookup failed';
                    }
                  }

                }
