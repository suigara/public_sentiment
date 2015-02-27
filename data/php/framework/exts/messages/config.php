<?php
/**
 * This is the configuration for generating message translations
 * for the Mod framework. It is used by the 'modc message' command.
 */
return array(
	'sourcePath'=>dirname(__FILE__).DIRECTORY_SEPARATOR.'..',
	'messagePath'=>dirname(__FILE__).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'messages',
	'languages'=>array('zh_cn','zh_tw','de','el','es','sv','he','nl','pt','pt_br','ru','it','fr','ja','pl','hu','ro','id','vi','bg','lv','sk'),
	'fileTypes'=>array('php'),
    'overwrite'=>true,
	'exclude'=>array(
		'.svn',
		'modlite.php',
		'modt.php',
		'/i18n/data',
		'/messages',
		'/vendors',
		'/web/js',
	),
);
