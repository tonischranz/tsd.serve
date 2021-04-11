<?php

use tsd\serve\Controller;
use tsd\serve\SecurityGroup;

class ClassesController extends Controller
{

    /**
    @SecurityGroup developer
   */
  #[SecurityGroup('developer')]
  function showIndex ($p) : Result
  {
      return $this->show(['tsd', 'serve']);
  }
  /**
    @SecurityGroup developer
   */
  #[SecurityGroup('developer')]
  function show ($p) : Result
  {
    $path = implode (DIRECTORY_SEPARATOR, $p);

    $basepath = 'classes' . DIRECTORY_SEPARATOR . $path;
    $filepath = $basepath . '.class.php';

    if (file_exists ($filepath))
    {
      $content = file_get_contents ($filepath);
      return $this->view (['content' => $content, 'path' => $path, 'name' => basename ($path) . '.class.php'], 'edit');
    }
    else if (file_exists ($basepath) && is_dir ($basepath))
    {
      $files = scandir ($basepath);
      $files = str_replace ('.class.php', '', $files);
      return $this->view (['files' => $files, 'path' => $path],'index');
    }
  }

  /**
    @SecurityGroup  developer
   */
  #[SecurityGroup('developer')]
  function doEdit ($path, $content) : Result
  {
    $basepath = 'classes' . DIRECTORY_SEPARATOR . $path;
    $filepath = $basepath . '.class.php';
    file_put_contents ($filepath, $content);
    //$this->render ('success', ['redirect' => $path]);
  }

}
