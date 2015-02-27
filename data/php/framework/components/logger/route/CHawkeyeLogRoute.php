<?php
require_once("bossapi.php");
/**
 * CHawkeyeLogRoute class file.
 *
 * @author  pechspeng
 * @link 
 * @copyright 
 * @license 
 */

/**
 * CWebLogRoute shows the log content in Web page.
 *
 * The log content can appear either at the end of the current Web page
 * or in FireBug console window (if {@link showInFireBug} is set true).
 *
 * @author 
 * @version 
 * @package system.logging
 * @since 1.0
 */
class CHawkeyeLogRoute extends CLogRoute
{
    /*
	 * Displays the log messages.
	 * @param array $logs list of log messages
	 */
    public $categoryprefix;
    public function init(){
        parent::init();
    }
	public function processLogs($logs)
	{
		foreach($logs as $log)
            $this->sendInfo($log);
        
	}

	/**
	 * function send the msg to hawkeye
	 * @param array $log to send to the hawkeye
	 */
	protected function sendInfo($log)
	{
        $levelarr = array(CLogger::LEVEL_ERROR=>LOG_ERROR,CLogger::LEVEL_WARNING=>LOG_WARN,CLogger::LEVEL_INFO=>LOG_INFO,CLogger::LEVEL_TRACE=>LOG_DEBUG);
        if(isset($levelarr[$log[1]])){
        	if($log[1] == CLogger::LEVEL_ERROR || $log[1] ==CLogger::LEVEL_WARNING)
                SEND_LOG_ERROR($this->categoryprefix.'.'.$log[2], 0, '', $levelarr[$log[1]], -1, substr($log[0],0,1024));
            else SEND_LOG_ACCESS(0,$this->categoryprefix.'.'.$log[2],'view',0,1000,substr($log[0],0,1024));
        }    
	}
}
