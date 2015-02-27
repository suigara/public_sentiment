<?php
//主体工作类
class CWaeIp extends CApplicationComponent
{
	//域名是否为qq.com
	//public $domainqq=false;
	private $_ipurl = "http://172.27.37.60/ipwhere?ip=";
	
	public function init(){
		/*if($this->domainqq === true){
			$this->_ipurl = "http://fw.qq.com/ipwhere?callback=backfunc&ip=";
		}*/
		$ip='';
		$port='';
		$nameServiceKey='internal.fw.qq.com';
		$ret=Mod::app()->nameService->getHostByKey($nameServiceKey, $ip, $port);
		if($ret){
			$this->_ipurl="http://${ip}:${port}/ipwhere?ip=";
		}
		parent::init();
	}
    /**
     * @function get ip detail info by ip
     * @params ip
     * @return array
     */
    public function getIpInfo($ip)
    {
       $url = $this->_ipurl.$ip;
       $obj = new CUrl;
       $obj->init();
       $data = $obj->get($url);
	   return iconv("GB2312", "UTF-8", $data); 
    }
}
