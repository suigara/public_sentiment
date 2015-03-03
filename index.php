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
include ("/protected/include/public_sentiment.php");
$ps = new PublicSentiment();
$pointNewsArray = $ps->getPublicSentimentJsonLastMonth("陈赫");
foreach ($pointNewsArray as $currentnews) {
    showNews($currentnews);
}
function showNews($news){
    echo '标题;'.$news["title"].', 时间:'.$news["date"].', 网址:'.$news["url"].', 热点数:'.$news["pointCount"] . '<br>';
}
//$su->snatchMoreNews("陈赫");


//echo implode('-',$str);
//echo json_encode($str);
