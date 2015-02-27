<?php
require_once("Base.php");

require_once("pb_proto_counterRes.php");
require_once("pb_proto_counterRequest.php");
/**
 * 轮询是否有新的Push消息
 * $obj = new PushReq();
 * $val = $obj->access("172.27.7.247", 10888 ...);
 * $val 就是需要取得的信息
 */
class CWaeCounter extends HexProtocol
{

	private $accessor;
	private $ip;
	private $port;
    public $server;
    public $nameServiceKey;
    public $enablePerformReport=false;
    public $_report;
    public function init()
    {
        if(is_array($this->server)){
            if(isset($this->server['host'])) $this->ip = $this->server['host'];
            if(isset($this->server['port'])) $this->port = $this->server['port'];
        }
        if($this->nameServiceKey){
            if(Mod::app()->nameService->getHostByKey($this->nameServiceKey, $ip, $port)==false)
                throw new CException("NameServer fail, key:{$this->nameServiceKeyMaster}");
            else{
                $this->ip = $ip;
                $this->port = $port;
            }
        }
        $accessor = new Accessor($this->ip, $this->port);
		$this->accessor = $accessor;
    }

	public function __destruct()
	{
		if($this->accessor !== null)
		{
			$this->accessor = null;
		}
	}
	
	public function encode()
	{
		return "";
	}

	public function makeRequest($wBizType, $body)
	{
		$head = new TransPkgHead();
		$head->dwLength = strlen($body);
		$head->wType = $wBizType;
		$head->cversion = 0;
		$head->dwSeq = 0;
		$head->cResLen = 0;
		$head->acReserve = "";

		//body is empty
		$pkg = $head->encode("") . $body;
		return $pkg;
	}

	public function parseResult($uin, &$cResult)
	{}
	
	/**
	 * wFace + cAge + cGender + cNickLength + strNick + dwFlag＋cEmalLen + strEmail
	 * 
	 */
	 
	 public function SaeCounterInit($ip,$port)
	 {
	     $accessor = new Accessor($ip, $port);
		 $this->accessor = $accessor;
		 $this->ip = $ip;
		 $this->port = $port;
	 }
	 
	 public function SaeCounterGet($key)
	 {
        if($this->enablePerformReport != false){
        	$this->_report = new CPhpPerfReporter();
            $this->_report->beginPerfReport(Mod::app()->id,'' , '', false, '', "");
		}
        $counterReq = new  CounterRequest();
	    $counterReq->set_bid($this->port);
	    $counterReq->set_keyName($key);
	    $counterReq->set_requestType(CounterRequest_RequestType::requestTypeGet);
        $type = CounterRequest_RequestType::requestTypeGet;
        $body = $counterReq->SerializeToString(); 
	    $serviceType = 0;
	    $pkg = pack("c", 0x55);
	    $pkg .= $this->makeRequest($serviceType, $body);
	    $pkg .= pack("c", 0xAA);
	    $ret = $this->accessor->access($pkg);
		if($ret !== false)
		{
			$head = new TransPkgHead;
			$len = $head->decode(substr($ret, 1));
			//echo $head->wLength . " " . $len . " " . ($head->wLength - $len);
			$body = substr($ret, $len + 1, $head->dwLength - $len - 2);
			
			
			$counterRes = new CounterRes();
			$counterRes->parseFromString($body);
            if($this->enablePerformReport)
                $this->_report->endPerfReport(0);
			if(intval($counterRes->rescode()) == -1)
			{
			   return -1;
			}
			else
			{
				return intval($counterRes->keyVaule());
			}
		}
		else
		{
			$reallyKey = $this->port."_".$key;
			if($this->enablePerformReport)
                $this->_report->endPerfReport(0);
            Mod::log('get key '.$reallyKey.' failed', CLogger::LEVEL_ERROR, 'counter.SaeCounterGet');
            return -1;
		}
		
	 }
	 
	 
	 public function SaeCounterIncr($key)
	 {
	      
        if($this->enablePerformReport != false){
        	$this->_report = new CPhpPerfReporter();
            $this->_report->beginPerfReport(Mod::app()->id,'' , '', false, '', "");
		}
        $counterReq = new  CounterRequest();
	    $counterReq->set_bid($this->port);
	    $counterReq->set_keyName($key);
	    $counterReq->set_requestType(CounterRequest_RequestType::requestTypeIncr);
	    $body = $counterReq->SerializeToString();
	   
	    $serviceType = 0;
	    $pkg = pack("c", 0x55);
	    $pkg .= $this->makeRequest($serviceType, $body);
	    $pkg .= pack("c", 0xAA);
	   
	    $ret = $this->accessor->access($pkg);
		if($ret !== false)
		{
			$head = new TransPkgHead;
			$len = $head->decode(substr($ret, 1));
			//echo $head->wLength . " " . $len . " " . ($head->wLength - $len);
			$body = substr($ret, $len + 1, $head->dwLength - $len - 2);
			
			
			$counterRes = new CounterRes();
			$counterRes->parseFromString($body);	
            if($this->enablePerformReport)
                $this->_report->endPerfReport(0);	
            if(intval($counterRes->rescode()) == -1)
			{
			   return -1;
			}
			else
			{
				return intval($counterRes->keyVaule());
			}
		}
		else
		{
			
			$reallyKey = $this->port."_".$key;
            if($this->enablePerformReport)
                $this->_report->endPerfReport(0);	
            Mod::log('incr key '.$reallyKey.' failed', CLogger::LEVEL_ERROR, 'counter.SaeCounterIncr');
            return -1;
		}
	 }
	 
	  public function SaeCounterDecr($key)
	 { 
        if($this->enablePerformReport != false){
        	$this->_report = new CPhpPerfReporter();
            $this->_report->beginPerfReport(Mod::app()->id,'' , '', false, '', "");
		}
        $counterReq = new  CounterRequest();
	    $counterReq->set_bid($this->port);
	    $counterReq->set_keyName($key);
	    $counterReq->set_requestType(CounterRequest_RequestType::requestTypeDecr);
	    $body = $counterReq->SerializeToString();
	   
	    $serviceType = 0;
	    $pkg = pack("c", 0x55);
	    $pkg .= $this->makeRequest($serviceType, $body);
	    $pkg .= pack("c", 0xAA);
	   
	    $ret = $this->accessor->access($pkg);
		if($ret !== false)
		{
			$head = new TransPkgHead;
			$len = $head->decode(substr($ret, 1));
			//echo $head->wLength . " " . $len . " " . ($head->wLength - $len);
			$body = substr($ret, $len + 1, $head->dwLength - $len - 2);
			
			
			$counterRes = new CounterRes();
			$counterRes->parseFromString($body);
            if($this->enablePerformReport)
                $this->_report->endPerfReport(0);
			if(intval($counterRes->rescode()) == -1)
			{
			   return -1;
			}
			else
			{
				return intval($counterRes->keyVaule());
			}
		}
		else
		{
			
			$reallyKey = $this->port."_".$key;
            if($this->enablePerformReport)
                $this->_report->endPerfReport(0);	
            Mod::log('decr key '.$reallyKey.' failed', CLogger::LEVEL_ERROR, 'counter.SaeCounterDecr');
            return -1;
		}
	 }
	 
	  public function SaeCounterExpire($key,$expireSeconds)
	 {
        if($this->enablePerformReport != false){
        	$this->_report = new CPhpPerfReporter();
            $this->_report->beginPerfReport(Mod::app()->id,'' , '', false, '', "");
        }
        $counterReq = new  CounterRequest();
	    $counterReq->set_bid($this->port);
	    $counterReq->set_keyName($key);
	    $counterReq->set_requestType(CounterRequest_RequestType::requestTypeExpire);
	    $counterReq->set_expiredSeconds($expireSeconds);
	    $body = $counterReq->SerializeToString();
	   
	    $serviceType = 0;
	    $pkg = pack("c", 0x55);
	    $pkg .= $this->makeRequest($serviceType, $body);
	    $pkg .= pack("c", 0xAA);
	   
	    $ret = $this->accessor->access($pkg);
		if($ret !== false)
		{
			$head = new TransPkgHead;
			$len = $head->decode(substr($ret, 1));
			//echo $head->wLength . " " . $len . " " . ($head->wLength - $len);
			$body = substr($ret, $len + 1, $head->dwLength - $len - 2);
			
			
			$counterRes = new CounterRes();
			$counterRes->parseFromString($body);
            if($this->enablePerformReport){
                $this->_report->endPerfReport(0);
            }
			if(intval($counterRes->rescode()) == -1)
			{
			   return -1;
			}
			else
			{
				return intval($counterRes->keyVaule());
			}
		}
		else
		{
			
			$reallyKey = $this->port."_".$key;
            if($this->enablePerformReport)
                $this->_report->endPerfReport(0);	
            Mod::log('expire key '.$reallyKey.' failed', CLogger::LEVEL_ERROR, 'counter.SaeCounterExpire');
            return -1;
		}
	 }
	  
}
