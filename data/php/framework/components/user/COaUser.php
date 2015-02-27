<?php

/**
 * COaUser class file
 *
 * @author wilsonwsong
 * @copyright Tencent OMG 
 * @license 
 */

class COaUser extends CWebUser 
{
    const TICKET_COOKIE = 'MOD_OA_TICKET';
    const TICKET_OA_COOKIE = 'TCOA_TICKET';
    
    public $ticketService = "http://10.6.12.14/services/passportservice.asmx?WSDL";
    public $userobj; 
    
    public function login($identity,$duration=0)
    {
    }


    public function logout($destroySession=true)
    {
        if($destroySession)
            Mod::app()->getSession()->destroy();
        else
            $this->clearStates();
            
        setcookie(self::TICKET_COOKIE, "", time()-3600, "/");
        //setcookie(self::TICKET_OA_COOKIE, "", time()-3600, "/");

        $request=Mod::app()->getRequest();
        $ref = $request->getUrlReferrer() ;
        $pos = strpos($ref, "?");
        if($pos !== false)
        {
            $ref = substr($ref, 0, $pos);
        }
        //$ticket_pair = "ticket=".$request->getParam('ticket');
        //$ref = str_replace(ticket_pair,"",$request->getUrlReferrer());
        //Mod::log("Logout-ref:{$ref}", CLogger::LEVEL_INFO, 'application.controller');
        $url = "http://www.oa.com/api/loginout.ashx?ref=".urlencode($ref);
        $request->redirect($url);
    }

    public function loginRequired()
    {
        $app = Mod::app();
        $request = $app->getRequest();
        
        if($request->getIsAjaxRequest() && isset($this->loginRequiredAjaxResponse))
        {
            echo $this->loginRequiredAjaxResponse;
            $app->end();
        }
        $url = $request->getHostInfo().$request->getUrl(); 
       // Mod::log("LoginRequired:{$url}", CLogger::LEVEL_INFO, 'application.controller');
        $url = "http://passport.oa.com/modules/passport/signin.ashx?url=".urlencode($url);
        $request->redirect($url);
        
    }
    
    public function getIsGuest()
    {
        $ticket = $_COOKIE[self::TICKET_COOKIE]; 
        if(empty($ticket))
        {
            $ticket = $_COOKIE[self::TICKET_OA_COOKIE]; 
        }
        //Mod::log("TICKET_COOKIE:{$ticket}", CLogger::LEVEL_INFO, 'application.controller');
        if(empty($ticket))
        {
            $ticket = Mod::app()->request->getParam('ticket');
            if(!empty($ticket))
            {
                setcookie(self::TICKET_COOKIE, $ticket, time()+86400, "/");
            }else
            {
                return true;
            }
        }
        $cl = new SoapClient($this->ticketService );
        $ret = $cl->DecryptTicket(array("encryptedTicket" => $ticket ) ); 
        if(empty($ret) || empty($ret->DecryptTicketResult))
        {
            //Mod::log("ErrorTicket:{$ticket}", CLogger::LEVEL_INFO, 'application.controller');
            setcookie(self::TICKET_COOKIE, "", time()-3600,"/");
            //setcookie(self::TICKET_OA_COOKIE, "", time()-3600,"/");
            return true;
        }
        //Mod::log("SuccessTicket:{$ticket}", CLogger::LEVEL_INFO, 'application.controller');
        $this->userobj = $ret->DecryptTicketResult;
        return false;
    }

    public function getName()
    {
        if(empty($this->userobj))
        {   
            return 'guest';
        }
        return $this->userobj->LoginName;
    }

    public function getChineseName()
    {
        return $this->userobj->ChineseName;
    }
    
    public function getId()
    {
        return $this->userobj->StaffId;
    }

    public function getDeptId()
    {
        return $this->userobj->DeptId;
    }

    public function getDepName()
    {
        return $this->userobj->DeptName;
    }

}
 
