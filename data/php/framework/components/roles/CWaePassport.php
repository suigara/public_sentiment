<?php
/**
 * 地方站根据passport获取qq号的权限
 * pechspeng
 * 2014-01-13
 * --------------------------------------
 * @modified 
 *     1. 组件化修改
 *     2. 增加验证server的ip和端口配置
 *     3. 增加验证server的名字配置
 * 
 * 一 实例化方式使用:
 *    Mod::import('system.components.roles.CWaePassport');
 *    $passport = new CWaePassport();
 *　　// 默认不设置使用PASSPORT_API_NAME获取
 *    // 开发环境(devnet内)使用如下配置
 *    $passport->ip = '172.25.39.75'; 
 *    $passport->port = '8801'; 
 *　　$passport->init();
 *    $ret = $passport->getRole();
 *    var_dump($ret);
 *
 * 二 获取通过配置方式使用
 * 'componenets'=>array(
 *      'passport'=>array(
 *          'class'=>'system.components.roles.CWaePassport',
 *          'ip'=>'172.25.39.75'，
 *          'port'=>'8801'，
 *		)
 *  )...
 *　调用方法：
 *  $ret = Mod::app()->passport->getRole();
 
 * @author wilsonwsong
 * @since 2014-04-22
 */

class CWaePassport extends CApplicationComponent
{	
	/*获取IP和PORT的的名字服务 */
	const PASSPORT_API_NAME = 'api.passport.areasite.com';
	
	/*获取IP和PORT的的名字服务 */
	public $nameServiceKey;
	public $ip ;
	public $port ;
	
	protected static $uin_idx = array('uin', 'luin');
	protected static $skey_idx = array('skey', 'lskey');
	
	public function init()
	{
		parent::init();
		if(!$this->ip || !$this->port){
			// 如果没有配置ip和port，使用名字服务获取相应的
			// 如果没有指定nameServiceKey，使用默认值
			if(!$this->nameServiceKey)
				$this->nameServiceKey = self::PASSPORT_API_NAME;	
			// 通过名字服务获取IP和PORT
			if(Mod::app()->nameService->getHostByKey($this->nameServiceKey, 
					$this->ip, $this->port)==false)
				throw new CException("NameServer fail, key:{$this->nameServiceKey}");
		}
		
	}
	/**
	 * get remote ip
	 * @return string
	 **/
	public function getIp() {
		return isset($_SERVER['HTTP_X_FORWARDED_FOR'])
				? trim(end(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])))
				: $_SERVER['REMOTE_ADDR'];
	}

	/**
	 * get uin
	 * @return string
	 **/
	public function getUin() {

		foreach(self::$uin_idx as $idx) {
			if(array_key_exists($idx, $_COOKIE)) {
				return ltrim($_COOKIE[$idx], 'o0 ');
			}
		}
		return '';
	}

	/**
	 * get skey
	 * @return string
	 **/
	public function getSkey() {

		foreach(self::$skey_idx as $idx) {
			if(array_key_exists($idx, $_COOKIE)) {
				return $_COOKIE[$idx];
			}
		}

		return '';
	}

	/**
	 * get rescode
	 * @return string
	 */
	public function getRescode() {
		if(array_key_exists('city_sites', $_COOKIE) &&
			preg_match('/^[a-z0-9_]+/', $_COOKIE['city_sites'], $matches)) {

			return $matches[0];
		}

		if(array_key_exists('PAS_COOKIE_PROJECT_GROUP', $_COOKIE) &&
			preg_match('/\|areasite\:([a-z0-9_]+)/',
			$_COOKIE['PAS_COOKIE_PROJECT_GROUP'], $matches)) {

			return $matches[1];
		}

		return '';
	}

	

	/**
	 * get role directly with sys_param code
	 * return false on request failed or decode failed
	 *
	 * @param $rescode
	 * @param $uin
	 * @param $skey
	 * @param $userip
	 * @return array
	 */
	public function __getRole($rescode = null, $uin = null, $skey = null, $userip = null) {

		$uin = $uin ? $uin : $this->getUin();
		$param = http_build_query(array(
			'rescode' => $rescode ? $rescode : $this->getRescode(),
			'uin' => $uin,
			'skey' => $skey ? $skey : $this->getSkey(),
			'userip' => $userip ? $userip : $this->getIp(),
			'of' => 'json'
		));
		$cookie = str_replace('&', ';', http_build_query($_COOKIE));

		$try = 2;
		while($try--) {
			try {
				$url = 'http://'.$this->ip.':'.$this->port.'/getrole.php?'.$param;
				$ch = curl_init($url);
				curl_setopt($ch, CURLOPT_COOKIE, $cookie);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
				$raw = curl_exec($ch);
				curl_close($ch);

				$raw = json_decode($raw, TRUE);
				if(is_array($raw)){
					$raw['sys_param']['uin'] = $uin;
					return $raw;
				}
			} catch(Exception $e) {
				// curl request exception
				continue;
			}
		}
		return false;
	}

	/**
	 * Get role data only
	 * return false on failed
	 *
	 * @param $rescode
	 * @param $uin
	 * @param $skey
	 * @param $userip
	 * @return array
	 * array(
	 * 	[int role1], [int role2], ...
	 * )
	 */
	public function getRole($rescode = null, $uin = null, $skey = null, $userip = null) {
		$role = $this->__getRole($rescode, $uin, $skey, $userip);

		return (is_array($role) && $role['sys_param']['ret_code'] === 0) ?
			$role['data'] : false;
	}

	

	

}




