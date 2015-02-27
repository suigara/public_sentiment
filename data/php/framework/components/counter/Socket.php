<?php
/**
 * socket操作基类
 * @category   qqvipphp
 * @package  qqvipphp
 * @subpackage
 * @static   
 * @author wecityyan <yanyongshan@gmail.com>
 * @since 1.0(2011-12-26)
 */
class Model_Push_Socket{
	/*
	 * @var socket配置
	 * @access public
	 */
	public $config=array(
		//是否使用长连接
		'persistent'	=> false,
		'protocol'		=> 'tcp',
		'host'			=> '127.0.0.1',
		'port'			=> 80,
		'timeout'		=> 30
	);
	
	/*
	 * @var socket连接句柄
	 * @access public
	 */
	public $connection=null;
	
	/*
	 * @var socket是否处于连接状态
	 * @access public
	 */
	public $connected=false;
	
	/*
	 * @var 上次发生的错误
	 * @access public
	 */
	public $lastError=array();
	
	/*
	 * @var 上次请求的命令字
	 * @access public
	 */
	public $lastRequest='';
	
	/*
	 * @var 上一次请求的返回信息
	 * @access public
	 */
	public $lastResponse='';
	/**
	 * socket构造函数，初始化配置
	 * @param array socket配置
	 * @access public
	 * @return void
	 */
	public function __construct($config=array()){
		$defaultConfig=array(
			//是否使用长连接
			'persistent'	=> false,
			'protocol'		=> 'tcp',
			'host'			=> '127.0.0.1',
			'port'			=> 80,
			'timeout'		=> 30
		);
		$this->config=array_merge($defaultConfig,$config);
	}
	
	/**
	 * 连接socket
	 * @access public
	 * @return bool
	 */
	public function connect(){
		if($this->connection!=null){
			$this->disconnect();
		}
		
		//检查是否为长连接
		if($this->config['persistent']==true){
			$this->connection=@pfsockopen($scheme.$this->config['host'],$this->config['port'],$errNum,$errStr,$this->config['timeout']);
		}else{
			$this->connection=@fsockopen($scheme.$this->config['host'],$this->config['port'],$errNum,$errStr,$this->config['timeout']);
		}

		if(!empty($errNum) || !empty($errStr)){
			$this->setError($errNum, $errStr);
		}

		$this->connected = is_resource($this->connection);
		//设置socket超时
		if($this->connected){
			stream_set_timeout($this->connection, $this->config['timeout']);
		}
		return $this->connected;
	}
	/**
	 * 设置socket错误
	 * @param int $errNum 错误号
	 * @param string $errStr 错误描述信息
	 * @access public
	 * @throws
	 * @return void
	 */
	public function setError($errNum, $errStr){
		$this->lastError=array(
			"num"=>$errNum,
			"msg"=>$errStr
		);
	}
	/**
	 * 读取错误信息
	 * @access public
	 * @return mixed 错误信息数组
	 */
	public function getError(){
		$lastError=$this->lastError;
		$lastError['config']=$this->config;
		return $lastError;
		
	}	
	/**
	 * 向socket写入数据
	 * @param string $data 写入的数据（字符串）
	 * @access public
	 * @return int 成功写入socket的长度(>0表示正常写入)
	 */	
	public function write($data){
		//初始化请求字符
		$this->lastRequest='';
		if(!$this->connected){
			if(!$this->connect()){
				return false;
			}
		}
		$totalBytes=strlen($data);
		//循环写入，如果写入长度小于总长度将剩下的字符再次写入
		for($written = 0, $rv = 0; $written < $totalBytes; $written += $rv) {
			$request=substr($data, $written);
			$this->lastRequest=$request;
			$rv = fwrite($this->connection, $request);
			//写入失败
			if($rv === false || $rv === 0) {
				$this->setError(E_WARNING, "can't write socket");
				return $written;
			}
		}
		return $written;
	}
	/**
	 * 从socket读取数据
	 * @param string $length 每次读取数据的长度（默认10k字节）
	 * @access public
	 * @return string 从socket中读取到的数据
	 */	
	public function read($length = 10240) {
		//检查是否socket连接状态
		if(!$this->connected){
			if(!$this->connect()){
				return false;
			}
		}

		if (!feof($this->connection)) {
			$buffer = fread($this->connection, $length);
			$info = stream_get_meta_data($this->connection);
			if ($info['timed_out']) {
				$this->setError(E_WARNING, 'Read socket timed out');
				return false;
			}
			//记录返回值
			$this->lastResponse=$buffer;
			return $buffer;
		}
		return false;
	}	
	/**
	 * 断开socket连接
	 * @access public
	 * @return bool
	 */			
	public function disconnect(){
		if(!is_resource($this->connection)){
			$this->connected = false;
			return true;
		}
		$this->connected = !fclose($this->connection);
		
		if(!$this->connected){
			$this->connection = null;
		}
		return !$this->connected;
	}
	/**
	 * 析构函数，关闭socket连接
	 * @access public
	 * @return void
	 */	
	public function __destruct() {
		$this->disconnect();
	}
}
?>
