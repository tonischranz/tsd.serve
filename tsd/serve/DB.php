<?php

namespace tsd\serve;

/**
 * CRUD interaction with a SQL database
 *
 * @author Toni Schhranz
 * @Implementation tsd\serve\MysqlDB
 */

interface DB
{
    /**
     * Execute a SELECT query
     * @param string $table table name
     * @param array $fields array of fieldnames
     * @param array $cond associative array with conditions $field=>$value
     * @param array $order array of either a name or an array with [0]=>$name, [1]=>ASC/DESC
     * @return array array of rows as associative arrays $field=>$value
     */
    function select(string $table, array $fields = null, array $cond = null, $order = false): array;

    /**
     * Execute a INSERT statement
     * @param string $table table name
     * @param array $values associative array $field=>$value
     * @return int ID of the created row
     */
    function insert(string $table, array $values): int;

    /**
     * Executes a UPDATE statement
     * @param string $table table name
     * @param array $values associative array $field=>$value
     * @param array $cond associative array with conditions $field=>$value
     * @return bool TRUE if any roows affected
     */
    function update(string $table, array $values, array $cond): bool;

    /**
     * Executes a DELETE statement
     * @param string $table table name
     * @param array $cond associative array with conditions $field=>$value 
     */
    function delete(string $table, array $cond): bool;
}

/**
 * MySQL implementation of tsd\serve\DB
 *
 * @Default
 * @Mode mysql
 */
class MysqlDB implements DB
{
    private ?\mysqli $_con;
    private string $prefix;
    private string $host;
    private string $database;
    private string $username;
    private string $password;

    #region private functions

    private function buildParams($cond)
    {
        $params = [];

        foreach ($cond as $k => $v) {
            if (\is_int($v) || \is_float($v)) $params[] = "$k=$v";
            else $params[] = "$k='" . \mysqli_escape_string($this->con, $v) . "'";
        }

        return $params;
    }

    private function buildConditions($cond)
    {
        $params = [];

        foreach ($cond as $k => $v) {
            if (\is_int($v) || \is_float($v)) $params[] = "$k=$v";
            else if (\is_null($v)) $params[] = "$k IS NULL";
            else if (\is_array($v)) {
                if ($v[0] == '!') {
                    if (\is_null($v[1])) $params[] = "$k IS NOT NULL";
                    else if (\is_int($v[1]) || \is_float($v[1])) $params[] = "$k!={$v[1]}";
                    else if (\is_string($v[1])) $params[] = "$k='" . \mysqli_escape_string(DB::$con, $v[1]) . "'";
                }
            } else if (\is_string($v)) $params[] = "$k='" . \mysqli_escape_string($this->con, $v) . "'";
        }

        return $params;
    }

    private function buildValues($cond)
    {
        $params = [];

        foreach ($cond as $v) {
            if (\is_int($v) || \is_float($v)) $params[] = $v;
            else $params[] = "'" . \mysqli_escape_string($this->con, $v) . "'";
        }

        return $params;
    }

    private function buildOrders(array $cond)
    {
        $params = [];

        foreach ($cond as $o) {
            if (\is_array($o)) $params[] = "$o[0] $o[1]";
            else if (\is_string($o)) $params[] = "$o ASC";
        }

        return $params;
    }

    private function buildFields(array $cond)
    {
        $params = [];
        $keys = \array_keys($cond);

        foreach ($keys as $k) {
            $params[] = $k;
        }

        return $params;
    }

    private function prefixTable(string $table)
    {
        return "$this->prefix$table";
    }

    private function con(): \mysqli
    {
        if (!$this->_con) {
            $host = $this->host;
            $user = $this->username;
            $pw = $this->password;
            $db = $this->database;

            $con = new \mysqli($host, $user, $pw, $db);

            if ($con) throw new Exception("MySQL connection to $user@$host failed.", 1);

            $con->set_charset('utf8');

            $this->_con = $con;
        }

        return $this->_con;
    }

    #endregion

    function __construct(?string $_plugin/*, string $host, string $username, string $password, string $database*/)
    {
        $this->prefix = $_plugin ? "$_plugin." : '';
        //$this->con = new \mysqli($host, $username, $password, $database);
        /*$this->host = $host;
        $this->database = $database;
        $this->username = $username;
        $this->password = $password;*/
    }

    function read($query)
    {
        $rows = [];

        $r = $this->con()->query($query);

        if (!$r) throw new Exception("MySQL error: $this->con->error", 1);

        while ($row = $r->fetch_array($r)) $rows[] = $row;

        return $rows;
    }

    function select(string $table, array $fields = null, array $cond = null, $order = false): array
    {
        $q = 'SELECT ';
        $q .= join(',', $fields);
        $q .= ' FROM ';
        $q .= $this->prefixTable($table);



        if ($cond) {
            $q .= ' WHERE ';
            $q .= join(' AND ', $this->buildConditions($cond));
        }

        if ($order) {
            $q .= ' ORDER BY ';
            $q .= join(', ', $this->buildOrders($order));
        }

        return $this->read($q);
    }

    function insert(string $table, $values): int
    {
        $fields = $this->buildFields($values);
        $vals = $this->buildValues($values);

        $q = 'INSERT INTO ';
        $q .= $this->prefixTable($table);;
        $q .= ' (';
        $q .= join(',', $fields);
        $q .= ' ) VALUES (';
        $q .= join(',', $vals);
        $q .= ')';

        $this->con()->query($q);
        return $this->con->insert_id;
    }

    function update(string $table, $values, $cond): bool
    {
        if (!is_array($cond) || count($cond) == 0) return false;

        $vals = $this->buildParams($values);
        $params = $this->buildConditions($cond);

        $q = 'UPDATE ';
        $q .= $this->prefixTable($table);;
        $q .= ' SET ';
        $q .= join(' ,', $vals);
        $q .= ' WHERE ';
        $q .= join(' AND ', $params);


        $this->con()->query($q);
        return $this->con->affected_rows == 1;
    }

    function delete(string $table, $cond): bool
    {
        if (!is_array($cond) || count($cond) == 0) return false;

        $params = $this->buildConditions($cond);

        $q = 'DELETE FROM ';
        $q .= $this->prefixTable($table);
        $q .= ' WHERE ';
        $q .= join(' AND ', $params);


        $this->con->query($q);
        return $this->con->affected_rows > 0;
    }
}
