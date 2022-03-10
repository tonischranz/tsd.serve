<?php

namespace tsd\serve;


class LoginController extends Controller
{
    function showIndex(?string $returnUrl = null)
    {
        return new ViewResult('login', ['returnUrl'=>$returnUrl]);
    }

    function doIndex(string $username, string $password, string $returnUrl = '_login/profile')
    {
        if ($this->_member->login($username, $password)) return $this->redirect(urldecode($returnUrl));

        return new ViewResult('login', ['returnUrl'=>$returnUrl, 'error'=>true]);        
    }

    #[SecurityUser]
    function showLogout(?string $returnUrl = null)
    {
        return new ViewResult('logout', ['returnUrl'=>$returnUrl]);
    }

    #[SecurityUser]
    function showProfile()
    {
        $username = $this->_member->getName();
        $fullname = $this->_member->getFullName();
        $email = $this->_member->getEMail();
        return new ViewResult('profile', ['username' => $username, 'fullname' => $fullname, 'email' => $email]);
    }

    #[SecurityUser]
    function doProfile(string $fullname, string $email)
    {
        $this->_member->setFullName($fullname);
        $this->_member->setEMail($email);
        $this->_member->save();

        return $this->showProfile();
    }

    #[SecurityUser]
    function showPassword()
    {        
        return new ViewResult('password',null);
    }

    #[SecurityUser]
    function doPassword(string $old_password, string $pw1, string $pw2)
    {
        $username = $this->_member->getName();
        if (!$this->_member->login($username, $old_password)) return new ViewResult('password', ['error_old_password'=>true]);
        if ($pw1 != $pw2) return new ViewResult('password', ['error_mismatch'=>true]);
            
        $this->_member->setPassword($pw1);
        $this->_member->save();

        return $this->success('password changed successfully', 'profile');
    }

    #[SecurityUser]
    function doLogout(?string $returnUrl = null)
    {
        $this->_member->logout();
        return new ViewResult('loggedout', ['returnUrl'=>$returnUrl?urlencode($returnUrl):'']);
    }
}