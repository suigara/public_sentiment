<?php
// 定义根目录
define("ROOT_DIR", dirname(__FILE__));
require(ROOT_DIR . "/protected/components/Environment.php");
// 创建环境配置对象
$env = new Environment("DEVELOPMENT", array('life_time'=>30));
// 设置输出编码，效果同php.ini中配置default_charset
header('Content-type:text/html;charset='.$env->get('charset'));
// 创建一个Web应用实例并执行
require($env->getModPath().'/Mod.php');
Mod::createWebApplication($env->getConfig());//->run();

//echo "nihao";
include ("/protected/include/PublicSentimentSnatch.php");
$su = new PublicSentimentSnatch();
$str = $su->getPublicSentimentJsonLastMonth("陈赫");
//$su->snatchMoreNews("陈赫");


//echo implode('-',$str);
//echo json_encode($str);
