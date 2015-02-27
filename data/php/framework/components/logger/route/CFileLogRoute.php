<?php
/**
 * CFileLogRoute class file.
 *
 * @author andehuang
 * @link 
 * @copyright 
 * @license 
 */

/**
 * CFileLogRoute使用文件来记录系统日志。
 * 日志文件保存在{@link setLogDir logDir}文件夹下，以天为单位，每天新建一个文件夹，通过
 * {@link logFileName}来设置日志文件的名称。
 * 比如：logDir='/data/var/'; logFileName='error.log';
 * 2012年8月1日的日志文件是：/data/var/20120801/error.log
 * 2012年12月30日的日志文件是：/data/var/20121230/error.log
 *
 * 如果Web系统每天产生大量的日志，比如/data/var/20120801/error.log为48G，那么可以通过
 * 配置{@link rotateFormat}成员变量，以小时为单位对日志文件进行切割。那么2012年8月1日的日志
 * 文件会被切割成如下24个文件：
 * /data/var/20120801/error.log.00
 * /data/var/20120801/error.log.01
 * /data/var/20120801/error.log.02
 * ...
 * /data/var/20120801/error.log.23
 * 
 * @property string $rotateFormat 切割文件的方式，默认按天进行切割。
 * @property string $logDir 保存日志文件的文件夹，默认为runtime path。
 * @property string $logFile 日志文件名称，默认为app.log。
 */
class CFileLogRoute extends CLogRoute
{
	/**
	 * 使用2种方式来切割日志文件
	 * 1、以天为单位，即每天一个日志文件
	 * 2、以小时为单位，即小时一个日志文件
	 */
	const ROTATE_DATE = 1;
	const ROTATE_HOUR = 2;
	/**
	 * @var int 切割日志文件的方式，默认根据日期来切割，即每天一个日志文件
	 */
	public $rotateFormat = self::ROTATE_DATE;
	/**
	 * @var string 日期文件夹的格式，即'/data/var/20120801/error.log'中的'20120801'
	 */
	public $dateDirFormat = 'Ymd';
	/**
	 * @var string 日志文件夹
	 */
	protected $_logDir;
	/**
	 * @var string 日志文件名称
	 */
	public $logFileName = 'app.log';
	/**
	 * @var string 日志文件后缀名
	 */
	protected $_logFileNameSuffix='';

	/**
	 * 初始化日志路由对象，日志路由Manager对象会调用本方法。
	 */
	public function init()
	{
		parent::init();
		if($this->getLogDir()===null)
			$this->setLogDir(Mod::app()->getRuntimePath());
	}

	/**
	 * @return string directory storing log files. Defaults to application runtime path.
	 */
	public function getLogDir()
	{
		return $this->_logDir;
	}

	/**
	 * 设置日志文件的后缀名
	 * @param string $suffix
	 */
	public function setLogFileNameSuffix($suffix)
	{
		$this->_logFileNameSuffix = $suffix;
	}

	/**
	 * @param string $value 保存日志文件的文件夹
	 * @throws CException 如果文件夹不存在或者没有写权限，则抛异常
	 */
	public function setLogDir($value)
	{
		$this->_logDir = rtrim(realpath($value), '/').'/';
		if($this->_logDir===false || !is_dir($this->_logDir) || !is_writable($this->_logDir))
			throw new CException(Mod::t('mod','CFileLogRoute.Dir "{path}" does not point to a valid directory. Make sure the directory exists and is writable by the Web server process.',
				array('{path}'=>$value)));
	}

	/**
	 * 将日志写入日志文件
	 * @param array $logs list of log messages
	 */
	protected function processLogs($logs)
	{
		//获取日志文件夹和日志名称
		list($logDirToday, $logFile) = $this->getLogDirAndLogFile();
		
		//写日志
		$logContent = '';
		foreach($logs as $log)
			$logContent .= $this->formatLogMessage($log[0],$log[1],$log[2],$log[3]);
		@file_put_contents($logFile, $logContent, FILE_APPEND);
	}

	/**
	 * 获取日志文件夹和日志名称
	 * @return array array($logDirToday, $logFile)
	 */
	protected function getLogDirAndLogFile()
	{
		//每天新建一个文件夹，用于保存当天的日志
		$logDirToday = $this->getLogDir().date($this->dateDirFormat);
		if(!is_dir($logDirToday))
		{
			$old_umask = umask(0);
			@mkdir($logDirToday);
			umask($old_umask);
		}
		
		//获取日志文件的全路径
		$logFile = "$logDirToday/{$this->logFileName}{$this->_logFileNameSuffix}";
		if($this->rotateFormat == self::ROTATE_HOUR)
			$logFile .= date('.H');
		
		return array($logDirToday, $logFile);
	}
}
