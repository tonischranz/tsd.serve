<?php

use tsd\serve\Controller;

class ClassesController extends Controller
{

  /**
    @SecurityGroup developer
   */
  function show ($p)
  {
    $path = implode (DIRECTORY_SEPARATOR, $p);

    $basepath = 'classes' . DIRECTORY_SEPARATOR . $path;
    $filepath = $basepath . '.class.php';

    if (file_exists ($filepath))
    {
      $content = file_get_contents ($filepath);
      $this->render ('edit', ['content' => $content, 'path' => $path, 'name' => basename ($path) . '.class.php']);
    }
    else if (file_exists ($basepath) && is_dir ($basepath))
    {
      $files = scandir ($basepath);
      $files = str_replace ('.class.php', '', $files);
      $this->render ('list', ['files' => $files, 'path' => $path]);
    }
  }

  /**
    @SecurityGroup  developer
   */
  function doEdit ($path, $content)
  {
    $basepath = 'classes' . DIRECTORY_SEPARATOR . $path;
    $filepath = $basepath . '.class.php';
    file_put_contents ($filepath, $content);
    $this->render ('success', ['redirect' => $path]);
  }

}
