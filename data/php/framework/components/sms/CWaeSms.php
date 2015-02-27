<?php

/**
 * 地方站短信发送接口
 * @author leohu
 * @framewok add pechspeng
 */
class CWaeSms extends CApplicationComponent
{
    
    const API_SMS_NAME = 'api.sms.areasite.com';
    
    const DEFAULT_CHARSET = 'utf-8';
    const MAX_RECV_COUNT = 10;
    const MAX_MSG_LENGTH = 120;
    const RECV_REGX = '/^\d{11}$/';
    
    const ERR_OK = 0;
    const ERR_MSG_TOO_LONG = 6004;// 短信内容过长
    const ERR_RECV_INVAILD = 6003;// 接收人号码无效
    const ERR_RECV_TOO_LONG = 6011;// 超过短信接收人最大个数
    const ERR_REQ_FAILD = 6010;// 接口请求失败
    const ERR_MSG_IS_NULL = 6013;// 短信正文为空
    const ERR_FETCH_HOST_FAILD = 6016;// 获取API主机失败
    
    public $appid;
    public $rescode;
    
    public function init()
    {
    	parent::init();
    }
    
    /**
     * 发送纯文字短信
     * 
     * @param $appid
     * 业务ID
     * 
     * @param $rescode
     * 业务资源码
     * 
     * @param $receiver
     * 短信接收人，使用半角逗号(",")分割，最多10个号码
     * 
     * @param $message
     * 短信内容，60个字符（非字节）一条短信，不超过120个字符（2条短信）
     * 若为数组，则下标[0]为tplid, 其他对应每个参数
     * 
     * @param $charset
     * 短信内容字符集
     * 
     * @return int
     * 返回消息发送状态
     * 0: 成功
     */
    public function send($receiver, $message, $charset = self::DEFAULT_CHARSET) {
        $recv_arr = explode(',', $receiver);
        if(count($recv_arr) > self::MAX_RECV_COUNT) {
            return self::ERR_RECV_TOO_LONG;
        }
        
        foreach($recv_arr as $recv) {
            if(!preg_match(self::RECV_REGX, $recv))
                return self::ERR_RECV_INVAILD;
        }
        
    	if($message == '') {
        	return self::ERR_MSG_IS_NULL;
        }
        
        if(mb_strlen($message, $charset) > self::MAX_MSG_LENGTH) {
        	return self::ERR_MSG_TOO_LONG;
        }
        if(Mod::app()->nameService->getHostByKey(self::API_SMS_NAME, $ip, $port) === true) {
        	$args = array(
	            'appid' => $this->appid,
        		'message' => $message,
	            'rescode' => $this->rescode,
	            'receiver' => $receiver,
	            'charset' => $charset
	        );
        	$url = "$ip:$port/send.php?".http_build_query($args);
        	$raw = self::request($url);
        	if($raw === FALSE) {
        		return self::ERR_REQ_FAILD;
        	}
        	
        	$json = json_decode($raw, true);
        	if(isset($json['sys_param']['ret_code'])) {
        		return (int)$json['sys_param']['ret_code'];
        	}
        	
        	return self::ERR_REQ_FAILD;
        }
        
        return self::ERR_FETCH_HOST_FAILD;

    }
    
    /**
     * 查询回复的短信
     * 
     * @param $appid
     * 业务ID
     * 
     * @param $rescode
     * 业务资源码
     * 
     * @param $page
     * 页码
     * 默认为第1页，每页固定返回20条数据
     * 
     * @param $date
     * 日期格式: Y-m-d
     * 默认为今日
     * 
     * @param $receiver
     * 指定号码的回复短信
     * 为空则返回全部号码的短信
     * 
     * @param $lastid
     * 返回小于lastid之前的短信数据
     * 
     * @deprecated JSON通讯仅UTF-8格式
     * @param $charset
     * 返回数据字符集
     * 默认UTF-8
     * 
     * @return array
     * 成功，返回数组
     * 失败，返回错误号
     */
    public function query( $page = 1, $date = '', $receiver = '', $lastid = '', $charset = self::DEFAULT_CHARSET) {
        
        if(self::get_host(self::API_SMS_NAME, $ip, $port) === 0) {
        	$url = "$ip:$port/query.php?".
	        	http_build_query(array(
		            'appid' => $this->appid,
		            'rescode' => $this->rescode,
		            'page' => $page,
		            'date' => $date,
		            'receiver' => $receiver,
	        		'lastid' => $lastid,
		            'charset' => $charset
		        ));
        	
        	$raw = self::request($url);
        	if($raw === FALSE) {
        		return self::ERR_REQ_FAILD;
        	}
        	
        	$json = json_decode($raw, true);
        	if(!isset($json['sys_param']['ret_code'])) {
        		return self::ERR_REQ_FAILD;
        	}
        	
	        if($json['sys_param']['ret_code'] !== 0) {
	            return $json['sys_param']['ret_code'];
	        }
        	
        	return $json['data'];
        }
        
        return self::ERR_FETCH_HOST_FAILD;
    }
    
    protected static function request($url) {
    	$ch = curl_init($url);
    	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    	$raw = curl_exec($ch);
    	curl_close($ch);
    	
    	return $raw;
    }
    
}

