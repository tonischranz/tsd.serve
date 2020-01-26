<?php

namespace tsd\serve;

use ReflectionClass;
use ReflectionType;

/**
 * The Factory
 * 
 * @author Toni Schranz
 */
class Factory
{
    private $config;
    private $plugins;

    function __construct(array $config, array $plugins)
    {
        $this->config = $config;
        $this->plugins = $plugins;
    }

    //function createAll($type, )
    //function createA($type, $$)

    function create(string $type, string $name = '', InjectionContext $ctx = null)
    {
        $in = $this->getInjection($type, $name);
        return $in->inject($name, $ctx ?? new InjectionContext(), $this);
    }

    function getInjection(string $type, string $name)
    {
        $plugin = '';

        if (!class_exists($type)) {
            $parts = explode('\\', $type);
            $plugin = '';
            $ramaining = $parts;

            foreach ($parts as $p) {
                array_shift($ramaining);

                if (!$plugin) $plugin = $p;
                else $plugin .= ".$p";

                if (in_array($plugin, $this->plugins)) {
                    $filename = App::PLUGINS . "/$plugin/src/" . join('/', $ramaining) . '.php';
                    if (file_exists($filename)) {
                        require_once $filename;
                        break;
                    }
                }
            }
        }

        $t = new \ReflectionClass($type);

        $config = array_key_exists($name, $this->config) ?
            $this->config[$name] : [];

        if ($t->isAbstract()) {
            if ($config && isset($config['mode'])) {
                $t = $this->getImplementation($t, $plugin, $config['mode']);
            } else {
                $t = $this->getImplementation($t, $plugin);
            }
        }

        return new Injection($t, $plugin, $config);
    }

    function getImplementation(\ReflectionClass $type, string &$plugin, string $mode = null)
    {
        $doc = $type->getDocComment();
        $matches = [];

        //todo: use plugins to search classes
        if (preg_match_all('/@Implementation\s((\w|\\\)*)/', $doc, $matches) > 0) {
            foreach ($matches[1] as $i) {
                $itype = new \ReflectionClass($i);

                $idoc = $itype->getDocComment();
                $imatches = [];


                if (!$mode && preg_match('/@Default/', $idoc)) {
                    return $itype;
                }

                if (preg_match_all('/@Mode\s(\w+)/', $idoc, $imatches) > 0) {
                    foreach ($imatches[1] as $m) {
                        if ($m == $mode) return $itype;
                    }
                }
            }
        }

        foreach ($this->plugins as $p) {
            $Directory = new \RecursiveDirectoryIterator(App::PLUGINS . "/$p/src");
            $Iterator = new \RecursiveIteratorIterator($Directory);
            $Regex = new \RegexIterator($Iterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);

            foreach ($Regex as $r) {
                $c = file_get_contents($r, false, null, 0, 100);
                $m = [];

                if ($mode && preg_match('/namespace\s([\w\\]+);\/\*\*.*@Mode\s(\w+).*\*\/.*class\s+(\w+)/', $c, $m)) {
                    if ($m[1] == $mode) {
                        require_once $r;

                        $try = new ReflectionClass("$m[1]\\$m[2]");
                        if ($try->isSubclassOf($type->name)) {
                            $plugin = $p;
                            return $try;
                        }
                    }
                }

                if (!$mode && preg_match('/namespace\s([\w\\]+);\/\*\*.*@Default.*\*\/.*class\s+(\w+)/', $c, $m)) {
                    require_once $r;

                    $try = new ReflectionClass("$m[1]\\$m[2]");
                    if ($try->isSubclassOf($type->name)) {
                        $plugin = $p;
                        return $try;
                    }
                }
            }
        }
    }
}


class Injection
{
    /** @var \ReflectionClass */
    private $type;

    /** @var string */
    private $plugin;


    /** @var array */
    private $config;

    function __construct(\ReflectionClass $type, string $plugin, array $config = [])
    {
        $this->type = $type;
        $this->plugin = $plugin;
        $this->config = $config;
    }

    function inject(string $name, InjectionContext $ctx, Factory $factory)
    {
        $con = $this->type->getConstructor();
        $par = $con ? $con->getParameters() : [];
        $args = [];

        $myctx = new InjectionContext();
        $myctx->name = $name;
        $myctx->fullname = $ctx ? "$ctx->fullname.$name" : $name;
        $myctx->plugin = $this->plugin;

        foreach ($par as $p) {
            if ($p->isArray() && $p->name == 'config' && $name)
                $args[] = $this->config;
            else if ($p->hasType() && !$p->isArray())
                $args[] = $factory->create($p->getType()->getName(), $p->getName(), $myctx);
            else if ($this->config[$p->name])
                $args[] = $this->config[$p->name];
            else if ($p->name == 'name')
                $args[] = $name;
            else if ($p->name == 'plugin')
                $args[] = $ctx->plugin;
            else if ($p->name == 'fullname')
                $args[] = $myctx->fullname;
            else if ($p->isArray())
                $args[] = [];
            else
                $args[] = null;
        }

        $type = $this->type->name;
        return $con ? $this->type->newInstanceArgs($args) : new $type();
    }
}
