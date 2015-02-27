<?php
//包体封装类i
define("TIME_OUT",3);
class HttpRequest
{
   public $host;
   public $port;
   public $url;
   public $params;
   public $refer;
   public $cookie;
   public $method;
   public function __construct($host="", $port="", $url="", $params="", $refer='', $cookie='', $method="GET"){
       $this->host = $host;
       $this->port = $port;
       $this->url = $url;
       $this->params = $params;
       $this->refer = $refer;
       $this->cookie = $cookie;
       $this->method = $method;
   }
   public function sendInfo(){
       $fp = fsockopen($this->host, $this->port, $errno, $errstr, TIME_OUT);
       if(!$fp){
            //if got log class use this
            Mod::log("socket Inited error!host:$this->host,port:$this->port", CLogger::LEVEL_ERROR, 'WaeHttpgetService.sendInfo');
            return array('retcode'=>-2,'header'=>'','body'=>'');
       }
       fputs($fp, "$this->method $this->url HTTP/1.1\r\n");
       fputs($fp, "Host: $this->host\r\n");
       if ($this->refer!='') fputs($fp, "Referer: $this->refer\r\n");
       if ($this->cookie!='') fputs($fp, "Cookie: $this->cookie\r\n");
       fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
       fputs($fp, "Content-length: ".strlen($this->params)."\r\n");
       fputs($fp, "Connection: close\r\n\r\n");
       if($this->params!='')fputs($fp, $this->params."\r\n\r\n");
       $body = '';
       $header = '';
       $header_body=0;
       $chunked_format=0;
       $chunked_len=0;
       while (!feof($fp)) {
           $str=fgets($fp);
           if ($header_body==1){
               if ($chunked_format){
                   if ($chunked_len<=0){
                       $chunked_len=hexdec($str);
                       if ($chunked_len==0) break;
                       else continue;
                   } else {
                        $chunked_len -= strlen($str);
                        if ($chunked_len<=0) $str=trim($str);
                   }            
               }
               $body.=$str;
           }
           else if ($str=="\r\n") $header_body=1;
           else {
               $header.=$str;
               if ($str=="Transfer-Encoding: chunked\r\n") $chunked_format=1;
           }
       }
       fclose($fp);
       return array('retcode' => 0,'header'=>$header,'body'=>$body);
   }
}

//url下载服务器返回的结果处理
class UrlResponse
{
   public  function getServerInfo($data, $http_request){
        $pos = strpos($data['body'], 'http://');
        if($pos === false || $pos !== 0){
            Mod::log($data['body']." pos is ".$pos,CLogger::LEVEL_ERROR,'CWaeHttpgetService.getServerInfo');            
            return false;
        }    
        else {  
            $rearr = explode(":", $data['body']);
            $http_request->host = substr($rearr[1],2);
            $learr = explode("/",$rearr[2]);
            $http_request->port = $learr[0];
            $http_request->url = strstr($rearr[2],"/");
            $http_request->method = "GET";
            return true;
        }     
    }
}
/*主体工作类
 *method httpGet
 *@params string url
 *@return string content
 *@creattime 2013-02-01
 *@author pechsepng
 */
class CWaeHttpgetService
{
    public $http_request;
    public $request;
    public $url_response; 
    public $nameServiceKey = 'access.common.download.com';
    public $enablePerformReport = false;
    public function init(){
        $this->http_request = new HttpRequest();
        $this->url_response = new UrlResponse();      
    }
    public function httpGet($url){
        if($this->enablePerformReport){
            $report = new CPhpPerfReporter();
            $report->setLocalIp(IP_LOCAL);
            $report->beginPerfReport(Mod::app()->id,'' , '', false, '', "");
        }
        if($this->nameServiceKey)
        {
            if(Mod::app()->nameService->getHostByKey($this->nameServiceKey, $ip, $port)==false)
                    throw new CException("NameServer fail, key:{$this->nameServiceKey}");
        }
        else throw new CException("Need nameServiceKey");
        $this->http_request->host=$ip;
        $this->http_request->port=$port;
        $this->http_request->url='/page_download';
        $this->http_request->params=$url;
        $this->http_request->method="POST";
        $re = $this->http_request->sendInfo();
        $return = $this->url_response->getServerInfo($re, $this->http_request);
        $content = array('retcode'=>-1,'header'=>$re['header'],'body'=>$re['body']);
        if($return)
            $content = $this->http_request->sendInfo();
        if($this->enablePerformReport){
            $report->endPerfReport(0);
        }
        return $content;
    }
}
