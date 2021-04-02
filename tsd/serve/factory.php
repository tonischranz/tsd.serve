<?php

namespace tsd\serve;

use \ReflectionClass;
use \ReflectionProperty;
use \RecursiveDirectoryIterator;
use \RecursiveIteratorIterator;
use \RegexIterator;
use \RecursiveRegexIterator;

/**
 * The Factory
 * 
 * @author Toni Schranz
 */
class Factory
{
    private array $config;
    private array $plugins;

    function __construct(array $config, array $plugins)
    {
        $this->config = $config;
        $this->plugins = $plugins;
    }

    //function createAll($type, )
    //function createA($type, $$)

    function create(string $type, string $name = '', ?InjectionContext $ctx = null)
    {
        $in = $this->getInjection($type, $name, $ctx);
        return $in->inject($this);
    }

    function getInjection(string $type, string $name, ?InjectionContext $ctx)
    {
        $plugin = '';

        //if (!class_exists($type)) {
        $parts = explode('\\', $type);
        $plugin = '';
        $found = false;
        $ramaining = $parts;

        foreach ($parts as $p) {
            array_shift($ramaining);

            if (!$plugin) $plugin = $p;
            else $plugin .= ".$p";

            if (in_array($plugin, $this->plugins)) {
                $filename = '.'.App::PLUGINS . "/$plugin/src/" . join('/', $ramaining) . '.php';
                if (file_exists($filename)) {
                    $found = true;
                    require_once $filename;
                    break;
                }
            }
        }

        if (!$found) $plugin = '';
        //}

        $t = new ReflectionClass($type);

        $config = array_key_exists($name, $this->config) ?
            $this->config[$name] : [];
        $nconfig = $ctx ? (array_key_exists("$ctx->plugin.$name", $this->config) ?
            $this->config["$ctx->plugin.$name"] : []) : [];
        $fnconfig = $ctx ? (array_key_exists("$ctx->fullname.$name", $this->config) ?
            $this->config["$ctx->fullname.$name"] : []) : [];

        $config = array_merge_recursive($config, $nconfig, $fnconfig);

        if ($t->isAbstract()) {
            if ($config && isset($config['_mode'])) {
                $t = $this->getImplementation($t, $plugin, $config['_mode']);
            } else {
                $t = $this->getImplementation($t, $plugin);
            }
        }

        return new Injection($t, $plugin, $name, $ctx, $config);
    }

    function getImplementation(ReflectionClass $type, string &$plugin, string $mode = null)
    {
        $doc = $type->getDocComment();
        $matches = [];

        //todo: use plugins to search classes
        if (preg_match_all('/@Implementation\s((\w|\\\)*)/', $doc, $matches) > 0) {
            foreach ($matches[1] as $i) {
                $itype = new ReflectionClass($i);

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
            $Directory = new RecursiveDirectoryIterator('.'.App::PLUGINS . "/$p/src");
            $Iterator = new RecursiveIteratorIterator($Directory);
            $Regex = new RegexIterator($Iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

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

    function plugins() : array
    {
        return $this->plugins;
    }
}


class Injection
{
    private ReflectionClass $type;

    private ?InjectionContext $ctx;

    private string $plugin;

    private array $config;

    private string $name;


    function __construct(ReflectionClass $type, string $plugin, string $name, ?InjectionContext $ctx, array $config = [])
    {
        $this->name = $name;
        $this->type = $type;
        $this->plugin = $plugin;
        $this->config = $config;
        $this->ctx = $ctx;
    }

    function inject(Factory $factory)
    {
        $con = $this->type->getConstructor();
        $par = $con ? $con->getParameters() : [];
        $args = [];
        $ctx = $this->ctx ?? new InjectionContext();

        $myctx = new InjectionContext();
        $myctx->name = $this->name;
        $myctx->fullname = $ctx ? "$ctx->fullname.$this->name" : $this->name;
        $myctx->plugin = $this->plugin;

        foreach ($par as $p) {
            if ($p->isArray() && $p->name == '_config' && $this->name)
                $args[] = $this->config;
            else if ($p->hasType() && !$p->isArray() && !$p->getType()->isBuiltIn()) {
                $name = $p->getType()->getName();
                if ($name == 'tsd\serve\Factory') $args[] = $factory;
                else $args[] = $factory->create($name, $p->getName(), $myctx);
            } else if (isset($this->config[$p->name]))
                $args[] = $this->config[$p->name];
            else if ($p->name == '_name')
                $args[] = $this->name;
            else if ($p->name == '_plugin')
                $args[] = $ctx->plugin;
            else if ($p->name == '_plugins')
                $args[] = $factory->plugins();
            else if ($p->name == '_fullname')
                $args[] = $myctx->fullname;
            else if ($p->isDefaultValueAvailable())
                $args[] = $p->getDefaultValue();
        }

        $type = $this->type->name;
        $obj = new $type(...$args);

        foreach ($this->type->getProperties($args ? 
            ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED : 
            ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE)
             as $p) {
            unset($val);

            if ($p->hasType() && !$p->getType()->isBuiltIn())
                $val = $factory->create($p->getType()->getName(), $p->getName(), $myctx);
            else if (isset($this->config[$p->name]))
                $val = $this->config[$p->name];
            else if ($p->name == '_name')
                $val = $this->name;
            else if ($p->name == '_plugin')
                $val = $ctx->plugin;
            else if ($p->name == '_fullname')
                $val = $myctx->fullname;

            if (isset($val)) {
                $p->setAccessible(true);
                $p->setValue($obj, $val);
            }
        }
        return $obj;
    }
}

class InjectionContext
{
    public string $name = '';

    public string $fullname = '';

    public string $plugin = '';
}
