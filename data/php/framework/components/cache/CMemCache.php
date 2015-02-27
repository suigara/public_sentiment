<?php
/**
 * CMemCache class file
 *
 * @author 
 */

/**
 * CMemCache implements a cache application component based on {@link http://memcached.org/ memcached}.
 *
 * CMemCache can be configured with a list of memcache servers by settings
 * its {@link setServers servers} property. By default, CMemCache assumes
 * there is a memcache server running on localhost at port 11211.
 *
 * See {@link CCache} manual for common cache operations that are supported by CMemCache.
 *
 * Note, there is no security measure to protected data in memcache.
 * All data in memcache can be accessed by any process running in the system.
 *
 * To use CMemCache as the cache application component, configure the application as follows,
 * <pre>
 * array(
 *     'components'=>array(
 *         'cache'=>array(
 *             'class'=>'CMemCache',
 *             'servers'=>array(
 *                 array(
 *                     'host'=>'server1',
 *                     'port'=>11211,
 *                     'weight'=>60,
 *                 ),
 *                 array(
 *                     'host'=>'server2',
 *                     'port'=>11211,
 *                     'weight'=>40,
 *                 ),
 *             ),
 *         ),
 *     ),
 * )
 * </pre>
 * In the above, two memcache servers are used: server1 and server2.
 * You can configure more properties of every server, including:
 * host, port, persistent, weight, timeout, retryInterval, status.
 * See {@link http://www.php.net/manual/en/function.memcache-addserver.php}
 * for more details.
 *
 * CMemCache can also be used with {@link http://pecl.php.net/package/memcached memcached}.
 * To do so, set {@link useMemcached} to be true.
 *
 * @property mixed $memCache The memcache instance (or memcached if {@link useMemcached} is true) used by this component.
 * @property array $servers List of memcache server configurations. Each element is a {@link CMemCacheServerConfiguration}.
 */
class CMemCache extends CCache
{
	/**
	 * @var boolean whether to use memcached or memcache as the underlying caching extension.
	 * If true {@link http://pecl.php.net/package/memcached memcached} will be used.
	 * If false {@link http://pecl.php.net/package/memcache memcache}. will be used.
	 * Defaults to false.
	 */
	public $useMemcached=false;
	/**
	 * @var array 名字服务的keys
	 */
	public $nameServiceKeys = array();
	/**
	 * @var Memcache the Memcache instance
	 */
	private $_cache=null;
	/**
	 * @var array list of memcache server configurations
	 */
	private $_servers=array();
    
    /**
     *@var enable report the memcache performance
     */
     public $enablePerformReport=false;
    /**
     * @var object of performreport
     */
    private $_report=null; 
    /**
     * 网络抖动或者重启会导致服务短时间的不可用(1~3秒)，为了缓解这种问题，使用了重试机制来尽量屏蔽这样的故障，从而做到对组件调用方的透明。
     * 使用如下4个成员变量来配置重试次数和重试间隔：
     * getRetryCount、getRetryInterval、setRetryCount、setRetryInterval。
     * @var int 读操作重试次数
     */
    public $getRetryCount = 1;
    /**
     * @var float 读操作重试间隔，单位是秒。
     */
    public $getRetryInterval = 0.1;
    /**
     * @var int 写操作重试次数
     */
    public $setRetryCount = 1;
    /**
     * @var float 读操作重试间隔，单位是秒，默认为0.2秒(200毫秒)。
     */
    public $setRetryInterval = 0.2;
    
	/**
	 * Initializes this application component.
	 * This method is required by the {@link IApplicationComponent} interface.
	 * It creates the memcache instance and adds memcache servers.
	 * @throws CException if memcache extension is not loaded
	 */
	public function init()
	{
		//查询名字服务，获取host和port      
		if(is_array($this->nameServiceKeys) && count($this->nameServiceKeys))       
        	{           
            		$weight = intval(100/count($this->nameServiceKeys));            
            		$serverConfigs = array();           
            		foreach($this->nameServiceKeys as $index=>$nameServiceKey)          
            		{               
                		if(Mod::app()->nameService->getHostByKey($nameServiceKey, $ip, $port)==false)                   
                    			throw new CException("NameServer fail, key:$nameServiceKey");               
                		$serverConfigs[] = array('host'=>$ip, 'port'=>$port, 'weight'=>$weight, 'persistent'=>true);            
            		}           
            		$this->setServers($serverConfigs);      
        	}
        
		$this->getRetryInterval *= 1000000;
        	$this->setRetryInterval *= 1000000;
        	parent::init();
        	$servers=$this->getServers();
		$cache=$this->getMemCache();
		if(count($servers))
		{
			foreach($servers as $server)
			{
				if($this->useMemcached)
					$cache->addServer($server->host,$server->port,$server->weight);
				else
					$cache->addServer($server->host,$server->port,$server->persistent,$server->weight,$server->timeout,$server->status);
			}
		}
		else
			$cache->addServer('localhost',11211);      
	}

	/**
	 * @return mixed the memcache instance (or memcached if {@link useMemcached} is true) used by this component.
	 */
	public function getMemCache()
	{
		if($this->_cache!==null)
			return $this->_cache;
		else
			return $this->_cache=$this->useMemcached ? new Memcached : new Memcache;
	}

	/**
	 * @return array list of memcache server configurations. Each element is a {@link CMemCacheServerConfiguration}.
	 */
	public function getServers()
	{
		return $this->_servers;
	}

	/**
	 * @param array $config list of memcache server configurations. Each element must be an array
	 * with the following keys: host, port, persistent, weight, timeout, retryInterval, status.
	 * @see http://www.php.net/manual/en/function.Memcache-addServer.php
	 */
	public function setServers($config)
	{
		foreach($config as $c)
			$this->_servers[]=new CMemCacheServerConfiguration($c);
	}

	/**
	 * Retrieves a value from cache with a specified key.
	 * This is the implementation of the method declared in the parent class.
	 * @param string $key a unique key identifying the cached value
	 * @return string the value stored in cache, false if the value is not in the cache or expired.
	 */
	protected function getValue($key)
	{ 
		if($this->enablePerformReport) {
			$reportObj = new CPhpPerfReporter();
			$reportObj->beginPerfReport(Mod::app()->id,$this->_servers[0]->host , $this->_servers[0]->port, false, '', "");
		}
		for($retryCount = 0; $retryCount < $this->getRetryCount; $retryCount++)
		{    
            		try{
				$re = $this->_cache->get($key);
				if($this->enablePerformReport){
					$reportObj->addParam("skeys",$key);
					$reportObj->endPerfReport(0);
				}
                		return $re;
            		}catch(Exception $e){
                		$logContent = array('file'=>__FILE__,'class'=>__CLASS__,'functionname'=>__METHOD__,'linenum'=>__LINE__);
                		if($retryCount)
                		{
                    			$logContent['retryCount'] = $retryCount;
                    			Mod::log($logContent, CLogger::LEVEL_ERROR, 'cache.CMemCache');
                		}
                		else
                    			Mod::log($logContent, CLogger::LEVEL_ERROR, 'cache.CMemCache');
                		if($this->getRetryCount && $this->getRetryInterval && $retryCount < $this->getRetryCount)
                    			usleep($this->getRetryInterval);
            		}
        	}
	}

	/**
	 * Retrieves multiple values from cache with the specified keys.
	 * @param array $keys a list of keys identifying the cached values
	 * @return array a list of cached values indexed by the keys
	 */
	protected function getValues($keys)
	{
        	if($this->enablePerformReport){
			$reportObj = new CPhpPerfReporter();
			$reportObj->beginPerfReport(Mod::app()->id,$this->_servers[0]->host , $this->_servers[0]->port, false, '', "");
        	}
		for($retryCount = 0; $retryCount < $this->getRetryCount; $retryCount++)
        	{
            		try{
            			$re = $this->useMemcached ? $this->_cache->getMulti($keys) : $this->_cache->get($keys);
				if($this->enablePerformReport) {
					$reportObj->addParam("skeys",$keys);
					$reportObj->endPerfReport(0);
				}
                		return $re;

            		}catch(Exception $e){
                		$logContent = array('file'=>__FILE__,'class'=>__CLASS__,'functionname'=>__METHOD__,'linenum'=>__LINE__);
                		if($retryCount)
                		{
                    			$logContent['retryCount'] = $retryCount;
                    			Mod::log($logContent, CLogger::LEVEL_ERROR, 'cache.CMemCache');
                		}
                		else
                    			Mod::log($logContent, CLogger::LEVEL_ERROR, 'cache.CMemCache');
                		if($this->getRetryCount && $this->getRetryInterval && $retryCount < $this->getRetryCount)
                    			usleep($this->getRetryInterval);
            		}
       	 	}
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
	protected function setValue($key,$value,$expire)
	{
		if($expire>0)
			$expire+=time();
		else
			$expire=0;
        	if($this->enablePerformReport){
			$reportObj = new CPhpPerfReporter();
			$reportObj->beginPerfReport(Mod::app()->id,$this->_servers[0]->host , $this->_servers[0]->port, false, '', "");
       		}
		for($retryCount = 0; $retryCount < $this->setRetryCount; $retryCount++)
        	{
            		try{
		        	$re = $this->useMemcached ? $this->_cache->set($key,$value,$expire) : $this->_cache->set($key,$value,0,$expire);
                		if($this->enablePerformReport) {
                			$reportObj->addParam("skeys",$key);
                			$reportObj->endPerfReport(0);
                		}
                		return $re;
            		}catch(Exception $e){
                		$logContent = array('file'=>__FILE__,'class'=>__CLASS__,'functionname'=>__METHOD__,'linenum'=>__LINE__);
                		if($retryCount)
                		{
                    			$logContent['retryCount'] = $retryCount;
                   	 		Mod::log($logContent, CLogger::LEVEL_ERROR, 'cache.CMemCache');
                		}
                		else
                    			Mod::log($logContent, CLogger::LEVEL_ERROR, 'cache.CMemCache');
                		if($this->setRetryCount && $this->setRetryInterval && $retryCount < $this->setRetryCount)
                    			usleep($this->setRetryInterval);
            		}
        	}
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
		if($expire>0)
			$expire+=time();
		else
			$expire=0;
        	if($this->enablePerformReport){
        		$reportObj = new CPhpPerfReporter();
			$reportObj->beginPerfReport(Mod::app()->id,$this->_servers[0]->host , $this->_servers[0]->port, false, '', "");
		}
		for($retryCount = 0; $retryCount < $this->setRetryCount; $retryCount++)
        	{
            		try{
                		$re = $this->useMemcached ? $this->_cache->add($key,$value,$expire) : $this->_cache->add($key,$value,0,$expire);
                		if($this->enablePerformReport) {
                			$reportObj->addParam("skeys",$key);
                			$reportObj->endPerfReport(0);
                		}
                		return $re;
            		}catch(Exception $e){
                		$logContent = array('file'=>__FILE__,'class'=>__CLASS__,'functionname'=>__METHOD__,'linenum'=>__LINE__);
                		if($retryCount)
                		{
                    			$logContent['retryCount'] = $retryCount;
                    			Mod::log($logContent, CLogger::LEVEL_ERROR, 'cache.CMemCache');
                		}
                		else
                    			Mod::log($logContent, CLogger::LEVEL_ERROR, 'cache.CMemCache');
                		if($this->setRetryCount && $this->setRetryInterval && $retryCount < $this->setRetryCount)
                    			usleep($this->setryInterval);
            		}
        	}
    }

	/**
	 * Deletes a value with the specified key from cache
	 * This is the implementation of the method declared in the parent class.
	 * @param string $key the key of the value to be deleted
	 * @return boolean if no error happens during deletion
	 */
	protected function deleteValue($key)
	{
        	if($this->enablePerformReport){ 
        		$reportObj = new CPhpPerfReporter();
        		$reportObj->beginPerfReport(Mod::app()->id,$this->_servers[0]->host , $this->_servers[0]->port, false, '', "");
        	}	
		for($retryCount = 0; $retryCount < $this->setRetryCount; $retryCount++)
        	{
            		try{
                		$re =$this->_cache->delete($key, 0);
                		if($this->enablePerformReport) {
                			$reportObj->addParam("skeys",$key);
                			$reportObj->endPerfReport(0);
                		}
                		return $re;
            		}catch(Exception $e){
                		$logContent = array('file'=>__FILE__,'class'=>__CLASS__,'functionname'=>__METHOD__,'linenum'=>__LINE__);
                		if($retryCount)
                		{
                    			$logContent['retryCount'] = $retryCount;
                    			Mod::log($logContent, CLogger::LEVEL_ERROR, 'cache.CMemCache');
                		}
                		else
                    			Mod::log($logContent, CLogger::LEVEL_ERROR, 'cache.CMemCache');
                		if($this->setRetryCount && $this->setRetryInterval && $retryCount < $this->setRetryCount)
                    			usleep($this->setRetryInterval);
            		}
        	}
	}

	/**
	 * Deletes all values from cache.
	 * This is the implementation of the method declared in the parent class.
	 * @return boolean whether the flush operation was successful.
	 * @since 1.0
	 */
	protected function flushValues()
	{
        	if($this->enablePerformReport){
        		$reportObj = new CPhpPerfReporter();  
        		$reportObj->beginPerfReport(Mod::app()->id,$this->_servers[0]->host , $this->_servers[0]->port, false, '', "");
        	}
		for($retryCount = 0; $retryCount < $this->setRetryCount; $retryCount++)
        	{
            		try{
		        	$re = $this->_cache->flush();
                		if($this->enablePerformReport) $reportObj->endPerfReport(0);
                		return $re;
            		}catch(Exception $e){
                		$logContent = array('file'=>__FILE__,'class'=>__CLASS__,'functionname'=>__METHOD__,'linenum'=>__LINE__);
                		if($retryCount)
                		{
                   		 	$logContent['retryCount'] = $retryCount;
                    			Mod::log($logContent, CLogger::LEVEL_ERROR, 'cache.CMemCache');
                		}
                		else
                    			Mod::log($logContent, CLogger::LEVEL_ERROR, 'cache.CMemCache');
                		if($this->setRetryCount && $this->setRetryInterval && $retryCount < $this->setRetryCount)
                    			usleep($this->setRetryInterval);
            		}
        	}
	}
}

/**
 * CMemCacheServerConfiguration represents the configuration data for a single memcache server.
 *
 * See {@link http://www.php.net/manual/en/function.Memcache-addServer.php}
 * for detailed explanation of each configuration property.
 *
 * @author 
 * @version 
 * @package system.caching
 * @since 1.0
 */
class CMemCacheServerConfiguration extends CComponent
{
	/**
	 * @var string memcache server hostname or IP address
	 */
	public $host;
	/**
	 * @var integer memcache server port
	 */
	public $port=11211;
	/**
	 * @var boolean whether to use a persistent connection
	 */
	public $persistent=true;
	/**
	 * @var integer probability of using this server among all servers.
	 */
	public $weight=1;
	/**
	 * @var integer value in seconds which will be used for connecting to the server
	 */
	public $timeout=15;
	/**
	 * @var integer how often a failed server will be retried (in seconds)
	 */
	public $retryInterval=15;
	/**
	 * @var boolean if the server should be flagged as online upon a failure
	 */
	public $status=true;
    
    /**
     * @var boolean if the server should be flagged as online upon a failure
     */
    public $enblePerformReport=false;
                  
    
	/**
	 * Constructor.
	 * @param array $config list of memcache server configurations.
	 * @throws CException if the configuration is not an array
	 */
	public function __construct($config)
	{
		if(is_array($config))
		{
			foreach($config as $key=>$value)
				$this->$key=$value;
			if($this->host===null)
				throw new CException(Mod::t('mod','CMemCache server configuration must have "host" value.'));
		}
		else
			throw new CException(Mod::t('mod','CMemCache server configuration must be an array.'));
	}
}