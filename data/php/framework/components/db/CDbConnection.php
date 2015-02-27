<?php
/**
 * CDbConnection class file
 * @author andehuang
 */

class CDbConnection extends CApplicationComponent
{
	/**
	 * @var string 主库的名字空间
	 */
	public $nameServiceKeyMaster;
	/**
	 * @var array 从库的名字空间
	 */
	public $nameServiceKeysSlave = array();
	
	/**
	 * @var string 主库配置(http://www.php.net/manual/en/function.PDO-construct.php)
	 */
	public $connectionString='';	//地址、端口和数据库名称，比如：'mysql:host=192.168.1.100;dbname=Db2012;port=8001'
	public $username='';			//用户名
	public $password='';			//密码
	/**
	 * @var array 从库配置，每个从库有三个配置项($connectionString、$username和$password)，例如：
	 * array(
	 * 		array('connectionString'=>'mysql:host=192.168.1.101;dbname=Db2012;port=8001', 'username'=>'root', 'password'=>'123456'),
	 * 		array('connectionString'=>'mysql:host=192.168.1.102;dbname=Db2012;port=8001', 'username'=>'root', 'password'=>'123456')
	 * );
	 */
	public $slaveConfigs = array();
	/**
	 * @var array 所有的数据库(主库和所有从库)配置信息，在init成员方法中将主库配置和从库配置进行合并，这样便于查找，合并结果为：
	 * array(
	 * 		'master'=>array('connectionString'=>'mysql:host=192.168.1.100;dbname=Db2012;port=8001', 'username'=>'root', 'password'=>'123456'),	//主库配置
	 * 		'slave1'=>array('connectionString'=>'mysql:host=192.168.1.101;dbname=Db2012;port=8001', 'username'=>'root', 'password'=>'123456'),	//从库1配置
	 * 		'slave2'=>array('connectionString'=>'mysql:host=192.168.1.102;dbname=Db2012;port=8001', 'username'=>'root', 'password'=>'123456'),	//从库2配置
	 * 		'slave3'=>array('connectionString'=>'mysql:host=192.168.1.103;dbname=Db2012;port=8001', 'username'=>'root', 'password'=>'123456')	//从库3配置
	 * );
	 * 每个DB分配一个唯一的id，即成员变量$allConfigs数组的key：主库是'master'，从库1是'slave1'，从库2是'slave2'，从库3是'slave3'。
	 */
	protected $allConfigs = array();
	/**
	 * @var array 数组中的每个元素都是一个PDO链接对象，比如：
	 * array(
	 * 		'master'=>$pdo,		//主库链接
	 * 		'slave1'=>$pdo,		//从库1链接
	 * 		'slave2'=>$pdo		//从库2链接
	 * 	);
	 */
	private $_pdos = array();
	/**
	 * @var PDO 当前使用的数据库链接。
	 */
	private $_curPdo;
	/**
	 * 网络抖动或者数据库重启会导致服务短时间的不可用(1~3秒)，为了缓解这种问题，使用了重试机制($retryCount和$retryInterval)来尽量屏蔽这种故障，从而做到对组件调用方的透明。
	 * @var int 重试次数，默认为0，表示不会重试。
	 */
	public $retryCount = 0;
	/**
	 * @var float 重试间隔，单位是秒，默认为0.5秒(500毫秒)。
	 */
	public $retryInterval = 0.5;
	/**
	 * @var bool 是否强制使用master，默认为false。
	 */
	public $forceMaster = false;
	/**
	 * @var float 主从延时，单位是秒。对主库进行写操作后，立刻读从库可能读不到刚才写的数据，因为主库与从库的数据同步存在一定的延时。
	 * 通过本参数可以配置主从延时的时间，默认为0.8秒(800毫秒)，即写操作完成后的0.8秒内，所有sql都直接操作主库，0.8秒后从库同步完成了主库的写操作，读操作才会使用从库。
	 * 即写操作后的0.8秒内，主从分离失效，0.8秒后才会进行主从分离。
	 * 本参数只对同一个链接生效，如果要实现跨链接的防主从延时，需要上层应用层来解决，比如：写操作后，用户立刻刷新页面，那么可以通过Get参数或者Session变量等来强制指定读主库。
	 */
	public $slaveSyncDelay = 0.8;
	/**
	 * @var float 最近一次更新主库的时间，单位是秒，该参数与$slaveSyncDelay一起来判断使用主库还是从库，使用updateMasterTime()成员函数更新本成员变量。
	 * 默认是-1000秒，之所以取负值，就是认为数据库链接创建之前的1000秒，数据库的主从同步已经完成。
	 */
	private $_updateMasterTime = -1000;
	/**
	 * @var integer number of seconds that table metadata can remain valid in cache.
	 * Use 0 or negative value to indicate not caching schema.
	 * If greater than 0 and the primary cache is enabled, the table metadata will be cached.
	 * @see schemaCachingExclude
	 */
	public $schemaCachingDuration=0;
	/**
	 * @var array list of tables whose metadata should NOT be cached. Defaults to empty array.
	 * @see schemaCachingDuration
	 */
	public $schemaCachingExclude=array();
	/**
	 * @var string the ID of the cache application component that is used to cache the table metadata.
	 * Defaults to 'cache' which refers to the primary cache application component.
	 * Set this property to false if you want to disable caching table metadata.
	 */
	public $schemaCacheID='cache';
	/**
	 * @var integer number of seconds that query results can remain valid in cache.
	 * Use 0 or negative value to indicate not caching query results (the default behavior).
	 *
	 * In order to enable query caching, this property must be a positive
	 * integer and {@link queryCacheID} must point to a valid cache component ID.
	 *
	 * The method {@link cache()} is provided as a convenient way of setting this property
	 * and {@link queryCachingDependency} on the fly.
	 *
	 * @see cache
	 * @see queryCachingDependency
	 * @see queryCacheID
	 * @since 1.0
	 */
	public $queryCachingDuration=0;
	/**
	 * @var CCacheDependency the dependency that will be used when saving query results into cache.
	 * @see queryCachingDuration
	 * @since 1.0
	 */
	public $queryCachingDependency;
	/**
	 * @var integer the number of SQL statements that need to be cached next.
	 * If this is 0, then even if query caching is enabled, no query will be cached.
	 * Note that each time after executing a SQL statement (whether executed on DB server or fetched from
	 * query cache), this property will be reduced by 1 until 0.
	 * @since 1.0
	 */
	public $queryCachingCount=0;
	/**
	 * @var string the ID of the cache application component that is used for query caching.
	 * Defaults to 'cache' which refers to the primary cache application component.
	 * Set this property to false if you want to disable query caching.
	 * @since 1.0
	 */
	public $queryCacheID='cache';
	/**
	 * @var string the charset used for database connection. The property is only used
	 * for MySQL and PostgreSQL databases. Defaults to null, meaning using default charset
	 * as specified by the database.
	 *
	 * Note that if you're using GBK or BIG5 then it's highly recommended to
	 * update to PHP 5.3.6+ and to specify charset via DSN like
	 * 'mysql:dbname=mydatabase;host=127.0.0.1;charset=GBK;'.
	 */
	public $charset;
	/**
	 * @var boolean whether to turn on prepare emulation. Defaults to false, meaning PDO
	 * will use the native prepare support if available. For some databases (such as MySQL),
	 * this may need to be set true so that PDO can emulate the prepare support to bypass
	 * the buggy native prepare support. Note, this property is only effective for PHP 5.1.3 or above.
	 * The default value is null, which will not change the ATTR_EMULATE_PREPARES value of PDO.
	 */
	public $emulatePrepare = true;
	/**
	 * @var boolean whether to log the values that are bound to a prepare SQL statement.
	 * Defaults to false. During development, you may consider setting this property to true
	 * so that parameter values bound to SQL statements are logged for debugging purpose.
	 * You should be aware that logging parameter values could be expensive and have significant
	 * impact on the performance of your application.
	 */
	public $enableParamLogging=false;
	/**
	 * @var boolean whether to enable profiling the SQL statements being executed.
	 * Defaults to false. This should be mainly enabled and used during development
	 * to find out the bottleneck of SQL executions.
	 */
	public $enableProfiling=false;
	/**
	 * @var string the default prefix for table names. Defaults to null, meaning no table prefix.
	 * By setting this property, any token like '{{tableName}}' in {@link CDbCommand::text} will
	 * be replaced by 'prefixTableName', where 'prefix' refers to this property value.
	 * @since 1.0
	 */
	public $tablePrefix;
	/**
	 * @var array list of SQL statements that should be executed right after the DB connection is established.
	 * @since 1.0
	 */
	public $initSQLs;
    /**
    *@var enable report the db performance
    */
    public $enablePerformReport=false;
	/**
	 * @var array mapping between PDO driver and schema class name.
	 * A schema class can be specified using path alias.
	 * @since 1.0
	 */
	public $driverMap=array(
		'mysqli'=>'CMysqlSchema',   // MySQL
		'mysql'=>'CMysqlSchema',    // MySQL
	);
	
	public $nameService="nameService" ;

	private $_attributes=array();
	private $_transaction;
	private $_schema;

	/**
	 * Constructor.
	 * Note, the DB connection is not established when this connection
	 * instance is created. Set {@link setActive active} property to true
	 * to establish the connection.
	 * @param string $dsn The Data Source Name, or DSN, contains the information required to connect to the database.
	 * @param string $username The user name for the DSN string.
	 * @param string $password The password for the DSN string.
	 * @see http://www.php.net/manual/en/function.PDO-construct.php
	 */
	public function __construct($dsn='',$username='',$password='',$slaveConfigs=array())
	{
		$this->connectionString=$dsn;
		$this->username=$username;
		$this->password=$password;
		$this->slaveConfigs=$slaveConfigs;
	}

	/**
	 * Close the connection when serializing.
	 * @return array
	 */
	public function __sleep()
	{
		$this->close();
		return array_keys(get_object_vars($this));
	}

	/**
	 * Returns a list of available PDO drivers.
	 * @return array list of available PDO drivers
	 * @see http://www.php.net/manual/en/function.PDO-getAvailableDrivers.php
	 */
	public static function getAvailableDrivers()
	{
		return PDO::getAvailableDrivers();
	}

	/**
	 * Initializes the component.
	 * This method is required by {@link IApplicationComponent} and is invoked by application
	 * when the CDbConnection is used as an application component.
	 * If you override this method, make sure to call the parent implementation
	 * so that the component can be marked as initialized.
	 */
	public function init()
	{
		//查询名字服务，获取host和port
		if($this->nameServiceKeyMaster)
		{
			if($this->getNS()->getHostByKey($this->nameServiceKeyMaster, $ip, $port)==false)
				throw new CException("NameServer fail, key:{$this->nameServiceKeyMaster}");
            $this->connectionString = str_replace(array('{{host}}', '{{port}}'), array($ip, $port), $this->connectionString);
		}
		if(is_array($this->nameServiceKeysSlave) && count($this->nameServiceKeysSlave))
		{
			foreach($this->nameServiceKeysSlave as $index=>$nameServiceKey)
			{
				if($this->getNS()->getHostByKey($nameServiceKey, $ip, $port)==false)
					throw new CException("NameServer fail, key:$nameServiceKey");
				if(isset($this->slaveConfigs[$index]['connectionString']))
					$this->slaveConfigs[$index]['connectionString'] = str_replace(array('{{host}}', '{{port}}'), array($ip, $port), $this->slaveConfigs[$index]['connectionString']);
				
			}
		}
		
		//合并数据库配置
		$this->allConfigs['master'] = array('connectionString'=>$this->connectionString, 'username'=>$this->username, 'password'=>$this->password ,'ns'=>$this->nameServiceKeyMaster);
		$this->_pdos['master'] = null;
		foreach($this->slaveConfigs as $key=>$config)
		{
			$dbId = 'slave'.($key+1);
			$this->allConfigs[$dbId] = array('connectionString'=>$config['connectionString'], 'username'=>$config['username'], 'password'=>$config['password'], 'ns'=>$this->nameServiceKeysSlave[$key]);
			$this->_pdos[$dbId] = null;
		}
		//修改重试间隔和主从延时的时间单位，秒=>微秒(一秒等于一百万微秒)
		$this->retryInterval *= 1000000;
		$this->slaveSyncDelay *= 1000000;
		
		parent::init();
	}

	/**
	 * 关闭一个或者全部数据库链接
	 */
	public function close($dbId=null)
	{
		Mod::trace('Closing DB connection','system.db.CDbConnection');
		
		if($dbId)
		{
			unset($this->_pdos[$dbId]);
			$this->_pdos[$dbId] = null;
		}
		else
		{
			foreach($this->_pdos as $dbId=>$pdo)
			{
				if($pdo!==null)
				{
					unset($pdo);
					$this->_pdos[$dbId] = null;
				}
			}
		}
		$this->_schema=null;
	}

	/**
	 * 设置主库更新时间
	 */
	public function updateMasterTime()
	{
		$this->_updateMasterTime = microtime(true);
	}

	/**
	 * CDbConnection给每个DB分配一个唯一的id，即成员变量$allConfigs数组的key，主库是'master'，从库1是'slave1'，从库2是'slave2'，从库3是'slave3'。
	 * 通过$isRead参数，来获取读操作或者写操作的数据库列表。
	 * 如果是写操作，则返回array('master')，写操作只能写主库；
	 * 如果是读操作，则将从库顺序打乱，然后加上主库，比如：array('slave2','slave1','slave3','master')，读操作先随机的读从库，如果所有的从库都失败了，再读主库。
	 * @param bool $isRead 对数据库进行读操作还在写操作
	 * @return array 数据库id列表
	 */
	public function getDbIds($isRead=false)
	{
		//计算从库数量
		$slaveCount = null;
		if($slaveCount === null)
			$slaveCount = count($this->slaveConfigs);
		/**
		 * 满足这些情况才使用从库：
		 * 1、存在从库；2、没有指定使用主库；3、没有执行事务；4、主从同步完成
		 */
		if($isRead && $slaveCount && !$this->forceMaster && $this->getCurrentTransaction()===null && (microtime(true)-$this->_updateMasterTime)>=$this->slaveSyncDelay)
		{
			$dbIds = array();
			for($i=1; $i<=$slaveCount; ++$i)
				$dbIds[] = 'slave'.$i;
			shuffle($dbIds);	//洗牌，打乱顺序
			$dbIds[] = 'master';
			return $dbIds;
		}
		else
			return array('master');
	}

	/**
	 * 根据dbId获取数据库链接
	 * @param string $dbId 每个数据库的唯一id，参考getDbIds成员函数
	 * @return PDO the PDO instance
	 */
	public function getPdoInstance($dbId)
	{
		if($this->_pdos[$dbId]===null)
		{
			if(empty($this->allConfigs[$dbId]['connectionString']))
				throw new CDbException(Mod::t('mod','CDbConnection.connectionString cannot be empty.'));
			try
			{
				Mod::trace("Opening DB:$dbId connection",'system.db.CDbConnection');
				$this->_pdos[$dbId] = new PDO($this->allConfigs[$dbId]['connectionString'], $this->allConfigs[$dbId]['username'], $this->allConfigs[$dbId]['password'], $this->_attributes);
				$this->initConnection($this->_pdos[$dbId]);
			}
			catch(PDOException $e)
			{
				Mod::log($e->getMessage(),CLogger::LEVEL_ERROR,'exception.CDbException');
				throw new CDbException(Mod::t('mod','CDbConnection failed to open the DB connection.'),(int)$e->getCode(),$e->errorInfo);
			}
		}
		return $this->_curPdo = $this->_pdos[$dbId];
	}

	/**
	 * Initializes the open db connection.
	 * This method is invoked right after the db connection is established.
	 * @param PDO $pdo the PDO instance
	 */
	protected function initConnection($pdo)
	{
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		if($this->emulatePrepare!==null && constant('PDO::ATTR_EMULATE_PREPARES'))
			$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,$this->emulatePrepare);
		if($this->charset!==null)
			$pdo->exec('SET NAMES '.$pdo->quote($this->charset));
		if($this->initSQLs!==null)
		{
			foreach($this->initSQLs as $sql)
				$pdo->exec($sql);
		}
	}

	/**
	 * Creates a command for execution.
	 * @param mixed $query the DB query to be executed. This can be either a string representing a SQL statement.
	 * @return CDbCommand the DB command
	 */
	public function createCommand($query=null)
	{
		return new CDbCommand($this,$query);
	}

	/**
	 * Returns the currently active transaction.
	 * @return CDbTransaction the currently active transaction. Null if no active transaction.
	 */
	public function getCurrentTransaction()
	{
		if($this->_transaction!==null)
		{
			if($this->_transaction->getActive())
				return $this->_transaction;
		}
		return null;
	}

	/**
	 * Starts a transaction.
	 * @return CDbTransaction the transaction initiated
	 */
	public function beginTransaction()
	{
		Mod::trace('Starting transaction','system.db.CDbConnection');
		for($retryCount=0; $retryCount<=$this->retryCount; ++$retryCount)
		{
			try
			{
				$this->getPdoInstance('master')->beginTransaction();
				return $this->_transaction=new CDbTransaction($this);
			}
			catch(Exception $e)
			{
				//说明这个数据库链接坏了，关闭数据库链接，下次重新创建链接
				$this->close('master');
				
				$errorInfo = $e instanceof PDOException ? $e->errorInfo : null;
				$message = $e->getMessage();
				//记录失败日志，需要区分是否为重试
				if($retryCount)
					Mod::log("beginTransaction fail(errorInfo:$errorInfo, message:$message, retryCount:$retryCount)",CLogger::LEVEL_ERROR,'system.db.CDbConnection');
				else
					Mod::log("beginTransaction fail(errorInfo:$errorInfo, message:$message)",CLogger::LEVEL_ERROR,'system.db.CDbConnection');
				
				//判断是否需要sleep
				if($this->retryCount && $this->retryInterval && $retryCount<$this->retryCount)
					usleep($this->retryInterval);
			}
		}
		//开启事务失败，抛异常。
		throw new CDbException("CDbConnection failed to beginTransaction(message:$message)",(int)$e->getCode(),$errorInfo);
	}

	/**
	 * Returns the database schema for the current connection
	 * @return CDbSchema the database schema for the current connection
	 */
	public function getSchema()
	{
		if($this->_schema!==null)
			return $this->_schema;
		else
			return $this->_schema=Mod::createComponent('CMysqlSchema', $this);
	}

	/**
	 * Returns the SQL command builder for the current DB connection.
	 * @return CDbCommandBuilder the command builder
	 */
	public function getCommandBuilder()
	{
		return $this->getSchema()->getCommandBuilder();
	}

	/**
	 * Returns the ID of the last inserted row or sequence value.
	 * @param string $sequenceName name of the sequence object (required by some DBMS)
	 * @return string the row ID of the last row inserted, or the last value retrieved from the sequence object
	 * @see http://www.php.net/manual/en/function.PDO-lastInsertId.php
	 */
	public function getLastInsertID($sequenceName='')
	{
		return $this->getPdoInstance('master')->lastInsertId($sequenceName);
	}

	/**
	 * Quotes a string value for use in a query.
	 * @param string $str string to be quoted
	 * @return string the properly quoted string
	 * @see http://www.php.net/manual/en/function.PDO-quote.php
	 */
	public function quoteValue($str)
	{
		if(is_int($str) || is_float($str))
			return $str;
		
		if($this->_curPdo===null)
			$this->getPdoInstance('master');
		return $this->_curPdo->quote($str);
	}

	/**
	 * Quotes a table name for use in a query.
	 * If the table name contains schema prefix, the prefix will also be properly quoted.
	 * @param string $name table name
	 * @return string the properly quoted table name
	 */
	public function quoteTableName($name)
	{
		return $this->getSchema()->quoteTableName($name);
	}

	/**
	 * Quotes a column name for use in a query.
	 * If the column name contains prefix, the prefix will also be properly quoted.
	 * @param string $name column name
	 * @return string the properly quoted column name
	 */
	public function quoteColumnName($name)
	{
		return $this->getSchema()->quoteColumnName($name);
	}

	/**
	 * Determines the PDO type for the specified PHP type.
	 * @param string $type The PHP type (obtained by gettype() call).
	 * @return integer the corresponding PDO type
	 */
	public function getPdoType($type)
	{
		static $map=array
		(
			'boolean'=>PDO::PARAM_BOOL,
			'integer'=>PDO::PARAM_INT,
			'string'=>PDO::PARAM_STR,
			'NULL'=>PDO::PARAM_NULL,
		);
		return isset($map[$type]) ? $map[$type] : PDO::PARAM_STR;
	}

	/**
	 * Returns the case of the column names
	 * @return mixed the case of the column names
	 * @see http://www.php.net/manual/en/pdo.setattribute.php
	 */
	public function getColumnCase()
	{
		return $this->getAttribute(PDO::ATTR_CASE);
	}

	/**
	 * Sets the case of the column names.
	 * @param mixed $value the case of the column names
	 * @see http://www.php.net/manual/en/pdo.setattribute.php
	 */
	public function setColumnCase($value)
	{
		$this->setAttribute(PDO::ATTR_CASE,$value);
	}

	/**
	 * Returns how the null and empty strings are converted.
	 * @return mixed how the null and empty strings are converted
	 * @see http://www.php.net/manual/en/pdo.setattribute.php
	 */
	public function getNullConversion()
	{
		return $this->getAttribute(PDO::ATTR_ORACLE_NULLS);
	}

	/**
	 * Sets how the null and empty strings are converted.
	 * @param mixed $value how the null and empty strings are converted
	 * @see http://www.php.net/manual/en/pdo.setattribute.php
	 */
	public function setNullConversion($value)
	{
		$this->setAttribute(PDO::ATTR_ORACLE_NULLS,$value);
	}

	/**
	 * Returns whether creating or updating a DB record will be automatically committed.
	 * Some DBMS (such as sqlite) may not support this feature.
	 * @return boolean whether creating or updating a DB record will be automatically committed.
	 */
	public function getAutoCommit()
	{
		return $this->getAttribute(PDO::ATTR_AUTOCOMMIT);
	}

	/**
	 * Sets whether creating or updating a DB record will be automatically committed.
	 * Some DBMS (such as sqlite) may not support this feature.
	 * @param boolean $value whether creating or updating a DB record will be automatically committed.
	 */
	public function setAutoCommit($value)
	{
		$this->setAttribute(PDO::ATTR_AUTOCOMMIT,$value);
	}

	/**
	 * Returns whether the connection is persistent or not.
	 * Some DBMS (such as sqlite) may not support this feature.
	 * @return boolean whether the connection is persistent or not
	 */
	public function getPersistent()
	{
		return $this->getAttribute(PDO::ATTR_PERSISTENT);
	}

	/**
	 * Sets whether the connection is persistent or not.
	 * Some DBMS (such as sqlite) may not support this feature.
	 * @param boolean $value whether the connection is persistent or not
	 */
	public function setPersistent($value)
	{
		return $this->setAttribute(PDO::ATTR_PERSISTENT,$value);
	}

	/**
	 * Returns the name of the DB driver
	 * @return string name of the DB driver
	 */
	public function getDriverName()
	{
		if(($pos=strpos($this->connectionString, ':'))!==false)
			return strtolower(substr($this->connectionString, 0, $pos));
	}

	/**
	 * Returns the version information of the DB driver.
	 * @return string the version information of the DB driver
	 */
	public function getClientVersion()
	{
		return $this->getAttribute(PDO::ATTR_CLIENT_VERSION);
	}

	/**
	 * Returns the status of the connection.
	 * Some DBMS (such as sqlite) may not support this feature.
	 * @return string the status of the connection
	 */
	public function getConnectionStatus()
	{
		return $this->getAttribute(PDO::ATTR_CONNECTION_STATUS);
	}

	/**
	 * Returns whether the connection performs data prefetching.
	 * @return boolean whether the connection performs data prefetching
	 */
	public function getPrefetch()
	{
		return $this->getAttribute(PDO::ATTR_PREFETCH);
	}

	/**
	 * Returns the information of DBMS server.
	 * @return string the information of DBMS server
	 */
	public function getServerInfo()
	{
		return $this->getAttribute(PDO::ATTR_SERVER_INFO);
	}

	/**
	 * Returns the version information of DBMS server.
	 * @return string the version information of DBMS server
	 */
	public function getServerVersion()
	{
		return $this->getAttribute(PDO::ATTR_SERVER_VERSION);
	}

	/**
	 * Returns the timeout settings for the connection.
	 * @return integer timeout settings for the connection
	 */
	public function getTimeout()
	{
		return $this->getAttribute(PDO::ATTR_TIMEOUT);
	}

	/**
	 * Obtains a specific DB connection attribute information.
	 * @param integer $name the attribute to be queried
	 * @return mixed the corresponding attribute information
	 * @see http://www.php.net/manual/en/function.PDO-getAttribute.php
	 */
	public function getAttribute($name)
	{
		if($this->_curPdo===null)
			$this->getPdoInstance('master');
		
		return $this->_curPdo->getAttribute($name);
	}

	/**
	 * Sets an attribute on the database connection.
	 * @param integer $name the attribute to be set
	 * @param mixed $value the attribute value
	 * @see http://www.php.net/manual/en/function.PDO-setAttribute.php
	 */
	public function setAttribute($name,$value)
	{
		if($this->_curPdo===null)
			$this->getPdoInstance('master');
		
		$this->_curPdo->setAttribute($name,$value);
		$this->_attributes[$name]=$value;
	}

	/**
	 * Returns the attributes that are previously explicitly set for the DB connection.
	 * @return array attributes (name=>value) that are previously explicitly set for the DB connection.
	 * @see setAttributes
	 * @since 1.0
	 */
	public function getAttributes()
	{
		return $this->_attributes;
	}

	/**
	 * Sets a set of attributes on the database connection.
	 * @param array $values attributes (name=>value) to be set.
	 * @see setAttribute
	 * @since 1.0
	 */
	public function setAttributes($values)
	{
		foreach($values as $name=>$value)
			$this->_attributes[$name]=$value;
	}

	/**
	 * Sets the parameters about query caching.
	 * This method can be used to enable or disable query caching.
	 * By setting the $duration parameter to be 0, the query caching will be disabled.
	 * Otherwise, query results of the new SQL statements executed next will be saved in cache
	 * and remain valid for the specified duration.
	 * If the same query is executed again, the result may be fetched from cache directly
	 * without actually executing the SQL statement.
	 * @param integer $duration the number of seconds that query results may remain valid in cache.
	 * If this is 0, the caching will be disabled.
	 * @param CCacheDependency $dependency the dependency that will be used when saving the query results into cache.
	 * @param integer $queryCount number of SQL queries that need to be cached after calling this method. Defaults to 1,
	 * meaning that the next SQL query will be cached.
	 * @return CDbConnection the connection instance itself.
	 * @since 1.0
	 */
	public function cache($duration, $dependency=null, $queryCount=1)
	{
		$this->queryCachingDuration=$duration;
		$this->queryCachingDependency=$dependency;
		$this->queryCachingCount=$queryCount;
		return $this;
	}

	/**
	 * Returns the statistical results of SQL executions.
	 * The results returned include the number of SQL statements executed and
	 * the total time spent.
	 * In order to use this method, {@link enableProfiling} has to be set true.
	 * @return array the first element indicates the number of SQL statements executed,
	 * and the second element the total time spent in SQL execution.
	 */
	public function getStats()
	{
		$logger=Mod::getLogger();
		$timings=$logger->getProfilingResults(null,'system.db.CDbCommand.query');
		$count=count($timings);
		$time=array_sum($timings);
		$timings=$logger->getProfilingResults(null,'system.db.CDbCommand.execute');
		$count+=count($timings);
		$time+=array_sum($timings);
		return array($count,$time);
	}
	
	public function getNS()
	{
		if(is_string($this->nameService))
		{
			return Mod::app()->getComponent($this->nameService);
		}
		return $this->nameService;
	}
	
	public function getConConfig($dbid)
	{
		return $this->allConfigs[$dbid];
	}
}
