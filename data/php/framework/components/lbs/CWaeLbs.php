<?php
/**
 * 封装Tencent Map Location Based Service
 * 
 * 一 实例化方式使用:
 *    Mod::import('system.components.lbs.CWaeLbs');
 *    $lbs = new CWaeLbs();
 *    $lbs->nameServiceKey = 'xxx.lbs.xxx';
 *　　$lbs->init();
 *    $ret = $lbs->getAddrByLoc(39.97090756862125, 116.31336165377651);
 *    var_dump($ret);
 *
 * 二 获取通过配置方式使用
 * 'componenets'=>array(
 *      'lbs'=>array(
 *          'class'=>'system.components.lbs.CWaeLbs',
 *          'nameServiceKey'=>'xxx.lbs.xxx'
 *		)
 *  )
 *  --------------
 *  调用方法： 
 *  $ret = Mod::app()->lbs->getAddrByLoc(39.97090756862125, 116.31336165377651);
 *  var_dump($ret);
 * 
 * @author wilsonwsong
 *
 */

class CWaeLbs extends CApplicationComponent
{
	/**
	* 接口地址，ip和端口被替换
	*/
	public $url= 'http://{{host}}:{{port}}/loc';
	/*默认测试IP*/
	public $ip = '10.163.2.100';
	/*默认测试PORT*/
	public $port = 8888;
	/*获取IP和PORT的的名字服务 */
	public $nameServiceKey;
	
	/**
	*  是否做逆地址解析，默认不进行逆地址解析
	*  0 为不解析，
	*  1 为解析名称和地址，
	*  2 为析名称、地址和街景ID（暂不对外），
	*  3 为城市详细数据（结构下述），
	*  4 为城市详细数据（结构下述）+周边POI数据，
	*  5 为行政区划数据（国外为国家），
	*　6 为周边带key 和翻页的检索，必须有sparam支持，
	*  7 为国际逆地址解析，支持全球主要国家和城市的逆址检索
	*/
	public $addressType = 3 ;
	/**
	* 定位请求来源，腾讯地图定位平台统一分
	* 申请地址: http://lbscp.map.qq.com/LBSCP/index.jsp
	*/
	public $source = 12345 ;
	/**
	*  定位主调方服务版本号，后台接入必须填写
	*/
	public $version = '0.1.0';
	
    /**
    * 请求和回送字符编码格式，0为GBK，1为UTF8，默认是0；
    * 
    * 当使用address 为7时，回送结果均为UTF8
    */
    public $charset = 1; 

	/**
	* 当前请求源的一些属性,可选属性包括
	* imei 用户手机的imei  
	* imsi 用户手机的imsi 
	* phonenum 用户手机号码否 
	* qq 用户QQ号码否 
	* wx 用户微信号 
	*/
	private $_attributes = array();
	
	public function init()
	{
		parent::init();
		// 是否使用名字服务
		if($this->nameServiceKey){
			// 通过名字服务获取IP和PORT
			if(Mod::app()->nameService->getHostByKey($this->nameServiceKey, 
					$this->ip, $this->port)==false)
				throw new CException("NameServer fail, key:{$this->nameServiceKey}");
		}
		// 使用ip和端口替换接口地址占位符
		$this->url = str_replace(array('{{host}}', '{{port}}'), 
			array($this->ip, $this->port), $this->url);
	}
	
	public function setAttributes($attrs)
	{
		$this->_attributes = $attrs;
	}
	
	/**
	* 可以通过$this->qq访问
	*/
	public function setQq($qq)
	{
		$this->_attributes['qq'] =  $qq ;
	}
	
	public function setImei($imei)
	{
		$this->_attributes['imei'] =  $imei ;
	}
	
	public function setImsi($imsi)
	{
		$this->_attributes['imsi'] =  $imsi ;
	}
	
	public function setPhonenum($phonenum)
	{
		$this->_attributes['phonenum'] =  $phonenum ;
	}
	
	public function setWx($wx)
	{
		$this->_attributes['wx'] =  $wx ;
	}	
	
	
	/**
	* @param $latitude 纬度 
	* @param $longitude 经度
	* @param $additional 经度 用户当前的GPS 的附加信息 Altitude, Accuracy,Direction, Speed ,Time
	* @param $coordinate 坐标格式，0为WGS84，1为GCJ-02，默认为WGS84
	* @return array(code,msg,data)
	* 		code  为0表示成功,其它为失败
	*       msg   失败原因
	*       data  返回数据array(), 格式和addressType有关，具体参考文档
	*/
	public function getAddrByLoc($latitude, $longitude, $additional=NULL, $coordinate=NULL)
	{
		$msgobj = array(
				'version'=> $this->version,
				'address'=> $this->addressType,
				'source'=> $this->source,
				'charset'=> $this->charset,
			);
		
		if(!empty($this->_attributes))
		{
			$msgobj['attribute']=$this->_attributes;
		}
		$location = array('latitude'=>$latitude, 'longitude'=>$longitude);
		if(isset($additional))
			$location['additional'] = $additional ; 
		if(isset($coordinate))
			$location['coordinate'] = $coordinate ; 
		$msgobj['location']=$location;
		
		$curl = new CUrl();
		$curl->init();
		$ret = $curl->post($this->url, json_encode($msgobj));
		if(!$ret || $ret=='{}')
		{
			return $this->_ret(-1, 'Unknow Error');
		}else{
			return $this->_ret(0, NULL, json_decode($ret,true));
		}
	}
	
	
	private function _ret($retcode, $msg='', $data=NULL)
	{
		$ret = array('code'=>$retcode,'msg'=>$msg);
		if(isset($data))
			$ret['data']=$data;
		return $ret;
	}
	
	
}