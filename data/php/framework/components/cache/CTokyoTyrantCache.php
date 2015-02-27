<?php
/**
 * CTokyoTyrantCache class file
 * @author andehuang
 */

/**
 * CTokyoTyrantCache使用{@link http://fallabs.com/tokyotyrant/ TokyoTyrant}作为缓存。使用php扩展或
 * Net_TokyoTyrant进行链接。默认使用php扩展{@link http://php.net/manual/en/class.tokyotyrant.php TokyoTyrant}
 * 与tokyotyrant进行链接，因为扩展的性能高一点；默认使用长链接(如果在高并发的情况下使用短链接，那么服务器会消耗大量的cpu和网络资
 * 源用于不停的创建链接和关闭链接)。
 * 每个IDC机房中，可以配置一个或多个TokyoTyrant作为缓存服务器集群，每个缓存服务器作为一个实例，根据key来进行数据分片保存。
 * 当使用缓存集群时，需要定义Key的分配算法，类似于MemCache的一致性哈希算法，具体见{@link keySplitChar}和{@link getInstanceId}。
 * CTokyoTyrantCache支持{@link CCache}定义的统一接口。
 * 如下是示例配置：
 * <pre>
 * array(
 *     'components'=>array(
 *         'tokyoTyrant'=>array(
 *             'class'=>'CTokyoTyrantCache',				//使用TokyoTyrant作为cache
 *             'timeout'=>1,								//超时时间
 *             'keySplitChar'=>'#',							//通过key来分配服务器，比如“guessList#52”表示的主键为52
 *             'serverConfigs'=>array(
 *                 'shenzhenIDC'=>array(					//深圳IDC
 *                     array('host'=>'192.168.0.1', 'port'=>11211),
 *                     array('host'=>'192.168.0.2', 'port'=>11211)
 *                 ),
 *                 'tianjinIDC'=>array(						//天津IDC
 *                     array('host'=>'192.168.0.1', 'port'=>11211),
 *                     array('host'=>'192.168.0.2', 'port'=>11211),
 *                 ),
 *                 'bakIDC'=>array(							//备份IDC
 *                     array('host'=>'192.168.0.1', 'port'=>11211)
 *                 ),
 *             ),
 *             'getIDC'=>array('shenzhenIDC', 'tianjinIDC'),//读操作时，先读取深圳IDC，如果失败再读取天津IDC
 *             'setIDC'=>'shenzhenIDC',						//写操作时，只写深圳IDC，其它机房使用异步写
 *             'localIDC'=>'tianjinIDC',					//将天津机房设置为本地IDC机房
 *             'getRetryCount'=>2,							//读操作重试次数2次
 *             'getRetryInterval'=>0						//读操作重试间隔0秒
 *             'setRetryCount'=>3,							//写操作重试次数3次
 *             'setRetryInterval'=>0.2						//写操作重试间隔0.2秒
 *         ),
 *     )
 * )
 * </pre>
 * 可以对某个IDC的TokyoTyrant单独操作，如下为示例代码：
 * Mod::app()->tokyoTyrant->shenzhenIDC[0]->putkeep();		//单独操作深圳机房的一台tokyotyrant
 * Mod::app()->tokyoTyrant->bakIDC->size();					//单独操作备份机房
 * Mod::app()->tokyoTyrant->localIDC[1]->putnr();			//单独操作当前机房的一台tokyotyrant
 */
class CTokyoTyrantCache extends CCache
{
	//两种链接TokyoTyrant的方式：php扩展、php类。
	const PHP_MODULE=0;
	const PHP_CLASS=1;
	/**
	 * @var int 链接TokyoTyrant的方式，默认使用php扩展。
	 */
	public $connectType = self::PHP_MODULE;
	/**
	 * @var int 超时时间，默认为1秒。
	 */
	public $timeout = 1;
	/**
	 * @var bool 是否建立长链接，默认使用长链接。
	 */
	public $persistent = true;
	/**
	 * @var string 从key中提取服务器id的分隔符，默认使用#。比如：key='guessList#52'，表示的主键为52的记录。
	 */
	public $keySplitChar = '#';
	/**
	 * @var mixed 读操作对应的IDC。当配置多个IDC时，可以提高系统的可靠性，就是一个互备的原理，
	 * 当第一个IDC读取失败的时候，可以读取第二的IDC。
	 */
	public $getIDC;
	/**
	 * @var mixed 写操作对应的IDC。当配置多个IDC时，会逐个写，这会降低写操作的性能，一种提升办法是先写本IDC，其余的IDC有离线程序来写，读的时候先读取本IDC。
	 * 如果第一个IDC写失败了，那么其它IDC就不会写了，通常第一个IDC表示本IDC，写本IDC是不允许失败的。
	 * 如果写其它IDC失败，则会记录redo日志，需要离线程序将失败的记录重新写入。
	 */
	public $setIDC;
	/**
	 * TokyoTyrant自定义函数中哪些是读操作对应的函数，哪些是写操作对应的函数，该配置用于读写分离。
	 */
	public $extGetFunctions = array();
	public $extSetFunctions = array();
	/**
	 * TokyoTyrant方法哪些是读操作对应的方法，哪些是写操作对应的方法，该配置用于读写分离。需要区分php扩展、php类两种链接方式。
	 */
	protected $getFunctions = array(
		self::PHP_MODULE=>array('get', 'size'),
		self::PHP_CLASS=>array('get', 'mget', 'getint', 'vsize')
	);
	protected $setFunctions = array(
		self::PHP_MODULE=>array('add', 'out', 'put', 'putCat', 'putKeep', 'putNr', 'putShl'),
		self::PHP_CLASS=>array('put', 'putkeep', 'putcat', 'putrtt', 'putnr', 'out', 'addint', 'adddouble', 'putint')
	);
	/**
	 * @var array 各个IDC机房的服务器配置，这是最重要的配置。
	 */
	public $serverConfigs;
	/**
	 * @var array 服务器链接对象
	 */
	private $_servers;
	/**
	 * @var string 当前IDC机房
	 */
	private $_localIDC;
	/**
	 * 网络抖动或者重启会导致服务短时间的不可用(1~3秒)，为了缓解这种问题，使用了重试机制来尽量屏蔽这样的故障，从而做到对组件调用方的透明。
	 * 使用如下4个成员变量来配置重试次数和重试间隔：
	 * getRetryCount、getRetryInterval、setRetryCount、setRetryInterval。
	 * @var int 读操作重试次数
	 */
	public $getRetryCount = 0;
	/**
	 * @var float 读操作重试间隔，单位是秒。
	 */
	public $getRetryInterval = 0;
	/**
	 * @var int 写操作重试次数
	 */
	public $setRetryCount = 0;
	/**
	 * @var float 读操作重试间隔，单位是秒，默认为0.2秒(200毫秒)。
	 */
	public $setRetryInterval = 0.2;

	/**
	 * Initializes this application component.
	 * This method is required by the {@link IApplicationComponent} interface.
	 * It checks the availability of tokyotyrant.
	 * @throws CException if tokyotyrant extension is not loaded or is disabled.
	 */
	public function init()
	{
		if($this->connectType==self::PHP_MODULE && !extension_loaded('tokyo_tyrant'))
			throw new CException(Mod::t('mod','CTokyoTyrantCache requires PHP tokyotyrant extension to be loaded.'));
		
		//修改重试间隔的时间单位，秒=>微秒(一秒等于一百万微秒)
		$this->getRetryInterval *= 1000000;
		$this->setRetryInterval *= 1000000;
		
		parent::init();
	}

	/**
	 * 析构函数，释放资源
	 */
	public function __destruct()
	{
		if(!$this->persistent && $this->connectType==self::PHP_CLASS)
		{
			foreach($this->_servers as $server)
				$server->close();
		}
	}

	/**
	 * 获取指定的Redis对象
	 */
	public function __get($name)
	{
		if($name==='localIDC' && $this->_localIDC)
			$name = $this->_localIDC;
		
		if(isset($this->serverConfigs[$name]))
		{
			for($retryCount=0; $retryCount<=$this->getRetryCount; ++$retryCount)
			{
				try
				{
					$servers = array();
					foreach($this->serverConfigs[$name] as $instanceId=>$serverConfig)
						$servers[$instanceId] = $this->getServer($name, $instanceId);
					
					return (count($servers)>1)?$servers:$servers[0];
				}
				catch(Exception $e)
				{
					//这个链接可能坏了，关闭链接，下次重新创建
					unset($server[$instanceId], $this->_servers["$name+$instanceId"]);
					
					$message = $e->getMessage();
					//记录失败日志，需要区分是否为重试
					if($retryCount)
						Mod::log("Get tokyotyrant server fail(message:$message, retryCount:$retryCount)",CLogger::LEVEL_ERROR,'system.cache.CTokyoTyrantCache');
					else
						Mod::log("Get tokyotyrant server fail(message:$message)",CLogger::LEVEL_ERROR,'system.cache.CTokyoTyrantCache');
					//判断是否需要sleep
					if($this->getRetryCount && $this->getRetryInterval && $retryCount<$this->getRetryCount)
						usleep($this->getRetryInterval);
				}
			}
			//指定的Redis对象，抛异常。
			throw new CException("Get tokyotyrant server fail(message:$message)",(int)$e->getCode());
		}
		else
			return parent::__get($name);
	}

	/**
	 * 设置当前IDC机房
	 */
	public function setlocalIDC($value)
	{
		$this->_localIDC = $value;
	}

	/**
	 * PHP魔术方法，当调用一个不存在成员方法时，会调用本方法。本方法相当于是对TokyoTyrant的一个路由，将方法调用路由给TokyoTyrant对象。
	 * 需要判断调用的方法是读操作还是写操作，真正的调用有callTokyotyrantFunction方法来处理。
	 * @param string $name 成员方法名称
	 * @param array $parameters 参数
	 * @return mixed
	 */
	public function __call($name, $parameters)
	{
		Mod::trace("CTokyoTyrantCache call function:$name, parameters:".implode('/', $parameters), 'system.cache.CTokyoTyrantCache');
		
		if(in_array($name, $this->getFunctions[$this->connectType]))
			return $this->callTokyotyrantFunction(true, $name, $parameters, is_array($parameters[0]));
		else if(in_array($name, $this->setFunctions[$this->connectType]))
			return $this->callTokyotyrantFunction(false, $name, $parameters, is_array($parameters[0]));
		else if($name=='ext')
		{
			if(in_array($parameters[0], $this->extGetFunctions))
				return $this->callTokyotyrantFunction(true, $name, $parameters);
			else if(in_array($parameters[0], $this->extSetFunctions))
				return $this->callTokyotyrantFunction(false, $name, $parameters);
			else
				throw new Exception("请配置自定义方法：{$parameters[0]}是Get操作还是Set操作。");
		}
		else
			return parent::__call();	//如果调用的不是TokyoTyrant的方法，则交给父类处理
	}

	/**
	 * 对Tokyotyrant进行路由，即对Tokyotyrant提供的原生函数进行一层封装，从而实现
	 * 1、失败重试
	 * 2、多IDC容错
	 * 3、集群分组透明
	 * @param bool $isGet 是读操作还是写操作
	 * @param string $name 调用的方法名称
	 * @param array $parameters 参数列表
	 * @param bool $isMultiKey 是否为多个key，如果是多个key，需要进行分组处理
	 */
	protected function callTokyotyrantFunction($isGet, $functionName, $functionParams, $isMultiKey=false)
	{
		//获取需要操作的IDC列表
		$listIDC = $isGet?$this->getIDC:$this->setIDC;
		if(!is_array($listIDC))
			$listIDC = array($listIDC);
		
		/**
		 * 是否为第一个IDC机房，对于写操作，如果第一个IDC写失败，则直接退出。
		 * 一般将第一个IDC机房作为当前IDC机房，必须保证当前IDC写成功。
		 */
		$isFirst = true;
		
		//设置重试参数
		$retryCountSum = $isGet?$this->getRetryCount:$this->setRetryCount;
		$retryInterval = $isGet?$this->getRetryInterval:$this->setRetryInterval;
		
		foreach($listIDC as $IDCName)
		{
			for($retryCount=0; $retryCount<=$retryCountSum; ++$retryCount)
			{
				try
				{
					//获取服务器分组
					$instanceId = $this->getInstanceId($IDCName, $functionName, $functionParams, $isMultiKey);
					$server = $this->getServer($IDCName, $instanceId);
					
					if($isGet)//读操作，直接返回
						return call_user_func_array(array($server, $functionName), $functionParams);
					else
					{
						$ret = call_user_func_array(array($server, $functionName), $functionParams);
						//需要记录第一个IDC机房的返回值
						if($isFirst)
							$retFrist = $ret;
						//判断写操作是否成功
						$writeSuccess = true;
						break;
					}
				}
				catch(Exception $e)
				{
					//这个链接可能坏了，关闭链接，下次重新创建
					unset($this->_servers["$IDCName+$instanceId"]);
					
					//记录写操作失败
					if(!$isGet)
						$writeSuccess = false;
					
					//记录失败日志，需要区分是否为重试
					$logContent = array('IDCName'=>$IDCName, 'functionName'=>$functionName, 'functionParams'=>$functionParams, 'errorMsg'=>$e->getMessage());
					if($retryCount)
					{
						$logContent['retryCount'] = $retryCount;
						Mod::log($logContent, CLogger::LEVEL_ERROR, 'system.cache.CTokyoTyrantCache');
					}
					else
						Mod::log($logContent, CLogger::LEVEL_ERROR, 'system.cache.CTokyoTyrantCache');
					
					//判断是否需要sleep
					if($retryCountSum && $retryInterval && $retryCount<$retryCountSum)
						usleep($retryInterval);
				}
			}//--foreach end 重试--
			
			//写操作失败，需要处理
			if(!$isGet && !$writeSuccess)
			{
				if($isFirst)//第一个IDC机房写失败，直接抛异常
					throw new CException('tokyotyrant error!');
				else//其它IDC机房写失败，写日志，需要进行重写
				{
					$logContent = array('IDCName'=>$IDCName, 'functionName'=>$functionName, 'functionParams'=>$functionParams);
					Mod::log($logContent, CLogger::LEVEL_ERROR, 'system.cache.CTokyoTyrantCache');
				}
			}
			
			//下次操作，就不是第一个IDC机房了
			$isFirst = false;
		}//--foreach end 多个IDC--
		
		if($isGet)	//对于读操作，如果运行到这里，那就悲剧了！所有IDC集群都读取失败，抛异常
		{
			$logContent = array('functionName'=>$functionName, 'functionParams'=>$functionParams, 'errorMsg'=>'all redis fail');
			Mod::log($logContent, CLogger::LEVEL_ERROR, 'system.cache.CTokyoTyrantCache');
			throw new CException('tokyotyrant error!');
		}
		else		//对于写操作，返回第一个IDC机房的返回值
			return $retFrist;
	}

	/**
	 * 一个集群由多个tokyotyrant实例组成，一般根据key进行分组，本方法用于计算分配的实例Id。
	 * 根据输入的Key来计算instanceId，此处应该使用一致性hash，目前，简单取模。
	 * @param string $IDCName IDC机房名称
	 * @param string $functionName 调用的函数名称
	 * @param array $functionParams 传入的参数
	 * @param bool $isMultiKey 是否为多key操作，比如mgets
	 * @return int instanceId
	 */
	private function getInstanceId($IDCName, $functionName, $functionParams, $isMultiKey=false)
	{
		//计算Key
		if($isMultiKey)
			$keyInput = $functionParams[0][0];
		else
			$keyInput = ($functionName=='ext')?$functionParams[2]:$functionParams[0];
		
		if(($pos=strpos($keyInput, $this->keySplitChar)) === false)
			$key = intval($keyInput);
		else
			$key = intval(substr($keyInput, $pos));
		
		//返回分配的实例Id
		return $key%count($this->serverConfigs[$IDCName]);
	}

	/**
	 * 获取Server链接对象
	 * @param string $IDCName IDC名称
	 * @param int $instanceId 实例Id
	 * @return object Net_TokyoTyrant
	 */
	public function getServer($IDCName, $instanceId)
	{
		//对已经创建的链接进行缓存
		$serverKey = "$IDCName+$instanceId";
		if(isset($this->_servers[$serverKey]) && $this->_servers[$serverKey])
			return $this->_servers[$serverKey];
		
		$serverConfig = $this->serverConfigs[$IDCName][$instanceId];
		if($this->connectType==self::PHP_MODULE)
		{
			$options = array(
				'timeout '=>$this->timeout,
				'reconnect'=>true,
				'persistent'=>$this->persistent
			);
			$server = new TokyoTyrant($serverConfig['host'], $serverConfig['port'], $options);
		}
		else
		{
			$server = new Net_TokyoTyrant;
			$server->connect($serverConfig['host'], $serverConfig['port'], $this->timeout, $this->persistent);
			$server->setTimeout($this->timeout);
		}
		
		return $this->_servers[$serverKey] = $server;
	}

	/**
	 * 从Cache中取出一个Key对应的Value
	 * @param string $key
	 * @return string 如果成功则返回字符串，如果失败则返回false
	 */
	protected function getValue($key)
	{
		return $this->__call('get', array($key));
	}

	/**
	 * 从Cache中取出多个Key对应的Value
	 * @param array $keys
	 * @return array 返回一个数组，下表输入的key
	 */
	protected function getValues($keys)
	{
		return $this->__call('mget', array($key));
	}

	/**
	 * 
	 * @param string $key the key identifying the value to be cached
	 * @param string $value the value to be cached
	 * @param integer $expire the number of seconds in which the cached value will expire. 0 means never expire.
	 * @return boolean true if the value is successfully stored into cache, false otherwise
	 */
	protected function setValue($key,$value,$expire)
	{
		return $this->__call('put', array($key));
	}

	/**
	 * Stores a value identified by a key into cache if the cache does not contain this key.
	 * This is the implementation of the method declared in the parent class.
	 *
	 * @param string $key the key identifying the value to be cached
	 * @param string $value the value to be cached
	 * @param integer $expire the number of seconds in which the cached value will expire. 0 means never expire.
	 * @return boolean true if the value is successfully stored into cache, false otherwise
	 */
	protected function addValue($key,$value,$expire)
	{
		return $this->__call('put', array($key));
	}

	/**
	 * Deletes a value with the specified key from cache
	 * This is the implementation of the method declared in the parent class.
	 * @param string $key the key of the value to be deleted
	 * @return boolean if no error happens during deletion
	 */
	protected function deleteValue($key)
	{
		return $this->__call('out', array($key));
	}
	
	public function get($id)
	{
		if(($value=$this->getValue($this->generateUniqueKey($id)))!==false)
		{
			$data=unserialize($value);
			if(!is_array($data))
				return $value;		//说明是原生存储的
			if(!($data[1] instanceof ICacheDependency) || !$data[1]->getHasChanged())
			{
				Mod::trace('Serving "'.$id.'" from cache','system.caching.'.get_class($this));
				return $data[0];
			}
		}
		return false;
	}
}

/**
 * Net_TokyoTyrant
 * @author cocoitiban <cocoiti@gmail.com>
 * License: MIT License
 * @package Net_TokyoTyrant
 */
/* base Excetion */
class Net_TokyoTyrantException extends Exception {};
/* network error */
class Net_TokyoTyrantNetworkException extends Net_TokyoTyrantException {};
/* tokyotyrant error */
class Net_TokyoTyrantProtocolException extends Net_TokyoTyrantException {};

/**
 * TokyoTyrant Base Class
 * @category Net
 * @package Net_TokyoTyrant
 * @author Keita Arai <cocoiti@gmail.com>
 *@license http://www.opensource.org/licenses/mit-license.html  MIT License
 */
class Net_TokyoTyrant
{
	/* @access private */
	private $connect = false;
	private $socket;
	private $errorNo, $errorMessage;
	private $socket_timeout;

	/* @access public */
	const RDBXOLCKNON = 0;
	const RDBXOLCKREC = 1;
	const RDBXOLCKGLB = 2;

	/**
	 * server connect
	 * @param string $server servername
	 * @param string $server port number
	 * @param string $server timeout (connection only)
	 * @param bool $persistent whether close the connection after the script finishes
	 */
	public function connect($server, $port, $timeout=1, $persistent=true)
	{
		if(!$persistent)
		{
			$this->close();
			$this->socket = @fsockopen($server, $port, $this->errorNo, $errorMessage, $timeout);
		}
		else
			$this->socket = @pfsockopen($server, $port, $this->errorNo, $errorMessage, $timeout);
		
		if(!$this->socket)
		{
			throw new Net_TokyoTyrantNetworkException(sprintf('%s, %s', $this->errorNo, $errorMessage));
			return false;
		}
		$this->connect = true;
		
		return true;
	}
	
	/**
	 * setting socket timeout
	 * @param integer $timeout timeout
	 */
	public function setTimeout($timeout)
	{
		$this->socket_timeout = $timeout;
		stream_set_timeout($this->socket, $timeout);
	}

	/**
	 * get timeout
	 * @return integer timeout
	 */
	public function getTimeout()
	{
		return $this->socket_timeout;
	}

	/**
	 * close session
	 */
	public function close()
	{
		if($this->connect)
			fclose($this->socket);
	}

	/**
	 * read buffer
	 * @access private
	 * @param $length readlength
	 * @result string buffer data
	 */
	private function _read($length)
	{
		if($this->connect === false)
			throw new Net_TokyoTyrantException('not connected');
		
		if(@feof($this->socket)) 
			throw new Net_TokyoTyrantNetworkException('socket read eof error');
		
		$result = $this->_fullread($this->socket, $length);
		if($result === false)
			throw new Net_TokyoTyrantNetworkException('socket read error');
		return $result;
	}

	/**
	 * send data
	 * @param $data data
	 */
	private function _write($data)
	{
		$result = $this->_fullwrite($this->socket, $data);
		if($result === false)
			throw new Net_TokyoTyrantNetworkException('socket read error');
	}

	private function _fullread ($sd, $len)
	{
		$ret = '';
		$read = 0;
		
		while($read < $len && ($buf = fread($sd, $len - $read)))
		{
			$read += strlen($buf);
			$ret .= $buf;
		}
		
		return $ret;
	}

	private function _fullwrite ($sd, $buf)
	{
		$total = 0;
		$len = strlen($buf);
		
		while($total < $len && ($written = fwrite($sd, $buf)))
		{
			$total += $written;
			$buf = substr($buf, $written);
		}
		
		return $total;
	} 

	private function _doRequest($cmd, $values = array())
	{
		$this->_write($cmd.$this->_makeBin($values));
	}

	/**
	 * make tokyotyrant data
	 * @param array $values send data
	 * @return string tokyotyrant data
	 */
	private function _makeBin($values)
	{
		$int = '';
		$str = '';
		
		foreach($values as $value)
		{
			if(is_array($value))
			{
				$str .= $this->_makeBin($value);
				continue;
			}
			if(!is_int($value))
			{
				$int .= pack('N', strlen($value));
				$str .= $value;
				continue;
			} 
			$int .= pack('N', $value);
		}
		return $int.$str;
	}

	/**
	 * get data
	 * @return 
	 */
	protected function _getResponse()
	{
		$res = fread($this->socket, 1);
		//$res= unpack("h",$res);
		//var_dump(unpack('c', $res));
		$res = unpack('c', $res);
		if($res[1] === -1)
			throw new Net_TokyoTyrantProtocolException('Error send');
		
		if($res[1] !== 0)
			throw new Net_TokyoTyrantProtocolException('Error Response');
		
		return true; 
	}

	protected function _getInt1()
	{
		$result = '';
		$res = $this->_read(1);
		$res = unpack('C', $res);
		return $res[1];
	}

	protected function _getInt4()
	{
		$result = '';
		$res = $this->_read(4);
		$res = unpack('N', $res);
		return $res[1];
	}

	protected function _getInt8()
	{
		$result = '';
		$res = $this->_read(8);
		$res = unpack('N*', $res);
		return array($res[1], $res[2]);
	}

	protected function _getValue()
	{
		$result = '';
		$size = $this->_getInt4();
		return $this->_read($size);
	}

	protected function _getKeyValue()
	{
		$result = array();
		$ksize = $this->_getInt4();
		$vsize = $this->_getInt4();
		$result[] = $this->_read($ksize);
		$result[] = $this->_read($vsize);
		return $result;
	}

	protected function _getData()
	{
		$result = '';
		$size = $this->_getInt4();
		if($size === 0)
			return '';
		return $this->_read((int) $size);
	}

	protected function _getDataList()
	{
		$result = array();
		$listCount = $this->_getInt4();
		for($i = 0; $i<$listCount; $i++)
			$result[] = $this->_getValue();
		return $result;
	}

	protected function _getKeyValueList()
	{
		$result = array();
		$listCount = $this->_getInt4();
		for($i = 0; $i<$listCount; $i++)
		{
			list($key, $value)  = $this->_getKeyValue();
			$result[$key] = $value;
		}
		return $result;
	}

	public function put($key, $value)
	{
		$cmd = pack('C*', 0xC8, 0x10);
		$this->_doRequest($cmd, array((string) $key,(string) $value));
		try {
			$this->_getResponse();
		} catch (Net_TokyoTyrantProtocolException $e) {
			return false;
		}
		return true;
	}

	public function putkeep($key, $value)
	{
		$cmd = pack('C*', 0xC8,0x11);
		$this->_doRequest($cmd, array((string) $key,(string) $value));
		try {
			$this->_getResponse();
		} catch (Net_TokyoTyrantProtocolException $e) {
			return false;
		}
		return true;
	}

	public function putcat($key, $value)
	{
		$cmd = pack('C*', 0xC8,0x12);
		$this->_doRequest($cmd, array((string) $key,(string) $value));
		try {
			$this->_getResponse();
		} catch (Net_TokyoTyrantProtocolException $e) {
			return false;
		}
		return true;
	}

	public function putrtt($key, $value, $width)
	{
		$cmd = pack('C*', 0xC8,0x13);
		$this->_doRequest($cmd, array((string) $key, (string) $value, $width));
		try {
			$this->_getResponse();
		} catch (Net_TokyoTyrantProtocolException $e) {
			return false;
		}
		return true;
	}

	public function putnr($key, $value)
	{
		$cmd = pack('C*', 0xC8,0x18);
		$this->_doRequest($cmd, array((string) $key, (string) $value, (int) $width));
		try {
			$this->_getResponse();
		} catch (Net_TokyoTyrantProtocolException $e) {
			return ;
		}
		return ; 
	}

	public function out($key)
	{
		$cmd = pack('C*', 0xC8,0x20);
		$this->_doRequest($cmd, array((string) $key));
		try {
			$this->_getResponse();
		} catch (Net_TokyoTyrantProtocolException $e) {
			return false;
		}
		return true;
	}

	public function get($key)
	{
		$cmd = pack('C*', 0xC8,0x30);
		$this->_doRequest($cmd, array((string) $key));
		try {
			$this->_getResponse();
		} catch (Net_TokyoTyrantProtocolException $e) {
			return false;
		}
		return $this->_getData();
	}

	public function mget($keys)
	{
		$cmd = pack('C*', 0xC8,0x31);
		$values = array();
		$values[] = count($keys);
		foreach($keys as $key)
			$values[] = array((string) $key);
		
		$this->_doRequest($cmd, $values);
		try {
			$this->_getResponse();
		} catch (Net_TokyoTyrantProtocolException $e) {
			return false; 
		}
		return $this->_getKeyValueList();
	}

	public function fwmkeys($prefix, $max)
	{
		$cmd = pack('C*', 0xC8,0x58);
		$this->_doRequest($cmd, array((string) $prefix, (int) $max));
		$this->_getResponse();
		return $this->_getDataList();
	}

	public function addint($key, $num)
	{
		$cmd = pack('C*', 0xC8,0x60);
		$this->_doRequest($cmd, array((string) $key, (int) $num));
		$this->_getResponse();
		return $this->_getInt4();
	}

	public function putint($key, $num)
	{
		//This Code is non support
		$value = pack('V', $num);
		return $this->put($key, $value);
	}

	public function getint($key)
	{
		return $this->addint($key, 0);
	}

	public function adddouble($key, $integ, $fract)
	{
		$cmd = pack('C*', 0xC8,0x61);
		$this->_doRequest($cmd, array((string) $key, (int) $intteg, (int) $fract));
		$this->_getResponse();
		return array($this->_getInt8(), $this->_getInt8());
	}

	public function ext($extname, $key, $value, $option = 0)
	{
		$cmd = pack('C*', 0xC8,0x68);
		$this->_doRequest($cmd, array((string) $extname, (int) $option, (string) $key, (string) $value));
		$this->_getResponse();
		return $this->_getData();
	}

	public function vsize($key)
	{
		$cmd = pack('C*', 0xC8,0x38);
		$this->_doRequest($cmd, array((string) $key));
		$this->_getResponse();
		return $this->_getInt4();
	}

	public function iterinit()
	{
		$cmd = pack('C*', 0xC8,0x50);
		$this->_doRequest($cmd);
		$this->_getResponse();
		return true;
	}

	public function iternext()
	{
		$cmd = pack('C*', 0xC8,0x51);
		$this->_doRequest($cmd);
		try {
			$this->_getResponse();
		} catch (Net_TokyoTyrantProtocolException $e) {
			return false;
		}
		return $this->_getValue();
	}

	public function sync()
	{
		$cmd = pack('C*', 0xC8,0x70);
		$this->_doRequest($cmd);
		$this->_getResponse();
		return true;
	}

	public function optimize($param)
	{
		$cmd = pack('C*', 0xC8,0x71);
		$this->_doRequest($cmd, array((string) $param));
		$this->_getResponse();
		return true;
	}

	public function vanish()
	{
		$cmd = pack('C*', 0xC8,0x72);
		$this->_doRequest($cmd);
		$this->_getResponse();
		return true;
	}

	public function copy($path)
	{
		$cmd = pack('C*', 0xC8,0x73);
		$this->_doRequest($cmd, array((string) $path));
		$this->_getResponse();
		return true;
	}

//    public function restore($path)
//    {
//        $cmd = pack('c*', 0xC8,0x74);
//        $this->_doRequest($cmd, array((string) $path));
//        $this->_getResponse();
//        return true;
//    }

	public function setmst($host, $port)
	{
		$cmd = pack('C*', 0xC8,0x78);
		$this->_doRequest($cmd, array((string) $host, (int) $port));
		$this->_getResponse();
		return true;
	}

	public function rnum()
	{
		$cmd = pack('C*', 0xC8,0x80);
		$this->_doRequest($cmd);
		$this->_getResponse();
		return $this->_getInt8();
	}

	public function size()
	{
		$cmd = pack('C*', 0xC8,0x81);
		$this->_doRequest($cmd);
		$this->_getResponse();
		return $this->_getInt8();
	}

	public function stat()
	{
		$cmd = pack('C*', 0xC8,0x88);
		$this->_doRequest($cmd);
		$this->_getResponse();
		return $this->_getValue();
	}

	public function misc($name, $args, $opts = 0)
	{
		$cmd = pack('C*', 0xC8, 0x90);
		$data = $cmd . pack('N*', strlen($name), $opts, count($args)) . $name;
		
		foreach($args as $arg)
			$data .= pack('N', strlen($arg)) . $arg;
		
		$this->_write($data);
		try {
			$this->_getResponse();
		} catch (Net_TokyoTyrantProtocolException $e) {
			$result_count = $this->_getInt4();
			throw $e;
		}
		$result_count = $this->_getInt4();
		$result = array();
		for($i = 0 ; $i < $result_count; $i++)
			$result[] = $this->_getValue();
		return $result;
	}
}
