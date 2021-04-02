<?php

namespace tsd\serve;

class LoginController extends Controller
{
    function showIndex(string $returnUrl)
    {
        return $this->view(['returnUrl'=>$returnUrl], 'login');
    }

    function doIndex(string $username, string $password, string $returnUrl)
    {
        if ($this->_member->login($username, $password)) $this->redirect(urldecode($returnUrl));

        $this->view(['returnUrl'=>$returnUrl, 'error'=>true], 'login');
    }

    function doLogout(string $returnUrl)
    {
        $this->_member->logout();
        $this->view(['returnUrl'=>$returnUrl], 'logout');
    }
}