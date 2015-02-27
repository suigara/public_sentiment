<?php
/**
 * CFileAutoCateLogRoute class file.
 *
 * @author andehuang
 * @link 
 * @copyright 
 * @license 
 */

/**
 * 自动根据分类写文件
 */
class CFileAutoCateLogRoute extends CFileLogRoute
{
	/**
	 * 将日志写入日志文件
	 * @param array $logs list of log messages
	 */
	protected function processLogs($logs)
	{
		//先将日志缓存在数组中，最后一起写日志
		$arrLogContent = array();
		
		foreach($logs as $log)
		{
			//获取日志文件夹和日志名称
			$logFile = $this->getLogFile($log[2]);
			if(!isset($arrLogContent[$logFile]))
				$arrLogContent[$logFile] = $this->formatLogMessage($log[0],$log[1],$log[2],$log[3]);
			else
				$arrLogContent[$logFile] .= $this->formatLogMessage($log[0],$log[1],$log[2],$log[3]);
		}
		
		//写日志
		foreach($arrLogContent as $logFile=>$logContent)
			@file_put_contents($logFile, $logContent, FILE_APPEND);
	}
	
	/**
	 * 获取日志文件夹和日志名称
	 * @return string $logFile
	 */
	protected function getLogFile($category)
	{
		//每天新建一个文件夹，用于保存当天的日志
		$logDirToday = $this->getLogDir().date($this->dateDirFormat);
		if(!is_dir($logDirToday))
			@mkdir($logDirToday);
		
		//获取日志文件的全路径
		$arrCate = explode('.', $category);
		if(count($arrCate)>1)
			$cateName = $arrCate[1];
		else
			$cateName = $arrCate[0];
		
		$logFile = "$logDirToday/$cateName.{$this->logFileName}{$this->_logFileNameSuffix}";
		if($this->rotateFormat == self::ROTATE_HOUR)
			$logFile .= date('.H');
		
		return $logFile;
	}
}