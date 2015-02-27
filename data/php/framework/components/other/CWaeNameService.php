<?php
/**
 * 通过名字服务查询ip和port
 */
define("ZKNAMES_API_UNPACK_ERR" , -105);
define("ZKNAMES_API_PARAM_ERR" , -104);
define("ZKNAMES_API_RECV_ERR" , -103);
define("ZKNAMES_API_SEND_ERR" , -102);
define("ZKNAMES_API_CONN_ERR" , -101);
class CWaeNameService extends CApplicationComponent
{
	public function init(){
		if(!class_exists('ZkHost',false)){
			require_once '/usr/local/zk_agent/names/nameapi.php';
		}
		parent::init();
	}
	/**
	 * 查询名字服务
	 *
	 * @param sting $key
	 * @param string $ip
	 * @param string $port
	 * @return bool
	 */
	public function getHostByKey($key, &$ip, &$port)
	{
		$server = new ZkHost;
		$ret = getHostByKey($key, $server);
		if(!$ret)
		{
			$ip = $server->ip;
			$port = $server->port;
			return true;
		}
		else{
			switch($ret){
				case ZKNAMES_API_CONN_ERR:
					Mod::log('unix connenct faild',CLogger::LEVEL_ERROR,'other.CWaeNameService');
					break;
				case ZKNAMES_API_SEND_ERR:
					Mod::log('unix send faild',CLogger::LEVEL_ERROR,'other.CWaeNameService');
					break;
				case ZKNAMES_API_RECV_ERR:
					Mod::log('unix recv faild',CLogger::LEVEL_ERROR,'other.CWaeNameService');
					break;
				case ZKNAMES_API_UNPACK_ERR:
					Mod::log('unPackData faild',CLogger::LEVEL_ERROR,'other.CWaeNameService');
					break;
				case ZKNAMES_API_PARAM_ERR:
					Mod::log('param error',CLogger::LEVEL_ERROR,'other.CWaeNameService');
					break;
			}
			return false;
		}
	}
	
	/**
	 * 查询字典服务
	 *
	 * @param sting $key
	 * @param string $value
	 * @return bool
	 */
	public function getValueByKey($key, &$value)
	{
		$ret = getValueByKey($key, $value);
		if(!$ret)
		{
			return true;
		}
		else{
			 switch($ret){
                case ZKNAMES_API_CONN_ERR:
                    Mod::log('zk error unix connenct faild:key is '.$key,CLogger::LEVEL_ERROR,'other.CWaeNameService');
                    break;  
                case ZKNAMES_API_SEND_ERR:
                    Mod::log('zk error unix send faild:key is '.$key,CLogger::LEVEL_ERROR,'other.CWaeNameService');
                    break;  
                case ZKNAMES_API_RECV_ERR:
                    Mod::log('zk error unix recv faild:key is '.$key,CLogger::LEVEL_ERROR,'other.CWaeNameService');
                    break;  
                case ZKNAMES_API_UNPACK_ERR:
                    Mod::log('zk error unPackData faild:key is '.$key,CLogger::LEVEL_ERROR,'other.CWaeNameService');
                    break;  
                case ZKNAMES_API_PARAM_ERR:
                    Mod::log('zk error param error:key is '.$key,CLogger::LEVEL_ERROR,'other.CWaeNameService');
                    break;  
            }   
			return false;
		}
	}
}
