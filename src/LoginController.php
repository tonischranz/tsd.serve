<?php

namespace tsd\serve;

class LoginController extends Controller
{
    function showIndex(string $returnUrl)
    {
        return new ViewResult('login', ['returnUrl'=>$returnUrl]);
    }

    function doIndex(string $username, string $password, string $returnUrl)
    {
        if ($this->_member->login($username, $password)) return $this->redirect(urldecode($returnUrl));

        return new ViewResult('login', ['returnUrl'=>$returnUrl, 'error'=>true]);        
    }

    function doLogout(string $returnUrl)
    {
        $this->_member->logout();
        return new ViewResult('logout', ['returnUrl'=>$returnUrl]);
    }
}