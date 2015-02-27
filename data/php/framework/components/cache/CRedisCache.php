<?php
/**
 * CRedisCache class file
 * @author andehuang
 */

/**
 * CRedisCache使用{@link http://redis.io/ Redis}作为缓存，这是一种基于内存的数据结构服务器。
 * 使用php扩展{@link https://github.com/nicolasff/phpredis/ Redis}与Redis进行交互。
 * 每个IDC机房有各自独立的Redis集群，为了提高容错机制，一般在多个IDC机房保存相同的数据，从而实现数据互备。
 * CRedisCache支持{@link CCache}定义的统一接口。
 * 如下是示例配置：
 * <pre>
 * array(
 *     'components'=>array(
 *         'redis'=>array(
 *             'class'=>'CRedisCache',						//使用Redis作为cache
 *             'timeout'=>1,								//超时时间
 *             'serverConfigs'=>array(
 *                 'shenzhenIDC'=>array('host'=>'192.168.0.1', 'port'=>11211),	//深圳IDC
 *                 'tianjinIDC'=>array('host'=>'192.168.0.2', 'port'=>11211),	//天津IDC
 *                 'bakIDC'=>array('host'=>'192.168.0.3', 'port'=>11211),		//备份IDC
 *             ),
 *             'getIDC'=>array('shenzhenIDC', 'tianjinIDC'),//读操作时，先读取深圳IDC，如果失败再读取天津IDC
 *             'setIDC'=>'shenzhenIDC',						//写操作时，只写深圳IDC，其它机房使用异步写
 *             'localIDC'=>'shenzhenIDC',					//将深圳机房设置为本地IDC机房
 *             'getRetryCount'=>2,							//读操作重试次数2次
 *             'getRetryInterval'=>0						//读操作重试间隔0秒
 *             'setRetryCount'=>3,							//写操作重试次数3次
 *             'setRetryInterval'=>0.2						//写操作重试间隔0.2秒
 *         ),
 *     )
 * )
 * </pre>
 * 可以对某个IDC的Redis单独操作，如下为示例代码：
 * Mod::app()->redis->shenzhenIDC->hSet();	//单独操作深圳机房
 * Mod::app()->redis->bakIDC->hGet();		//单独操作备份机房
 * Mod::app()->redis->localIDC->hSet();		//单独操作本机机房
 */
class CRedisCache extends CCache
{
	/**
	 * @var int 超时时间，默认为1秒。
	 */
	public $timeout = 1;
	/**
	 * @var bool 是否建立长链接，默认使用长链接。
	 */
	public $persistent = true;
	/**
	 * @var array 各个IDC机房的服务器配置，这是最重要的配置。
	 */
	public $serverConfigs = array();
    /**
     *@var bool 是否开启性能上报
     */
    public $enablePerformReport = false;
    /**
     *@var bool 如果开启了性能上报，则new 一个上报的对象
     */
    public $_report = null; 
    
	/**
	 * @var array 名字服务的keys。
	 */
	public $nameServiceKeys = array();
	/**
	 * @var array 服务器链接对象
	 */
	private $_servers = array();
	/**
	 * @var string/array 读操作对应的IDC。当配置多个IDC时，可以提高系统的可靠性，就是一个互备的原理，
	 * 当第一个IDC读取失败的时候，可以读取第二的IDC。
	 */
	public $getIDC;
	/**
	 * @var string/array 写操作对应的IDC。当配置多个IDC时，会逐个写，但这会降低写操作的性能，一种提升办法是只写本IDC，使用离线程序来写其它的IDC。
	 * 如果第一个IDC写失败了，那么其它IDC就不会写了，通常第一个IDC表示本IDC，写本IDC是不允许失败的。
	 * 如果写其它IDC失败，则会记录redo日志，需要离线程序将失败的记录重新写入。
	 */
	public $setIDC;
	/**
	 * Redis方法哪些是读操作对应的方法，哪些是写操作对应的方法，该配置用于读写分离。
	 */
	protected $getFunctions = array(
		//key
		'dump', 'exists', 'keys', 'getKeys', 'object', 'pttl', 'randomKey', 'sort', 'ttl', 'type', 
		//string
		'bitcount', 'get', 'getBit', 'getRange', 'mGet', 'getMultiple', 'strlen', 
		//hash
		'hExists', 'hGet', 'hGetAll', 'hKeys', 'hLen', 'hMGet', 'hVals', 
		//list
		'lIndex', 'lLen', 'lSize', 'lGet', 'lRange', 'lGetRange', 
		//set
		'sCard', 'sSize', 'sDiff', 'sInter', 'sIsMember', 'sContains', 'sMembers', 'sGetMembers', 'sRandMember', 'sUnion', 
		//sorted set
		'zCard', 'zCount', 'zSize', 'zRange', 'zRangeByScore', 'zRank', 'zRevRange', 'zRevRangeByScore', 'zRevRank', 'zScore', 
		//server
		'dbSize', 'info', 
		//transactions
		'multi', 'exec', 
	);
	protected $setFunctions = array(
		//key
		'del', 'delete', 'setTimeout', 'expire', 'expireAt', 'migrate', 'move', 'persist', 'pexpire', 'pexpireAt', 'rename', 'renameKey', 'renameNx', 'restore', 
		//string
		'append', 'bitop', 'decr', 'decrBy', 'getSet', 'incr', 'incrBy', 'incrByFloat', 'mset', 'msetnx', 'psetex', 'set', 'setBit', 'setex', 'setnx', 'setRange', 
		//hash
		'hDel', 'hIncrBy', 'hIncrByFloat', 'hMset', 'hSet', 'hSetNx', 
		//list
		'blPop', 'brPop', 'brpoplpush', 'lInsert', 'lPop', 'lPush', 'lPushx', 'lRem', 'lRemove', 'lSet', 'lTrim', 'listTrim', 'rPop', 'rpoplpush', 'rPush', 'rPushx', 
		//set
		'sAdd', 'sDiffStore', 'sInterStore', 'sMove', 'sPop', 'sRem', 'sRemove', 'sUnionStore', 
		//sorted set
		'zAdd', 'zIncrBy', 'zInter', 'zRem', 'zDelete', 'zRemRangeByRank', 'zDeleteRangeByRank', 'zRemRangeByScore', 'zDeleteRangeByScore', 'zUnion', 
		//server
		'flushAll', 'flushDB', 
	);
	/**
	 * @var array 调用setFunctions时，返回哪些值认为是失败了，这样的话，可以进行重试。
	 */
	protected $setFunctionsRetFail = array(
		//key
		'del'=>false, 'delete'=>false, 'setTimeout'=>false, 'expire'=>false, 'expireAt'=>false, 'migrate'=>false, 'move'=>false, 'persist'=>false, 'pexpire'=>false, 'pexpireAt'=>false, 'rename'=>false, 'renameKey'=>false, 'renameNx'=>false, 'restore'=>false, 
		//string
		'append'=>false, 'bitop'=>false, 'decr'=>false, 'decrBy'=>false, 'getSet'=>false, 'incr'=>false, 'incrBy'=>false, 'incrByFloat'=>false, 'mset'=>false, /*'msetnx'=>false, */'psetex'=>false, 'set'=>false, 'setBit'=>false, 'setex'=>false, /*'setnx'=>false, */'setRange'=>false, 
		//hash
		/*'hDel'=>false, */'hIncrBy'=>false, 'hIncrByFloat'=>false, 'hMset'=>false, 'hSet'=>false, /*'hSetNx'=>false, */
		//list
		/*'blPop'=>false, *//*'brPop'=>false, */'brpoplpush'=>false, 'lInsert'=>false, /*'lPop'=>false, */'lPush'=>false, 'lPushx'=>false, 'lRem'=>false, 'lRemove'=>false, 'lSet'=>false, 'lTrim'=>false, 'listTrim'=>false, /*'rPop'=>false, */'rpoplpush'=>false, 'rPush'=>false, 'rPushx'=>false, 
		//set
		/*'sAdd'=>false, */'sDiffStore'=>false, 'sInterStore'=>false, 'sMove'=>false, /*'sPop'=>false, 'sRem'=>false, 'sRemove'=>false, */'sUnionStore'=>false, 
		//sorted set
		/*'zAdd'=>false, */'zIncrBy'=>false, 'zInter'=>false, /*'zRem'=>false, 'zDelete'=>false, */'zRemRangeByRank'=>false, 'zDeleteRangeByRank'=>false, 'zRemRangeByScore'=>false, 'zDeleteRangeByScore'=>false, 'zUnion'=>false, 
		//server
		'flushAll'=>false, 'flushDB'=>false, 
	);
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
	 * @var string 使用的数据库名称
	 */
	public $selectDb;
	/**
	 * @var array 对Redis进行选项配置，例如：
	 * array(
	 * 		Redis::OPT_SERIALIZER=>Redis::SERIALIZER_IGBINARY,
	 * 		Redis::OPT_PREFIX=>'myAppName:',
	 * );
	 * 相当于调用setOption函数：
	 * $redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_IGBINARY);	//use igBinary serialize/unserialize
	 * $redis->setOption(Redis::OPT_PREFIX, 'myAppName:');						//use custom prefix on all keys
	 */
	public $options = array();

	/**
	 * Initializes this application component.
	 * This method is required by the {@link IApplicationComponent} interface.
	 * It checks the availability of redis.
	 * @throws CException if redis extension is not loaded or is disabled.
	 */
	public function init()
	{
		if(!extension_loaded('redis'))
			throw new CException(Mod::t('mod','CRedisCache requires PHP redis extension to be loaded.'));
		
		//修改重试间隔的时间单位，秒=>微秒(一秒等于一百万微秒)
		$this->getRetryInterval *= 1000000;
		$this->setRetryInterval *= 1000000;
		
		//查询名字服务，获取host和port
		if(is_array($this->nameServiceKeys) && count($this->nameServiceKeys))
		{
			foreach($this->nameServiceKeys as $index=>$nameServiceKey)
			{
				if(Mod::app()->nameService->getHostByKey($nameServiceKey, $ip, $port)==false)
					throw new CException("NameServer fail, key:$nameServiceKey");
				$this->serverConfigs[$index] = array('host'=>$ip, 'port'=>$port);
			}
		}
		
		parent::init();
	}

	/**
	 * 析构函数，释放资源
	 */
	public function __destruct()
	{
		if(!$this->persistent)
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
					return $this->getServer($name);
				}
				catch(Exception $e)
				{
					//这个链接可能坏了，关闭链接，下次重新创建
					unset($this->_servers[$name]);
					
					$message = $e->getMessage();
					//记录失败日志，需要区分是否为重试
					if($retryCount)
						Mod::log("Get redis server fail(message:$message, retryCount:$retryCount)",CLogger::LEVEL_ERROR,'system.cache.CRedisCache');
					else
						Mod::log("Get redis server fail(message:$message)",CLogger::LEVEL_ERROR,'system.cache.CRedisCache');
					//判断是否需要sleep
					if($this->getRetryCount && $this->getRetryInterval && $retryCount<$this->getRetryCount)
						usleep($this->getRetryInterval);
				}
			}
			//指定的Redis对象，抛异常。
			throw new CException("Get redis server fail(message:$message)",(int)$e->getCode());
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
	 * PHP魔术方法，当调用一个不存在成员方法时，会触发本方法的调用。本方法是对Redis调用的路由，将方法调用路由给Redis对象。
	 * 需要判断调用的方法是读操作还是写操作，真正的调用有callRedisFunction方法来处理。
	 * @param string $name 成员方法名称
	 * @param array $parameters 参数
	 * @return mixed
	 */
	public function __call($name, $parameters=array())
	{
        	$idc = $this->_localIDC;
		if($this->enablePerformReport){
			$reportObj = new CPhpPerfReporter(); 
			$reportObj->beginPerfReport(Mod::app()->id,$this->serverConfigs[$idc]['host'] , $this->serverConfigs[$idc]['port'], false, '', "");
        		$reportObj->setFunctionName($name);
        	}
        	if(in_array($name, $this->getFunctions))
			$re = $this->callRedisFunction(true, $name, $parameters);
		else if(in_array($name, $this->setFunctions))
			$re = $this->callRedisFunction(false, $name, $parameters);
		else
			$re = parent::__call();	//如果调用的不是Redis的方法，则交给父类处理
            
         	if($this->enablePerformReport) {
            		$strparameters = var_export($parameters,true);
            		$strparameters=preg_replace('/\s/','',$strparameters);
            		//截断是为了防止传的value过长导致socketbuf出现问题
            		$strparameters = substr($strparameters, 0, 512);
            		$reportObj->addParam("skeys",$strparameters);
            		$reportObj->endPerfReport(0);
        	}
         	return $re;
	}

	/**
	 * 对Redis进行路由，即对Redis提供的原生函数进行一层封装，从而实现：
	 * 1、失败重试
	 * 2、多IDC容错
	 * @param bool $isGet 是读操作还是写操作
	 * @param string $name 调用的方法名称
	 * @param array $parameters 参数列表
	 */
	protected function callRedisFunction($isGet, $functionName, $functionParams)
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
					$server = $this->getServer($IDCName);
					if($isGet)//读操作，直接返回
						return call_user_func_array(array($server, $functionName), $functionParams);
					else
					{
						$ret = call_user_func_array(array($server, $functionName), $functionParams);
						//需要记录第一个IDC机房的返回值
						if($isFirst)
							$retFrist = $ret;
						//判断写操作是否成功
						if(isset($this->setFunctionsRetFail[$functionName]) && $ret===$this->setFunctionsRetFail[$functionName])
							throw new CException('Redis return fail!', 500);
						else
						{
							$writeSuccess = true;
							break;
						}
					}
				}
				catch(Exception $e)
				{
					//这个链接可能坏了，关闭链接，下次重新创建
					unset($this->_servers[$IDCName]);
					
					//记录写操作失败
					if(!$isGet)
						$writeSuccess = false;
					
					//记录失败日志，需要区分是否为重试
					$logContent = array('IDCName'=>$IDCName, 'functionName'=>$functionName, 'functionParams'=>$functionParams, 'errorMsg'=>$e->getMessage());
					if($retryCount)
					{
						$logContent['retryCount'] = $retryCount;
						Mod::log($logContent, CLogger::LEVEL_ERROR, 'system.cache.CRedisCache');
					}
					else
						Mod::log($logContent, CLogger::LEVEL_ERROR, 'system.cache.CRedisCache');
					
					//判断是否需要sleep
					if($retryCountSum && $retryInterval && $retryCount<$retryCountSum)
						usleep($retryInterval);
				}
			}//--foreach end 重试--
			
			//写操作失败，需要处理
			if(!$isGet && !$writeSuccess)
			{
				if($isFirst){//第一个IDC机房写失败，直接抛异常
					$exceptstr = 'redis error!'.$e->getMessage().';name is '.var_export($this->nameServiceKeys,true).',Server is '.$this->serverConfigs[$IDCName]['host'].':';
					$exceptstr .= $this->serverConfigs[$IDCName]['port'].',function is '.$functionName;
					$exceptstr .= ',key is '.var_export($functionParams,true);
					throw new CException($exceptstr);
				}
				else//其它IDC机房写失败，写日志，需要进行重写
				{
					$logContent = array('IDCName'=>$IDCName, 'functionName'=>$functionName, 'functionParams'=>$functionParams);
					Mod::log($logContent, CLogger::LEVEL_ERROR, 'system.cache.CRedisCache');
				}
			}
			
			//下次操作，就不是第一个IDC机房了
			$isFirst = false;
		}//--foreach end 多个IDC--
		
		if($isGet)	//对于读操作，如果运行到这里，那就悲剧了！所有IDC集群都读取失败，抛异常
		{
			$logContent = array('functionName'=>$functionName, 'functionParams'=>$functionParams, 'errorMsg'=>'all redis fail');
			Mod::log($logContent, CLogger::LEVEL_ERROR, 'system.cache.redis');
			$exceptstr = 'redis error!'.$e->getMessage().';name is '.var_export($this->nameServiceKeys,true).',Server is '.$this->serverConfigs[$IDCName]['host'].':';
			$exceptstr .= $this->serverConfigs[$IDCName]['port'].',function is '.$functionName;
			$exceptstr .= ',key is '.var_export($functionParams,true);
			throw new CException($exceptstr);
		}
		else		//对于写操作，返回第一个IDC机房的返回值
			return $retFrist;
	}

	/**
	 * 获取Server链接对象
	 * @param string $IDCName IDC名称
	 * @return object Redis
	 */
	public function getServer($IDCName)
	{
		//对已经创建的链接进行缓存
		if(isset($this->_servers[$IDCName]) && $this->_servers[$IDCName])
			return $this->_servers[$IDCName];
		
		//链接Redis
		$host = $this->serverConfigs[$IDCName]['host'];
		$port = $this->serverConfigs[$IDCName]['port'];
		$server = new Redis;
		if($this->persistent)//建立持久链接
			$ret = $server->pconnect($host, $port, $this->timeout);
		else
			$ret = $server->connect($host, $port, $this->timeout);
		
		//判断链接是否成功
		if($ret === false)
		{
			Mod::log("connect redis fail(host:$host, port:$port, IDCName:$IDCName)", CLogger::LEVEL_ERROR, 'cache.redis');
			throw new CException("connect redis fail(host:$host, port:$port, IDCName:$IDCName)");
		}
		
		//设置option
		foreach($this->options as $optionKey=>$optionValue)
			$server->setOption($optionKey, $optionValue);
		
		//设置数据库
		if($this->selectDb)
			$server->select($this->selectDb);
		
		return $this->_servers[$IDCName] = $server;
	}

	/**
	 * Retrieves a value from cache with a specified key.
	 * This is the implementation of the method declared in the parent class.
	 * @param string $key a unique key identifying the cached value
	 * @return string the value stored in cache, false if the value is not in the cache or expired.
	 */
	protected function getValue($key)
	{
		return $this->__call('get', array($key));
	}

	/**
	 * Retrieves multiple values from cache with the specified keys.
	 * @param array $keys a list of keys identifying the cached values
	 * @return array a list of cached values indexed by the keys
	 */
	protected function getValues($keys)
	{
		return $this->__call('mGet', array($keys));
	}

	/**
	 * Stores a value identified by a key in cache.
	 * This is the implementation of the method declared in the parent class.
	 *
	 * @param string $key the key identifying the value to be cached
	 * @param string $value the value to be cached
	 * @param integer $expire the number of seconds in which the cached value will expire. 0 means never expire.
	 * @return boolean true if the value is successfully stored into cache, false otherwise
	 */
	protected function setValue($key,$value,$expire=0)
	{
		if($expire)
			return $this->__call('setex', array($key, $expire, $value));
		else
			return $this->__call('set', array($key, $value));
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
		if($this->__call('setnx', array($key, $value)))
			return $this->__call('expire', array($key, $expire));
	}

	/**
	 * Deletes a value with the specified key from cache
	 * This is the implementation of the method declared in the parent class.
	 * @param string $key the key of the value to be deleted
	 * @return boolean if no error happens during deletion
	 */
	protected function deleteValue($key)
	{
		return $this->__call('delete', array($key));
	}

	/**
	 * Deletes all values from cache.
	 * This is the implementation of the method declared in the parent class.
	 * @return boolean whether the flush operation was successful.
	 * @since 1.0
	 */
	protected function flushValues()
	{
		if($this->selectDb)
			return $this->__call('flushDB');
		else
			return $this->__call('flushAll');
	}

	/**
	 * Retrieves multiple values from cache with the specified keys.
	 * Some caches (such as memcache, apc) allow retrieving multiple cached values at one time,
	 * which may improve the performance since it reduces the communication cost.
	 * In case a cache doesn't support this feature natively, it will be simulated by this method.
	 * @param array $ids list of keys identifying the cached values
	 * @return array list of cached values corresponding to the specified keys. The array
	 * is returned in terms of (key,value) pairs.
	 * If a value is not cached or expired, the corresponding array value will be false.
	 */
	public function mget($ids)
	{
		$uniqueIDs=array();
		$results=array();
		foreach($ids as $id)
		{
			$uniqueIDs[$id]=$this->generateUniqueKey($id);
			$results[$id]=false;
		}
		$values=$this->getValues($uniqueIDs);
		$index = 0;
		foreach($uniqueIDs as $id=>$uniqueID)
		{
			$data=unserialize($values[$index]);
			if(is_array($data) && (!($data[1] instanceof ICacheDependency) || !$data[1]->getHasChanged()))
			{
				Mod::trace('Serving "'.$id.'" from cache','system.caching.redis');
				$results[$id]=$data[0];
			}
			else $results[$id]=$values[$index];
			$index++;
		}
		return $results;
	}
}
