<?php
/**
 * Mod command line script file.
 *
 * This script is meant to be run on command line to execute
 * one of the pre-defined console commands.
 *
 * @author 
 * @link 
 * @copyright 
 * @license 
 * @version 
 */

// fix for fcgi
defined('STDIN') or define('STDIN', fopen('php://stdin', 'r'));

defined('MOD_DEBUG') or define('MOD_DEBUG',true);

require_once(dirname(__FILE__).'/Mod.php');

if(isset($config))
{
	$app=Mod::createConsoleApplication($config);
	//$app->commandRunner->addCommands(MOD_PATH.'/console/cli/commands');
	$env=@getenv('MOD_CONSOLE_COMMANDS');
	if(!empty($env))
		$app->commandRunner->addCommands($env);
}
else
	$app=Mod::createConsoleApplication(array('basePath'=>dirname(__FILE__).'/console/cli'));

$app->run();