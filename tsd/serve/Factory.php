<?php

namespace tsd\serve;

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

    function create(string $type, $name = '', InjectionContext $ctx = null)
    {
        $t = new \ReflectionClass($type);

        $plugin = $ctx ? $ctx->plugin : '';

        $config = array_key_exists($name, $this->config) ?
            $this->config[$name] : [];

        if ($t->isAbstract()) {
            if ($config && isset($config['mode'])) {
                $t = $this->getImplementation($t, $plugin, $config['mode']);
            } else {
                $t = $this->getImplementation($t, $plugin);
            }
        }

        $con = $t->getConstructor();
        $par = $con ? $con->getParameters() : [];
        $args = [];

        $myctx = new InjectionContext();
        $myctx->name = $name;
        $myctx->fullname = $ctx ? "$ctx->fullname.$name" : $name;
        $myctx->plugin = $plugin;

        foreach ($par as $p) {
            if ($p->isArray() && $p->name == 'config' && $name)
                $args[] = $config;
            else if ($p->hasType() && !$p->isArray())
                $args[] = $this->create($p->getType()->getName(), $p->getName(), $myctx);
            else if ($config[$p->name])
                $args[] = $config[$p->name];
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

        $type = $t->name;
        return $con ? $t->newInstanceArgs($args) : new $type();
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
    }
}
