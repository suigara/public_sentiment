<?php

class CWaeBossApi extends CApplicationComponent {
	const LOG_DEBUG = 1;
	const LOG_INFO = 2;
	const LOG_WARN = 3;
	const LOG_ERROR = 4;
	const LOG_FATAL = 5;
	const MAX_MSGLEN = 4096;
	private $agentIP = "127.0.0.1";
	private $agentPort = 6578;

	function SEND_LOG_ERROR($module, $uin, $cmd, $level, $errcode, $msg)
	{
    	$this->sendError($module, $uin, $cmd, $level, $errcode, $msg);
	}
	/**********error log api*****错误日志API********/

	/**********access log api******流水日志API****/
	function SEND_LOG_ACCESS($uin, $module, $oper, $retcode, $iflow, $msg)
	{
    	$this->sendAccessLog($uin, $module, $oper, $retcode, $iflow, $msg);
	}
	
	//ip:通常就是本机ip
	function loginit($ip, $port)
	{
		$this->agentIP = $ip;
		$this->agentPort = $port;
	}
	
	function logprintf()
	{
		$num = func_num_args();
	
		//7个必填的字段，再加一个格式串
		if($num < 8)
		{
			return -1;
		}
		$args = func_get_args();
		$log = vsprintf($args[0], array_slice($args, 1));
		
		$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		if($socket < 0)
		{
			return -1;
		}
	
		if(!socket_connect($socket, $this->agentIP, $this->agentPort))
		{
			socket_close($socket);
			return -1;
		}
	
		$len = strlen($log);
		$ret = socket_write($socket, $log, strlen($log));
		if($ret != $len)
		{
			socket_close($socket);
			return -1;
		}
		socket_close($socket);
		return 0;
	}
	
	function sendError($module, $uin, $cmd, $level, $errcode, $msg)
	{
	    if(!isset($module) || !isset($uin) || !isset($cmd) || !isset($level) 
		    || !isset($errcode) || !isset($msg))
	    {
	        return -1;
	    }
	
	    $localip = $_SERVER['SERVER_ADDR'];
		if($localip == "") $localip = "unkown";
	
	    $pid = posix_getpid();
		
	    $e = new Exception("");
	    $trace = $e->getTrace();
	    if(isset($trace[2]))
	    {
	        $srcfile = $trace[2]["file"];
	        $func = $trace[2]["function"];
	        $srcline = $trace[1]["line"];
	    }
	    else if(isset($trace[1]))
	    {
	        $srcfile = $trace[1]["file"];
			$func = $trace[1]["function"];
			$srcline = $trace[1]["line"];
	    }
		else
		{
			$srcfile = $trace[0]["file"];
			$func = $trace[0]["function"];
			$srcline = $trace[0]["line"];
		}
	
		if(strlen($msg) > self::MAX_MSGLEN) 
			$msg = substr($msg, 0, self::MAX_MSGLEN);
		
	    $msg = str_replace("&"," ",$msg);
		$msg = str_replace(",","&",$msg);
	
	    return $this->logprintf("%s,%u,%s,%s,%d,%d,%d,%s,%d,%s,%s,%d,%d,%s", 
			$localip, $uin, $module, $cmd, $errcode, 479, 0, 
			$srcfile, $srcline, $func, "httpd", $pid,  $level, $msg);
	}


	function sendAccessLog($uin, $module, $oper, $retcode, $iflow, $msg)
	{
	    if(!isset($module) || !isset($uin) || !isset($oper) || !isset($iflow) 
		    || !isset($retcode) || !isset($msg))
	    {
	        return -1;
	    }
	
	    $localip = IP2LONG($_SERVER['SERVER_ADDR']);
	
	    $pid = posix_getpid();
	
	    $e = new Exception("");
	    $trace = $e->getTrace();
	    if(isset($trace[2]))
	    {
	        $srcfile = $trace[2]["file"];
	        $func = $trace[2]["function"];
	        $srcline = $trace[1]["line"];
	    }
	    else if(isset($trace[1]))
	    {
	        $srcfile = $trace[1]["file"];
			$func = $trace[1]["function"];
			$srcline = $trace[1]["line"];
	    }
		else
		{
			$srcfile = $trace[0]["file"];
			$func = $trace[0]["function"];
			$srcline = $trace[0]["line"];
		}
		
		if(strlen($msg) > self::MAX_MSGLEN) 
			$msg = substr($msg, 0, self::MAX_MSGLEN);
	
	    $msg = str_replace("&"," ",$msg);
		$msg = str_replace(",","&",$msg);
		
		return $this->logprintf("%u,%u,%s,%s,%d,%d,%u,%s,%s,%d,%s",
			$localip, $uin, $module, $oper, $retcode, 534, $iflow,
			$srcfile, $func, $srcline, $msg);
	}
}

?>
