<?php

namespace tsd\serve;

use tsd\serve\model\Config;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/*    setlocale(LC_ALL, "de_CH.UTF-8");
  session_start(); */

/**
 * Description of Controller
 *
 * @author tonti
 */
class Controller
{

  public $name;
  public $basePath;
  //private $config;
  //private $membership;
  //private $viewsPath;
  

  function __construct ()
  {
    //$this->viewsPath = './views';
  }

  protected function view ($data = null, string $view = null)
  {
    if ($view == null)
    {
      $backtrace = debug_backtrace();
      $view = $backtrace[0]['function'];
    }

    return new ViewResult($this->name."/$view", $data);
  }

  protected function redirect ($url)
  {
    return new RedirectResult($url);
  }

  /*protected function render (string $view, array $data = null)
  {
    $template = $this->getTemplatePath ($view);


    if (file_exists ($template))
    {
      Controller::renderTemplate ($template, ['viewData' => $data, 'basePath' => $this->basePath]);
    }
    else
    {
      Controller::error (404, 'Not Found', 'No suitable view could be found.');
    }
  }

  protected function getTemplatePath (string $view)
  {
    return "$this->viewsPath/$this->name/$view.html";
  }

  protected function setViewsPath (string $path)
  {
    $this->viewsPath = $path;
  }

  protected function redirect (string $path)
  {
    header ("Location: $path");
    exit;
  }*/


  protected function buildMenu ()
  {
    /* $mem = $this->getMembership();

      $menu = [];

      if ($mem->isInGroup('user') || $mem->isInGroup('editor'))
      {
      $menu [] = [url => '/', name => 'Offerten'];
      }
      if ($mem->isInGroup('editor'))
      {
      $menu [] = [url => '/drafts', name => 'Entwürfe'];
      }
      if ($mem->isInGroup('user') || $mem->isInGroup('editor'))
      {
      $menu [] = [url => '/archive', name => 'Archiv'];
      }
      if ($mem->isInGroup('admin'))
      {
      $menu [] = [url => '/member/users', name => 'Benutzerverwaltung'];
      }

      return $menu; */
  }

  protected function buildUserMenu ()
  {
    /*
      $mem = $this->getMembership();

      $userMenu = [];


      if (!$mem->isAnonymous())
      {
      $u          = $mem->getCurrentUser();
      $userMenu[] = [url => '/member', name => $u['fullname'], icon => 'fa-user'];
      $userMenu[] = [url => '/member/logout', name => 'abmelden', icon => 'fa-power-off'];
      }

      return $userMenu;
     *
     */
  }

  protected function renderPDF ($view, $viewData)
  {
    /*
      global $pdf;
      global $data;

      $pdf  = new ArenaPDF();
      $data = $viewData;

      $template = $this->name . '/' . $view . '.php';

      if (file_exists("./views/$template"))
      require "./views/$template";
      else
      Controller::error(500, 'Internal Error');

      $pdf->Output();
     *
     */
    Controller::error (501, 'Not Implemented', 'PDF Rendering is not yet implemented');
  }

  private static function renderTemplate (string $view, array $data)
  {
    $v = new View ($view);
    $v->render ($data);
  }

    

  private static function error (int $code, string $message, string $description = '')
  {
    echo "Error: $code<br />";
    echo "$message<br />";
    echo "$description<br />";
    //header ("HTTP/1.0 $code $message");
    //Controller::renderInt('error.php', [code=>$code, message=>$message, description=>$description]);
    exit;
  }

  /**
   *
   * @param string $template
   * @param array $viewData
   * @param array $layoutData
   *
   * @deprecated since version 16.12.0
   */
  private static function renderInt (string $template, array $viewData, array $layoutData = [])
  {
    Controller::error (501, 'Internal Error', 'Direct .php File rendering not supported anymore');
  }

  protected function setBasePath ($path)
  {
    $this->basePath = $path;
  }

  protected function getBasePath ()
  {
    return $this->basePath;
  }

}
