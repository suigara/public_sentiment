<?php

/**
 * CWebPassUser class file
 *
 * @author wilsonwsong
 * @link CwebUser
 * @copyright Tencent OMG 
 * @license 
 */

class CWebPassUserTest extends CWebUser 
{
    public  $project = 'php';
    public $UserName='Test';
    public $UserPrivilege='admin';
    
    public function __construct()
    {
    }
    
    
    public function login($identity,$duration=0)
    {
    	
    }


    public function logout($destroySession=true)
    {
       
    }

    public function loginRequired()
    {
        Mod::log("LoginRequired", CLogger::LEVEL_TRACE, 'application.controller');
        
    }
    
    public function getIsGuest()
    {
     
    }

    public function getName()
    {
       return $this->UserName;
    }

    public function setName($value)
    {
    }
    
    public function getId()
    {
        return $this->UserName;
    }

    public function setId($value)
    {
    }

	public static function queryUserInfo($user)
    {
    	return array();
    }
    function getUser()
    {
    	return $this->UserName;
    }
    function getPrivilege()
    {
    	return $this->UserPrivilege;
    }
    
}
