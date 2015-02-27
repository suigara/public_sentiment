<?php
/*
 *CWebapplication的配置文件,所有的配置都在此配置
 *
 */
define('LOG_DIR', realpath(dirname(__FILE__).'/../runtime'));
return array(
	'basePath'=>dirname(__FILE__).DIRECTORY_SEPARATOR.'..',
	// 应用中文名
	'name'=>'海豹平台框架示例',
	// 应用英文名，用来唯一标识应用
	'id'=>'public_sentimeent',
	// 应用编码（html、db等）
	'charset'=>'utf-8',	
	// 语言包选择，默认为英语
	'language'=>'zh_cn',
	// 预加载的组件，
	'preload'=>array('log','bootstrap'),
	/**
	 * 这里如果定义了theme,那么视图的渲染就会去找webroot下的themes目录
	 * 如下定义了theme为bootstrap,框架在webroot/themes/bootstrap/views目录下查找
	 * 视图文件，如果不存在，再查找protected/views目录
	 */
	//'theme'=>'bootstrap',
	// 默认导入类型
	'import'=>array(
		/**
		 * application表示webroot的protected目录路径
		 */
		'application.models.*',
		'application.components.*',
	),
	// 默认的controller, 
	//'defaultController'=>'site',

	// 组件配置, 通过key引用（如：Mod::app()->bootstrap);
	'components'=>array(
		//url管理组件
		'urlManager'=>array(
			'urlFormat'=>'path',
			//要不要显示url中的index.php
			'showScriptName' => false,
			//url对应的解析规则,类似于nginx和apache的rewite,支持正则
			'rules'=>array(
				'extra/intrThemes/<themes:.*?>'=>'extra/intrThemes',
				"extra/<params:.\d+>"=>"extra/intrUrl",
				'<controller:\w+>/<action:\w+>'=>'<controller>/<action>',
			),
		),
		// 日志配置，必须预加载生效
		'log'=>array(
			'class'=>'CLogRouter',
			'routes'=>array(
				array(					
					'class'=>'CFileLogRoute', // 写入
					'levels'=>'', // 记录所有级别的
					'LogDir'=>LOG_DIR,//此目录可配置,在此目录下，每天一个文件夹
					'logFileName'=>'all.log'//记录日志的文件名可配置
				)
			),
		),

		'bootstrap'=>array(
            'class'=>'system.exts.bootstrap.Bootstrap',
        ),
		'messages'=>array(
			'class'=>'CPhpMessageSource',
			'basePath'=> realpath(dirname(__FILE__).'/../extensions/messages'),
		),
        // .tpl结尾的视图文件，会采用smarty模板进行渲染
        'viewRenderer'=>array(
        	'class'=>'system.exts.smarty.ESmartyViewRenderer',
    		'fileExtension' => '.tpl',
        ),
		'weixin'=>array(
			'class' => 'CWaeWeixin',
			'conf' => array(
				'mp'=>array(
					'token'=>'dd5a79f8c4ca6dede991dc459a8d404c',
					'appid' => 'wxe7bff7328747bb27',
					'secret' => '3a18f443efc2f5788175c74f09747c86'
				)
			 )       
		 ), 
	),
	'params'=>array(
		// CGI性能上报开关
		'enableCgiPerform'=>true,
		'enableUriXss'=>true,
		'modPath' => '/data/php/framework',
	),
);