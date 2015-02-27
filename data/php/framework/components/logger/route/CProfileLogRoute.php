<?php
/**
 * CProfileLogRoute class file.
 *
 * @author andehuang
 */

/**
 * CProfileLogRoute 
 */
class CProfileLogRoute extends CFileLogRoute
{
	/**
	 * Initializes the route.
	 * This method is invoked after the route is created by the route manager.
	 */
	public function init()
	{
		parent::init();
		$this->levels=CLogger::LEVEL_PROFILE;
	}

	/**
	 * 将日志写入日志文件
	 * @param array $logs list of log messages
	 */
	protected function processLogs($logs)
	{
		//获取日志文件夹和日志名称
		list($logDirToday, $logFile) = $this->getLogDirAndLogFile();
		
		//生成日志文件
		$timings = $this->calculateTimings($logs);
		
		//写日志
		$logContent = '';
		foreach($timings as $log)
			$logContent .= $this->formatLogMessage($log[0],$log[1],$log[2],$log[3],$log[4]);
		@file_put_contents($logFile, $logContent, FILE_APPEND);
	}

	/**
	 * 计算profile日志
	 * @return array 计算结果，数值中的每个元素为：array($token,$category,$delta,$startTime,$endTime)
	 */
	protected function calculateTimings($logs)
	{
		$timings=array();
		$stack=array();
		
		foreach($logs as $log)
		{
			if($log[1]!==CLogger::LEVEL_PROFILE)
				continue;
			list($message,$level,$category,$timestamp)=$log;
			if(!strncasecmp($message,'begin:',6))
			{
				$log[0]=substr($message,6);
				$stack[]=$log;
			}
			else if(!strncasecmp($message,'end:',4))
			{
				$token=substr($message,4);
				if(($last=array_pop($stack))!==null && $last[0]===$token)
				{
					$delta=$log[3]-$last[3];
					$timings[]=array($token,$category,$delta,$log[3],$last[3]);
				}
				else
					throw new CException(Mod::t('mod','CProfileLogRoute found a mismatching code block "{token}". Make sure the calls to Mod::beginProfile() and Mod::endProfile() be properly nested.',
						array('{token}'=>$token)));
			}
		}

		$now=microtime(true);
		while(($last=array_pop($stack))!==null)
		{
			$delta=$now-$last[3];
			$timings[]=array($last[0],$last[2],$delta,$last[2],microtime(true));
		}
		return $timings;
	}

	protected function formatLogMessage($token,$category,$delta,$startTime,$endTime=0)
	{
		return @date('Y/m/d H:i:s',$startTime)." [$token] [$category] [$delta] [$startTime] [$endTime]\n";
	}
}