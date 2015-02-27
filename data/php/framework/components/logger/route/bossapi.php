<?php
if(MOD_DEBUG)
	error_reporting(E_ALL ^ E_NOTICE);
else
	error_reporting(0);

$agentIP = "127.0.0.1";
$agentPort = 6578;
//ip:通常就是本机ip
function loginit($ip, $port)
{
	global $agentIP, $agentPort;
    $agentIP = $ip;
	$agentPort = $port;
}

//与printf的用法相似
//logprintf(format, data1, data2, ....);
//至少要包含8个参数，其中第一个为格式串，其余为对应的数据内容(至少7个必填字段。请参考接口文档说明)
//return 0:成功 -1:失败
function logprintf()
{
	global $agentIP, $agentPort;
	$num = func_num_args();
    loginit('127.0.0.1',6578);
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

	if(!socket_connect($socket, $agentIP, $agentPort))
	{
		socket_close($socket);
		return -1;
	}

	$len = strlen($log);
	//var_dump($log);
    $ret = socket_write($socket, $log, strlen($log));
	if($ret != $len)
	{
		socket_close($socket);
		return -1;
	}
	socket_close($socket);
	return 0;
}

/********************* demo usage for logprintf 
$ip = "192.168.1.1";
$qq = 12345;
$biz = "finance.stock.dpfx";
$op = "login";
$status = 0;
$logid = 119;
$flowid = 345678;
$custom = "custom message from php";
loginit("127.0.0.1", 6578);
if(logprintf("%s,%d,%s,%s,%d,%d,%d,%s", $ip, $qq, $biz, $op, $status, $logid, $flowid, $custom) < 0)
{
	echo "logprintf failed\n";
}
**********************/

/**********error log api*****错误日志API********/
//level: LOG_DEBUG, LOG_INFO, LOG_WARN, LOG_ERROR, LOG_FATAL
define("LOG_DEBUG", 1);
define("LOG_INFO", 2);
define("LOG_WARN", 3);
define("LOG_ERROR", 4);
define("LOG_FATAL", 5);
define("MAX_MSGLEN", 4096);

function SEND_LOG_ERROR($module, $uin, $cmd, $level, $errcode, $msg)
{
    sendError($module, $uin, $cmd, $level, $errcode, $msg);
}
/**********error log api*****错误日志API********/

/**********access log api******流水日志API****/
function SEND_LOG_ACCESS($uin, $module, $oper, $retcode, $iflow, $msg)
{
    sendAccessLog($uin, $module, $oper, $retcode, $iflow, $msg);
}
/**********access log api*******流水日志API****/



//====================================================================================================
function sendError($module, $uin, $cmd, $level, $errcode, $msg)
{
    if(!isset($module) || !isset($uin) || !isset($cmd) || !isset($level) 
	    || !isset($errcode) || !isset($msg))
    {
        return -1;
    }

    $localip = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : IP_LOCAL;
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

	if(strlen($msg) > MAX_MSGLEN) 
		$msg = substr($msg, 0, MAX_MSGLEN);
	
    $msg = str_replace("&"," ",$msg);
	$msg = str_replace(",","&",$msg);

    return logprintf("%s,%u,%s,%s,%d,%d,%d,%s,%d,%s,%s,%d,%d,%s", 
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

    $localip = IP2LONG(isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : IP_LOCAL);

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
	
	if(strlen($msg) > MAX_MSGLEN) 
		$msg = substr($msg, 0, MAX_MSGLEN);

    $msg = str_replace("&"," ",$msg);
	$msg = str_replace(",","&",$msg);
	
	return logprintf("%u,%u,%s,%s,%d,%d,%u,%s,%s,%d,%s",
		$localip, $uin, $module, $oper, $retcode, 534, $iflow,
		$srcfile, $func, $srcline, $msg);
}

?>
