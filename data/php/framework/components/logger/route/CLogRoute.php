<?php
/**
 * CLogRoute class file.
 *
 * @author 
 * @link 
 * @copyright 
 * @license 
 */

/**
 * CLogRoute is the base class for all log route classes.
 *
 * A log route object retrieves log messages from a logger and sends it
 * somewhere, such as files, emails.
 * The messages being retrieved may be filtered first before being sent
 * to the destination. The filters include log level filter and log category filter.
 *
 * To specify level filter, set {@link levels} property,
 * which takes a string of comma-separated desired level names (e.g. 'Error, Debug').
 * To specify category filter, set {@link categories} property,
 * which takes a string of comma-separated desired category names (e.g. 'System.Web, System.IO').
 *
 * Level filter and category filter are combinational, i.e., only messages
 * satisfying both filter conditions will they be returned.
 *
 * @author 
 * @version 
 * @package system.logging
 * @since 1.0
 */
abstract class CLogRoute extends CComponent
{
	/**
	 * @var boolean whether to enable this log route. Defaults to true.
	 */
	public $enabled=true;
	/**
	 * @var string list of levels separated by comma or space. Defaults to empty, meaning all levels.
	 */
	public $levels='';
	/**
	 * @var string list of categories separated by comma or space. Defaults to empty, meaning all categories.
	 */
	public $categories='';
	/**
	 * @var mixed the additional filter (eg {@link CLogFilter}) that can be applied to the log messages.
	 * The value of this property will be passed to {@link Mod::createComponent} to create
	 * a log filter object. As a result, this can be either a string representing the
	 * filter class name or an array representing the filter configuration.
	 * In general, the log filter class should be {@link CLogFilter} or a child class of it.
	 * Defaults to null, meaning no filter will be used.
	 */
	public $filter;
	/**
	 * @var array the logs that are collected so far by this log route.
	 * @since 1.0
	 */
	public $logs;
	/**
	 * @var float 记录日志的概率。比如，希望线上环境按照一定概率记录数据库的profile日志，可以通过配置该参数来实现。
	 * 相当于抽样记录profile日志，这样既可以减少写日志的压力，有可以监控数据库访问的速度。最小记录概率为百万分之一。
	 */
	public $ratio=1;

	/**
	 * Initializes the route.
	 * This method is invoked after the route is created by the route manager.
	 */
	public function init()
	{
		//判断是否进行抽样记录，最小概率为百万分之一。
		if($this->ratio<1)
		{
			$rand = mt_rand(1, 1000000);
			if(($rand/1000000) > $this->ratio)
				$this->enabled = false;
		}
	}

	/**
	 * Formats a log message given different fields.
	 * @param string $message message content
	 * @param integer $level message level
	 * @param string $category message category
	 * @param integer $time timestamp
	 * @return string formatted message
	 */
	protected function formatLogMessage($message,$level,$category,$time,$endTime=0)
	{
		return @date('Y/m/d H:i:s',$time)." [$level] [$category] $message\n";
	}

	/**
	 * Retrieves filtered log messages from logger for further processing.
	 * @param CLogger $logger logger instance
	 * @param boolean $processLogs whether to process the logs after they are collected from the logger
	 */
	public function collectLogs($logger, $processLogs=false)
	{
		$logs=$logger->getLogs($this->levels,$this->categories);
		$this->logs=empty($this->logs) ? $logs : array_merge($this->logs,$logs);
		if($processLogs && !empty($this->logs))
		{
			if($this->filter!==null)
				Mod::createComponent($this->filter)->filter($this->logs);
			$this->processLogs($this->logs);
			$this->logs=array();
		}
	}

	/**
	 * Processes log messages and sends them to specific destination.
	 * Derived child classes must implement this method.
	 * @param array $logs list of messages.  Each array elements represents one message
	 * with the following structure:
	 * array(
	 *   [0] => message (string)
	 *   [1] => level (string)
	 *   [2] => category (string)
	 *   [3] => timestamp (float, obtained by microtime(true));
	 */
	abstract protected function processLogs($logs);
}
