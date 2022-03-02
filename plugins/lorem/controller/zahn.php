<?php

use tsd\serve\Controller;

class zahnController extends Controller
{
  function showIndex ()
  {
    var_dump($this);
    return $this->view (['text' => 'Es isch e mau Maa gsi, dä het ä hohlä Zahn gha, i dä Zahn hets es Briefli gha u da isch gstandä: Es isch e mau Maa gsi, dä het ä hohlä Zahn gha, i dä Zahn hets es Briefli gha u da isch gstandä: Es isch e mau Maa gsi, dä het ä hohlä Zahn gha, i dä Zahn hets es Briefli gha u da isch gstandä: ... ',
    'code'=>'<html>{bla}<bod><textarea>asdf&lt;/textarea></body></html>']);
  }

  function showData ()
  {
    $data = new ViewData;
    $aa = (array) $data;
    $a = $aa['foo'];
    $b = ((array)$data)['bar'];
    $ab = ((array)((array)$data)['foo'])['bar'];


    return $this->view(new ViewData);
  }

}

class ViewData
{
  public Foo $foo;
  public string $bar = "9876";

  function __construct()
  {
    $this->foo = new Foo;
  }

}

class Foo
{
  public string $bar = "1234";
}