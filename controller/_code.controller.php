<?php

use tsd\serve\Controller;

class _codeController extends Controller
{

  function showIndex ()
  {
    
    $this->render ('index', []);
  }
  
  function showClasses ($path)
  {
    //var_dump ($path);exit();
  
  
    $filepath = 'classes' . implode(DIRECTORY_SEPARATOR, $path) . '.class.php';
    $content = file_get_contents($filepath);
    $this->render ('edit', ['content'=>$content, 'path'=>$filepath, 'name'=>filename($filepath)]);
  }
  
  function doClasses ($path, $content)
  {
    //var_dump($path, $content);
    $this->render ();  
  } 

}
