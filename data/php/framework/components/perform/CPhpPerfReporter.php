<?php
/*
 * php接口性能上报类
 * @author samuelxu
 * @date 20130104
 * @alter pechspeng
 * @updatetime 20140218
 * @updatedetail 修改上报的东西过长,导致程序中断,限制上报的长度(因为这个相比业务还是次要一点的)
 */

/*
 * 上报的字段中不要再包含逗号,
 * Example: report information of myFunction
 * 
 * function myFunction() {
 * 	   $profiler = new CPhpPerfReporter();
 *     $profiler->beginPerfReport(1);
 *     //do something
 *     $code = 0;
 *     $profiler->endPerfReport($code);
 *     return $code;
 * }
 *
 * //do something
 * myFunction();
 * 注意： 对于同一个profiler对象，只可同级调用，不可跨层级调用，例如：
 * $profiler = new CPhpPerfReporter();
 * $profiler->beginPerfReport(1);
 * function errExample() {
 *     global $profiler;
 *     $profiler->beginPerfReport(1);	//跨级调用 不允许
 *     //do something
 *     $code = 0;
 *     $profiler->endPerfReport($code);
 *     return $code;
 * }
 * $retCode = errExample();
 * $profiler->endPerfReport($retCode);
 */

class CPhpPerfReporter
{
    private $agentIp;
    private $agentPort;
    private $appName; //业务名称
    private $phpFileName; //php文件名
    private $phpClassName; //php类名
    private $phpFuncName; //php函数名
    private $clientIp; //客户机IP
    private $localIp; //本机IP
    private $remoteIp; //接口机Ip地址，可选
    private $remotePort; //接口机端口Port，可选
    private $retCode; //调用CGI/接口返回码
    private $params;    //自定义参数
    private $beginTime; //记录起始时间
    private $isCgi;    //是否整体cgi监测
    private $device;    //终端设备型号，可选
    private $devId;    //终端设备ID，可选
    private $appVer;    //app版本号，可选
    private $fChannelId;    //来源渠道ID，可选
    private $sChannelId;    //场景渠道ID，可选
    private $reportLength;  //一次上报的长度
    
    function __construct ()
    {
        $this->agentIp = "127.0.0.1";
        $this->agentPort = 6578;
        $this->params = array();
        $this->reportLength = 1024;
    }
    
    public function addParam($key, $value)
    {
        $this->params[$key] = $value;
    }
    
    public function setClassName($name)
    {
        $this->phpClassName = $name;
    }
    
    public function setLocalIp($ip)
    {
        $this->localIp = $ip;
    }
    
    public function getLocalIp(){
    	return $this->localIp;
    }
    
    public function setFunctionName($name)
    {
        $this->phpFuncName = $name;
    }
    
    private function getClientIp()
    {
        $unknown = 'unknown';
        if(getenv("HTTP_CLIENT_IP") && strcasecmp(getenv("HTTP_CLIENT_IP"), $unknown))
        {
            $ip = getenv("HTTP_CLIENT_IP");
        }
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] && strcasecmp($_SERVER['HTTP_X_FORWARDED_FOR'], $unknown))
        {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } 
    	else if(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], $unknown))
        {
            $ip = $_SERVER['REMOTE_ADDR'];
        } 
        /*  处理多层代理的情况  或者使用正则方式：
         *  $ip = preg_match("/[\d\.]{7,15}/", $ip, $matches) ? $matches[0] : $unknown;  
         */ 
        if (false !== strpos($ip, ',')){
        	$ip_array=explode(',', $ip);
            $ip = reset($ip_array);
        }  
        return $ip;
    }   
    
    /*
     * 监控代码起始标记
     */
    public function beginPerfReport ($appName, $remoteIp = '', $remotePort = '', $isCgi = false, $device = '', $devId = '', $appVer = '', $fChannelId = 0, $sChannelId = 0)
    {
        $e = new Exception("");
        $trace = $e->getTrace();
        $idx = min(count($trace) - 1, 1);
        $this->phpFileName = $trace[$idx]['file'];
        
        $this->phpClassName = $trace[$idx]['class'];
        $this->phpFuncName = $trace[$idx]['function'];
        
        $this->appName = $appName;
        $this->clientIp = $this->getClientIp();
        $this->localIp = $_SERVER['SERVER_ADDR'];
        if($this->localIp == "") $this->localIp = "unknow";
        $this->remoteIp = $remoteIp;
        $this->remotePort = $remotePort;
        $this->isCgi = $isCgi;
        $this->device = $device;
        $this->devId = $devId;
        $this->appVer = $appVer;
        $this->fChannelId = $fChannelId;
        $this->sChannelId = $sChannelId;
        $this->beginTime = microtime(true);
        return 0;
    }
    
    /*
     * 监控代码结束标记，上报性能数据
     */
    public function endPerfReport ($retCode)
    {
        $endTime = microtime(true);
        $usedTime = ($endTime - $this->beginTime) * 1000000;
        
        //调用boss接口上报数据
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($socket < 0)
        {
            return - 1;
        }
        
        if (!socket_connect($socket, $this->agentIp, $this->agentPort))
        {
            socket_close($socket);
            return - 1;
        }
        
        $content = sprintf(
        	"%s,%d,%s,%d,%d,%d,%d,%s,%s,%s,%s,%s,%d,%d,%s,%s,%s,%d,%d", 
            $this->clientIp, 0, $this->appName, $retCode, $this->isCgi, 1666, 0, 
            $this->phpFileName, $this->phpClassName, $this->phpFuncName, 
            $this->localIp, $this->remoteIp, $this->remotePort, 
            $usedTime,
            $this->device, $this->devId,
            $this->appVer,
            $this->fChannelId, $this->sChannelId);

        //自定义参数
        $format = str_repeat(",%s", count($this->params));
        $attach = vsprintf($format, $this->params);
        $content .= $attach;
        //echo $content;
        $content = substr($content,0,$this->reportLength);
        $len = strlen($content);
        $ret = @socket_write($socket, $content, $len);
        if($ret === false)
        {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            Mod::log("errorcode= {$errorcode} errormsg = {$errormsg} \n len={$len} \ncontent= {$content}",CLogger::LEVEL_ERROR);

            return - 2;
        }
        if ($ret != $len)
        {
            socket_close($socket);
            return - 1;
        }
        socket_close($socket);
        unset($this->params);
        return 0;
    }
    
    /*
     *获取本机的内网ip
     */
    public function genLocalIp(){
		if(!file_exists('/tmp/ipLocal.php'))
		{
			//判断ifcfg-eth1文件是否存在，tlinux的文件路径需要区别对待
			if(file_exists('/etc/sysconfig/network/ifcfg-eth1'))
				$ifcfgEth1 = '/etc/sysconfig/network/ifcfg-eth1';
			else if(file_exists('/etc/sysconfig/network-scripts/ifcfg-eth1'))
				$ifcfgEth1 = '/etc/sysconfig/network-scripts/ifcfg-eth1';
			else
				return 'unknow';
			
			//从ifcfg-eth1文件解析出本机ip
			$data = file($ifcfgEth1);
			foreach($data as $line)
			{
				list($tmp1, $tmp2) = explode('=', $line);
				if($tmp1=='IPADDR')
				{
					$ipLocal = str_replace(array('\'', "\n"), '', $tmp2);
					if(!file_put_contents('/tmp/ipLocal.php', "<?php\n\treturn '$ipLocal';\n"))
						return 'unknow';
					else
						return  $ipLocal;
				}
			}
			
			//解析出本机ip失败
			return 'unknow';
		}
		else
			$ipLocal = require('/tmp/ipLocal.php');
		return $ipLocal;
	}
}
?>
