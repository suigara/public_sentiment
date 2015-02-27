<?php
/**
 * @desc  集成L5路由查询功能
 * 	  由于L5通过modid和cmdid查询ip, 名字服务的Key表示成modid:cmdid的字符串
 *   注：需要安装L5客户端和php的对应扩展模块
 * @author wilsonwsong
 * @since 2013-07-27
 */
class CWaeL5NameService extends CApplicationComponent
{
	public $l5_time_out = 0.2 ;
	/**
	 * @param sting $key modid:cmdid的字符串
	 * @param string $ip
	 * @param string $port
	 * @return bool
	 */
	public function getHostByKey($key, &$ip, &$port)
	{
		$ids = explode(":" ,$key);
		if(count($ids) != 2 ) return false;
		$l5_req = array('flow' => 0,'modid' => $ids[0],'cmd' => $ids[1],'host_ip' => '','host_port' => 0 );
		$errmsg="";
		$ret = l5sys_get_route($l5_req, $this->l5_time_out, $errmsg);
		
		if($ret >=0 )
		{
			$ip = $l5_req->host_ip;
			$port = $l5_req->host_port;
			return true;
		}else{
			Mod::log($errmsg, CLogger::LEVEL_ERROR,'application.components.l5');			
			return false;
		}
	}
	
	/**
	 * 上报接口服务调用时间(毫秒)
	 * @param unknown_type $key
	 * @param unknown_type $ip
	 * @param unknown_type $port
	 * @param unknown_type $ret
	 * @param unknown_type $usetime 
	 * @return boolean
	 */
	public function feedback($key, $ip, $port, $ret, $usetime)
	{
		$ids = explode(":" ,$key);
		if(count($ids) != 2 ) return false;
		$l5_req = array('flow' => 0,'modid' => $ids[0],'cmd' => $ids[1],'host_ip' =>$ip,'host_port' =>$port);
		$errmsg="";
		$ret = l5sys_route_result_update($l5_req, $ret, $usetime, $errmsg);
		if($ret < 0 )
		{
			Mod::log($errmsg, CLogger::LEVEL_ERROR,'application.components.l5');
			return false;
		}
		return true;
	}
}

