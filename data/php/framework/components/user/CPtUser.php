<?php
/**
 * @uses QQ登录组件,使用前请安装ptlogin的php模块
 * 使用方法：
 *  	if(!Mod::app()->user->isGuest){
 *  		username = Mod::app()->user->name;
 *  		uin = Mod::app()->user->uin;	 
 * 		}
 * 配置说明：
 *  'user'=>array(
 * 		'class'=>'system.components.user.CPtUser',
 * 		'appid'=>'申请的appid',
 * 		'daid' =>'隔离登录态使用的域名id',
 * 		'login_type'=>'登录类型, 默认隔离普通登录',
 * 		'login_url'=>'跳转地址',
 * 		'loginRequiredAjaxResponse'=>'ajax未登录返回'	
 *    )
 * @property name the nick name of the QQ user
 * @property id the alias of uin
 * @property mail 
 * @property gender
 * @property face
 * @property logintime
 * @property lastaccess 
 * @author wilsonwsong
 * @since 2013-11-06
 */
class CPtUser extends CWebUser {
	
	const PT_VERIFY4 = 0x01;
	const PT_VERIFY4_LLOGIN = 0x02;
	const PT_VERIFY4_EX = 0x04;
	const PT_VERIFY4_LLOGIN_EX = 0x08;
	
	const DAID_DIC_NAME = 'daid.%s.%s.dic';
	public $appid = 5000701;
	public $daid;
	public $login_type ; 
	public $login_url= "http://ui.ptlogin2.qq.com/cgi-bin/login";
	public $devnet=false;
	
	/**
	 * @var array(
	 * 	'gender' => [int],
	 *  'face' => [int],
	 *  'logintime' => [int timestamp],
	 *  'lastaccess' => [int timestamp],
	 *  'nick' => [string charset:utf-8],
	 *  'mail' => [string]
	 * )
	 */
	private $loginObject ; 
	private $uin;

	public function init()
	{
		if($this->devnet)
				pt_php_init_forTest("172.25.38.16",58000);
		parent::init();
	}
	
	public function logout($destroySession=true)
	{
		if($destroySession)
			Mod::app()->getSession()->destroy();
		else
			$this->clearStates();
		/* $this->uin = isset ( $_COOKIE ['p_uin'] ) ? ltrim ( $_COOKIE ['p_uin'], 'o0' ) : '';
		$skey = isset ( $_COOKIE ['p_skey'] ) ? $_COOKIE ['p_skey'] : '';
		$luin = isset ( $_COOKIE ['p_luin'] ) ? ltrim ( $_COOKIE ['p_luin'], 'o0' ) : '';
		$lskey = isset ( $_COOKIE ['p_lskey'] ) ? $_COOKIE ['p_lskey'] : '';
		logout($this->uin,$skey,$this->appid); */
		//logout_llogin(uin,lskey,appid);
	}
	
	public function loginRequired()
	{
		Mod::log("LoginRequired", CLogger::LEVEL_INFO, 'application.controller');
		$app = Mod::app();
		$request = $app->getRequest();
	
		if($request->getIsAjaxRequest() && isset($this->loginRequiredAjaxResponse))
		{
			echo $this->loginRequiredAjaxResponse;
			$app->end();
		}
		$url = $this->login_url;
		if (strpos ( $url, '?' ) === false)
			$url .= "?";
		$url .= "&appid=".$this->appid."&daid=".$this->daid;
		$referrer = $request->getHostInfo().$request->getUrl();
		$url .= "&s_url=" . urlencode ( $referrer );
		$request->redirect ( $url );
	
	}
	
	public function getIsGuest()
	{
		return !$this->_check_login();
	}
	
	
	private function _check_login()
	{
		if (! isset ( $this->login_type ))
			$this->login_type = self::PT_VERIFY4 | self::PT_VERIFY4_LLOGIN | self::PT_VERIFY4_EX | self::PT_VERIFY4_LLOGIN_EX;
		
		$result = $this->_get_login_object ();
			
		if(is_array($result)) {
			$this->_compat_result($result);
			$this->loginObject = $result;
			return true;
		}
		return false;
	}
	
	private function _get_login_object()
	{
		$result = $this->_get_ex_login_object();
		if(is_array($result))
		{
			return $result;
		}
		return $this->_get_old_login_object();
		
	}
	
	private function _get_old_login_object()
	{
		$this->uin = isset ( $_COOKIE ['uin'] ) ? ltrim ( $_COOKIE ['uin'], 'o0' ) : '';
		$skey = isset ( $_COOKIE ['skey'] ) ? $_COOKIE ['skey'] : '';
		$luin = isset ( $_COOKIE ['luin'] ) ? ltrim ( $_COOKIE ['luin'], 'o0' ) : '';
		$lskey = isset ( $_COOKIE ['lskey'] ) ? $_COOKIE ['lskey'] : '';
		
		if ($this->login_type & self::PT_VERIFY4
				&& $this->uin != '' && $skey != '' ) {
			$result = pt_php_verify4 ( $this->uin, $skey, $this->appid, 1, 1 );
			if(is_array($result))
				return $result;
		}
		
		if($this->login_type & self::PT_VERIFY4_LLOGIN
				&& $luin != '' && $lskey != '' ) {
			$this->uin = $luin;
			$result = pt_php_verify4_llogin('0', '@', $this->uin, $lskey, $this->appid, 1, 1, 0);
			if(is_array($result))
				return $result;
		}
		
	}
	
	
	private function _get_ex_login_object()
	{
		$this->uin = isset ( $_COOKIE ['p_uin'] ) ? ltrim ( $_COOKIE ['p_uin'], 'o0' ) : '';
		$skey = isset ( $_COOKIE ['p_skey'] ) ? $_COOKIE ['p_skey'] : '';
		$luin = isset ( $_COOKIE ['p_luin'] ) ? ltrim ( $_COOKIE ['p_luin'], 'o0' ) : '';
		$lskey = isset ( $_COOKIE ['p_lskey'] ) ? $_COOKIE ['p_lskey'] : '';
		$daid = $this->_get_domain_id();
		
		if($this->login_type & self::PT_VERIFY4_EX
				&& $this->uin != '' && $skey != '' && $daid != '' ){
			$result = pt_php_verify4_ex($this->uin, $skey, $this->appid, $daid, 1, 1, 0);
			if(is_array($result))
				return $result;
		}
		
		if($this->login_type & self::PT_VERIFY4_LLOGIN_EX
				&& $luin != '' && $lskey != '' && $daid != '' ){
			$this->uin = $luin;
			$result = pt_php_verify4_llogin_ex('0', '@', $this->uin, $lskey, $this->appid, $daid, 1, 1, 0);
			if(is_array($result))
				return $result;
		}
		
	}
	
	
	
	private function _get_domain_id()
	{
		if($this->daid == '' && $this->login_type & (self::PT_VERIFY4_EX | self::PT_VERIFY4_LLOGIN_EX)) {
			$sub_domain = join('.', array_slice(explode('.', $_SERVER['HTTP_HOST']), -3));
			$daid_key = sprintf(self::DAID_DIC_NAME, $this->appid, $sub_domain);
			if(Mod::app()->nameService->getValueByKey($daid_key, $this->daid) !== 0) {
				return $this->daid;
			}
		}
		return $this->daid;
	}
	
	private function _compat_result(&$arr) {
		foreach(array_keys($arr) as $key) {
			$val = $arr[$key];
			unset($arr[$key]);
			switch($key) {
				case 'NickName':
					$arr['nick'] = $val;
					break;
				case 'Email':
					$arr['mail'] = $val;
					break;
				default:
					$arr[strtolower($key)] = $val;
			}
		}
	}
	
	public function login($identity,$duration=0){/*do nothing*/}
	
	public function getName()
	{
		if(!empty($this->loginObject))
		{
			return $this->loginObject['nick'];
		}else
		{
			return $this->guestName;
		}
	}
	
	public function setName($value){/*do nothing*/}
	
	
	public function getId()
	{
		return $this->uin;
	}
	
	public function setId($value){/*do nothing*/}
	
	public function getMail()
	{
		return $this->loginObject['mail'];
	}
	
	public function getUin()
	{
		return $this->uin;
	}
	
	public function getGender()
	{
		return $this->loginObject['gender'];
	}
	
	public function getFace()
	{
		return $this->loginObject['face'];
	}
	
	public function getLogintime()
	{
		return $this->loginObject['logintime'];
	}
	
	public function getLastaccess()
	{
		return $this->loginObject['lastaccess'];
	}
	
}

?>