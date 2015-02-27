<?php
require_once("webdev_cs.php");
require_once("CWaeOidb0x6e4.php");
require_once("CWaeOidb0x5eb.php");
require_once("CWaeOidb0x462.php");
require_once("CWaeOidb0x436.php");
require_once("CWaeOidb0x49d.php");
require_once("CWaeOidb0x4b9.php");
require_once("CWaeOidb0x513.php");
require_once("CWaeOidb0x5d5.php");
require_once("CWaeOidb0x5c0.php");
require_once("CWaeOidb0x668.php");
require_once("CWaeOidb0x8250.php");
require_once("CWaeOidb0x576.php");
require_once("CWaeOidb0x8009.php");
require_once("CWaeOidb0x829d.php");
require_once("CWaeOidb0x9999.php");

class CWaeOidbSdk extends CApplicationComponent
{
    //public function init(){
    //}
    public $enablePerformReport = true;
    public $nameServiceKey = "php.test.oidb.webdev.com";
    //public $nameServiceKey = "waephp.oidbservice.webdev.com";
    private $_report = NULL;

    private function is_utf8($word)
    {
        if (preg_match("/^([".chr(228)."-".chr(233)."]{1}[".chr(128)."-".chr(191)."]{1}[".chr(128)."-".chr(191)."]{1}){1}/",$word) == true
        || preg_match("/([".chr(228)."-".chr(233)."]{1}[".chr(128)."-".chr(191)."]{1}[".chr(128)."-".chr(191)."]{1}){1}$/",$word) == true
        || preg_match("/([".chr(228)."-".chr(233)."]{1}[".chr(128)."-".chr(191)."]{1}[".chr(128)."-".chr(191)."]{1}){2,}/",$word) == true) 
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    public function __construct()
    {
        if($this->enablePerformReport)
            $this->_report = new CPhpPerfReporter();
    }

    /**
     *get microblog id by uin 
     *can get multiply microblog a time
     *@param uin32_t $dwUin the uin used in oidb head
     *@param string $skey the key after login which oidb head need
     *@param array $uinlist the uinlist which wanted to get to microblog id
     *@return array the result of microblog id when success, boolen false will return when failed
     */
    public function getMblogID($dwUin , $skey , $uinlist)
    {
        if($dwUin<0 || !is_array($uinlist))
            return false;

        $nametype = array(1 , 2 , 4);
        $data = array(
            "uin" => $dwUin,
            "servicetype" => 0,
            "skey_len" => strlen($skey),
            "skey" => $skey,
            "waetype" => "",
            "dwUin" => $dwUin,
            "uinlist" => $uinlist,
            "nametype" => $nametype
        );
                
        $reqObj = new CWaeOidb0x6e4($data , $this->nameServiceKey);
        if(!$reqObj)
            return false;

        if($this->enablePerformReport)
            $this->_report->beginPerfReport(Mod::app()->id , $reqObj->getoidbip() , $reqObj->getoidbport() , false);

        if($reqObj->encode() < 0)
        {
            if($this->enablePerformReport)
                $this->_report->endPerfReport(-1);
            return false;
        }

		$ret = 0;
		$rsp_buf = $reqObj->access($ret);
		if($rsp_buf === false)
		{
            if($this->enablePerformReport)
                $this->_report->endPerfReport(-2);
			return false;
        }

        $retarr = $reqObj->decode($rsp_buf , $ret); 
        
        if($this->enablePerformReport)
            $this->_report->endPerfReport(0);

        return $retarr;

    }

    /**
     *get microblog id by uin 
     *can get multiply head url a time
     *@param uin32_t $dwUin the uin used in oidb head
     *@param string $skey the key after login which oidb head need
     *@param array $uinlist the uinlist which wanted to get to microblog head url
     *@return array the result of microblog head url when success, boolen false will return when failed
     */
	public function getMblogHeadUrl($dwUin , $skey , $uinlist)
	{
		if($dwUin<0 || !is_array($uinlist))
                        return false;

		$fieldlist = array(24028);
		$data = array(
                        "uin" => $dwUin,
                        "servicetype" => 0,
                        "skey_len" => strlen($skey),
                        "skey" => $skey,
                        "waetype" => "",
                        "dwUin" => $dwUin,
                        "uinlist" => $uinlist,
                        "fieldlist" => $fieldlist
                );

		$reqObj = new CWaeOidb0x5eb($data , $this->nameServiceKey);
		if(!$reqObj)
			return false;

        if($this->enablePerformReport)
            $this->_report->beginPerfReport(Mod::app()->id , $reqObj->getoidbip() , $reqObj->getoidbport() , false);
            
		if($reqObj->encode() < 0)
        {
            if($this->enablePerformReport)
                $this->_report->endPerfReport(-1);
            return false;
        }

		$ret = 0 ;
		$rsp_buf = $reqObj->access($ret);
		if($rsp_buf === false)
		{
            if($this->enablePerformReport)
                $this->_report->endPerfReport(-2);
			return false;
        }

        $retarr = $reqObj->decode($rsp_buf , $ret);

        if($this->enablePerformReport)
            $this->_report->endPerfReport(0);

		return $retarr;
	}

    /**
     *get friend list 
     *@param uin32_t $dwUin the uin used in oidb head
     *@param string $skey the key after login which oidb head need
     *@return array the result of friend list when success, boolen false will return when failed
     */
	public function getFriendList($dwUin , $skey)
	{
		if($dwUin <= 0)
			return false;
	
		$data = array(
			"uin" => $dwUin,
			"servicetype" => 33,
			"skey_len"=> strlen($skey),
			"skey" => $skey,
			"waetype" => ""
		);
		
		$reqObj = new CWaeOidb0x462($data , $this->nameServiceKey);
		if(!$reqObj)
            return false;

        if($this->enablePerformReport)
            $this->_report->beginPerfReport(Mod::app()->id , $reqObj->getoidbip() , $reqObj->getoidbport() , false);
            
        if($reqObj->encode() < 0)
        {
        	if($this->enablePerformReport)
                $this->_report->endPerfReport(-1);
            return false;
          }

                $ret = 0 ;
                $rsp_buf = $reqObj->access($ret);
		if($rsp_buf === false)
		{
			if($this->enablePerformReport)
				$this->_report->endPerfReport(-2);
			return false;
		}

        $retarr = $reqObj->decode($rsp_buf , $ret);

        if($this->enablePerformReport)
            $this->_report->endPerfReport(0);

        return $retarr;
	}

    /**
     *get vip info 
     *@param uin32_t $dwUin the uin used in oidb head
     *@param string $skey the key after login which oidb head need
     *@param array $uinlist the uin list which want to get vip info
     *@return array the result of vip info when success, boolen false will return when failed
     */
	public function getVipInfo($dwUin , $skey , $uinlist)
	{
		if($dwUin <= 0 || !is_array($uinlist))
                        return false;

                $data = array(
                        "uin" => $dwUin,
                        "servicetype" => 41,
                        "skey_len"=> strlen($skey),
                        "skey" => $skey,
                        "waetype" => "",
			            "uinlist" => $uinlist
                );

        $reqObj = new CWaeOidb0x436($data , $this->nameServiceKey);
        if(!$reqObj)
            return false;

        if($this->enablePerformReport)
            $this->_report->beginPerfReport(Mod::app()->id , $reqObj->getoidbip() , $reqObj->getoidbport() , false);
            
        if($reqObj->encode() < 0)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-1);
            return false;
          }

                $ret = 0 ;
                $rsp_buf = $reqObj->access($ret);
                if($rsp_buf === false)
                {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-2);
                        return false;
                }

        $retarr = $reqObj->decode($rsp_buf , $ret);

        if($this->enablePerformReport)
            $this->_report->endPerfReport(0);

        unset($reqObj);
        return $retarr;
	}
    
	/*public function getVipInfoNotLogin($dwUin , $uinlist)
	{
		if($dwUin <= 0 || !is_array($uinlist))
            return false;

        $data = array(
            "uin" => $dwUin,
            "servicetype" => 31,
            "skey_len"=> 0,
            "skey" => "",
            "waetype" => "",
			"uinlist" => $uinlist
        );

        $reqObj = new CWaeOidb0x436($data , $this->nameServiceKey);
        if(!$reqObj)
            return false;

        if($this->enablePerformReport)
            $this->_report->beginPerfReport(Mod::app()->id , $reqObj->getoidbip() , $reqObj->getoidbport() , false);
            
        if($reqObj->encode() < 0)
            return false;

        $ret = 0 ;
        $rsp_buf = $reqObj->access($ret);
        if($rsp_buf === false)
        {
            return false;
        }

        $retarr = $reqObj->decode($rsp_buf , $ret);

        if($this->enablePerformReport)
            $this->_report->endPerfReport(0);

        return $retarr;
	}*/

    public function getNick($dwUin , $skey , $uinlist)
    {
        
		if($dwUin <= 0 || !is_array($uinlist))
                        return false;

        $fieldlist = array(20002);
        $data = array(
            "uin" => $dwUin,
            "servicetype" => 70,
            "skey_len"=> strlen($skey),
            "skey" => $skey,
            "waetype" => "",
		    "uinlist" => $uinlist,
            "fieldlist" => $fieldlist
        );

        $reqObj = new CWaeOidb0x49d($data , $this->nameServiceKey);
        if(!$reqObj)
            return false;

        if($this->enablePerformReport)
            $this->_report->beginPerfReport(Mod::app()->id , $reqObj->getoidbip() , $reqObj->getoidbport() , false);
            
        if($reqObj->encode() < 0)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-1);
            return false;
          }

        $ret = 0 ;
        $rsp_buf = $reqObj->access($ret);
        if($rsp_buf === false)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-2);
            return false;
        }
        
        $retarr = $reqObj->decode($rsp_buf , $ret);
        
        if($this->enablePerformReport)
            $this->_report->endPerfReport(0);

        return $retarr;
    }

    public function getFriendGroup($dwUin , $skey)
    {
		if($dwUin <= 0)
            return false;

        $data = array(
            "uin" => $dwUin,
            "servicetype" => 100,
            "skey_len"=> strlen($skey),
            "skey" => $skey,
            "waetype" => "",
            "cType" => 0
        );

        $reqObj = new CWaeOidb0x4b9($data , $this->nameServiceKey);
        if(!$reqObj)
            return false;

        if($this->enablePerformReport)
            $this->_report->beginPerfReport(Mod::app()->id , $reqObj->getoidbip() , $reqObj->getoidbport() , false);
            
        if($reqObj->encode() < 0)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-1);
            return false;
          }

        $ret = 0 ;
        $rsp_buf = $reqObj->access($ret);
        if($rsp_buf === false)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-2);
            return false;
        }

        $retarr = $reqObj->decode($rsp_buf , $ret);
                
        if($this->enablePerformReport)
            $this->_report->endPerfReport(0);

        return $retarr;
    }
    
    public function getFriendRemark($dwUin , $skey , $uinlist)
    {
		if($dwUin <= 0 || !is_array($uinlist))
            return false;

        $data = array(
            "uin" => $dwUin,
            "servicetype" => 0,
            "skey_len"=> strlen($skey),
            "skey" => $skey,
            "waetype" => "",
            "adwFriendUin" => $uinlist
        );

        $reqObj = new CWaeOidb0x513($data , $this->nameServiceKey);
        if(!$reqObj)
            return false;

        if($this->enablePerformReport)
            $this->_report->beginPerfReport(Mod::app()->id , $reqObj->getoidbip() , $reqObj->getoidbport() , false);
            
        if($reqObj->encode() < 0)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-1);
            return false;
          }

        $ret = 0 ;
        $rsp_buf = $reqObj->access($ret);
        if($rsp_buf === false)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-2);
            return false;
        }
        
        $retarr = $reqObj->decode($rsp_buf , $ret);
        
        if($this->enablePerformReport)
            $this->_report->endPerfReport(0);

        return $retarr;
    }
    
    public function getUinByMblogid($dwUin , $skey , $mblogid)
    {
		if($dwUin <= 0 || !is_array($mblogid))
            return false;

        $data = array(
            "uin" => $dwUin,
            "servicetype" => 0,
            "skey_len"=> strlen($skey),
            "skey" => $skey,
            "waetype" => "",
            "mblogid" => $mblogid
        );

        $reqObj = new CWaeOidb0x5d5($data , $this->nameServiceKey);
        if(!$reqObj)
            return false;

        if($this->enablePerformReport)
            $this->_report->beginPerfReport(Mod::app()->id , $reqObj->getoidbip() , $reqObj->getoidbport() , false);
            
        if($reqObj->encode() < 0)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-1);
            return false;
          }

        $ret = 0 ;
        $rsp_buf = $reqObj->access($ret);
        if($rsp_buf === false)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-2);
            return false;
        }
        
        $retarr = $reqObj->decode($rsp_buf , $ret);
        
        if($this->enablePerformReport)
            $this->_report->endPerfReport(0);

        return $retarr;
    }

    public function getFriendLevel($dwUin , $skey , $uinlist)
    {
        if($dwUin <= 0 || !is_array($uinlist))
            return false;

        $data = array(
            "uin" => $dwUin,
            "servicetype" => 0,
            "skey_len" => strlen($skey),
            "skey" => $skey,
            "waetype" => "",
            "uinlist" => $uinlist
        );

        $reqObj = new CWaeOidb0x668($data , $this->nameServiceKey);
        if(!$reqObj)
            return false;

        if($this->enablePerformReport)
            $this->_report->beginPerfReport(Mod::app()->id , $reqObj->getoidbip() , $reqObj->getoidbport() , false);
            
        if($reqObj->encode() < 0)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-1);
            return false;
          }

        $ret = 0;
        $rsp_buf = $reqObj->access($ret);
        if($rsp_buf === false)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-2);
            return false;
        }

        $retarr = $reqObj->decode($rsp_buf , $ret);

        if($this->enablePerformReport)
            $this->_report->endPerfReport(0);

        return $retarr;
    }
    
    public function pubTextMblog($dwUin , $skey , $textcontent)
    {
        if($dwUin <= 0 || !$textcontent)
            return false;
        
        if(!$this->is_utf8($textcontent))
        {
            $textcontent = iconv("GBK" , "UTF-8" , $textcontent);
        }

        $data = array(
            "uin" => $dwUin,
            "servicetype" => 0,
            "skey_len" => strlen($skey),
            "skey" => $skey,
            "waetype" => "",
            "cType" => 1,
            "cContentType" => 1,
            "strContent" => $textcontent
        );

        $reqObj = new CWaeOidb0x5c0($data , $this->nameServiceKey);
        if(!$reqObj)
            return false;

        if($this->enablePerformReport)
            $this->_report->beginPerfReport(Mod::app()->id , $reqObj->getoidbip() , $reqObj->getoidbport() , false);
            
        if($reqObj->encode() < 0)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-1);
            return false;
          }

        $ret = 0;
        $rsp_buf = $reqObj->access($ret);
        if($rsp_buf === false)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-2);
            return false;
        }

        $retarr = $reqObj->decode($rsp_buf , $ret);

        if($this->enablePerformReport)
            $this->_report->endPerfReport(0);

        return $retarr;
    }
    
    public function getShortUrl($dwUin , $skey , $url)
    {
        if($dwUin <= 0 || $url == '')
            return false;
        
        $data = array(
            "uin" => $dwUin,
            "servicetype" => 0,
            "skey_len" => strlen($skey),
            "skey" => $skey,
            "waetype" => "",
            "acLongUrl" => $url
        );

        $reqObj = new CWaeOidb0x8250($data , $this->nameServiceKey);
        if(!$reqObj)
            return false;

        if($this->enablePerformReport)
            $this->_report->beginPerfReport(Mod::app()->id , $reqObj->getoidbip() , $reqObj->getoidbport() , false);
            
        if($reqObj->encode() < 0)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-1);
            return false;
          }

        $ret = 0;
        $rsp_buf = $reqObj->access($ret);
        if($rsp_buf === false)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-2);
            return false;
        }

        $retarr = $reqObj->decode($rsp_buf , $ret);

        if($this->enablePerformReport)
            $this->_report->endPerfReport(0);

        return $retarr;
    }

    public function getGroupRole($dwUin , $skey , $group)
    {
        if($dwUin <= 0 || $group <= 0)
            return false;
        
        $data = array(
            "uin" => $dwUin,
            "servicetype" => 1,
            "skey_len" => strlen($skey),
            "skey" => $skey,
            "waetype" => "",
            "dwGroupCode" => $group
        );

        $reqObj = new CWaeOidb0x576($data , $this->nameServiceKey);
        if(!$reqObj)
            return false;

        if($this->enablePerformReport)
            $this->_report->beginPerfReport(Mod::app()->id , $reqObj->getoidbip() , $reqObj->getoidbport() , false);
           
        if($reqObj->encode() < 0)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-1);
            return false;
          }

        $ret = 0;
        $rsp_buf = $reqObj->access($ret);
        if($rsp_buf === false)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-2);
            return false;
        }

        $retarr = $reqObj->decode($rsp_buf , $ret);

        if($this->enablePerformReport)
            $this->_report->endPerfReport(0);

        return $retarr;
    }
    
    public function getMblogFndlst($dwUin , $skey , $startidx , $num)
    {
        if($dwUin <= 0 || !$skey)
            return false;
        
        $data = array(
            "uin" => $dwUin,
            "servicetype" => 1,
            "skey_len" => strlen($skey),
            "skey" => $skey,
            "waetype" => "",
            "dwToUin" => $dwUin,
            "dwStartIndex" => $startidx,
            "wReqNum" => $num
        );

        $reqObj = new CWaeOidb0x8009($data , $this->nameServiceKey);
        if(!$reqObj)
            return false;

        if($this->enablePerformReport)
            $this->_report->beginPerfReport(Mod::app()->id , $reqObj->getoidbip() , $reqObj->getoidbport() , false);
            
        if($reqObj->encode() < 0)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-1);
            return false;
          }

        $ret = 0;
        $rsp_buf = $reqObj->access($ret);
        if($rsp_buf === false)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-2);
            return false;
        }

        $retarr = $reqObj->decode($rsp_buf , $ret);

        if($this->enablePerformReport)
            $this->_report->endPerfReport(0);

        return $retarr;
    }
    
    public function sendMblogMsg($dwUin , $skey , $touin , $textcontent , $imgurl='')
    {
        if($dwUin <= 0 || !$skey)
            return false;
        
        $data = array(
            "uin" => $dwUin,
            "servicetype" => 0,
            "skey_len" => strlen($skey),
            "skey" => $skey,
            "waetype" => "",
            "toUin" => $touin,
            "strContent" => $textcontent
        );
        if($imgurl)
        {
        	$data["cContentType"] = 4;
        }
        else if(strpos($textcontent , "http://") === false)
        {
        	$data["cContentType"] = 2;
        }
        else
        {
        	$data["cContentType"] = 1;
        }

        $reqObj = new CWaeOidb0x829d($data , $this->nameServiceKey);
        if(!$reqObj)
            return false;

        if($this->enablePerformReport)
            $this->_report->beginPerfReport(Mod::app()->id , $reqObj->getoidbip() , $reqObj->getoidbport() , false);
            
        if($reqObj->encode() < 0)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-1);
            return false;
          }

        $ret = 0;
        $rsp_buf = $reqObj->access($ret);
        if($rsp_buf === false)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-2);
            return false;
        }

        $retarr = $reqObj->decode($rsp_buf , $ret);

        if($this->enablePerformReport)
            $this->_report->endPerfReport(0);

        return $retarr;
    }

    public function getQQHead($dwUin , $skey)
    {
        if($dwUin <= 0 || !$skey)
            return false;

        $data = array(
            "uin" => $dwUin,
            "servicetype" => 1,
            "skey" => $skey,
            "skey_len" => strlen($skey),
            "waetype" => ""
        );


        $reqObj = new CWaeOidb0x9999($data , $this->nameServiceKey);
        if(!$reqObj)
            return false;

        if($this->enablePerformReport)
            $this->_report->beginPerfReport(Mod::app()->id , $reqObj->getoidbip() , $reqObj->getoidbport() , false);

        if($reqObj->encode() < 0)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-1);
            return false;
          }

        $ret = 0;
        $rsp_buf = $reqObj->access($ret);
        if($rsp_buf === false)
        {
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(-2);
            return false;
        }

        $retarr = $reqObj->decode($rsp_buf , $ret);

        if($this->enablePerformReport)
            $this->_report->endPerfReport(0);

        return $retarr;

    }
}
?>
