<?php
require_once(dirname(__FILE__)."/../protocolbuf/message/pb_message.php");
require_once("pb_proto_ip.php");
//包体封装类
class PbRequest
{
    public $req_op;
    public $sid;
    public $msg_seq;
    public $ip;
    //参数校验
    public function validate() {
    }

    public function unserializeBody($body) {
        $ip_proto = new ip_proto_c();
        $ip_proto->ParseFromString($body);

        $a = $ip_proto->ip_rsp();
        //var_dump($a->values["1"]); exit;

        return array(
            'ret'=>$ip_proto->ip_rsp()->ret(),
            'desc'=>$ip_proto->ip_rsp()->desc(),
            'pid'=>$ip_proto->ip_rsp()->pid(),
            'province'=>$ip_proto->ip_rsp()->province(),
        );
    }
    //封装ip请求的包体
    public function serializeBody() {
        $ip_msg_req = new ip_req_c();
        $ip_msg_req->set_msg_seq($this->msg_seq);
        $ip_msg_req->set_ip($this->ip);
        $ip_proto = new ip_proto_c();
        $ip_proto->set_req_op(ip_proto_c_op::IP_REQ);
        $ip_proto->set_sid($this->sid);
        $ip_proto->set_ip_req($ip_msg_req);
        $body = $ip_proto->SerializeToString();

        return $body;
    }
}
//向ip_server发出请求类
class Accessor
{
    private $sock = null;
    public $errMsg = "";

    public function access($host, $port, $pkg, $isWait=false)
    {
        $this->sock = fsockopen($host, $port, $errno, $errstr, 2);
        if($this->sock == false) 
        {
            $this->errMsg = "socket Inited error!";
            Mod::log("socket Inited error!host:$host,port:$port", CLogger::LEVEL_ERROR, 'ip.CWaeIpService');
            return false;
        }

        stream_set_timeout($this->sock,1); //设置超时为1s

        for($written = 0; $written < strlen($pkg); $written += $fwrite) {
            $fwrite = fwrite($this->sock, substr($pkg, $written));
            if ($fwrite === false) {
                Mod::log("socket write error!host:$host,port:$port,pkg:$pkg", CLogger::LEVEL_ERROR, 'ip.CWaeIpService');
                return false;   
            }
        }

        if($isWait)
        {
            //此应用只读一次就够
            $result = fread($this->sock, 512);
            return $result;
        }

        return true;
    }
}

class PackAble
{
    protected function getChar($str, $pos)
    {
        $res = unpack("C", substr($str, $pos, 1));
        return $res[1];
    }
    protected function getShort($str, $pos)
    {
        $res = unpack("n", substr($str, $pos, 2));
        return $res[1];
    }

    protected function getInt($str, $pos)
    {
        $res = unpack("N", substr($str, $pos, 4));
        return $res[1];
    }
}
//网站部协议封装类
class CSRequest extends PackAble
{
	public $wType = 0;
	public $cVersion = 0;
	public $dwSeq = 0;
	public $cResLen = 0; //暂时固定为0
	public $acReserve = 0;
	public $body = 0;

	public function decode($buffer)
	{
		$pos =0;
		$web_header = $this->getChar($buffer, $pos);
		$pos +=1;
		$dwLength = $this->getInt($buffer, $pos);
		$pos +=4;
		$this->wType = $this->getShort($buffer, $pos);
		$pos +=2;
		$this->cversion = $this->getChar($buffer, $pos);
		$pos +=1;
		$this->dwSeq = $this->getInt($buffer, $pos);
		$pos +=4;
		$this->cResLen = $this->getChar($buffer, $pos);
        $pos +=1;
        $pos += $this->cResLen;
        $this->body = substr($buffer, $pos, $dwLength - $pos - 1);
        $pos += $dwLength - $pos - 1;
        // $this->body = substr($buffer, $pos, $dwLength-$pos-1);
		// $pos +=strlen($this->body);
		$web_tail = $this->getChar($buffer, $pos);
		$pos +=1;
		return true;
	}

	public function encode()
	{
		$pkg = pack("n", $this->wType);
		$pkg .= pack("c", $this->cVersion);
		$pkg .= pack("N", $this->dwSeq);
		$pkg .= pack("c", $this->cResLen);
		$pkg .= $this->body;


		$len = pack("N", strlen($pkg) + 6); //6 = int + 2
		return pack("c", 0x55). $len . $pkg . pack("c", 0xAA);
	}
}
//主体工作类
class CWaeIpService
{
    public $pb_request;
    public $request;
    public $conn;
    public $nameServiceKey = 'sz.ipdict.push.com';
    public $enablePerformReport=false;
    public function init()
    {
        $this->pb_request = new PbRequest();
        $this->request = new CSRequest();
        $this->conn = new Accessor();
    }

    /**
     * @function get province by ip
     * @params array
     * @return array or boolean
     */
    public function getIp($params)
    {
        if($this->enablePerformReport){
            $report = new CPhpPerfReporter();
            $report->setLocalIp(IP_LOCAL);
            $report->beginPerfReport(Mod::app()->id,'' , '', false, '', "");
        }       
        $this->pb_request->ip = $params['ip'];
        $this->pb_request->sid = 1; /*$params['sid'];*/
        $this->pb_request->msg_seq = mt_rand();
        $body = $this->pb_request->serializeBody();

        $this->request->dwLength = strlen($body);
        $this->request->wType = 13;
        $this->request->cVersion = 0x10;
        $this->request->dwSeq = 0;
        $this->request->body = $body;
        $pkg = $this->request->encode();
        
        //根据名字服务获取ip和port
        if($this->nameServiceKey)
        {
            if(Mod::app()->nameService->getHostByKey($this->nameServiceKey, $ip, $port)==false)
                throw new CException("NameServer fail, key:{$this->nameServiceKey}");
        }
        else throw new CException("Need nameServiceKey");
        //是否等待回包
        //TODO
        $isWait = true;
        $res = $this->conn->access($ip,$port,$pkg,$isWait);
        //check $res
        
        if($res && $isWait && $this->request->decode($res)) {
            //file_put_contents('/tmp/testpb', $this->request->body);
            $res = $this->pb_request->unserializeBody($this->request->body);
        }
        if($this->enablePerformReport){
            $report->endPerfReport(0);
        }
        return $res;
    }
}
