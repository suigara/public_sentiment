<?php
//微信接口，参考如下文档
//http://mp.weixin.qq.com/wiki/index.php?title=%E6%B6%88%E6%81%AF%E6%8E%A5%E5%8F%A3%E6%8C%87%E5%8D%97

class CWaeWeixin extends CApplicationComponent
{
	const MSG_TYPE_TEXT = 'text';
	const MSG_TYPE_IMAGE = 'image';
	const MSG_TYPE_MUSIC = 'music';
	const MSG_TYPE_NEWS = 'news';
	const MENU_TYPE_CREATE = 'create';
	const MENU_TYPE_GET    = 'get';
	const MENU_TYPE_DELETE = 'delete';

	const MEDIA_TYPE_IMAGE = 'image';
	const MEDIA_TYPE_VOICE = 'voice';
	const MEDIA_TYPE_VIDEO = 'video';
	const MEDIA_TYPE_THUMB = 'thumb';
	
    //conf
    public $conf = array(); 
    //cookiefilepath
    public $cookiefilepath = ''; 
    //access_token
    private $access_token = array();
    //enable perform report
    public $enablePerformReport = true;
    //php perform object
    public $_report;  
	// use proxy , defualt false
    public $useProxy;
    /**
    ToUserName	 接收方帐号（收到的OpenID）
    FromUserName	 开发者微信号
    CreateTime	 消息创建时间
    MsgType	 text
    Content	 回复的消息内容
    FuncFlag	 位0x0001被标志时，星标刚收到的消息。
    */
    protected $_TextMsgTpl = "<xml>
                <ToUserName><![CDATA[%s]]></ToUserName>
                <FromUserName><![CDATA[%s]]></FromUserName>
                <CreateTime>%s</CreateTime>
                <MsgType><![CDATA[text]]></MsgType>
                <Content><![CDATA[%s]]></Content>
                <FuncFlag>%d</FuncFlag>
                </xml>";

    /**
    ToUserName	 接收方帐号（收到的OpenID）
    FromUserName	 开发者微信号
    CreateTime	 消息创建时间
    MsgType	 text
    MediaId	 回复的消息内容
    */
    protected $_ImageMsgTpl = "<xml>
                <ToUserName><![CDATA[%s]]></ToUserName>
                <FromUserName><![CDATA[%s]]></FromUserName>
                <CreateTime>%s</CreateTime>
                <MsgType><![CDATA[image]]></MsgType>
		<Image>
                <MediaId><![CDATA[%s]]></MediaId>
		</Image>
                </xml>";
    //音乐消息模版
    /**
    ToUserName	 接收方帐号（收到的OpenID）
    FromUserName	 开发者微信号
    CreateTime	 消息创建时间
    MsgType	 music
    MusicUrl	 音乐链接
    HQMusicUrl	 高质量音乐链接，WIFI环境优先使用该链接播放音乐
    FuncFlag	 位0x0001被标志时，星标刚收到的消息。
    */
    protected $_MusicMsgTpl = "<xml>
                <ToUserName><![CDATA[%s]]></ToUserName>
                <FromUserName><![CDATA[%s]]></FromUserName>
                <CreateTime>%s</CreateTime>
                <MsgType><![CDATA[music]]></MsgType>
                <Music>
                    <Title><![CDATA[%s]]></Title>
                    <Description><![CDATA[%s]]></Description>
                    <MusicUrl><![CDATA[%s]]></MusicUrl>
                    <HQMusicUrl><![CDATA[%s]]></HQMusicUrl>
					<ThumbMediaId><![CDATA[%s]]></ThumbMediaId>
                </Music>
                </xml>";
    //图文消息模版
    /**
    ToUserName	 接收方帐号（收到的OpenID）
    FromUserName	 开发者微信号
    CreateTime	 消息创建时间
    MsgType	 news
    ArticleCount	 图文消息个数，限制为10条以内
    Articles	 多条图文消息信息，默认第一个item为大图
    Title	 图文消息标题
    Description	 图文消息描述
    PicUrl	 图片链接，支持JPG、PNG格式，较好的效果为大图640*320，小图80*80，限制图片链接的域名需要与开发者填写的基本资料中的Url一致
    Url	 点击图文消息跳转链接
    */
    protected $_ArticlesMsgTpl = "<xml>
                <ToUserName><![CDATA[%s]]></ToUserName>
                <FromUserName><![CDATA[%s]]></FromUserName>
                <CreateTime>%s</CreateTime>
                <MsgType><![CDATA[news]]></MsgType>
                <ArticleCount>%d</ArticleCount>
                <Articles>%s</Articles>
                <FuncFlag>%d</FuncFlag>
                </xml>";
    //单个文章的模版
    protected $_ArticleTpl = "<item>
                <Title><![CDATA[%s]]></Title> 
                <Description><![CDATA[%s]]></Description>
                <PicUrl><![CDATA[%s]]></PicUrl>
                <Url><![CDATA[%s]]></Url>
                </item>"; 
    
    //返回给微信支付的packge模板
    protected $_PackageTpl = '<xml>
				<AppId><![CDATA[%s]]></AppId>
				<Package><![CDATA[%s]]></Package>
				<TimeStamp>%d</TimeStamp>
				<NonceStr><![CDATA[%s]]></NonceStr>
				<RetCode>%d</RetCode>
    			<RetErrMsg><![CDATA[%s]]></ RetErrMsg>
				<AppSignature><![CDATA[%s]]></AppSignature>
				<SignMethod><![CDATA[%s]]></ SignMethod >
				</xml>
    		';
    
    private $_PushTpl = '{"touser": "%s","template_id": "%s","data": %s}';
    private $_PushImageTpl = '{"touser": "%s", "msgtype" : "image", "image" : { "media_id": "%s" } }';
    
    public function init()
    {
    	parent::init();
		if(!isset($this->useProxy)){
			$this->useProxy = Mod::app()->params['useProxy'];
			if(!isset($this->useProxy)) $this->useProxy = false;
		}
    }

    /**
    * 发送消息，根据不同的消息类型进行发送
    */
    public function setMsg($msg_info)
    {
        $msg_str = '';

        //根据类型处理
        if (!empty($msg_info['MsgType']))
        {
            switch ($msg_info['MsgType'])
            {
                case 'text':
                    $msg_str = $this->buildTextMsg($msg_info);
                    break;
                case 'music':
                    $msg_str = $this->buildMusicMsg($msg_info);
                    break;
                case 'news':
                    $msg_str = $this->buildNewsMsg($msg_info);
                    break;
                default:
                    $msg_str = '';
            }
        }

        echo $msg_str;
    }

    /**
    * 组装文本信息
    */
    public function buildTextMsg($msgInfo)
    {
        $result = '';

        if (0 != $this->checkSetMsg(self::MSG_TYPE_TEXT, $msgInfo))
        {
            return false;
        }

        //其他参数的缺省处理
        if (empty($msgInfo['CreateTime']))
        {
            $msgInfo['CreateTime'] = time();
        }

        if (empty($msgInfo['FuncFlag']) || !is_numeric($msgInfo['FuncFlag']))
        {
            $msgInfo['FuncFlag'] = 0;
        }

        $result = sprintf($this->_TextMsgTpl, $msgInfo['ToUserName'], 
                            $msgInfo['FromUserName'], $msgInfo['CreateTime'],
                            $msgInfo['Content'],$msgInfo['FuncFlag']);

        echo $result;
        
        return true;
    }

    /**
    * 组装图片信息
    */
    public function buildImageMsg($msgInfo)
    {
        $result = '';

        if (0 != $this->checkSetMsg(self::MSG_TYPE_IMAGE, $msgInfo))
        {
            return false;
        }

        //其他参数的缺省处理
        if (empty($msgInfo['CreateTime']))
        {
            $msgInfo['CreateTime'] = time();
        }

        $result = sprintf($this->_ImageMsgTpl, $msgInfo['ToUserName'], 
                            $msgInfo['FromUserName'], $msgInfo['CreateTime'],
                            $msgInfo['MediaId']);

	Mod::log($result, CLogger::LEVEL_ERROR, 'index.check');
        echo $result;
        
        return true;
    }
	
	/**
    * 组装音乐信息
    */
    public function buildMusicMsg($msgInfo)
    {
        $result = '';

        if (0 != $this->checkSetMsg(self::MSG_TYPE_MUSIC, $msgInfo))
        {
            return false;
        }

        //其他参数的缺省处理
        if (empty($msgInfo['CreateTime']))
        {
            $msgInfo['CreateTime'] = time();
        }
		if (empty($msgInfo['Description']))
        {
            $msgInfo['Description'] = '';
        }
		if (empty($msgInfo['HQMusicUrl']))
        {
            $msgInfo['HQMusicUrl'] = '';
        }
		if (empty($msgInfo['MusicUrl']))
        {
            $msgInfo['MusicUrl'] = '';
        }
		if (empty($msgInfo['Title']))
        {
            $msgInfo['Title'] = '';
        }
        $result = sprintf($this->_MusicMsgTpl, $msgInfo['ToUserName'], 
                            $msgInfo['FromUserName'], $msgInfo['CreateTime'],
							$msgInfo['Title'], $msgInfo['Description'], 
							$msgInfo['MusicUrl'], $msgInfo['HQMusicUrl'],
							$msgInfo['ThumbMediaId']);

		Mod::log($result, CLogger::LEVEL_ERROR, 'index.check');
        echo $result;
        
        return true;
    }

    /**
    * 组装图文信息
    */
    public function buildNewsMsg($msgInfo)
    {
        $result = '';

        //参数校验
        if (0 != ($rst=$this->checkSetMsg(self::MSG_TYPE_NEWS, $msgInfo)))
        {
            return false;
        }

        //其他参数的缺省处理
        if (empty($msgInfo['CreateTime']))
        {
            $msgInfo['CreateTime'] = time();
        }

        if (empty($msgInfo['FuncFlag']) || !is_numeric($msgInfo['FuncFlag']))
        {
            $msgInfo['FuncFlag'] = 0;
        }

        //Articles 相关参数的
        $article = '';

        for ($i = 0; $i < $msgInfo['ArticleCount']; $i++)
        {
            //缺省处理
            if (empty($msgInfo['Articles'][$i]['Title']))
            {
                $msg_info['Articles'][$i]['Title'] = '';
            }

            if (empty($msgInfo['Articles'][$i]['Description']))
            {
                $msgInfo['Articles'][$i]['Description'] = '';
            }

            if (empty($msgInfo['Articles'][$i]['PicUrl']))
            {
                $msgInfo['Articles'][$i]['PicUrl'] = '';
            }else{
				$msgInfo['Articles'][$i]['PicUrl'] = $this->__outer_proxy($msgInfo['Articles'][$i]['PicUrl']);
			}

            if (empty($msgInfo['Articles'][$i]['Url']))
            {
                $msgInfo['Articles'][$i]['Url'] = '';
            }else{
				$msgInfo['Articles'][$i]['Url'] = $this->__outer_proxy($msgInfo['Articles'][$i]['Url']);
			}

            $article .= sprintf($this->_ArticleTpl, $msgInfo['Articles'][$i]['Title'], 
                            $msgInfo['Articles'][$i]['Description'],
                            $msgInfo['Articles'][$i]['PicUrl'],
                            $msgInfo['Articles'][$i]['Url']);
        }
        
        $result = sprintf($this->_ArticlesMsgTpl, $msgInfo['ToUserName'], 
                            $msgInfo['FromUserName'], $msgInfo['CreateTime'],
                            $msgInfo['ArticleCount'],$article, $msgInfo['FuncFlag']);

        echo $result;
        
        return true;        
    }

    /**
    * 获取微信公众平台的消息，各类消息，以数组形式返回
    */
    public function getMsg()
    {
		Mod::log("getMsgcalled", CLogger::LEVEL_TRACE, 'index.CheckUser');
    	if($this->enablePerformReport){
    		$this->_report = new CPhpPerfReporter();
    		$this->_report->beginPerfReport(Mod::app()->id,'','',false,'','');
    	}
		$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
	        
		$postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
	
		$msg = array();
		if (empty($postObj))
		{
			if($this->enablePerformReport)
    			$this->_report->endPerfReport(0);
			return null;
		}
	
		foreach($postObj as $key => $value)
		{
			//echo $key,"\t",$value,"\n";
			$msg[$key] = (string)$value;
		}
		if($this->enablePerformReport)
    			$this->_report->endPerfReport(0);
        if (!empty($msg))
        {
            return $msg;
        }else
        {
            return null;
        }
    }

	public function register($gAccount)
	{
		$this->checkAccessTokenTable();
		return $this->checkSignature($gAccount);	
	}

        /**
         * 签名校验
         */
	public function checkSignature($gAccount, $output=true)
	{
		if(empty($gAccount))
		{
			return false;
		}
		
		if($this->enablePerformReport){
			$this->_report = new CPhpPerfReporter();
    			$this->_report->beginPerfReport(Mod::app()->id,'','',false,'','');
    		}	
    
		$signature = $_GET["signature"];
		$timestamp = $_GET["timestamp"];
		$nonce = $_GET["nonce"];
		
        	$token = $this->conf[$gAccount]['token'];
        
		$tmpArr = array($token, $timestamp, $nonce);
		sort($tmpArr);
		$tmpStr = implode( $tmpArr );
		$tmpStr = sha1( $tmpStr );
		
		if( $tmpStr != $signature )
		{
			if($this->enablePerformReport)
    				$this->_report->endPerfReport(0);
			return false;
		}
		if($output === true){
			echo $_GET["echostr"];
		}
		if($this->enablePerformReport)
    			$this->_report->endPerfReport(0);

		return true;
	}
	
	public function getIsRegister()
	{
		$signature = $_GET["signature"];
		$timestamp = $_GET["timestamp"];
		$nonce = $_GET["nonce"];
		$echostr = $_GET["echostr"];

		if(isset($signature) && isset($timestamp)&& isset($nonce) && isset($echostr))
		{
			return true;
		}
		return false;
	}

    /*
     *push模板
     */
    public function sendTemplate($gAccount, $touserid,$templateid,$data)
    {
    	if($this->enablePerformReport){
    		$this->_report = new CPhpPerfReporter();
    		$this->_report->beginPerfReport(Mod::app()->id,'','',false,'','');
    	}
        $re = $this->getAccessToken($gAccount);
        if(0 != $re)
        {
        	if($this->enablePerformReport)
    			$this->_report->endPerfReport(0);
        	return false;  
        }
        $template_url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.$this->access_token['access_token'];
        $msg = sprintf(Mod::app()->weixin->_PushTpl,$touserid,$templateid,$data);
        $template_url = $this->__inner_proxy($template_url);
	$result = $this->phpPost($template_url, $msg);
        if($this->enablePerformReport)
    			$this->_report->endPerfReport(0);
        return $result;
    }

    /*
     *push图片
     */
    public function sendImage($gAccount, $touserid, $media_id)
    {
    	if($this->enablePerformReport){
    		$this->_report = new CPhpPerfReporter();
    		$this->_report->beginPerfReport(Mod::app()->id,'','',false,'','');
    	}
        $re = $this->getAccessToken($gAccount);
        if(0 != $re)
        {
        	if($this->enablePerformReport)
    			$this->_report->endPerfReport(0);
        	return false;  
        }

        $template_url = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$this->access_token['access_token'];
        $msg = sprintf(Mod::app()->weixin->_PushImageTpl, $touserid, $media_id);
        $result = $this->phpPost($this->__inner_proxy($template_url), $msg);
        if($this->enablePerformReport)
    			$this->_report->endPerfReport(0);
        return $result;
    }

    /*
     *菜单配置
     */
    public function sendMenuConf($gAccount, $type, $data=array(), $withMenu = true)
    {
    	if($this->enablePerformReport){
    		$this->_report = new CPhpPerfReporter();
    		$this->_report->beginPerfReport(Mod::app()->id,'','',false,'','');
    	}
        $re = $this->getAccessToken($gAccount);
        if(0 != $re)
        {
        	 if($this->enablePerformReport)
    			$this->_report->endPerfReport(0);
        	return false;  
        }
        
        if(!in_array($type, array(self::MENU_TYPE_CREATE, self::MENU_TYPE_GET, self::MENU_TYPE_DELETE)))
        {
        	Mod::log('type:'.$type.' not in the array',CLogger::LEVEL_ERROR,'components.weixin');
			if($this->enablePerformReport)
    			$this->_report->endPerfReport(0);
        	return false;
        }
		
	if($type == self::MENU_TYPE_CREATE && !empty($data) ){
		$data = $this->__proxyMenu($data, $withMenu);			
	}
        
        $menuConfUrl = "https://api.weixin.qq.com/cgi-bin/menu/". $type ."?access_token=". $this->access_token['access_token'];
        $menuConfUrl = $this->__inner_proxy($menuConfUrl);
	$result = $this->phpPost($menuConfUrl, $data);
        if($this->enablePerformReport)
    			$this->_report->endPerfReport(0);
        return $result;
    }

	private function __arrayRecursive(&$array, $function, $apply_to_keys_also = false)
	{
		static $recursive_counter = 0;
		if (++$recursive_counter > 1000) {
		    die('possible deep recursion attack');
		}
		foreach ($array as $key => $value) {
		    if (is_array($value)) {
		        $this->__arrayRecursive($array[$key], $function, $apply_to_keys_also);
		    } else {
		        $array[$key] = $function($value);
		    }

		    if ($apply_to_keys_also && is_string($key)) {
		        $new_key = $function($key);
		        if ($new_key != $key) {
		            $array[$new_key] = $array[$key];
		            unset($array[$key]);
		        }
		    }
		}
		$recursive_counter--;
	}

	private function __urlencode($array) 
	{
		$this->__arrayRecursive($array, 'urlencode', true);
		$json = json_encode($array);
		return urldecode($json);
	}
	
	private function __proxyMenu($data, $withMenu)
	{
		$arr = json_decode($data, true);
		if(is_array($arr) && $this->useProxy === true && $withMenu === true )
		{
			foreach($arr as $k1 => &$row1)
			{
				if($k1 == 'url') {
					$row1 = $this->__outer_proxy($row1);
					continue;
				}
				if($k1 == 'button' && is_array($row1)){
					foreach($row1 as &$row2)
					{
						if($row2['url']){
							$row2['url'] = $this->__outer_proxy($row2['url']);
							continue;
						} 
						if(!empty($row2['sub_button'])){
							foreach($row2['sub_button'] as &$row3)
							{
								if($row3['url']) 
									$row3['url'] = $this->__outer_proxy($row3['url']);
							}
						}
					}
				}
			}
		}
		return $this->__urlencode($arr);
	}
    /*
     *创建媒体文件
     */
    public function createMedia($gAccount, $type, $data)
    {
    	if($this->enablePerformReport){
    		$this->_report = new CPhpPerfReporter();
    		$this->_report->beginPerfReport(Mod::app()->id,'','',false,'','');
    	}
        $re = $this->getAccessToken($gAccount);
        if(0 != $re)
        {
        	 if($this->enablePerformReport)
    			$this->_report->endPerfReport(0);
        	return false;  
        }
        
        if(!in_array($type, array(self::MEDIA_TYPE_IMAGE, self::MEDIA_TYPE_VOICE, self::MEDIA_TYPE_VIDEO, self::MEDIA_TYPE_THUMB)))
        {
        	Mod::log('type:'.$type.' not in the array',CLogger::LEVEL_ERROR,'components.weixin');
		if($this->enablePerformReport)
    			$this->_report->endPerfReport(0);
        	return false;
        }
        
        $mediaConfUrl = "https://api.weixin.qq.com/cgi-bin/media/upload?access_token=". $this->access_token['access_token'] . "&type=" . $type;
        $result = $this->phpPost($this->__inner_proxy($mediaConfUrl), $data);
        if($this->enablePerformReport)
    			$this->_report->endPerfReport(0);
        return $result;
    }
    /*
     *发送客服消息
     */
    public function sendPushMsg($gAccount, $data)
    {
    	if($this->enablePerformReport){
    		$this->_report = new CPhpPerfReporter();
    		$this->_report->beginPerfReport(Mod::app()->id,'','',false,'','');
    	}
        $re = $this->getAccessToken($gAccount);
        if(0 != $re)
        {
        	 if($this->enablePerformReport)
    			$this->_report->endPerfReport(0);
        	return false;  
        }
        
        $menuConfUrl = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=". $this->access_token['access_token'];
        $result = $this->phpPost($this->__inner_proxy($menuConfUrl), $data);
        if($this->enablePerformReport)
    			$this->_report->endPerfReport(0);
        return $result;
    }
    
    /*
     *拉取公众帐号关注用户
     */
    public function getGAccountUsers($gAccount, $nextOpenId='')
    {
    	$apiTpl = "https://api.weixin.qq.com/cgi-bin/user/get?access_token=%s";
    	$apiTailTpl = "&next_openid=%s";
    	
    	if($this->enablePerformReport){
    		$this->_report = new CPhpPerfReporter();
    		$this->_report->beginPerfReport(Mod::app()->id,'','',false,'','');
    	}
    	$rst = $this->getAccessToken($gAccount);
        if(0 != $rst)
        {
        	if($this->enablePerformReport)
    			$this->_report->endPerfReport(0);
        	return false;  
        }
    	
        $url = sprintf($apiTpl, $this->access_token['access_token']);
        if(!empty($nextOpenId))
        {
        	$url .= sprintf($apiTailTpl, $nextOpenId);
        }
        
        $url = $this->__inner_proxy($url);
        $result = $this->phpGet($url);
        if($this->enablePerformReport)
    			$this->_report->endPerfReport(0);
    			
        return $result;
    }
    /**
    * 发送消息的校验
    * @retrun errno, 0 success, -1 base info error; -2 content error
    */
    private function checkSetMsg($msgType, $msgInfo)
    {
        //参数校验
        if (empty($msgInfo) || empty($msgInfo['ToUserName']) || empty($msgInfo['FromUserName']))
        {
            return -1;
        }

        switch($msgType)
        {
        	case 'text':
	        	if (empty($msgInfo['Content']))
	            {
	                return -2;
	            }
	            break;
        	case 'image':
	        	if (empty($msgInfo['MediaId']))
	            {
	                return -2;
	            }
	            break;
        	case 'news':
        		//文章参数
	            if (empty($msgInfo['Articles']) || empty($msgInfo['ArticleCount']) || (count($msgInfo['Articles']) != $msgInfo['ArticleCount']))
	            {
	                return -2;
	            }
	            
	            break;
		case 'music':
        		//music参数
	            if (empty($msgInfo['ThumbMediaId']))
	            {
	                return -2;
	            }
	            break;	
        	default:
	            return -3;
        		break;
        }

        return 0;
    }
    
    private function getAccessToken($gAccount){
    	if($this->enablePerformReport){
    		$this->_report = new CPhpPerfReporter();
    		$this->_report->beginPerfReport(Mod::app()->id,'','',false,'','');
    	}
		$nowtime = strtotime('now');
		if(!empty($this->access_token) && $this->access_token['valid_time'] >= $nowtime)
		{
			return 0;
		}
		
        $dbAccessTokenInfo = $this->_GetAccessTokenFromDB($gAccount);
        $isExist = false;
		if(!empty($dbAccessTokenInfo))
		{
			if($dbAccessTokenInfo['FExpiredTime'] >= $nowtime)
			{
				$access_token['valid_time'] = strtotime('now') + $access_token['expires_in'];
				$this->access_token = array(
				    'access_token' => $dbAccessTokenInfo['FAccessToken'],
				    'valid_time'   => $dbAccessTokenInfo['FExpiredTime'],
				);
				if($this->enablePerformReport)
    				$this->_report->endPerfReport(0);
				return 0;
			}
			
			$isExist = true;
			
		}
    
		if(!isset($this->conf[$gAccount]))
		{
			return -1;
		}
		
		$url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$this->conf[$gAccount]['appid'].'&secret='.$this->conf[$gAccount]['secret'];
		
        $url = $this->__inner_proxy($url);
        $access_token = $this->phpGet($url);
		//$access_token = '{"access_token":"or-aHbEuRjua4QHKVRREwEIuivTTuQvnlcxfpoVoL8xdnPHgDUXA71EySv8XykJbrRGeairyHRHhzdAopffu0c1eg9f0Z5gv9OSb9DIcG7tFMpUyCMSv7EqzjOHWFD-yDGqDVurb2PwQ4LKTAzfuKA","expires_in":7200}';
		$access_token = json_decode($access_token,true);
		if(!isset($access_token['access_token']))
		{
			if($this->enablePerformReport)
    				$this->_report->endPerfReport(0);
			return -1;
		}
		
		$access_token['access_token'] = $access_token['access_token'];
		$access_token['valid_time'] = strtotime('now') + $access_token['expires_in'];
		$this->access_token = $access_token;
		
		$accessInfo = array(
		    'FId' => $isExist ? $dbAccessTokenInfo['FId'] : 0,
		    'FGlobalAccount' => $gAccount,
		    'FAccessToken'   => $access_token['access_token'],
		    'FExpiredTime'   => strtotime('now') + $access_token['expires_in'],
		    'FUpdateTime'    => date('Y-m-d H:i:s'),
		    'FStatus'        => 1
		); 
		
		$this->_UpdateAccessToken($accessInfo);
		if($this->enablePerformReport)
			$this->_report->endPerfReport(0);
		return 0;
    }
    
    private function _GetAccessTokenFromDB($gAccount)
    {
    	$sql = "select * from t_weixin_access_token where FStatus=1 and FGlobalAccount='". $gAccount ."'";
    	$DBConn = Mod::app()->db;
        $rstTmp = $DBConn->createCommand($sql)->queryAll();
        
        return $rstTmp[key($rstTmp)];
    }
    
    private function _UpdateAccessToken($accessTokenInfo)
    {
    	$id = $accessTokenInfo['FId'];
    	unset($accessTokenInfo['FId']);
    	
    	$DBConn = Mod::app()->db;
    	$sql = '';
    	if(empty($id))
    	{
    		$columns = "(". implode(",", array_keys($accessTokenInfo)) .")";
        	$values  = "('". implode("' , '", array_values($accessTokenInfo))."')";
        
			$sql = "insert into t_weixin_access_token ". $columns ." values ". $values;
    	}else 
    	{
	    	$sql = "update t_weixin_access_token set ";
			
			$setStr = '';
			foreach($accessTokenInfo as $key=>$value)
			{
				$setStr .= $key ."='". $value ."',";
			}
			$setStr = trim($setStr, ',');
			
			$sql .= $setStr;
			$sql .= " where FId=". $id;
    	}
    	
		$DBConn->createCommand($sql)->execute();
        
        return true;
    }
    
    /*
     * 支付系列的函数
     * 生成支付所需要的package字段
     * params array(
     * 		'bank_type'=>'WX',//necessary
     * 		'body'=>'this is a good good',//necessary
     * 		'attach'=>'I will send back by wenxin',//not neccessary
     * 		'partner'=>'dgdgdgdg',//the partnerId you get when you apply the pay,necessary
     * 		'out_trade_no'=>'12333',//商户系统内唯一的订单号，necessary
     * 		'total_fee'=>20000,//总支付额,单位分,necessary
     * 		'fee_type'=>1,//支付币种,目前只支持1,人民币,necessary
     * 		'notify_url'=>'http://domain.webdev.com/weixin_notify',//微信支付成功后的回调,necessary
     * 		'spbill_create_ip'=>'127.0.0.1',//客户端ip,necessary
     * 		'time_start'=>'20131018150323',//订单生成时间,格式必须如实例,not necessary
     * 		'time_expire'=>'20131019150323',//交易结束时间， 也是订单失效时间,格式必须入实例,not necessary
     * 		'transport_fee'=>5000,//如果这里填了，那么就要保证transport_fee+product_fee=total_fee,not necessary
     * 		'product_fee'=>15000,//如果这里填了，那么就要保证transport_fee+product_fee=total_fee,not necessary
     * 		'goods_tag'=>'',//商品标记,not necessary
     * 		'input_charset'=>'UTF-8',//necessary
     * )
     * params paternerkey
     * return string
     */
    
    public function genPackage($packarr,$paternerkey){
    	//first check the params
    	$cr = $this->checkPackgeParam($packarr);
    	if($cr['code'] != 0)
    		return $cr;
    	//按字典排序
    	ksort($packarr);
    	//组装成querystring类型的字符串
    	$str = $this->arrayToQuerystring($packarr);
    	//拼接上paternerkey
    	$str .= '&key='.$paternerkey;
    	//进行MD5转换
    	$sign =  strtoupper(md5((string)$str));
    	//对	$packarr的数据进行urlencode转码
    	foreach($packarr as $k=>$v){
    		$packarr[$k] = urlencode($v);
    	}
    	$str =  $this->arrayToQuerystring($packarr);
    	$str .= '&sign='.$sign;
    	return $str; 
    }
    
    /*
     * 支付系列函数
     * 生成支付所需要的签名
     * param array(
     * 		'appid'=>'sssssss',//
     * 		'timestamp'=>'189026618',//时间戳
     * 		'noncestr'=>'aasdsassaa',//前面生成随机字符串
     * 		'package'=>'',//生成的package
     * 		'appkey' =>'',//
     * )
     * 
     * return string
     */
    
    public function genPaySign($arr){
    	//按字典排序
    	ksort($arr);
    	//组装成querystring类型的字符串
    	$str = $this->arrayToQuerystring($arr);
    	$str = sha1($str);
    	return $str;
    }
    
    /*
     * 支付系列函数
     * 生成native格式的支付url
     * param array(
     * 		'appid'=>'',//账户appid
     *		'timestamp'=>'',//时间戳，32字符以下
     *		'noncestr'=>'',//随机数,32字符以下
     *		'productid'=>'',//商品id,32字符以下
     * )
     * param $appkey
     * return url or array
     */
    public function genNativeUrl($paramarr,$appkey){
    	//得到signature
    	$ret = $this->checkNativeParam($paramarr);
    	if($ret['code'] != 0)
    		return $ret;
    	$sign = $this->genNativeSign($paramarr, $appkey);
    	$paramarr['sign'] = $sign;
    	$url = 'weixin://wxpay/bizpayurl?';
    	$str = $this->arrayToQuerystring($paramarr);
    	$url .= $str;
    	return $url;
    }
    
    /*
     * 支付系列函数
     * 生成native支付url的签名
     * param array(
     * 		'appid'=>'',//账户appid
     *		'timestamp'=>'',//时间戳，32字符以下
     *		'noncestr'=>'',//随机数,32字符以下
     *		'productid'=>'',//商品id,32字符以下
     * )
     * param $appkey
     * return str 
     */
    public function genNativeSign($paramarr, $appkey){
    	//检查参数是否符合要求
    	$paramarr['appkey'] = $appkey;
    	ksort($paramarr);
    	$str = $this->arrayToQuerystring($paramarr);
    	return sha1($str);
    }
    
    /*
     * 支付系列函数
     * native方式回调获取package包
     * param xml
     * return boolean
     */
    public function checkPackageCallback($xmldata,$appkey=''){
    	$clientpack = simplexml_load_string($xmldata, 'SimpleXMLElement',LIBXML_NOCDATA );
    	$clientpack = (array)$clientpack;
    	if( isset($clientpack['AppId']) && isset($clientpack['OpenId']) && isset($clientpack['IsSubscribe']) && isset($clientpack['ProductId']) 
    			&& isset($clientpack['TimeStamp']) && isset($clientpack['NonceStr']) && isset($clientpack['AppSignature']) && isset($clientpack['SignMethod']) )
    	{
			$param = $clientpack;
			$param['appkey'] = $appkey;
			unset($param['AppSignature']);
			unset($param['SignMethod']);
    		//比对签名
			$sign = $this->genPaySign($param); 
			return  ($sign == $clientpack['AppSignature']);	
    	}
    	else return false;
    }
    
    /*
     * 支付系列函数
     * 返回给回调的xml文件
     * param array(
     * 		'appid'=>'',
     * 		'package'=>'',
     * 		'timestamp'=>,
     * 		'noncestr'=>'',
     * 		'retcode'=>'',
     * 		'reterrmsg'=>'',//utf-8编码
     * 		'appsignature'=>'',
     * 		'signmethod'=>'',//可不填，默认sha1,目前也只支持sha1
     * )
     * return xmlstr
     */
    
    public function genPackageXml($paramarr){
    	$signmethod = isset($paramarr['signmethod']) ? $paramarr['signmethod'] : 'sha1';
    	$xml = sprintf($this->_PackageTpl,$paramarr['appid'],$paramarr['package'],$paramarr['timestamp'],$paramarr['noncestr'],$paramarr['retcode'],
    					$paramarr['reterrmsg'],$paramarr['appsignature'],$signmethod);
    	return $xml;
    } 
    
   /*
    * 支付系列函数
    * 校验url参数支付是否来自微信
    * param array(
    * 
    * )
    * return boolean
    */
    public function checkNotifySign($paramarr, $paternerkey){
    	$param = array();
    	foreach($paramarr as $k=>$v){
    		if($k == 'sign'){
    			$sign = $v;
    			continue;
    		}
    		if($v != '' && $v != NULL)
    			$param[$k]=$v;
    		
    	}
    	ksort($param);
    	$str = $this->arrayToQuerystring($param);
    	$str .= '&key='.$paternerkey;
    	$strtmp  = md5($str);
    	if($sign == strtoupper($strtmp))
    		return true;
    	else return false;
    }
    
    /*
     * 支付系列函数
    * 校验postdata支付是否来自微信
    * param xml
    * return boolean or array
    */
    public function checkNotifyAppSign($xml){
    	$xmldata = simplexml_load_string($xmldata, 'SimpleXMLElement',LIBXML_NOCDATA );
    	$xmldata = (array)xmldata;
    	if(isset($xmldata['AppSignature']))
    		$appsignature = $xmldata['AppSignature'];
    	else return false;
    	unset( $xmldata['AppSignature'] );
    	$sign = $this->genPaySign($xmldata);
    	if($appsignature == $sign)
    		return true;
    }
    /*
     * 支付系列的函数
     * 检查生成Nativeurl各个字段是否满足要求
     */
    public function checkNativeParam($paramarr){
    	$ret = array('code'=>-1,'msg'=>'');
    	if(!isset($paramarr['timestamp']) || strlen($paramarr['timestamp']) > 32){
    		$ret['msg'] = 'timestamp needed and length must be under 32';
    		return $ret;
    	}
    	if(!isset($paramarr['noncestr']) || strlen($paramarr['noncestr']) > 32){
    		$ret['msg'] = 'noncestr needed and length must be under 32';
    		return $ret;
    	}
    	if(!isset($paramarr['productid']) || strlen($paramarr['productid']) > 32){
    		$ret['msg'] = 'productid needed and length must be under 32';
    		return $ret;
    	}
    	$ret['code'] = 0;
    	$ret['msg'] = 'ok';
    	return 	$ret;
    }
    
    /*
     * 支付系列的函数
    * 检查生成package的各个字段是否满足要求
    * params array(
    * 		'bank_type'=>'WX',//necessary
    * 		'body'=>'this is a good good',//necessary
    * 		'attach'=>'I will send back by wenxin',//not neccessary,
    * 		'partner'=>'dgdgdgdg',//the partnerId you get when you apply the pay,necessary
    * 		'out_trade_no'=>'12333',//商户系统内唯一的订单号，necessary
    * 		'total_fee'=>20000,//总支付额,单位分,necessary
    * 		'fee_type'=>1,//支付币种,目前只支持1,人民币,necessary
    * 		'notify_url'=>'http://domain.webdev.com/weixin_notify',//微信支付成功后的回调,necessary
    * 		'spbill_create_ip'=>'127.0.0.1',//客户端ip,necessary
    * 		'time_start'=>'20131018150323',//订单生成时间,格式必须如实例,not necessary
    * 		'time_expire'=>'20131019150323',//交易结束时间， 也是订单失效时间,格式必须入实例,not necessary
    * 		'transport_fee'=>5000,//如果这里填了，那么就要保证transport_fee+product_fee=total_fee,not necessary
    * 		'product_fee'=>15000,//如果这里填了，那么就要保证transport_fee+product_fee=total_fee,not necessary
    * 		'goods_tag'=>'',//商品标记,not necessary
    * 		'input_charset'=>'UTF-8',//necessary
    * )
    */
    public function checkPackgeParam($packarr){
    	$ret = array('code'=>-1,'msg'=>'');
    	if(!isset($packarr['bank_type']) || $packarr['bank_type'] != 'WX'){
    		$ret['msg'] = 'bank_type needed and must set WX';
    		return $ret;
    	}
    	if(!isset($packarr['body']) || strlen($packarr['body']) > 128){
    		$ret['msg'] = 'body needed and length must be under 128';
    		return $ret;
    	}
    	if(isset($packarr['attach']) && strlen($packarr['attach']) > 128){
    		$ret['msg'] = 'if you set attach,please control the length under 128';
    		return $ret;
    	}
    	if(!isset($packarr['partner'])){
    		$ret['msg'] = 'partner needed';
    		return $ret;
    	}
    	if(!isset($packarr['out_trade_no']) || strlen($packarr['out_trade_no']) > '32'){
    		$ret['msg'] = 'out_trade_no needed and length must be under 32';
    		return $ret;
    	}
    	if(!isset($packarr['total_fee'])){
    		$ret['msg'] = 'total_fee needed';
    		return $ret;
    	}
    	if(!isset($packarr['fee_type']) || $packarr['fee_type'] != 1){
    		$ret['msg'] = 'fee_type needed and must be set 1';
    		return $ret;
    	}
    	if(!isset($packarr['notify_url']) || strlen($packarr['notify_url']) > 255){
    		$ret['msg'] = 'notify_url needed and length must be under 255';
    		return $ret;
    	}
    	if(!isset($packarr['spbill_create_ip']) || strlen($packarr['spbill_create_ip']) > 15){
    		$ret['msg'] = 'spbill_create_ip needed and length must under 15';
    		return $ret;
    	}
    	if( isset($packarr['product_fee']) || isset($packarr['transport_fee']) ){
    		$product_fee = isset($packarr['product_fee'])?$packarr['product_fee']:0;
    		$transport_fee = isset($packarr['transport_fee'])?$packarr['transport_fee']:0;
    		$tfee = $product_fee + $transport_fee;
    		if($tfee != $packarr['total_fee']){
				$ret ['msg'] = 'product_fee + transport_fee must equal total_fee';
				return $ret;
    		}
    	}
    	if(!isset($packarr['input_charset']) || ($packarr['input_charset'] !='GBK' && $packarr['input_charset'] != 'UTF-8')){
    		$ret ['msg'] = 'input_charset must be set UTF-8 or GBK';
    		return $ret;
    	}
    	$ret['code'] = 0;
    	$ret['msg'] = 'ok';
    	return $ret;
    }
    
    /*
     * 数组变成querystring
     * param array
     * return string
     */
    public function arrayToQuerystring($arr){
    	$ret = '';
    	foreach($arr as $k=>$v){
    		$k = strtolower($k);
    		$ret .= $k.'='.$v.'&';
    	}
    	$ret = substr($ret, 0, -1);
    	return $ret;
    }
    
    
    private function phpGet($url,$refer=''){
        $ch = curl_init($url);
        $options = array(
                CURLOPT_RETURNTRANSFER => true,         // return web page
                CURLOPT_HEADER         => false,        // don't return headers
                CURLOPT_FOLLOWLOCATION => true,         // follow redirects
                CURLOPT_ENCODING       => "",           // handle all encodings
                CURLOPT_USERAGENT      => "",           // who am i
                CURLOPT_AUTOREFERER    => true,         // set referer on redirect
                CURLOPT_CONNECTTIMEOUT => 5,            // timeout on connect
                CURLOPT_TIMEOUT        => 5,            // timeout on response
                CURLOPT_MAXREDIRS      => 10,           // stop after 10 redirects
                CURLOPT_SSL_VERIFYHOST => 0,            // don't verify ssl
                CURLOPT_SSL_VERIFYPEER => false,        //
                CURLOPT_COOKIEFILE     =>'./',
                CURLOPT_COOKIEJAR      =>'./',
                CURLOPT_REFERER        =>$refer,
        );
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
    
    private function phpPost($url, $postfields, $refer='')
    {
        $ch = curl_init($url);
        $options = array(
            CURLOPT_RETURNTRANSFER => true,         // return web page
            CURLOPT_HEADER         => false,        // don't return headers
            CURLOPT_FOLLOWLOCATION => true,         // follow redirects
            CURLOPT_ENCODING       => "",           // handle all encodings
            CURLOPT_USERAGENT      => "",           // who am i
            CURLOPT_AUTOREFERER    => true,         // set referer on redirect
            CURLOPT_CONNECTTIMEOUT => 120,          // timeout on connect
            CURLOPT_TIMEOUT        => 120,          // timeout on response
            CURLOPT_MAXREDIRS      => 10,           // stop after 10 redirects
            CURLOPT_POST           => true,         // i am sending post data
            CURLOPT_POSTFIELDS     => $postfields,  // this are my post vars
            CURLOPT_SSL_VERIFYHOST => 0,            // don't verify ssl
            CURLOPT_SSL_VERIFYPEER => false,        //
            CURLOPT_COOKIEFILE     =>$this->cookiefilepath,
            CURLOPT_COOKIEJAR      =>$this->cookiefilepath,
            CURLOPT_REFERER        =>$refer,
        );
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
	
	private function __inner_proxy($url)
	{
		if($this->useProxy === true)
			return 'http://mp.seals.webdev.com/proxy/proxyServe?url='.urlencode($url); 
		return $url;	
	}
	
	private function __outer_proxy($url)
	{
		if($this->useProxy === true)
			return 'http://mp.seals.qq.com/proxy/proxyServe?url='.urlencode($url).'&appName='.Mod::app()->id; 
		return $url;	
	}
	
    /*支付系列函数
         发货通知 delivernotify   
         $data 为 除了app_signature和sign_method以外的数组
         例子：
         $data = array(
            'appid'=>"yuwqyui1231",
            'openid'=>"suiyueir23123",
            'transid'=>"123123123",
            'deliver_timestamp'=>time(),//时间戳
            'out_trade_no'=>"123123123123",//前面生成随机字符串
            "deliver_status" => "1",   
            "deliver_msg" => "ok", 
            'appkey' =>"sadasdasd123",//
        );
    */
    public function delivernotify($gAccount,$data){
        if($this->enablePerformReport){
            $this->_report = new CPhpPerfReporter();
            $this->_report->beginPerfReport(Mod::app()->id,'','',false,'','');
        }
        $rst = $this->getAccessToken($gAccount);
        if(0 != $rst)
        {
            if($this->enablePerformReport)
                $this->_report->endPerfReport(0);
            return false;  
        }
        $menuConfUrl = "https://api.weixin.qq.com/pay/delivernotify?access_token=". $this->access_token['access_token'];
        $sign = $this->genPaySign($data);
        $data['app_signature'] =$sign;
        $data['sign_method'] ="sha1";
        $json_data = json_encode($data);
        $result = $this->phpPost($this->__inner_proxy($menuConfUrl), $json_data);
        if($this->enablePerformReport)
                $this->_report->endPerfReport(0);
        return $result;

    }
    /*
     * 生成accesstoken表
     * 需要用户指定数据库
     * param $dbobj(Mod::app->db实例化出来的)
     * return boolean
     */
    public function genAccessTokenTable($dbobj){
    	$sql = "CREATE TABLE `t_weixin_access_token` (
		    	`FId` int(10) unsigned NOT NULL auto_increment COMMENT '自增ID',
		    	`FGlobalAccount` varchar(32) NOT NULL default '' COMMENT '公众帐号',
		    	`FAccessToken` varchar(1024) NOT NULL default '' COMMENT 'access token',
		    	`FExpiredTime` int(11) NOT NULL default '0' COMMENT '过期时间',
		    	`FUpdateTime` datetime NOT NULL default '1970-01-01 00:00:00' COMMENT '更新时间',
		    	`FStatus` tinyint(4) NOT NULL default '1' COMMENT '1: 发布; 0: 草稿; -1: 删除',
		    	PRIMARY KEY  (`FId`),
		    	KEY `FGlobalAccount` (`FGlobalAccount`)
		    	) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='微信access token表'
    		";
    	return $dbobj->createCommand($sql)->execute();
    }

    private function checkAccessTokenTable()
    {
    	try{
		$db = Mod::app()->db;
		if($db->getSchema()->getTable('t_weixin_access_token') === null)
		{
			$this->genAccessTokenTable($db);
		}
	}catch(Exception $e){
		Mod::log(addslashes($e->getMessage()), CLogger::LEVEL_ERROR, 'components.weixin');
	}
    }
    
}
