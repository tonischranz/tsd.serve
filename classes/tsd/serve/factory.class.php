<?php

namespace tsd\serve;

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
    //fu todo:

    function create($type, $name = '')
    {
        $t = new \ReflectionClass($type);

        $config = $this->config[$name];
        
        if ($t->isAbstract() && $config && isset($config['mode']))
        {
            $t=$this->getImplementation($t, $config['mode']);
        }

        $con = $t->getConstructor();
        $par = $con ? $con->getParameters() : [];
        $args = [];

        foreach ($par as $p) {
            if ($p->isArray() && $p->name == 'config' && $name)
                $args[] = model\Config::getConfig($name);
            else if ($p->hasType() && !$p->isArray())
                $args[] = $this->create($p->getType()->getName(), $p->getName());
            else if ($p->name == 'name')
                $args[]=$name;
            else if ($p->isArray())
                $args[] = [];
            else
                $args[] = null;
        }

        //var_dump($con);
        //var_dump($args);
        return $con ? $t->newInstanceArgs($args) : new $type();
        
    }

    function getImplementation(\ReflectionClass $type, string $mode)
    {
        $doc = $type->getDocComment();
        $matches = [];

        //var_dump($doc);
        //todo: use plugins to search classes
        if (preg_match_all ('#@Implementation\s(.*)#', $doc, $matches) > 0)
        {
            foreach ($matches[1] as $i)
            {
                //var_dump($i);
                $itype = new \ReflectionClass($i);
                
                $idoc = $itype->getDocComment();
                $imatches = [];
                //var_dump($idoc);

                if (preg_match_all ('#@Mode\s(\w+)#', $idoc, $imatches) > 0)
                {
                    //var_dump($imatches);
                    foreach ($imatches[1] as $m)
                    {
                        if ($m == $mode) return $itype;
                    }
                }
            }
        }
    }
}
