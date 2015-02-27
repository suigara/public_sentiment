<?php
require_once dirname(__FILE__).'/base.php';

class COidbPkg extends HexProtocol
{
    public $uin;
    public $skey;
    public $wCommand;
    public $requestData;
    public $serviceType=0;
    public $rep_msg;//返回数据

    public function checkparam($data)
    {
        if(!isset($data['uin']))
            return false;
        $this->uin = $data['uin'];

        if(!isset($data['skey']))
            return false;
        $this->skey = $data['skey'];
		
		if(!isset($data['clientIP']))
            return false;
		$this->clientIP = $data['clientIP'];
		
        return true;
    }

    public function accessInternal($host, $port, $data, $proxy=false)
    {
        if($this->checkparam($data)==false)
            return false;

        if ($proxy)
            $pkg = $this->makeProxyRequest($this->uin, $this->skey);
        else 
        {
            try
            {
            	$pkg = $this->makeRequest($host,$port,$this->uin, $this->skey);
			}
			catch(Exception $e)
			{
				return false;
			}
        }
        $accessor = new Accessor();
        $ret = $accessor->access($host, $port, $pkg);
        if($ret !== false)
        {
            if ($proxy)
                $head = new TransPkgProxy_K_API();
            else
                $head = new TransPkgHead_K_API();
            
            $len = $head->decode($ret);
            if($head->cResult != 0)
                return false;

            $this->rep_msg = $this->decodeResponseData($ret,$len);
            if(!$this->rep_msg)
                return false;

            return true;
        }
        else
        	return false;
    }
    
    public function decodeResponseData($buffer, $len)
    {
        return array();
    }

    public function encodeRequest()
    {
        return '';
    }

    public function makeRequest($host, $port, $uin, $skey)
    {
		$this->uin = $uin;
		$head = new TransPkgHead_K_API();
		$head->wCommand = $this->wCommand;
		$head->wVersion = 5;
		$head->dwUin = $uin;
		$head->assistInfo->wVersion = 0;
		$head->assistInfo->serviceType = $this->serviceType;
		$head->assistInfo->userName = '';
		$head->assistInfo->passwd = '';
		$head->assistInfo->serviceName = '';
		$head->assistInfo->serviceIP = $host;
		$head->assistInfo->clientIP = $this->clientIP ;
		$head->assistInfo->clientName = '' ;
		
        $head->transPkgHeadExt->wExVer = 600;
		/*便于测试 写的是u.qq.com的ptlogin值*/
        $head->transPkgHeadExt->dwAppID = 5000501;
        if(strlen($skey)>10)
            $head->transPkgHeadExt->cKeyType = 11;
        else
            $head->transPkgHeadExt->cKeyType = 1;
        
        $head->transPkgHeadExt->sessionKey = $skey;
        $pkg = $head->encode($this->encodeRequestData());
        return $pkg;
    }

    public function makeProxyRequest($uin, $skey)
    {
        $this->uin = $uin;
        $oidbPkg = new TransPkgProxy_K_API();
        $oidbPkg->wCommand = $this->wCommand;
        $oidbPkg->wVersion = 5;
        $oidbPkg->dwUin = intval($uin);
        $oidbPkg->serviceType = $this->serviceType;
        $oidbPkg->userIp = 33444433; //@todo 获取用户ip
        if(strlen($skey)>10)
        {
            $len = strlen($skey);
            $oidbPkg->sessionKeyType = 11;
            $oidbPkg->sessionKey = pack("H{$len}",$skey);
        }
        else
        {
            $oidbPkg->sessionKeyType = 1;
            $oidbPkg->sessionKey = $skey;
        }
        $pkg = $oidbPkg->encode($this->encodeRequestData());
        return $pkg;
    }
}