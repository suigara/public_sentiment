<?php

class CWeChatClient extends CApplicationComponent {
	
	// SETTING
	// Tail this file N U will see.
	public static $_URL_API_ROOT;
	public static $_URL_FILE_API_ROOT;
	public static $_QRCODE_TICKET_DEFAULT_ID = 1;
	public static $ERRCODE_MAP;
	public static $_USERINFO_LANG;
	
	public static $_ACCESS_TOKEN_KEY_PREFIX ;
	public static $_PROXY_URL ;
	// DATA
	public $appid;
	public $appsecret;
	public $cache = 'cache';
	public $proxy_mode = false; 
	
	private static $_cache ;
	private static $ERROR_LOGS = array();
	
	public function __construct( $appid=null, $appsecret=null ){
		$this->appid     = $appid;
		$this->appsecret = $appsecret;
		if(self::$_cache === null){
			self::$_cache = Mod::app()->getComponent($this->cache);
		}
	}
	
	/**
	 * 
	 * @param array $res
	 * @return boolean
	 */
	private function checkIsSuc( $res ){
		$result = true;
		if( is_string( $res ) ){
			$res = json_decode( $res, true );
		}
		if( isset($res['errcode']) && ( 0 !== (int)$res['errcode']) ){
			array_push( self::$ERROR_LOGS, $res );
			$result = false;
		}
		return $result;
	}
    
    /**
    *
    * @ return array();
    */
    public function getErrors()
    {
        $errors = array();
        foreach( self::$ERROR_LOGS as $e)
        {
            $errors[] = $e['errmsg'];
        }
        return $errors;
    }
   
	/**
	 * @method get
	 * @static
	 * @param  {string}
	 * @return {string|boolen}
	 */
	private function get( $url ){
		$ch = curl_init();
		if ($this->proxy_mode === true) {
			curl_setopt ( $ch, CURLOPT_URL, self::$_PROXY_URL . urlencode ( $url ) );
		} else {
			curl_setopt ( $ch, CURLOPT_URL, $url );
		}

		# curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	
		if(!curl_exec($ch)){
			error_log( curl_error ( $ch ));
			$data = '';
		} else {
			$data = curl_multi_getcontent($ch);
	
		}
		curl_close($ch);
		return $data;
	}
	
	/**
	 * @method post
	 * @static
	 * @param  {string}        $url URL to post data to
	 * @param  {string|array}  $data Data to be post
	 * @return {string|boolen} Response string or false for failure.
	 */
	private function post( $url, $data ){
		$ch = curl_init();
		if ($this->proxy_mode === true) {
			curl_setopt ( $ch, CURLOPT_URL, self::$_PROXY_URL . urlencode ( $url ) );
		} else {
			curl_setopt ( $ch, CURLOPT_URL, $url );
		}
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		# curl_setopt( $ch, CURLOPT_HEADER, 1);
	
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );
	
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
		$data = curl_exec($ch);
		if(!$data) error_log( curl_error ( $ch ) );
		curl_close( $ch );
		return $data;
	}
	
	/**
	 * 
	 * @param boolean $tokenOnly
	 * @return 
	 */
	public function getAccessToken( $tokenOnly = 1 ){
		$myToeknInfo = null;
		$appid       = $this->appid;
		$appsecret   = $this->appsecret;
		// check cache
		if (self::$_cache && ($tokeninfo = self::$_cache->get ( self::$_ACCESS_TOKEN_KEY_PREFIX . $appid ))) {
			if (time () < $tokeninfo ['expire']) {
				return $tokenOnly ? $tokeninfo ['token'] : $tokeninfo;
			}
		}
	
		// get new token
		$url = self::$_URL_API_ROOT;
		$url  = "$url/cgi-bin/token?grant_type=client_credential&appid=$appid&secret=$appsecret";
	
		$json = $this->get( $url );
		$res = json_decode( $json, true );
	
		if ( $this->checkIsSuc($res) ){
			
			$tokeninfo = array(
					'token'  => $res['access_token'],
					'expire' => time() + (int) $res['expires_in']
			);
			// update cache
			if (self::$_cache)
				self::$_cache->set ( self::$_ACCESS_TOKEN_KEY_PREFIX . $appid, $tokeninfo, $res ['expires_in'] );
		}
		return $tokenOnly ? $tokeninfo['token'] : $tokeninfo;
	}
	
	
	// *************** media file upload/download ************
	public function upload( $type, $file_path, $mediaidOnly = 1 ){
		$access_token = $this->getAccessToken();
		$url = self::$_URL_FILE_API_ROOT . "/cgi-bin/media/upload?access_token=$access_token&type=$type";
	
		$res = $this->post( $url, array( 'media' => "@$file_path" ) );
		$res = json_decode( $res, true );
	
		if( $this->checkIsSuc( $res ) ){
			return $mediaidOnly ? $res['media_id'] : $res;
		}
		return null;
	}
	
	public function download( $mid ){
		$access_token = $this->getAccessToken();
		$url = self::$_URL_FILE_API_ROOT . "/cgi-bin/media/get?access_token=$access_token&media_id=$mid";
	
		return $this->get( $url );
	}
	
	// *************** MENU ******************
	public function getMenu(){
	
		$access_token = $this->getAccessToken();
		$url = self::$_URL_API_ROOT . "/cgi-bin/menu/get?access_token=$access_token";
		$json = $this->get($url);
		$res = json_decode($json, true);
		if( $this->checkIsSuc( $res ) ){
			return $res['menu'];
		}
		return null;
	}
	
	public function deleteMenu(){
		$access_token = $this->getAccessToken();
		$url = self::$_URL_API_ROOT . "/cgi-bin/menu/delete?access_token=$access_token";
	
		$res = $this->get($url);
		return $this->checkIsSuc( $res );
	}
	
	public function setMenu( $myMenu ){
		$access_token = $this->getAccessToken();
		$url = self::$_URL_API_ROOT . "/cgi-bin/menu/create?access_token=$access_token";
	
		if(is_string($myMenu)){
            $myMenu = json_decode($myMenu , true);
		}
        $this->_menu_encode($myMenu, 'urlencode', true);
        $json = urldecode(json_encode($myMenu));

		$res = $this->post($url, $json);
	
		return $this->checkIsSuc( $res );
	}

    private function _menu_encode(&$array, $function, $apply_to_keys_also = false)
    {
        static $recursive_counter = 0;
        if (++$recursive_counter > 1000) {
            die('possible deep recursion attack');
        }
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $this->_menu_encode($array[$key], $function, $apply_to_keys_also);
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

	// *************** send msg ******************
	private  function _send( $to, $type, $data ){
		$access_token = $this->getAccessToken();
		$url = self::$_URL_API_ROOT . "/cgi-bin/message/custom/send?access_token=$access_token";
	
		$json = json_encode(
				array(
						'touser'  => $to,
						'msgtype' => $type,
						$type     => $data
				)
		);
	
		$res = $this->post($url, $json);
	
		return $this->checkIsSuc( $res );
	}
	
	public function sendTextMsg( $to, $msg ){
		return $this->_send( $to, 'text', array( 'content' => $msg ) );
	}
	
	public function sendImgMsg( $to, $mid ){
		return $this->_send( $to, 'image', array( 'media_id' => $mid ) );
	}
	
	public function sendVoice( $to, $mid ){
		return $this->_send( $to, 'voice', array( 'media_id' => $mid ) );
	}
	
	public function sendVideo( $to, $mid, $title, $desc ){
		return $this->_send( $to, 'video', array(
				'media_id'    => $mid,
				'title'       => $title,
				'description' => $desc
		) );
	}
	
	public function sendMusic( $to, $url, $thumb_mid, $title, $desc = '', $hq_url = '' ){
		return $this->_send( $to, 'music', array(
				'title'          => $title,
				'description'    => $desc || $title,
				'musicurl'       => $url,
				'thumb_media_id' => $thumb_mid,
				'hqmusicurl'     => $hq_url || $url
		) );
	}
	
	static private function _filterForRichMsg( $articles ){
		$i = 0;
		$ii = count( $articles );
		$list = array( 'title', 'desc', 'url', 'thumb_url' );
		$result = array();
		while( $i < $ii ){
			$currentArticle = $articles[ $i++ ];
			try{
				array_push( $result, array(
				'title'       => $currentArticle['title'],
				'description' => $currentArticle['desc'],
				'url'         => $currentArticle['url'],
				'picurl'      => $currentArticle['thumb_url']
				) );
			} catch( Exception $e ){}
		}
		return $result;
	}
	
	public function sendRichMsg( $to, $articles ){
	
		return $this->_send( $to, 'news', array(
				'articles' => self::_filterForRichMsg( $articles )
		) );
	}
	
	// *************** followers admin ******************
	// follower group
	public function createGroup( $name ){
		$access_token = $this->getAccessToken();
		$url = self::$_URL_API_ROOT . "/cgi-bin/groups/create?access_token=$access_token";
	
		$res = $this->post( $url, json_encode( array(
				'group' => array( 'name' => $name )
		) ) );
	
		$res = json_decode( $res, true );
		return $this->checkIsSuc( $res ) ? $res['group']['id'] : null;
	}
	
	public function renameGroup( $gid, $name ){
		$access_token = $this->getAccessToken();
		$url = self::$_URL_API_ROOT . "/cgi-bin/groups/update?access_token=$access_token";
	
		$res = $this->post( $url, json_encode( array(
				'group' => array(
						'id'   => $gid,
						'name' => $name
				)
		) ) );
	
		$res = json_decode( $res, true );
		return $this->checkIsSuc( $res );
	}
	
	public function moveUserById( $uid, $gid ){
		$access_token = $this->getAccessToken();
		$url = self::$_URL_API_ROOT . "/cgi-bin/groups/members/update?access_token=$access_token";
	
		$res = $this->post(
				$url,
				json_encode(
						array(
								'openid'     => $uid,
								'to_groupid' => $gid
						)
				)
		);
	
		$res = json_decode( $res, true );
		return $this->checkIsSuc( $res );
	}
	
	public function getAllGroups(){
		$access_token = $this->getAccessToken();
		$url = self::$_URL_API_ROOT . "/cgi-bin/groups/get?access_token=$access_token";
	
		$res = $this->get( $url );
		echo $res;
		$res = json_decode( $res, true );
	
		return $this->checkIsSuc( $res ) ? $res['groups'] : null;
	}
	
	public function getGroupidByUserid( $uid ){
		$access_token = $this->getAccessToken();
		$url = self::$_URL_API_ROOT . "/cgi-bin/groups/getid?access_token=$access_token";
	
		$res = $this->post( $url, json_encode( array(
				'openid' => $uid
		) ) );
	
		$res = json_decode( $res, true );
		return $this->checkIsSuc( $res ) ? $res['groupid'] : null;
	}
	
	// *************** Followers info ******************
	public function getUserInfoById( $uid, $lang='' ){
		if( !$lang ) $lang = self::$_USERINFO_LANG;
		$access_token = $this->getAccessToken();
		$url = self::$_URL_API_ROOT . "/cgi-bin/user/info?access_token=$access_token&openid=$uid&lang=$lang";
	
		$res = json_decode( $this->get( $url ), true );
	
		return $this->checkIsSuc( $res ) ? $res : null;
	}
	
	public function getFollowersList( $next_id = '' ){
		$access_token = $this->getAccessToken();
		$extend = '';
		if( $next_id ){
			$extend = "&next_openid=$next_id";
		}
		$url = self::$_URL_API_ROOT . "/cgi-bin/user/get?access_token=${access_token}$extend";
	
		$res = json_decode(
				$this->get( $url ),
				true
		);
	
		return $this->checkIsSuc( $res )
		? array(
				'total'   => $res['total'],
				'list'    => $res['data']['openid'],
				'next_id' => isset( $res['next_openid'] ) ? $res['next_openid'] : null
		)
		: null;
	}
	
	// ************************** qr code *****************
	public function getQrcodeImgByTicket( $ticket ){
		return $this->get( $this->getQrcodeImgUrlByTicket( $ticket ) );
	}

	public function getQrcodeImgUrlByTicket( $ticket ){
		$ticket = urlencode( $ticket );
		return self::$_URL_API_ROOT . "/cgi-bin/showqrcode?ticket=$ticket";
	}
	
	/**
	* @description 获取 qrcode ticket
	* @param $options {Array}
	*       array(
	*           'scene_id'   => // default = 1 
	*           'expire'     => // default = 0 mean no limit /
	*           'ticketOnly' => // default = true /
	*       )
	* @return {string|null} 当ticketOnly = true, return ticket string
	* @return {array|null}  when ticketOnly = false,
	*       return array(
	*                   'ticket' => ... ,
	*                   'expire' => ... 
	*              )
	*/
	public function getQrcodeTicket( $options = array() ){
		$access_token = $this->getAccessToken();
	
		$scene_id   = isset( $options[ 'scene_id' ] )   ? (int)$options[ 'scene_id' ] : 0;
		$expire     = isset( $options[ 'expire' ] )     ? (int)$options[ 'expire' ]   : 0;
		$ticketOnly = isset( $options[ 'ticketOnly' ] ) ? $options[ 'ticketOnly' ]    : 1;
	
		if( $scene_id < 1 || $scene_id > 100000 ){
			$scene_id = self::$_QRCODE_TICKET_DEFAULT_ID;
		}
	
		$url = self::$_URL_API_ROOT."/cgi-bin/qrcode/create?access_token=$access_token";
		$data = array(
				'action_name' => 'QR_LIMIT_SCENE',
				'action_info' => array(
						'scene' => array(
								'scene_id' => $scene_id
						)
				)
		);
		if( $expire ){
			$data['expire_seconds'] = $expire;
			$data['action_name']    = 'QR_SCENE';
		}
		$data = json_encode( $data );
	
		$res = $this->post( $url, $data );
		$res = json_decode( $res, true );
	
		if( $this->checkIsSuc( $res ) ){
			return $ticketOnly ? $res['ticket'] : array(
					'ticket' => $res['ticket'],
					'expire' => $res['expire_seconds']
			);
		}
		return null;
	}
}
	
# ######################################################################
# admin.wechat.com
# ######################################################################
CWeChatClient::$_URL_API_ROOT      = 'https://api.wechat.com';
CWeChatClient::$_URL_FILE_API_ROOT = 'http://file.api.weixin.qq.com';

CWeChatClient::$_USERINFO_LANG     = 'en';
CWeChatClient::$_ACCESS_TOKEN_KEY_PREFIX = 'cwechatclient_access_token_';
CWeChatClient::$_PROXY_URL = 'http://mp.seals.webdev.com/proxy/proxyServe?url=';

# @see http://admin.wechat.com/wiki/index.php?title=Return_Codes
CWeChatClient::$ERRCODE_MAP = array (
		'-1' => 'System busy',
		'0' => 'Request succeeded',
		'40001' => 'Verification failed',
		'40002' => 'Invalid certificate type',
		'40003' => 'Invalid Open ID',
		'40004' => 'Invalid media file type',
		'40005' => 'Invalid file type',
		'40006' => 'Invalid file size',
		'40007' => 'Invalid media file ID',
		'40008' => 'Invalid message type',
		'40009' => 'Invalid image file size',
		'40010' => 'Invalid audio file size',
		'40011' => 'Invalid video file size',
		'40012' => 'Invalid thumbnail file size',
		'40013' => 'Invalid App ID',
		'40014' => 'Invalid access token',
		'40015' => 'Invalid menu type',
		'40016' => 'Invalid button quantity',
		'40017' => 'Invalid button quantity',
		'40018' => 'Invalid button name length',
		'40019' => 'Invalid button KEY length',
		'40020' => 'Invalid button URL length',
		'40021' => 'Invalid menu version',
		'40022' => 'Invalid sub-menu levels',
		'40023' => 'Invalid sub-menu button quantity',
		'40024' => 'Invalid sub-menu button type',
		'40025' => 'Invalid sub-menu button name length',
		'40026' => 'Invalid sub-menu button KEY length',
		'40027' => 'Invalid sub-menu button URL length',
		'40028' => 'Invalid custom menu user',
		'40029' => 'Invalid oauth code',
		'40030' => 'Invalid refresh token',
		'40031' => 'Invalid openid list',
		'40032' => 'Invalid openid list length',
		'40033' => 'Invalid request characters: The character "\uxxxx" cannot be included.',
		'40035' => 'Invalid parameters',
		'40038' => 'Invalid request format',
		'40039' => 'Invalid URL length',
		'40050' => 'Invalid group ID',
		'40051' => 'Invalid group name',
		'41001' => 'Parameter missing: access token',
		'41002' => 'Parameter missing: appid',
		'41003' => 'Parameter missing: refresh token',
		'41004' => 'Parameter missing: secret',
		'41005' => 'Multimedia file data missing',
		'41006' => 'Parameter missing: media id',
		'41007' => 'Sub-menu data missing',
		'41008' => 'Parameter missing: oauth code',
		'41009' => 'Parameter missing: openid',
		'42001' => 'access token timed out',
		'42002' => 'refresh token timed out',
		'42003' => 'oauth code timed out',
		'43001' => 'GET request required',
		'43002' => 'POST request required',
		'43003' => 'HTTPS request required',
		'43004' => 'The other user is not yet a follower',
		'43005' => 'The other user is not yet a follower',
		'44001' => 'Multimedia file is empty',
		'44002' => 'POST package is empty',
		'44003' => 'Rich media message is empty',
		'44004' => 'Text message is empty',
		'45001' => 'Error source: multimedia file size',
		'45002' => 'Message contents too long',
		'45003' => 'Title too long',
		'45004' => 'Description too long',
		'45005' => 'URL too long',
		'45006' => 'Image URL too long',
		'45007' => 'Audio play time over limit',
		'45008' => 'Rich media messages over limit',
		'45009' => 'Error source: interface call',
		'45010' => 'Message quantity over limit',
		'45015' => 'Response too late',
		'45016' => 'System group cannot be changed.',
		'45017' => 'System name too long',
		'45018' => 'Too many groups',
		'46001' => 'Media data missing',
		'46002' => 'This menu version doesn\'t exist.',
		'46003' => 'This menu data doesn\'t exist.',
		'46004' => 'This user doesn\'t exist.',
		'47001' => 'Error while extracting JSON/XML contents',
		'48001' => 'Unauthorized API function',
		'50001' => 'The user is not authorized for this API' 
);
# ######################################################################
