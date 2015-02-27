<?php
/**
 * Beanstalk组件
 */

require(dirname(__FILE__).'/pheanstalk/pheanstalk_init.php');

/**
 * CBeanstalk提供消息队列服务{@link http://kr.github.com/beanstalkd/}，使用{@link https://github.com/pda/pheanstalk/}与beanstalkd进行链接。
 * 为了提高服务的可用性，需要配置多个beanstalkd实例，当一个实例写失败了，可以写另外一实例。通过“weight”字段可以配置各个beanstalk实例的权重。从而实现如下2个目标：
 * 1、容错透明。即当一个beanstalkd实例不可用，可以使用其它beanstalkd实例。
 * 2、负载均衡。通过weight配置分配比例。
 * 如下是示例配置：
 * <pre>
 * array(
 *     'components'=>array(
 *         'beanstalk'=>array(
 *             'class'=>'CBeanstalk',
 *             'timeout'=>1,													//超时时间
 *             'useTubeFunction'=>useByQQ,										//根据消息的内容来设置使用哪个tube
 *             'serverConfigs'=>array(											//配置三个beanstalkd实例，每个实例的权重weight根据机器性能进行配置
 *                 array('host'=>'192.168.0.1', 'port'=>11211, 'weight'=>80),	//权重为80%，该机器性能好一些，所以压力大一些
 *                 array('host'=>'192.168.0.2', 'port'=>11211, 'weight'=>15),	//权重为15%
 *                 array('host'=>'192.168.0.3', 'port'=>11211, 'weight'=>5),	//权重为5%
 *             )
 *         ),
 *     )
 * )
 * </pre>
 * 
 * 可以对某个beanstalkd单独操作，如下为示例代码：
 * Mod::app()->beanstalk[0]->reserve();		//单独操作深圳机房
 * Mod::app()->beanstalk[0]->reserve();		//单独操作备份机房
 * Mod::app()->beanstalk[0]->reserve();		//单独操作本机机房
 */
class CBeanstalk extends CApplicationComponent implements ArrayAccess
{
	/**
	 * @var int 超时时间，默认为1秒。
	 */
	public $timeout = 1;
	/**
	 * @var array 各个beanstalkd实例配置，这是最重要的配置。
	 */
	public $serverConfigs = array();
	/**
	 * @var array 服务器链接对象
	 */
	private $_servers = array();
	/**
	 * @var string 默认use的tube
	 */
	public $using = Pheanstalk::DEFAULT_TUBE;
	/**
	 * @var array 默认watch的tube
	 */
	public $watching = array(Pheanstalk::DEFAULT_TUBE=>true);
	/**
	 * @var function 根据put的消息体来选择tube的方法
	 */
	public $useTubeFunction;

	/**
	 * 获取Server链接对象
	 * @param int/sting $serverId 服务器id，即成员变量$serverConfigs的key
	 * @return object Pheanstalk
	 */
	public function getServer($serverId)
	{
		//对已经创建的链接进行缓存
		if(isset($this->_servers[$serverId]) && $this->_servers[$serverId])
			return $this->_servers[$serverId];
		
		$server = new Pheanstalk($this->serverConfigs[$serverId]['host'], $this->serverConfigs[$serverId]['port'], $this->timeout);
		
		//设置tube
		if($this->using != Pheanstalk::DEFAULT_TUBE)
			$server->useTube($this->using);
		
		//设置watch
		foreach($this->watching as $tube=>$true)
		{
			if($tube != Pheanstalk::DEFAULT_TUBE)
				$server->watch($tube);
		}
		if(!isset($this->watching[Pheanstalk::DEFAULT_TUBE]))
			$server->ignore(Pheanstalk::DEFAULT_TUBE);
		
		return $this->_servers[$serverId] = $server;
	}

	/**
	 * 关闭Server链接对象
	 * @param int/sting $serverId 服务器id，即成员变量$serverConfigs的key
	 */
	public function close($serverId=null)
	{
		if($serverId!==null)
			unset($this->_servers[$serverId]);
		else
		{
			foreach($this->_servers as $serverId=>$server)
				unset($this->_servers[$serverId]);
		}
	}

	/**
	 * 添加一条消息到消息队列
	 * @param string $data job的内容
	 * @param int $priority 优先级，最高优先级是0，最低优先级是4294967295
	 * @param int $delay 将job放入ready队列需要等待的秒数
	 * @param int $ttr 允许一个worker执行该job的秒数
	 * @return int The new job ID
	 */
	public function put($data, $priority=Pheanstalk::DEFAULT_PRIORITY, $delay=Pheanstalk::DEFAULT_DELAY, $ttr=Pheanstalk::DEFAULT_TTR)
	{
		$balanceServerIds = $this->getBalanceServerIds();
		
		foreach($balanceServerIds as $serverId)
		{
			try
			{
				$server = $this->getServer($serverId);
				if(is_callable($this->useTubeFunction))
				{
					$tubeName = call_user_func($this->useTubeFunction, $data);
					$server->useTube($tubeName);
				}
				return $server->put($data, $priority, $delay, $ttr);
			}
			catch(Exception $e)
			{
				//这个链接可能坏了，关闭链接，下次重新创建
				unset($this->_servers[$serverId]);
				$message = $e->getMessage();
				Mod::log("Beanstalk put fail(message:$message, serverId:$serverId)",CLogger::LEVEL_ERROR,'system.beanstalk');
			}
			
		}
		throw new CException("Beanstalk put all fail(message:$message)",(int)$e->getCode());
	}

	/**
	 * 获取beanstalk服务器列表，考虑如下2个目标：
	 * 1、负载均衡
	 * 2、容错
	 * 如果有多台beanstalk，则根据权重weight来进行负载均衡。
	 * 如果第一台beanstalk添加失败，则会操作其它beanstalk进行容错。
	 */
	public function getBalanceServerIds()
	{
		//计算总的权重
		static $randMax = 0;
		if($randMax==0)
		{
			foreach($this->serverConfigs as $serverId=>$config)
			{
				//如果没有配置权重，则默认配置为100
				if(!isset($config['weight']) || $config['weight']<0)
				{
					$this->serverConfigs[$serverId]['weight'] = 100;
					$randMax += 100;
				}
				else
					$randMax += $config['weight'];
			}
		}
		if($randMax<=0)
			throw new CException('请配置beanstalk的权重weight');
		
		//根据权重，选择第一台beanstalk
		$rand = mt_rand(1, $randMax);
		$curTotal = 0;
		foreach($this->serverConfigs as $serverId=>$config)
		{
			if($rand>$curTotal && $rand<=$curTotal+$config['weight'])
				break;
			else
				$curTotal += $config['weight'];
		}
		$balanceServerIds = array($serverId);
		
		//如果第一台beanstalk添加失败，则会操作其它beanstalk进行容错
		$allServerIds = array_keys($this->serverConfigs);
		shuffle($allServerIds);
		foreach($allServerIds as $serverId)
		{
			if($serverId != $balanceServerIds[0])
				$balanceServerIds[] = $serverId;
		}
		
		return $balanceServerIds;
	}
	
	/**
	 * Returns whether there is a cache entry with a specified key.
	 * This method is required by the interface ArrayAccess.
	 * @param string $id a key identifying the cached value
	 * @return boolean
	 */
	public function offsetExists($serverId)
	{
		return isset($this->serverConfigs[$serverId]);
	}

	/**
	 * Retrieves the value from cache with a specified key.
	 * This method is required by the interface ArrayAccess.
	 * @param string $id a key identifying the cached value
	 * @return mixed the value stored in cache, false if the value is not in the cache or expired.
	 */
	public function offsetGet($serverId)
	{
		return $this->getServer($serverId);
	}

	/**
	 * Stores the value identified by a key into cache.
	 * If the cache already contains such a key, the existing value will be
	 * replaced with the new ones. To add expiration and dependencies, use the set() method.
	 * This method is required by the interface ArrayAccess.
	 * @param string $id the key identifying the value to be cached
	 * @param mixed $value the value to be cached
	 */
	public function offsetSet($serverId, $server)
	{
		$this->close($serverId);
		$this->_server[$serverId] = $server;
	}

	/**
	 * Deletes the value with the specified key from cache
	 * This method is required by the interface ArrayAccess.
	 * @param string $id the key of the value to be deleted
	 * @return boolean if no error happens during deletion
	 */
	public function offsetUnset($serverId)
	{
		$this->close($serverId);
	}
}