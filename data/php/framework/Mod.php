<?php
/**
 * Mod is a helper class serving common framework functionalities.
 */

error_reporting(E_ALL ^ E_NOTICE);

/**
 * Gets the application start timestamp.
 */
defined('MOD_BEGIN_TIME') or define('MOD_BEGIN_TIME',microtime(true));
/**
 * This constant defines whether the application should be in debug mode or not. Defaults to false.
 */
//defined('MOD_DEBUG') or define('MOD_DEBUG',false);
/**
 * This constant defines how much call stack information (file name and line number) should be logged by Mod::trace().
 * Defaults to 0, meaning no backtrace information. If it is greater than 0,
 * at most that number of call stacks will be logged. Note, only user application call stacks are considered.
 */
defined('MOD_TRACE_LEVEL') or define('MOD_TRACE_LEVEL',0);
/**
 * This constant defines whether exception handling should be enabled. Defaults to true.
 */
defined('MOD_ENABLE_EXCEPTION_HANDLER') or define('MOD_ENABLE_EXCEPTION_HANDLER',true);
/**
 * This constant defines whether error handling should be enabled. Defaults to true.
 */
defined('MOD_ENABLE_ERROR_HANDLER') or define('MOD_ENABLE_ERROR_HANDLER',true);
/**
 * Defines the Mod framework installation path.
 */
defined('MOD_PATH') or define('MOD_PATH',dirname(__FILE__));
/**
 * Defines the Zii library installation path.
 */
defined('MOD_ZII_PATH') or define('MOD_ZII_PATH',MOD_PATH.DIRECTORY_SEPARATOR.'exts/zii');

/**
 * Mod is a helper class serving common framework functionalities.
 *
 * @author 
 * @version 
 * @package system
 * @since 1.0
 */
class Mod
{
	/**
	 * @var array class map used by the Mod autoloading mechanism.
	 * The array keys are the class names and the array values are the corresponding class file paths.
	 * @since 1.0
	 */
	public static $classMap=array();
	/**
	 * @var boolean whether to rely on PHP include path to autoload class files. Defaults to true.
	 * You may set this to be false if your hosting environment doesn't allow changing PHP include path,
	 * or if you want to append additional autoloaders to the default Mod autoloader.
	 * @since 1.0
	 */
	public static $enableIncludePath=true;

	private static $_aliases=array('system'=>MOD_PATH, 'zii'=>MOD_ZII_PATH); // alias => path
	private static $_imports=array();					// alias => class name or directory
	private static $_includePaths;						// list of include paths
	private static $_app;
	private static $_logger;

	/**
	 * @return string the version of Mod framework
	 */
	public static function getVersion()
	{
		return '2.4.5';
	}
	
	/*
	 *每天上报一次当前框架的版本
	 */
	public static function reportVersion(){
        $filename = '/tmp/framework_version';
		if( !@file_exists($filename) || ( @gmdate("Ymd", filemtime($filename)) != @gmdate("Ymd") ))
		{
			$obj = New CPhpPerfReporter();
			$obj->beginPerfReport('phpplatform','',0,true);
			if($obj->getLocalIp() == 'unknow'){
				$localip = $obj->genLocalIp();
				$obj->setLocalIp($localip);
			}
			@file_put_contents($filename, self::getVersion());
			$obj->addParam('framework_version',self::getVersion());
			$obj->endPerfReport(0);
		}
	}

	/**
	 * Creates a Web application instance.
	 * @param mixed $config application configuration.
	 * If a string, it is treated as the path of the file that contains the configuration;
	 * If an array, it is the actual configuration information.
	 * Please make sure you specify the {@link CApplication::basePath basePath} property in the configuration,
	 * which should point to the directory containing all application logic, template and data.
	 * If not, the directory will be defaulted to 'protected'.
	 * @return CWebApplication
	 */
	public static function createWebApplication($config=null)
	{
		return self::createApplication('CWebApplication',$config);
	}

	/**
	 * Creates a console application instance.
	 * @param mixed $config application configuration.
	 * If a string, it is treated as the path of the file that contains the configuration;
	 * If an array, it is the actual configuration information.
	 * Please make sure you specify the {@link CApplication::basePath basePath} property in the configuration,
	 * which should point to the directory containing all application logic, template and data.
	 * If not, the directory will be defaulted to 'protected'.
	 * @return CConsoleApplication
	 */
	public static function createConsoleApplication($config=null)
	{
		return self::createApplication('CConsoleApplication',$config);
	}

	/**
	 * Creates an application of the specified class.
	 * @param string $class the application class name
	 * @param mixed $config application configuration. This parameter will be passed as the parameter
	 * to the constructor of the application class.
	 * @return mixed the application instance
	 */
	public static function createApplication($class,$config=null)
	{
		return new $class($config);
	}

	/**
	 * Returns the application singleton, null if the singleton has not been created yet.
	 * @return CApplication the application singleton, null if the singleton has not been created yet.
	 */
	public static function app()
	{
		return self::$_app;
	}

	/**
	 * Stores the application instance in the class static member.
	 * This method helps implement a singleton pattern for CApplication.
	 * Repeated invocation of this method or the CApplication constructor
	 * will cause the throw of an exception.
	 * To retrieve the application instance, use {@link app()}.
	 * @param CApplication $app the application instance. If this is null, the existing
	 * application singleton will be removed.
	 * @throws CException if multiple application instances are registered.
	 */
	public static function setApplication($app)
	{
		if(self::$_app===null || $app===null)
			self::$_app=$app;
		else
			throw new CException(Mod::t('mod','Mod application can only be created once.'));
	}

	/**
	 * @return string the path of the framework
	 */
	public static function getFrameworkPath()
	{
		return MOD_PATH;
	}

	/**
	 * Creates an object and initializes it based on the given configuration.
	 *
	 * The specified configuration can be either a string or an array.
	 * If the former, the string is treated as the object type which can
	 * be either the class name or {@link Mod::getPathOfAlias class path alias}.
	 * If the latter, the 'class' element is treated as the object type,
	 * and the rest name-value pairs in the array are used to initialize
	 * the corresponding object properties.
	 *
	 * Any additional parameters passed to this method will be
	 * passed to the constructor of the object being created.
	 *
	 * @param mixed $config the configuration. It can be either a string or an array.
	 * @return mixed the created object
	 * @throws CException if the configuration does not have a 'class' element.
	 */
	public static function createComponent($config)
	{
		if(is_string($config))
		{
			$type=$config;
			$config=array();
		}
		else if(isset($config['class']))
		{
			$type=$config['class'];
			unset($config['class']);
		}
		else
			throw new CException(Mod::t('mod','Object configuration must be an array containing a "class" element.'));

		if(!class_exists($type,false))
			$type=Mod::import($type,true);

		if(($n=func_num_args())>1)
		{
			$args=func_get_args();
			if($n===2)
				$object=new $type($args[1]);
			else if($n===3)
				$object=new $type($args[1],$args[2]);
			else if($n===4)
				$object=new $type($args[1],$args[2],$args[3]);
			else
			{
				unset($args[0]);
				$class=new ReflectionClass($type);
				// Note: ReflectionClass::newInstanceArgs() is available for PHP 5.1.3+
				// $object=$class->newInstanceArgs($args);
				$object=call_user_func_array(array($class,'newInstance'),$args);
			}
		}
		else
			$object=new $type;

		foreach($config as $key=>$value)
			$object->$key=$value;

		return $object;
	}

	/**
	 * Imports a class or a directory.
	 *
	 * Importing a class is like including the corresponding class file.
	 * The main difference is that importing a class is much lighter because it only
	 * includes the class file when the class is referenced the first time.
	 *
	 * Importing a directory is equivalent to adding a directory into the PHP include path.
	 * If multiple directories are imported, the directories imported later will take
	 * precedence in class file searching (i.e., they are added to the front of the PHP include path).
	 *
	 * Path aliases are used to import a class or directory. For example,
	 * <ul>
	 *   <li><code>application.components.GoogleMap</code>: import the <code>GoogleMap</code> class.</li>
	 *   <li><code>application.components.*</code>: import the <code>components</code> directory.</li>
	 * </ul>
	 *
	 * The same path alias can be imported multiple times, but only the first time is effective.
	 * Importing a directory does not import any of its subdirectories.
	 *
	 * Starting from version 1.1.5, this method can also be used to import a class in namespace format
	 * (available for PHP 5.3 or above only). It is similar to importing a class in path alias format,
	 * except that the dot separator is replaced by the backslash separator. For example, importing
	 * <code>application\components\GoogleMap</code> is similar to importing <code>application.components.GoogleMap</code>.
	 * The difference is that the former class is using qualified name, while the latter unqualified.
	 *
	 * Note, importing a class in namespace format requires that the namespace is corresponding to
	 * a valid path alias if we replace the backslash characters with dot characters.
	 * For example, the namespace <code>application\components</code> must correspond to a valid
	 * path alias <code>application.components</code>.
	 *
	 * @param string $alias path alias to be imported
	 * @param boolean $forceInclude whether to include the class file immediately. If false, the class file
	 * will be included only when the class is being used. This parameter is used only when
	 * the path alias refers to a class.
	 * @return string the class name or the directory that this alias refers to
	 * @throws CException if the alias is invalid
	 */
	public static function import($alias,$forceInclude=false)
	{
		if(isset(self::$_imports[$alias]))  // previously imported
			return self::$_imports[$alias];

		if(class_exists($alias,false) || interface_exists($alias,false))
			return self::$_imports[$alias]=$alias;

		if(($pos=strrpos($alias,'.'))===false)  // a simple class name
		{
			if($forceInclude && self::autoload($alias))
				self::$_imports[$alias]=$alias;
			return $alias;
		}

		$className=(string)substr($alias,$pos+1);
		$isClass=$className!=='*';

		if($isClass && (class_exists($className,false) || interface_exists($className,false)))
			return self::$_imports[$alias]=$className;

		if(($path=self::getPathOfAlias($alias))!==false)
		{
			if($isClass)
			{
				if($forceInclude)
				{
					if(is_file($path.'.php'))
						require($path.'.php');
					else
						throw new CException(Mod::t('mod','Alias "{alias}" is invalid. Make sure it points to an existing PHP file.',array('{alias}'=>$alias)));
					self::$_imports[$alias]=$className;
				}
				else
					self::$classMap[$className]=$path.'.php';
				return $className;
			}
			else  // a directory
			{
				if(self::$_includePaths===null)
				{
					self::$_includePaths=array_unique(explode(PATH_SEPARATOR,get_include_path()));
					if(($pos=array_search('.',self::$_includePaths,true))!==false)
						unset(self::$_includePaths[$pos]);
				}

				array_unshift(self::$_includePaths,$path);

				if(self::$enableIncludePath && set_include_path('.'.PATH_SEPARATOR.implode(PATH_SEPARATOR,self::$_includePaths))===false)
					self::$enableIncludePath=false;

				return self::$_imports[$alias]=$path;
			}
		}
		else
			throw new CException(Mod::t('mod','Alias "{alias}" is invalid. Make sure it points to an existing directory or file.',
				array('{alias}'=>$alias)));
	}

	/**
	 * Translates an alias into a file path.
	 * Note, this method does not ensure the existence of the resulting file path.
	 * It only checks if the root alias is valid or not.
	 * @param string $alias alias (e.g. system.web.CController)
	 * @return mixed file path corresponding to the alias, false if the alias is invalid.
	 */
	public static function getPathOfAlias($alias)
	{
		if(isset(self::$_aliases[$alias]))
			return self::$_aliases[$alias];
		else if(($pos=strpos($alias,'.'))!==false)
		{
			$rootAlias=substr($alias,0,$pos);
			if(isset(self::$_aliases[$rootAlias]))
				return self::$_aliases[$alias]=rtrim(self::$_aliases[$rootAlias].DIRECTORY_SEPARATOR.str_replace('.',DIRECTORY_SEPARATOR,substr($alias,$pos+1)),'*'.DIRECTORY_SEPARATOR);
			else if(self::$_app instanceof CWebApplication)
			{
				if(self::$_app->findModule($rootAlias)!==null)
					return self::getPathOfAlias($alias);
			}
		}
		return false;
	}

	/**
	 * Create a path alias.
	 * Note, this method neither checks the existence of the path nor normalizes the path.
	 * @param string $alias alias to the path
	 * @param string $path the path corresponding to the alias. If this is null, the corresponding
	 * path alias will be removed.
	 */
	public static function setPathOfAlias($alias,$path)
	{
		if(empty($path))
			unset(self::$_aliases[$alias]);
		else
			self::$_aliases[$alias]=rtrim($path,'\\/');
	}

	/**
	 * Class autoload loader.
	 * This method is provided to be invoked within an __autoload() magic method.
	 * @param string $className class name
	 * @return boolean whether the class has been loaded successfully
	 */
	public static function autoload($className)
	{
		// use include so that the error PHP file may appear
		if(isset(self::$classMap[$className]))
			include(self::$classMap[$className]);
		else if(isset(self::$coreClasses[$className]))
			include(MOD_PATH.self::$coreClasses[$className]);
		else
		{
			if(self::$enableIncludePath===true)
			{
				foreach(self::$_includePaths as $path)
				{
					$classFile=$path.DIRECTORY_SEPARATOR.$className.'.php';
					if(is_file($classFile))
					{
						include($classFile);
						break;
					}
					else if(strpos($className, '\\') && 'wx' == substr($className, 0, 2)){
						$subPath = substr($className, 0, strpos($className, '\\'));
					    $subPathArr = explode('_', $subPath);
					    unset($subPathArr[key($subPathArr)]);
					    
					    $subFilePath = '';
					    foreach($subPathArr as $lPath)
					    {
					    	$subFilePath .= $lPath ."/";
					    }
					    $filePath = str_replace($subPath ."\\", $subFilePath, $className.'.php');
						require_once($filePath);
					}
				}
			}
			else
			{
				$filePath = $className.'.php';
				if(strpos($className, '\\') && 'wx' == substr($className, 0, 2))
				{
					$subPath = substr($className, 0, strpos($className, '\\'));
				    $subPathArr = explode('_', $subPath);
				    unset($subPathArr[key($subPathArr)]);
				    
				    $subFilePath = '';
				    foreach($subPathArr as $lPath)
				    {
				    	$subFilePath .= $lPath ."/";
				    }
				    $filePath = str_replace($subPath ."\\", $subFilePath, $className.'.php');
				}
				include($filePath);
			}
			return class_exists($className,false) || interface_exists($className,false);
		}
		return true;
	}

	/**
	 * Writes a trace message.
	 * This method will only log a message when the application is in debug mode.
	 * @param string $msg message to be logged
	 * @param string $category category of the message
	 * @see log
	 */
	public static function trace($msg,$category='application')
	{
		if(MOD_DEBUG)
			self::log($msg,CLogger::LEVEL_TRACE,$category);
	}

	/**
	 * Logs a message.
	 * Messages logged by this method may be retrieved via {@link CLogger::getLogs}
	 * and may be recorded in different media, such as file, email, database, using
	 * {@link CLogRouter}.
	 * @param string/array $msg message to be logged
	 * @param string $level level of the message (e.g. 'trace', 'warning', 'error'). It is case-insensitive.
	 * @param string $category category of the message (e.g. 'system.web'). It is case-insensitive.
	 */
	public static function log($msg,$level=CLogger::LEVEL_INFO,$category='application')
	{
		if(self::$_logger===null)
			self::$_logger=new CLogger;
		if(MOD_DEBUG && MOD_TRACE_LEVEL>0 && $level!==CLogger::LEVEL_PROFILE)
		{
			$traces = debug_backtrace();
			$count = 0;
			$strTrace = '';
			foreach($traces as $trace)
			{
				if(isset($trace['file'],$trace['line']) && strpos($trace['file'],MOD_PATH)!==0)
				{
					$strTrace .= "\nin ".$trace['file'].' ('.$trace['line'].')';
					if(++$count>=MOD_TRACE_LEVEL)
						break;
				}
			}
			if(is_array($msg))
				$msg['trace'] = $strTrace;
			else
				$msg .= $strTrace;
		}
		
		$data = is_array($msg)?var_export($msg, true):$msg;
		self::$_logger->log($data,$level,$category);
	}

	/**
	 * Marks the begin of a code block for profiling.
	 * This has to be matched with a call to {@link endProfile()} with the same token.
	 * The begin- and end- calls must also be properly nested, e.g.,
	 * <pre>
	 * Mod::beginProfile('block1');
	 * Mod::beginProfile('block2');
	 * Mod::endProfile('block2');
	 * Mod::endProfile('block1');
	 * </pre>
	 * The following sequence is not valid:
	 * <pre>
	 * Mod::beginProfile('block1');
	 * Mod::beginProfile('block2');
	 * Mod::endProfile('block1');
	 * Mod::endProfile('block2');
	 * </pre>
	 * @param string $token token for the code block
	 * @param string $category the category of this log message
	 * @see endProfile
	 */
	public static function beginProfile($token,$category='application')
	{
		self::log('begin:'.$token,CLogger::LEVEL_PROFILE,$category);
	}

	/**
	 * Marks the end of a code block for profiling.
	 * This has to be matched with a previous call to {@link beginProfile()} with the same token.
	 * @param string $token token for the code block
	 * @param string $category the category of this log message
	 * @see beginProfile
	 */
	public static function endProfile($token,$category='application')
	{
		self::log('end:'.$token,CLogger::LEVEL_PROFILE,$category);
	}

	/**
	 * @return CLogger message logger
	 */
	public static function getLogger()
	{
		if(self::$_logger!==null)
			return self::$_logger;
		else
			return self::$_logger=new CLogger;
	}

	/**
	 * Sets the logger object.
	 * @param CLogger $logger the logger object.
	 * @since 1.0
	 */
	public static function setLogger($logger)
	{
		self::$_logger=$logger;
	}

	/**
	 * Returns a string that can be displayed on your Web page showing Powered-by-Mod information
	 * @return string a string that can be displayed on your Web page showing Powered-by-Mod information
	 */
	public static function powered()
	{
		return Mod::t('mod','Powered by mod.');
	}

	/**
	 * Translates a message to the specified language.
	 * This method supports choice format (see {@link CChoiceFormat}),
	 * i.e., the message returned will be chosen from a few candidates according to the given
	 * number value. This feature is mainly used to solve plural format issue in case
	 * a message has different plural forms in some languages.
	 * @param string $category message category. Please use only word letters. Note, category 'mod' is
	 * reserved for Mod framework core code use. See {@link CPhpMessageSource} for
	 * more interpretation about message category.
	 * @param string $message the original message
	 * @param array $params parameters to be applied to the message using <code>strtr</code>.
	 * The first parameter can be a number without key.
	 * And in this case, the method will call {@link CChoiceFormat::format} to choose
	 * an appropriate message translation.
	 * You can pass parameter for {@link CChoiceFormat::format}
	 * or plural forms format without wrapping it with array.
	 * @param string $source which message source application component to use.
	 * Defaults to null, meaning using 'coreMessages' for messages belonging to
	 * the 'mod' category and using 'messages' for the rest messages.
	 * @param string $language the target language. If null (default), the {@link CApplication::getLanguage application language} will be used.
	 * @return string the translated message
	 * @see CMessageSource
	 */
	public static function t($category,$message,$params=array(),$source=null,$language=null)
	{
		if(self::$_app!==null)
		{
			if($source===null)
				$source=($category==='mod'||$category==='zii'||$category==='common')?'coreMessages':'messages';
			if(($source=self::$_app->getComponent($source))!==null)
				$message=$source->translate($category,$message,$language);
		}
		if($params===array())
			return $message;
		if(!is_array($params))
			$params=array($params);
		if(isset($params[0])) // number choice
		{
			if(strpos($message,'|')!==false)
			{
				if(strpos($message,'#')===false)
				{
					$chunks=explode('|',$message);
					$expressions=self::$_app->getLocale($language)->getPluralRules();
					if($n=min(count($chunks),count($expressions)))
					{
						for($i=0;$i<$n;$i++)
							$chunks[$i]=$expressions[$i].'#'.$chunks[$i];

						$message=implode('|',$chunks);
					}
				}
				$message=CChoiceFormat::format($message,$params[0]);
			}
			if(!isset($params['{n}']))
				$params['{n}']=$params[0];
			unset($params[0]);
		}
		return $params!==array() ? strtr($message,$params) : $message;
	}

	/**
	 * Registers a new class autoloader.
	 * The new autoloader will be placed before {@link autoload} and after
	 * any other existing autoloaders.
	 * @param callback $callback a valid PHP callback (function name or array($className,$methodName)).
	 * @param boolean $append whether to append the new autoloader after the default Mod autoloader.
	 */
	public static function registerAutoloader($callback, $append=false)
	{
		if($append)
		{
			self::$enableIncludePath=false;
			spl_autoload_register($callback);
		}
		else
		{
			spl_autoload_unregister(array('Mod','autoload'));
			spl_autoload_register($callback);
			spl_autoload_register(array('Mod','autoload'));
		}
	}
	/*
	 * 名字服务根据名字获取ip和port 
	 */
	public static function getNameHost($key, &$ip, &$port) {
		if(! function_exists('getHostByKey'))
		{
			require_once '/usr/local/zk_agent/names/nameapi.php';
		}
		
		$host = new ZkHost();
		$ret = getHostByKey($key, $host);
		if($ret == 0)
		{
			$ip = $host->ip;
			$port = $host->port;
		}
		return $ret;
	}
	
	/*
	 * 名字服务获取字典内容
	 */
	public static function getDictValue($key, &$value) {
		if(! function_exists('getValueByKey'))
		{
			require_once '/usr/local/zk_agent/names/nameapi.php';
		}
		
		return getValueByKey($key, $value);
	}
	
	/**
	 * @var array class map for core Mod classes.
	 * NOTE, DO NOT MODIFY THIS ARRAY MANUALLY. IF YOU CHANGE OR ADD SOME CORE CLASSES,
	 * PLEASE RUN 'build autoload' COMMAND TO UPDATE THIS ARRAY.
	 */
	public static $coreClasses=array(
		'CApplication'=>'/core/CApplication.php',
		'CApplicationComponent'=>'/core/CApplicationComponent.php',
		'CBehavior'=>'/core/CBehavior.php',
		'CComponent'=>'/core/CComponent.php',
		'CErrorEvent'=>'/core/CErrorEvent.php',
		'CErrorHandler'=>'/core/CErrorHandler.php',
		'CException'=>'/core/CException.php',
		'CExceptionEvent'=>'/core/CExceptionEvent.php',
		'CHttpException'=>'/core/CHttpException.php',
		'CModule'=>'/core/CModule.php',
		'CSecurityManager'=>'/core/CSecurityManager.php',
		'CStatePersister'=>'/core/CStatePersister.php',
		'CAttributeCollection'=>'/core/collections/CAttributeCollection.php',
		'CConfiguration'=>'/core/collections/CConfiguration.php',
		'CList'=>'/core/collections/CList.php',
		'CListIterator'=>'/core/collections/CListIterator.php',
		'CMap'=>'/core/collections/CMap.php',
		'CMapIterator'=>'/core/collections/CMapIterator.php',
		'CQueue'=>'/core/collections/CQueue.php',
		'CQueueIterator'=>'/core/collections/CQueueIterator.php',
		'CStack'=>'/core/collections/CStack.php',
		'CStackIterator'=>'/core/collections/CStackIterator.php',
		'CTypedList'=>'/core/collections/CTypedList.php',
		'CTypedMap'=>'/core/collections/CTypedMap.php',
		'CDateTimeParser'=>'/core/utils/CDateTimeParser.php',
		'CFileHelper'=>'/core/utils/CFileHelper.php',
		'CFormatter'=>'/core/utils/CFormatter.php',
		'CMarkdownParser'=>'/core/utils/CMarkdownParser.php',
		'CPropertyValue'=>'/core/utils/CPropertyValue.php',
		'CTimestamp'=>'/core/utils/CTimestamp.php',
		'CVarDumper'=>'/core/utils/CVarDumper.php',
		'CBeanstalk'=>'/components/queue/CBeanstalk.php',
		'CApcCache'=>'/components/cache/CApcCache.php',
		'CCache'=>'/components/cache/CCache.php',
		'CDummyCache'=>'/components/cache/CDummyCache.php',
		'CMemCache'=>'/components/cache/CMemCache.php',
		'CRedisCache'=>'/components/cache/CRedisCache.php',
		'CTokyoTyrantCache'=>'/components/cache/CTokyoTyrantCache.php',
		'CXCache'=>'/components/cache/CXCache.php',
		'CCacheDependency'=>'/components/cache/dependencies/CCacheDependency.php',
		'CChainedCacheDependency'=>'/components/cache/dependencies/CChainedCacheDependency.php',
		'CExpressionDependency'=>'/components/cache/dependencies/CExpressionDependency.php',
		'CDbCommand'=>'/components/db/CDbCommand.php',
		'CDbConnection'=>'/components/db/CDbConnection.php',
		'CDbDataReader'=>'/components/db/CDbDataReader.php',
		'CDbException'=>'/components/db/CDbException.php',
		'CDbTransaction'=>'/components/db/CDbTransaction.php',
		'CChoiceFormat'=>'/components/i18n/CChoiceFormat.php',
		'CDateFormatter'=>'/components/i18n/CDateFormatter.php',
		'CDbMessageSource'=>'/components/i18n/CDbMessageSource.php',
		'CGettextMessageSource'=>'/components/i18n/CGettextMessageSource.php',
		'CLocale'=>'/components/i18n/CLocale.php',
		'CMessageSource'=>'/components/i18n/CMessageSource.php',
		'CNumberFormatter'=>'/components/i18n/CNumberFormatter.php',
		'CPhpMessageSource'=>'/components/i18n/CPhpMessageSource.php',
		'CGettextFile'=>'/components/i18n/gettext/CGettextFile.php',
		'CGettextMoFile'=>'/components/i18n/gettext/CGettextMoFile.php',
		'CGettextPoFile'=>'/components/i18n/gettext/CGettextPoFile.php',
		'CLogFilter'=>'/components/logger/CLogFilter.php',
		'CLogRouter'=>'/components/logger/CLogRouter.php',
		'CLogger'=>'/components/logger/CLogger.php',
		'CFileAutoCateLogRoute'=>'/components/logger/route/CFileAutoCateLogRoute.php',
		'CFileLogRoute'=>'/components/logger/route/CFileLogRoute.php',
		'CLogRoute'=>'/components/logger/route/CLogRoute.php',
		'CProfileLogRoute'=>'/components/logger/route/CProfileLogRoute.php',
		'CWebLogRoute'=>'/components/logger/route/CWebLogRoute.php',
		'CHawkeyeLogRoute'=>'/components/logger/route/CHawkeyeLogRoute.php',
		'CImageComponent'=>'/components/image/CImageComponent.php',
		'CWaeOidbSdk'=>'/services/oidb/CWaeOidbSdk.php',
		'CConsoleApplication'=>'/console/CConsoleApplication.php',
		'CConsoleCommand'=>'/console/CConsoleCommand.php',
		'CConsoleCommandRunner'=>'/console/CConsoleCommandRunner.php',
		'CHelpCommand'=>'/console/CHelpCommand.php',
		'CTaskDaemonCommand'=>'/console/CTaskDaemonCommand.php',
		'CModel'=>'/core/model/CModel.php',
		'CModelBehavior'=>'/core/model/CModelBehavior.php',
		'CModelEvent'=>'/core/model/CModelEvent.php',
		'CActiveFinder'=>'/core/model/db/ar/CActiveFinder.php',
		'CActiveRecord'=>'/core/model/db/ar/CActiveRecord.php',
		'CActiveRecordBehavior'=>'/core/model/db/ar/CActiveRecordBehavior.php',
		'CDbColumnSchema'=>'/core/model/db/schema/CDbColumnSchema.php',
		'CDbCommandBuilder'=>'/core/model/db/schema/CDbCommandBuilder.php',
		'CDbCriteria'=>'/core/model/db/schema/CDbCriteria.php',
		'CDbExpression'=>'/core/model/db/schema/CDbExpression.php',
		'CDbSchema'=>'/core/model/db/schema/CDbSchema.php',
		'CDbTableSchema'=>'/core/model/db/schema/CDbTableSchema.php',
		'CMysqlColumnSchema'=>'/core/model/db/schema/mysql/CMysqlColumnSchema.php',
		'CMysqlSchema'=>'/core/model/db/schema/mysql/CMysqlSchema.php',
		'CMysqlTableSchema'=>'/core/model/db/schema/mysql/CMysqlTableSchema.php',
		'CBooleanValidator'=>'/core/model/db/validators/CBooleanValidator.php',
		'CCaptchaValidator'=>'/core/model/db/validators/CCaptchaValidator.php',
		'CCompareValidator'=>'/core/model/db/validators/CCompareValidator.php',
		'CDateValidator'=>'/core/model/db/validators/CDateValidator.php',
		'CDefaultValueValidator'=>'/core/model/db/validators/CDefaultValueValidator.php',
		'CEmailValidator'=>'/core/model/db/validators/CEmailValidator.php',
		'CExistValidator'=>'/core/model/db/validators/CExistValidator.php',
		'CFileValidator'=>'/core/model/db/validators/CFileValidator.php',
		'CFilterValidator'=>'/core/model/db/validators/CFilterValidator.php',
		'CInlineValidator'=>'/core/model/db/validators/CInlineValidator.php',
		'CNumberValidator'=>'/core/model/db/validators/CNumberValidator.php',
		'CRangeValidator'=>'/core/model/db/validators/CRangeValidator.php',
		'CRegularExpressionValidator'=>'/core/model/db/validators/CRegularExpressionValidator.php',
		'CRequiredValidator'=>'/core/model/db/validators/CRequiredValidator.php',
		'CSafeValidator'=>'/core/model/db/validators/CSafeValidator.php',
		'CStringValidator'=>'/core/model/db/validators/CStringValidator.php',
		'CTypeValidator'=>'/core/model/db/validators/CTypeValidator.php',
		'CUniqueValidator'=>'/core/model/db/validators/CUniqueValidator.php',
		'CUnsafeValidator'=>'/core/model/db/validators/CUnsafeValidator.php',
		'CUrlValidator'=>'/core/model/db/validators/CUrlValidator.php',
		'CValidator'=>'/core/model/db/validators/CValidator.php',
		'CActiveDataProvider'=>'/core/web/CActiveDataProvider.php',
		'CArrayDataProvider'=>'/core/web/CArrayDataProvider.php',
		'CAssetManager'=>'/core/web/CAssetManager.php',
		'CBaseController'=>'/core/web/CBaseController.php',
		'CCacheHttpSession'=>'/core/web/CCacheHttpSession.php',
		'CClientScript'=>'/core/web/CClientScript.php',
		'CController'=>'/core/web/CController.php',
		'CDataProvider'=>'/core/web/CDataProvider.php',
		'CDbHttpSession'=>'/core/web/CDbHttpSession.php',
		'CExtController'=>'/core/web/CExtController.php',
		'CFormModel'=>'/core/web/CFormModel.php',
		'CHttpCookie'=>'/core/web/CHttpCookie.php',
		'CHttpRequest'=>'/core/web/CHttpRequest.php',
		'CHttpSession'=>'/core/web/CHttpSession.php',
		'CHttpSessionIterator'=>'/core/web/CHttpSessionIterator.php',
		'COutputEvent'=>'/core/web/COutputEvent.php',
		'CPagination'=>'/core/web/CPagination.php',
		'CSort'=>'/core/web/CSort.php',
		'CSqlDataProvider'=>'/core/web/CSqlDataProvider.php',
		'CTheme'=>'/core/web/CTheme.php',
		'CThemeManager'=>'/core/web/CThemeManager.php',
		'CUploadedFile'=>'/core/web/CUploadedFile.php',
		'CUrlManager'=>'/core/web/CUrlManager.php',
		'CWebApplication'=>'/core/web/CWebApplication.php',
		'CWebModule'=>'/core/web/CWebModule.php',
		'CWidgetFactory'=>'/core/web/CWidgetFactory.php',
		'CAction'=>'/core/web/actions/CAction.php',
		'CInlineAction'=>'/core/web/actions/CInlineAction.php',
		'CViewAction'=>'/core/web/actions/CViewAction.php',
		'CAccessControlFilter'=>'/core/web/auth/CAccessControlFilter.php',
		'CAuthAssignment'=>'/core/web/auth/CAuthAssignment.php',
		'CAuthItem'=>'/core/web/auth/CAuthItem.php',
		'CAuthManager'=>'/core/web/auth/CAuthManager.php',
		'CBaseUserIdentity'=>'/core/web/auth/CBaseUserIdentity.php',
		'CDbAuthManager'=>'/core/web/auth/CDbAuthManager.php',
		'CPhpAuthManager'=>'/core/web/auth/CPhpAuthManager.php',
		'CUserIdentity'=>'/core/web/auth/CUserIdentity.php',
		'CWebUser.odl'=>'/core/web/auth/CWebUser.odl.php',
		'CWebUser'=>'/core/web/auth/CWebUser.php',
		'CFilter'=>'/core/web/filters/CFilter.php',
		'CFilterChain'=>'/core/web/filters/CFilterChain.php',
		'CHttpCacheFilter'=>'/core/web/filters/CHttpCacheFilter.php',
		'CInlineFilter'=>'/core/web/filters/CInlineFilter.php',
		'CHtml'=>'/core/web/helpers/CHtml.php',
		'CJSON'=>'/core/web/helpers/CJSON.php',
		'CJavaScript'=>'/core/web/helpers/CJavaScript.php',
		'CJavaScriptExpression'=>'/core/web/helpers/CJavaScriptExpression.php',
		'CActiveForm'=>'/core/web/widgets/CActiveForm.php',
		'CAutoComplete'=>'/core/web/widgets/CAutoComplete.php',
		'CClipWidget'=>'/core/web/widgets/CClipWidget.php',
		'CContentDecorator'=>'/core/web/widgets/CContentDecorator.php',
		'CFilterWidget'=>'/core/web/widgets/CFilterWidget.php',
		'CFlexWidget'=>'/core/web/widgets/CFlexWidget.php',
		'CHtmlPurifier'=>'/core/web/widgets/CHtmlPurifier.php',
		'CInputWidget'=>'/core/web/widgets/CInputWidget.php',
		'CMarkdown'=>'/core/web/widgets/CMarkdown.php',
		'CMaskedTextField'=>'/core/web/widgets/CMaskedTextField.php',
		'CMultiFileUpload'=>'/core/web/widgets/CMultiFileUpload.php',
		'COutputCache'=>'/core/web/widgets/COutputCache.php',
		'COutputProcessor'=>'/core/web/widgets/COutputProcessor.php',
		'CStarRating'=>'/core/web/widgets/CStarRating.php',
		'CTabView'=>'/core/web/widgets/CTabView.php',
		'CTextHighlighter'=>'/core/web/widgets/CTextHighlighter.php',
		'CTreeView'=>'/core/web/widgets/CTreeView.php',
		'CWidget'=>'/core/web/widgets/CWidget.php',
		'CCaptcha'=>'/core/web/widgets/captcha/CCaptcha.php',
		'CCaptchaAction'=>'/core/web/widgets/captcha/CCaptchaAction.php',
		'CBasePager'=>'/core/web/widgets/pagers/CBasePager.php',
		'CLinkPager'=>'/core/web/widgets/pagers/CLinkPager.php',
		'CListPager'=>'/core/web/widgets/pagers/CListPager.php',
		'CPhpPerfReporter'=>'/components/perform/CPhpPerfReporter.php',
		'CWaeCounter'=>'/components/counter/CWaeCounter.php',
		'CWaeFileService'=>'/components/file/CWaeFileService.php',
		'CWaeBossService'=>'/components/data/CWaeBossService.php',
		'CWaePushService'=>'/components/push/CWaePushService.php',
		'CWaeShareService'=>'/services/share/CWaeShareService.php',
		'CWaeIpService'=>'/components/ip/CWaeIpService.php',
		'CWaeIp'=>'/components/ip/CWaeIp.php',
		'CWaeHttpgetService'=>'/components/httpget/CWaeHttpgetService.php',
		'CWaeNameService'=>'/components/other/CWaeNameService.php',
		'CWaeL5NameService'=>'/components/other/CWaeL5NameService.php',
		'CWaeBossApi'=>'/components/other/CWaeBossApi.php',
		'CWebPassUser'=>'/components/user/CWebPassUser.php',
		'CWebPassUserTest'=>'/components/user/CWebPassUserTest.php',
		'COaUser'=>'/components/user/COaUser.php',
		'CWaeStorage'=>'/components/storage/CWaeStorage.php',
		'PBMessage'=>'/components/protocolbuf/message/pb_message.php',
		'CWaeWeixin'=>'/components/weixin/CWaeWeixin.php',
		'CWaeWeixinSimple'=>'/components/weixin/CWaeWeixinSimple.php',
		'CWaeSms'=>'/components/sms/CWaeSms.php',
		'CWaeId'=>'/components/id/CWaeId.php',
		'CUrl'=>'/exts/CUrl.php',
		'CWaePassport'=>'/components/roles/CWaePassport.php',
    ); 
}

require(MOD_PATH.'/core/interfaces.php');

//If only use CApplicationComponent, don't need register autoload.
spl_autoload_register(array('Mod','autoload'));

Mod::reportVersion();
