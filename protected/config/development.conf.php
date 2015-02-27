<?php
return array(
	'params'=>array(
		'modPath' => __DIR__ . '/../../data/php/framework',
	),
	'components'=>array(
		'db'=>array(
			'class'=>'CDbConnection',
			'charset' => 'utf8',
			'tablePrefix' => 't_',
			'connectionString' => 'mysql:host=localhost;port=3306;dbname=test',
			'username' => 'root',
			'password' => 'root',
		),
		'themeManager'=>array(
			'class'=>'CThemeManager',
			'basePath'=> realpath(dirname(__FILE__).'/../themes')
		),
	)
);
?>
