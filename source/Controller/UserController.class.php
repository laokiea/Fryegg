<?php

namespace EatWhat\Controller;

use EatWhat\Base\Controller;

/**
 * user controller for request obj
 * 
 */
class UserController extends Controller
{
    /**
     * generate user date after verifing token
     * 
     */
    private $userData = null;

    /**
     * generate user date after verifing token
     * 
     */
    private $accessToken = null;

    /**
     * set user data
     * 
     */
    public function setUserData(?array $userData) : void
    {
        $this->userData = $userData;
    }

    /**
     * set user data field
     * 
     */
    public function setUserField(string $field, $value) : void
    {
        $this->userData[$field] = $value;
    }

    /**
     * set access token analyzer
     * 
     */
    public function setAccessToken(?string $accessToken) : void
    {
        $this->accessToken = $accessToken;
    }

    /**
     * set user status
     * 
     */
    public function setUserStatus($userStatus)
    {
        $this->userData && ($this->userData["userStatus"] = $userStatus);
    }

    /**
     * get user data
     * 
     */
    public function getUserData() : ?array
    {
        return $this->userData;
    }

     /**
     * get access token
     * 
     */
    public function getAccessToken() : ?string
    {
        return $this->accessToken;
    }

    /**
     * get user data
     * 
     */
    public function logout()
    {
        $this->setUserData(null);
        $this->setAccessToken(null);
    }
}