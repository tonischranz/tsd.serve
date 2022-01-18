<?php

namespace tsd\serve;

use \ReflectionClass;
use \ReflectionProperty;
use \RecursiveDirectoryIterator;
use \RecursiveIteratorIterator;
use \RegexIterator;
use \RecursiveRegexIterator;
use ReflectionNamedType;

/**
 * The Factory
 * 
 * @author Toni Schranz
 */
class Factory
{
    public static array $classes = array();
    private array $config;
    private array $singletons = array();

    function __construct(array $config)
    {
        $this->config = $config;
        $this->singletons['tsd\\serve\\Factory'] = $this;

        spl_autoload_register(function ($name) {
            $parts = explode('\\', $name);
            $n = $i = count($parts);
            $nm = $parts[$n - 1];

            while ($i > 0) {
                $i--;
                $ns = implode('\\', array_slice($parts, 0, $i));
                $dn = implode(DIRECTORY_SEPARATOR, array_slice($parts, $i, $n - $i - 1));

                foreach (App::$plugins as $k => $p) {
                    if (is_array($p)) {
                        if (@$p['namespace'] == $ns) {
                            $file = App::PLUGINS . DIRECTORY_SEPARATOR . $k . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $dn . DIRECTORY_SEPARATOR . $nm . '.php';
                            if (file_exists($file)) {
                                include $file;
                                return;
                            }
                        }
                    }
                }
            }
        });

        $files = get_included_files();
        $sfiles = [];
        $stats = '';
        foreach ($files as $f) {
            if (basename($f) == 'App.php') {
                $sfiles = glob(dirname($f) . DIRECTORY_SEPARATOR . '*.php');

                foreach ($sfiles as $sf) $stats .= stat($sf)['mtime'];
                break;
            }
            if (basename($f) == '.tsd.serve.php') {
                $stats .= stat($f)['mtime'];
                break;
            }
        }

        $plugin_files = Factory::rglob(App::PLUGINS . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . 'src', '*.php');
        foreach ($plugin_files as $pf) $stats .= stat($pf)['mtime'];


        $md5 = md5($stats);
        $cache_file = ".cached_classes.$md5.php";

        if (file_exists($cache_file)) include $cache_file;
        else {
            array_map('unlink', glob(".cached_classes.*.php"));

            foreach ($sfiles as $sf) require_once $sf;
            foreach ($plugin_files as $pf) require_once $pf;

            $classes = get_declared_classes();

            foreach ($classes as $c) {
                $default = false;
                $modes = array();
                $mode_matches = array();
                $r = new ReflectionClass($c);
                $com = $r->getDocComment();

                $default = preg_match('/@Default/', $com);
                if (preg_match_all('/@Mode\s(\w+)/', $com, $mode_matches)) $modes = $mode_matches[1];

                if ($default || $modes) {
                    foreach ($r->getInterfaces() as $i) {
                        if (!$i->isUserDefined()) continue;
                        if (@!Factory::$classes[$i->getName()]) Factory::$classes[$i->getName()] = [];
                        if ($default) Factory::$classes[$i->getName()][0] = $c;
                        foreach ($modes as $mode) {
                            if (@!Factory::$classes[$i->getName()][1]) Factory::$classes[$i->getName()][1] = [];
                            Factory::$classes[$i->getName()][1][$mode] = $c;
                        }
                    }

                    $p = $r;

                    do {
                        if (!$p->isUserDefined()) break;
                        if (@!Factory::$classes[$p->getName()]) Factory::$classes[$p->getName()] = [];
                        if ($default) Factory::$classes[$p->getName()][0] = $c;
                        foreach ($modes as $mode) {
                            if (@!Factory::$classes[$p->getName()][1]) Factory::$classes[$p->getName()][1] = [];
                            Factory::$classes[$p->getName()][1][$mode] = $c;
                        }
                    } while ($p = $p->getParentClass());
                }
            }

            file_put_contents($cache_file, ["<?php\n", "use tsd\serve\Factory;\n", 'Factory::$classes = [']);

            foreach (Factory::$classes as $k => $v) {
                file_put_contents($cache_file, "'$k'=>[", FILE_APPEND);
                if (@$v[0]) file_put_contents($cache_file, "'$v[0]',", FILE_APPEND);
                else file_put_contents($cache_file, "0,", FILE_APPEND);

                if (@$v[1]) {
                    file_put_contents($cache_file, '[', FILE_APPEND);
                    foreach ($v[1] as $k1 => $v1) file_put_contents($cache_file, "'$k1'=>'$v1',", FILE_APPEND);
                    file_put_contents($cache_file, '],', FILE_APPEND);
                }

                file_put_contents($cache_file, '],', FILE_APPEND);
            }

            file_put_contents($cache_file, '];', FILE_APPEND);
        }
    }

    private  static function rglob($path, $exp, ?array &$arr = null): array
    {
        if (!$arr) $arr = [];

        $files = glob($path . DIRECTORY_SEPARATOR . $exp);

        foreach ($files as $f) $arr[] = $f;

        $dirs = glob($path . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        foreach ($dirs as $d) Factory::rglob($d, $exp, $arr);

        return $arr;
    }

    function create(string $type, string $name = '', ?InjectionContext $ctx = null)
    {
        if (@$this->singletons[$type]) return $this->singletons[$type];

        $in = $this->getInjection($type, $name, $ctx);
        return $in->inject($this);
    }

    function createSingleton(string $type, string $name = '', ?InjectionContext $ctx = null)
    {
        $in = $this->create($type, $name, $ctx);
        $this->singletons[$type] = $in;
        return $in;
    }

    function getInjection(string $type, string $name, ?InjectionContext $ctx)
    {      
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
                $t = $this->getImplementation($t->getName(), $config['_mode']);
            } else {
            $t = $this->getImplementation($t->getName());
            }
        }

        return new Injection($t, $name, $ctx, $config);
    }

    function getImplementation(string $type, string $mode = null)
    {
        if (!$mode && @Factory::$classes[$type][0]) return new ReflectionClass(Factory::$classes[$type][0]);
        else if ($mode && @Factory::$classes[$type][1][$mode]) return new ReflectionClass(Factory::$classes[$type][1][$mode]);
        else return false;
    }
}


class Injection
{
    private ReflectionClass $type;

    private ?InjectionContext $ctx;

    private array $config;

    private string $name;


    function __construct(ReflectionClass $type, string $name, ?InjectionContext $ctx, array $config = [])
    {
        $this->name = $name;
        $this->type = $type;
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
        $myctx->plugin = $ctx->plugin;

        foreach ($par as $p) {
            if ($p->getType() instanceof ReflectionNamedType)
            {                    
                $pt = $p->getType();
                
                if ($pt->getName() == 'array' && $p->name == '_config' && $this->name)
                {
                    $args[] = $this->config;
                    continue;
                }
                else if (!$pt->isBuiltIn()) 
                {
                    $args[] = $factory->create($pt->getName(), $p->getName(), $myctx);
                    continue;
                }
         }
         if (isset($this->config[$p->name]))
                $args[] = $this->config[$p->name];
            else if ($p->name == '_name')
                $args[] = $this->name;
            else if ($p->name == '_plugin')
                $args[] = $ctx->plugin;
            else if ($p->name == '_plugins')
                $args[] = App::$plugins;
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

            if ($p->hasType() && $p->getType() instanceof ReflectionNamedType && !$p->getType()->isBuiltIn())
                $val = $factory->create($p->getType()?->getName(), $p->getName(), $myctx);
            if (isset($this->config[$p->name]))
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