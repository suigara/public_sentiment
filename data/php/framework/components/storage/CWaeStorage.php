<?php
//主体工作类
class CWaeStorage extends CApplicationComponent
{
    /**
	 * 服务器配置信息
     * @var array{
     * 'host'=>'...'
     * 'port'=>...
     * }
	 */
	public $serverConfig = array('host'=>'127.0.0.1','port'=>30002);

    public $enablePerformReport;
	public $nameServiceKey;
	public $buid = 10000;
	public $timeout=10;
	private $_report;
	
	
	public function init()
	{
		//查询名字服务，获取host和port
		if($this->nameServiceKey)
		{
			if(Mod::app()->nameService->getHostByKey($this->nameServiceKey, $ip, $port)==false)
				throw new CException("NameServer fail, key:{$this->nameServiceKeyMaster}");
			$this->serverConfig['host'] = $ip;
			$this->serverConfig['port'] = $port;
		}
		parent::init();
	}
    /**
     * http get请求
     * @param string $cgiPath
     * @return 返回正文
     */
    private function httpGet($cgiPath, $type=false, $host='', $port=80)
    {
    	if($host != '') $fp = @fsockopen($host, $port, $errno, $errstr, 3);
        else $fp = @fsockopen($this->serverConfig['host'], $this->serverConfig['port'], $errno, $errstr, 3);
        if($fp === false) {
            Mod::log("connect time out error host:".$this->serverConfig['host'].", port:".$this->serverConfig['port'], 
            			CLogger::LEVEL_ERROR, 'smallfile.httpGet');
            return false;   
        }

        stream_set_timeout($fp,$this->timeout); //设置超时为1s
        $ret = @fwrite($fp,"GET $cgiPath HTTP/1.0\r\n\r\n");
        if($ret === false) {
            Mod::log("write data(GET $cgiPath HTTP/1.0\r\n\r\n) to fp(".$fp.") error:", 
            			CLogger::LEVEL_ERROR, 'smallfile.httpGet');
            return false;
        }
		
        $response_data = @fread($fp,512);
        if($response_data){
	        $CLSTR = 'Content-Length:';
	        $clPos = stripos($response_data,$CLSTR);
	        if($clPos !== false){
	            //暂且假设第一次读取就能读到Content-Length
	            //TODO
	            
	            $clEndPos = strpos($response_data,"\r\n",$clPos+strlen($CLSTR));
	            if($clEndPos === false) return false;
	
	            $contentLen = intval(substr($response_data,$clPos+strlen($CLSTR),$clEndPos-$clPos-strlen($CLSTR)));
	            //长度不应该为0
	            if($contentLen <= 0) return false;
				if($type){
					$CTSTR = 'Content-Type:';
	        		$cTPos = stripos($response_data,$CTSTR);
	        		$ctEndPos = strpos($response_data,"\r\n",$cTPos+strlen($CTSTR));
	        		$contenttype = substr($response_data,$cTPos+strlen($CTSTR),$ctEndPos-$cTPos-strlen($CTSTR));
				}
	            //获取正文内容
	            $pattern_str = "\r\n\r\n";
	            $pattern_pos = strpos($response_data,$pattern_str);
	            if($pattern_pos !== false) {
	                $partData = substr($response_data,$pattern_pos+strlen($pattern_str));
	            
	                while(strlen($partData) < $contentLen) {
	                    $response_data = @fread($fp,$contentLen-strlen($partData));
	                    if(empty($response_data)) {fclose($fp);return false;}
	                    $partData .= $response_data;
	                }
	                if($type && !is_numeric($partData)){
	                	fclose($fp);
	                	return array("Content_type"=>$contenttype,"data"=>$partData);
	                }
	                fclose($fp);
	                return $partData;
	            }
	        }
		}
		Mod::log("read data error host:".$this->serverConfig['host'].", port:".$this->serverConfig['port'], 
            			CLogger::LEVEL_ERROR, 'smallfile.httpGet');
        fclose($fp);
        return false;
    }

    /**
     * http 上传文件，以字符串形式上传
     * 由于小文件上传为异部操作，并且提前做了分享，所以必需保证数据处理可靠，必要情况下要重试
     * @param string $cgiPath
     * @param $data string 上传内容
     * @return bool
     */
    private function httpPostFile($data,$cgiPath)
    {
        $fp = @fsockopen($this->serverConfig['host'], $this->serverConfig['port'], $errno, $errstr, 3);
        if($fp === false) {
            Mod::log("connect time out error host:".$this->serverConfig['host'].", port:".$this->serverConfig['port'], 
            			CLogger::LEVEL_ERROR, 'smallfile.httpPostFile');
            return false;   
        }
        stream_set_timeout($fp,$this->timeout); //设置超时为1s

        //一次性写入
        $line_1 = "POST $cgiPath HTTP/1.0\r\n";
        $line_2 = "Content-Type: application/x-www-form-urlencoded\r\n";
        $line_3 = "Content-length: ".strlen($data)."\r\n\r\n";
        $ret = @fwrite($fp,$line_1.$line_2.$line_3);
        if($ret === false) {
            Mod::log("write data(GET $cgiPath HTTP/1.0\r\n\r\n) to fp(".$fp.") error:", 
            			CLogger::LEVEL_ERROR, 'smallfile.httpPostFile');
            fclose($fp);
            return false;   
        }

        //数据量比较大，一次写不完
        for($written = 0; $written < strlen($data); $written += $fwrite) {
            $contents .= substr($data, $written);
            $fwrite = @fwrite($fp, $contents);
            if ($fwrite === false) {
                Mod::log("write data(GET $cgiPath HTTP/1.0\r\n\r\n) to fp(".$fp.") error:", 
            			CLogger::LEVEL_ERROR, 'smallfile.httpPostFile');
                fclose($fp);
                return false;   
            }
        }
        $response_data = @fread($fp, 512);
        if(empty($response_data)) {
            Mod::log("read data error host:".$this->serverConfig['host'].", port:".$this->serverConfig['port'], 
            			CLogger::LEVEL_ERROR, 'smallfile.httpPostFile');
            fclose($fp);
            return false;   
        }
		$CLSTR = 'Content-Length:';
		$clPos = stripos($response_data,$CLSTR);
		if($clPos !== false){
			//暂且假设第一次读取就能读到Content-Length
			//TODO
			$clEndPos = strpos($response_data,"\r\n",$clPos+strlen($CLSTR));
			if($clEndPos === false) { fclose($fp); return false;}
	
			$contentLen = intval(substr($response_data,$clPos+strlen($CLSTR),$clEndPos-$clPos-strlen($CLSTR)));
			//长度不应该为0
			if($contentLen <= 0) {fclose($fp); return false;}
			//获取正文内容
			$pattern_str = "\r\n\r\n";
			$pattern_pos = strrpos($response_data,$pattern_str);
			if($pattern_pos !== false) {
				$partData = substr($response_data,$pattern_pos+strlen($pattern_str));
				while(strlen($partData) < $contentLen) {
					$response_data = @fread($fp,$contentLen-strlen($partData));
					if(empty($response_data)) {fclose($fp);return false;}
						$partData .= $response_data;
	                }
	            }
	        }
        fclose($fp);
        //解析返回码
        return $partData;

    }
	
    /**
     * 文件夹创建
     * @param $info array(
     * 'buid'=>,
     * 'uin'=>,
     * 'dir_path'=>,
     * 'name'=>,
     * 'info'=>,
     * )
     */
	public function dirCreate($info)
	{
		if($this->enablePerformReport){
			$this->_report = new CPhpPerfReporter();
			$this->_report->beginPerfReport(Mod::app()->id,$this->serverConfig['host'],$this->serverConfig['port'],false,'','');
        }
        $cgiName = '/store_dir_create?';
        $cgiPath = $cgiName.http_build_query($info);
        $response_code = $this->httpGet($cgiPath);
        //TODO
        //解析返回码
        if($this->enablePerformReport)
        	$this->_report->endPerfReport(0);
        return $response_code;
	}
	/*
	 *删除一个目录
	 *@param $info array(
	 * 'buid'=>,
     * 'uin'=>,
     * 'dir_path'=>
     * )
	 */
	public function dirDelete($info)
	{
		if($this->enablePerformReport){
			$this->_report = new CPhpPerfReporter();
			$this->_report->beginPerfReport(Mod::app()->id,$this->serverConfig['host'],$this->serverConfig['port'],false,'','');
		}
		$cgiName = '/store_dir_delete?';
        $cgiPath = $cgiName.http_build_query($info);
        $response_code = $this->httpGet($cgiPath);
        //TODO
        //解析返回码
        if($this->enablePerformReport)
        	$this->_report->endPerfReport(0);
        return $response_code;
	}
	/*
	 *查看一个目录下的文件和目录
	 *@param $info array(
	 * 'buid'=>,
     * 'uin'=>,
     * 'dir_path'=>
     * )
	 */
	public function dirList($info)
	{
		if($this->enablePerformReport){
			$this->_report = new CPhpPerfReporter();
			$this->_report->beginPerfReport(Mod::app()->id,$this->serverConfig['host'],$this->serverConfig['port'],false,'','');
		}
		$cgiName = '/store_dir_list?';
        $cgiPath = $cgiName.http_build_query($info);
        $response_code = $this->httpGet($cgiPath);
        //TODO
        //解析返回码
        if($this->enablePerformReport)
        	$this->_report->endPerfReport(0);
        return $response_code;
	}
	
    /**
     * 小文件内网上传,为性能考虑,文件夹提前手动建好
     * @param $data 文件内容
     * @param $info array(
     * 'buid'=>,
     * 'uin'=>,
     * 'dir_path'=>,
     * 'name'=>,
     * 'fsize'=>,
     * 'offset'=>,
     * )
     */
	public function fileUpload($data,$info)
	{
		if($this->enablePerformReport){
			$this->_report = new CPhpPerfReporter();
			$this->_report->beginPerfReport(Mod::app()->id,$this->serverConfig['host'],$this->serverConfig['port'],false,'','');
        }
        $cgiName = '/store_file_upload?';
        $cgiPath = $cgiName.http_build_query($info);
        $re = $this->httpPostFile($data,$cgiPath);
        if($this->enablePerformReport)
        	$this->_report->endPerfReport(0);
        if($re !== false && intval($re) == 0){
        	return $this->fileOuterDownload($info);
        }
        else return $re; 
	}
	
	/*
	 *根据文件名上传一个文件,文件最大不超过5M
	 *@param string filepath
	 *@return string url or int (failed) -3 filepath read error,-1 upload error
	 */
	 
	 public function fileUploadByName($filepath)
	{
		if($this->enablePerformReport){
			$this->_report = new CPhpPerfReporter();
			$this->_report->beginPerfReport(Mod::app()->id,$this->serverConfig['host'],$this->serverConfig['port'],false,'','');
        }
        $ret = -1;
        $filename = preg_split('/\//',$filepath);
        $filename = $filename[count($filename) - 1];
        $fileinfo = preg_split('/\./',$filename);
        $filename = $fileinfo[0].'_'.substr(strtotime("now"), 3);
        if(isset($fileinfo[1])){
        	$filename .= '.'.$fileinfo[1];
        }
       	$uinfo = array(
	     	'buid'=>$this->buid,
	     	'uin'=>1000,
	     	'dir_path'=>'/file',
	     	'name'=>$filename,
	     	'fsize'=>filesize($filepath),
	     	'offset'=>0,
     	);
     	$handle = @fopen ($filepath, "rb");
     	if($handle){
     		$filedata = fread($handle, 5242880);
     		$ret = $this->fileUpload($filedata,$uinfo);
     	}
     	else $ret = -3;
        if($this->enablePerformReport)
        		$this->_report->endPerfReport(0);
        fclose($handle);
        return $ret;
	}
	
	/*
	 *删除一个文件的cache
	 *@param $info array(
	 * 'buid'=>,
     * 'uin'=>,
     * 'dir_path'=>,
     * 'name'=>,
     * )
	 */
	public function filecachedelete($info){
		$file = $this->fileOuterDownload($info);
        $url = "http://10.137.134.90/cgi-bin/deletecache.php?file_list=".$file;
        /*if ($stream = fopen($url, 'r')) {
    		$ret = stream_get_contents($stream, 100);
    		fclose($stream);
		}*/
		$ret = $this->curl_get($url);
		$re = json_decode($ret,true);
		return $re;
	}
	 
    /*
	 *删除一个文件
	 *@param $info array(
	 * 'buid'=>,
     * 'uin'=>,
     * 'dir_path'=>,
     * 'name'=>,
     * )
	 */
	public function fileDelete($info)
	{
		if($this->enablePerformReport){
			$this->_report = new CPhpPerfReporter();
			$this->_report->beginPerfReport(Mod::app()->id,$this->serverConfig['host'],$this->serverConfig['port'],false,'','');
		}
		$cgiName = '/store_file_delete?';
        $cgiPath = $cgiName.http_build_query($info);
        $response_code = $this->httpGet($cgiPath);
        //同时清除掉缓存
        $re = $this->filecachedelete($info);
		$rarray = array('tfs'=>$response_code,'cache'=>$re);
        //TODO
        //解析返回码
        if($this->enablePerformReport)
        	$this->_report->endPerfReport(0);
        return $rarray;
	}
	
	/*
	 *根据文件url删除一个文件
	 *@param $url
	 */
	public function fileDeleteByUrl($url)
	{
		$ret = -1;
		$path = '';
		$urlinfo = preg_split('/\//', $url);
		$urlinfolen = count($urlinfo) - 1;
		if($urlinfolen < 10) return $ret;
		for($i = 9; $i < $urlinfolen; $i++){
			$path .= '/'.$urlinfo[$i];
		}
		$info = array(
	 		'buid'=>$urlinfo[4],
     		'uin'=>$urlinfo[8],
     		'dir_path'=>$path,
     		'name'=>$urlinfo[$urlinfolen],
     	);
     	$ret = $this->fileDelete($info);
     	return $ret;
	}
	
	/*
	 *查询文件是上传的状态
	 *@param $info array(
	 * 'buid'=>,
     * 'uin'=>,
     * 'dir_path'=>,
     * 'name'=>,
     * )
     *return array("offset"=>41190,"finish_flag"=>1)
     *finish_flag == 1表示文件存在,否则不存在
	 */
	public function fileQuery($info)
	{
		if($this->enablePerformReport){
			$this->_report = new CPhpPerfReporter();
			$this->_report->beginPerfReport(Mod::app()->id,$this->serverConfig['host'],$this->serverConfig['port'],false,'','');
		}
		$cgiName = '/store_file_query?';
        	$cgiPath = $cgiName.http_build_query($info);
        	$response_code = $this->httpGet($cgiPath);
        	//TODO
        	//解析返回码
        	if($this->enablePerformReport)
        		$this->_report->endPerfReport(0);
        	if($response_code < 0)
        		return array("offset"=>0,"finish_flag"=>-1);
        	return json_decode($response_code,true);
	}
	/*
	 *下载指定的文件
	 *@param $info array(
     * 'buid'=>,
     * 'uin'=>,
     * 'dir_path'=>,
     * 'name'=>,
     * 'offset'=>,
     * 'length'=>
     * )
	 */
	public function fileDownload($info, $type=false )
	{
		if($this->enablePerformReport){
			$this->_report = new CPhpPerfReporter();
			$this->_report->beginPerfReport(Mod::app()->id,$this->serverConfig['host'],$this->serverConfig['port'],false,'','');
		}
		$cgiName = '/store_file_download?';
        $cgiPath = $cgiName.http_build_query($info);
        $response_code = $this->httpGet($cgiPath, $type);
        //TODO
        //解析返回码
        if($this->enablePerformReport)
        	$this->_report->endPerfReport(0);
        return $response_code;
	}
	
	 /**
     * 小文件外网下载
     * @param $info array(
     * 'buid'=>,
     * 'uin'=>,
     * 'dir_path'=>,
     * 'name'=>,
     * )
     * @return string 文件跳转url
     */
	public function fileOuterDownload($info)
	{
        //生成外网key
        if($this->enablePerformReport){
        	$this->_report = new CPhpPerfReporter();
			$this->_report->beginPerfReport(Mod::app()->id,$this->serverConfig['host'],$this->serverConfig['port'],false,'','');
        }
        $uinmd5 = md5($info['uin']);
        $a = substr($uinmd5,30,2);
      	$b = substr($uinmd5,28,2);
      	$c = substr($uinmd5,26,2);
        $info['dir_path'] = '/'.$info['buid'].'/'.$a.'/'.$b.'/'.$c.'/'.$info['uin'].$info['dir_path'].'/'.$info['name'];
        
        //多带了一个time参数，不要紧
        $preUrl = 'http://f.seals.qq.com/filestore'.$info['dir_path'];
        if($this->enablePerformReport)
        	$this->_report->endPerfReport(0);
        return $preUrl;
	}
	
	/**
	 *将数组转换成url
	 *与http_build_query的区别是，此函数不会做转义
	 *params $array
	 *return $string 
	 */
	private function gen_url($param){
		$str = '';
		if(!is_array($param))
			return $str;
		foreach($param as $k=>$v){
			$str .= $k.'='.$v.'&';
		}
		return substr($str, 0, strlen($str) - 1);
	}
	
	/*
	 * function curl_get
	 * desc the function is the curl fucntions assemble
	 */
	 
	function curl_get($url, $timeout=10, &$http_info=null)
	{
		if ('' == $url) 
			return false;

		if ($timeout <= 0) 
			$timeout = 10;

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPGET, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_ENCODING, '');

		$response = curl_exec($ch);
		$http_info = curl_getinfo($ch);

		curl_close($ch);	

		return $response;
	}
	
}
