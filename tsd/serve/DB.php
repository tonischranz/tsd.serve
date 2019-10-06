<?php
namespace tsd\serve;
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of DB
 *
 * @author tonti
 * @Implementation tsd\serve\MysqlDB
 */
 
interface DB 
{

    function select (string $table, array $fields, $cond = false, $order = false) : array;

    function insert (string $table, $values) : int;

    function update (string $table, $values, $cond):bool;

    function delete (string $table, $cond):bool;

}
 
 /**
 * @Default
 * @Mode mysql
 */
class MysqlDB implements DB {
    private $con;
    
    private function buildParams ($cond)
    {
        $params = [];
        
        foreach ($cond as $k => $v) 
        {
            if (\is_int($v) || \is_float($v)) $params[] = "$k=$v";
            else $params[] = "$k='".\mysqli_escape_string ($this->con, $v)."'";
        }
        
        return $params;
    }
    
    private function buildConditions ($cond)
    {
        $params = [];
        
        foreach ($cond as $k => $v) 
        {
            if (\is_int($v) || \is_float($v)) $params[] = "$k=$v";
            else if (\is_null($v)) $params[] = "$k IS NULL";
            else if (\is_array($v))
            {
                if ($v[0] == '!')
                {
                    if(\is_null($v[1])) $params[] = "$k IS NOT NULL";
                    else if (\is_int($v[1]) || \is_float($v[1])) $params[] = "$k!={$v[1]}";
                    else if (\is_string($v[1])) $params[] = "$k='".\mysqli_escape_string (DB::$con, $v[1])."'";
                }
            }
            else if (\is_string($v)) $params[] = "$k='".\mysqli_escape_string ($this->con, $v)."'";
        }
        
        return $params;
    }
    
    private function buildValues ($cond)
    {
        $params = [];
        
        foreach ($cond as $v) 
        {
            if (\is_int($v) || \is_float($v)) $params[] = $v;
            else $params[] = "'".\mysqli_escape_string ($this->con, $v)."'";
        }
        
        return $params;
    }
    
    private function buildOrders ($cond)
    {
        $params = [];
        
        foreach ($cond as $o) 
        {
            if (\is_array($o)) $params[] = "$o[0] $o[1]";
            else if (\is_string($o)) $params[] = "$o ASC";
        }
        
        return $params;
    }
    
    private function buildFields (array $cond)
    {
        $params = [];
        $keys = \array_keys($cond);
        
        foreach ($keys as $k)
        {
            $params[] = $k;
        }
        
        return $params;
    }
    
    
    
    function __construct() {
   
        DB::$con = \mysqli_connect('tontich.mysql.db.internal', 'tontich_apps', 'App$3cur3P@$$', 'tontich_offerten');
        \mysqli_set_charset(DB::$con, 'utf8');
        
    }

    function read ($query)
    {        
        $rows = [];
        
        $r = \mysqli_query($this->con, $query);
        
        while ($row = \mysqli_fetch_array($r)) $rows[] = $row;
        
        return $rows;
    }
    
    function select (string $table, array $fields, $cond = false, $order = false) : array
    {
        $q = 'SELECT ';
        $q .= join(',',$fields);
        $q .= ' FROM ';
        $q .= $table;
        
        
        
        if ($cond)
        {
            $q .= ' WHERE ';
            $q .= join(' AND ', $this->buildConditions($cond));
        }
        
        if ($order)
        {
            $q .= ' ORDER BY ';
            $q .= join(', ', $this->buildOrders($order));
        }
        
        return $this->read($q);
    }
    
    function insert (string $table, $values) : int
    {
        $fields = $this->buildFields($values);
        $vals = $this->buildValues($values);
        
        $q = 'INSERT INTO ';
        $q .= $table;
        $q .= ' (';
        $q .= join(',',$fields);
        $q .= ' ) VALUES (';
        $q .= join(',',$vals);
        $q .= ')';
        
        mysqli_query(DB::$con, $q);
        return mysqli_insert_id($this->con);
    }
    
    function update (string $table, $values, $cond) : bool
    {
        if (!is_array($cond) || count($cond) == 0) return false;
        
        $vals = $this->buildParams($values);
        $params = $this->buildConditions($cond);
        
        $q = 'UPDATE ';
        $q .= $table;
        $q .= ' SET ';
        $q .= join(' ,',$vals);
        $q .= ' WHERE ';
        $q .= join(' AND ',$params);
        
        
        mysqli_query($this->con, $q);
        return mysqli_affected_rows($this->con) == 1;
    }
    
    function delete (string $table, $cond) : bool
    {
        if (!is_array($cond) || count($cond) == 0) return false;
        
        $params = $this->buildConditions($cond);
        
        $q = 'DELETE FROM ';
        $q .= $table;
        $q .= ' WHERE ';
        $q .= join(' AND ',$params);
              
        
        mysqli_query($this->con, $q);
        return mysqli_affected_rows($this->con) > 0;
    }
}
