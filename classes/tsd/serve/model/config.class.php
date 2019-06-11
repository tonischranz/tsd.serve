<?php

namespace tsd\serve\model;

class Config
{

  const FILE = 'config.json';

  private $data;

  function __construct ()
  {
    $this->data = Config::read_data (Config::FILE);
  }

  function getDBConfig ()
  {
    return $this->data['db'];
  }

  function setDBConfig ($host, $username, $password, $db)
  {
    $d = $this->data;
    $d['db'] = ['host' => $host, 'username' => $username, 'password' => $password, 'database' => $db];

    $this->data = $d;
    $this->save ();
  }

  function setLanguages ($languages)
  {
    $d = $this->data;
    $d['lang'] = $languages;

    $this->data = $d;
    $this->save ();
  }

  function getLanguages ()
  {
    return $this->data['lang'];
  }
  
  function getMembershipConfig ()
  {
    return $this->data['member'];
  }

  protected function save ()
  {
    Config::write_data (Config::FILE, $this->data);
  }

  private static function read_data ($path)
  {
    return json_decode (file_get_contents ($path), true);
  }

  private static function write_data ($path, $data)
  {
    file_put_contents ($path, json_encode ($data));
  }

  /**
   * 
   */
  static function getConfig(string $name)
  {
    if ($name == "member")
      return ['mode'=>'install', 'password'=>'1234'];
    else
      return [];
  }

}
