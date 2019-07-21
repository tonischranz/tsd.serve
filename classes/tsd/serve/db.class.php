<?php

namespace tsd\serve;

interface DB
{
    function select();
}


/**
 * @Default
 * @Mode mysql
 */
class MySQLDB implements DB
{
    function __construct(string $host, string $username, string $password, string $database)
    {
        
    }

    function select()
    {

    }
}