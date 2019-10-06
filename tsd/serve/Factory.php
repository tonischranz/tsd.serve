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
    { //echo "$type, ";
        $t = new \ReflectionClass($type);

        $config = array_key_exists($name, $this->config) ? 
                    $this->config[$name]:array();
        
        if ($t->isAbstract())
        {
            if($config && isset($config['mode']))
            {
                $t=$this->getImplementation($t, $config['mode']);
            }
            else {
                $t=$this->getImplementation($t);
            }
        }

        $con = $t->getConstructor();
        $par = $con ? $con->getParameters() : [];
        $args = [];

        foreach ($par as $p) {
            if ($p->isArray() && $p->name == 'config' && $name)
                $args[] = $config;
            else if ($p->hasType() && !$p->isArray())
                $args[] = $this->create($p->getType()->getName(), $p->getName());
		//todo: single values
		else if ($config[$p->name])
		$args[] = $config[$p->name];
            else if ($p->name == 'name')
                $args[]=$name;
            else if ($p->isArray())
                $args[] = [];
            else
                $args[] = null;
        }

        $type = $t->name;
        return $con ? $t->newInstanceArgs($args) : new $type();
        
    }

    function getImplementation(\ReflectionClass $type, string $mode = null)
    {
        $doc = $type->getDocComment();
        $matches = [];

        //todo: use plugins to search classes
        if (preg_match_all ('/@Implementation\s((\w|\\\)*)/', $doc, $matches) > 0)
        {
            foreach ($matches[1] as $i)
            {
                $itype = new \ReflectionClass($i);
                
                $idoc = $itype->getDocComment();
                $imatches = [];
                

                if (!$mode && preg_match('/@Default/', $idoc))
                {
                    return $itype;
                } 

                if (preg_match_all ('/@Mode\s(\w+)/', $idoc, $imatches) > 0)
                {
                    foreach ($imatches[1] as $m)
                    {
                        if ($m == $mode) return $itype;
                    }
                }
            }
        }
    }
}
