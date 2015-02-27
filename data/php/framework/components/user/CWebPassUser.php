<?php

/**
 * CWebPassUser class file
 *
 * @author wilsonwsong
 * @link CwebUser
 * @copyright Tencent OMG 
 * @license 
 */
require_once('pass_client/PasClient.php');

class CWebPassUser extends CWebUser 
{
    public  $project = 'php';
    
    public function __construct()
    {
        if(!isset($this->behaviors['passclient']))
        {
            $this->behaviors['passclient']= new PasClient();
        }
    }
    
    
    public function login($identity,$duration=0)
    {
    }


    public function logout($destroySession=true)
    {
        if($destroySession)
            Mod::app()->getSession()->destroy();
        else
            $this->clearStates();
        
        $request=Mod::app()->getRequest();
        $url = "http://passport.webdev.com/cgi-bin/logout?project={$this->project}&url=".urlencode($request->getUrlReferrer());
        $request->redirect($url);
    }

    public function loginRequired()
    {
        Mod::log("LoginRequired", CLogger::LEVEL_TRACE, 'application.controller');
        $app = Mod::app();
        $request = $app->getRequest();
        
        if($request->getIsAjaxRequest() && isset($this->loginRequiredAjaxResponse))
        {
            echo $this->loginRequiredAjaxResponse;
            $app->end();
        }
        $url = $request->getHostInfo().$request->getUrl(); 
        $url = "http://passport.webdev.com/cgi-bin/login?project={$this->project}&url=".urlencode($url);
        $request->redirect($url);
        
    }
    
    public function getIsGuest()
    {
        $result = $this->verifyTicket($this->project, '', 1);
        Mod::log("verify ticket result : ".$result, CLogger::LEVEL_TRACE, 'application.controller');
        return SUCCESS !== $result && WITHOUTPRIVILEGE !== $result  ;
    }

    public function getName()
    {
        if($this->user !== null )
        {
            return $this->user;
        }else
        {
            return $this->guestName;
        }
    }

    public function setName($value)
    {
    }
    
    public function getId()
    {
        return $this->name;
    }

    public function setId($value)
    {
    }

	public static function queryUserInfo($user)
    {
    	$pc = new PasClient();
    	$ret = $pc->queryUserInfo($user);
    	if ($ret == SUCCESS)
    	{
    		return $pc->getUserInfo() ;
    	}
    	return array();
    }
    
}
