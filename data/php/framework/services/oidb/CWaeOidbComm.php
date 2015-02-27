<?php
require_once(dirname(__FILE__)."/../../components/protocolbuf/message/pb_message.php");
require_once("pb_proto_CWaeOidb.php");
//require_once("/usr/local/zk_agent/names/nameapi.php");

class CWaeOidbComm
{
	public $seq = 0;
	public $result = "0";
	public $cmd;
	public $uin = 0;
	public $servicetype = 0;
	public $skey_len = 0;
	public $skey = "";
	public $waetype_len = 0;
	public $waetype = "";
    public $waeappid_len = 0; 
	public $waeappid = "";
	//public $waeapppwd_len = 0;
	//public $waeapppwd = "";
	public $data_len = 0;
	public $pdata = "";

    public $enablePerformReport = false;
    public $nameServiceKey = "php.test.oidb.webdev.com";
    //public $nameServiceKey = "waephp.oidbservice.webdev.com";
    protected $_report;
	private $_sock;
    private $_ip;
    private $_port;

    public function is_utf8($word) { 
        if (preg_match("/^([".chr(228)."-".chr(233)."]{1}[".chr(128)."-".chr(191)."]{1}[".chr(128)."-".chr(191)."]{1}){1}/",$word) == true
        || preg_match("/([".chr(228)."-".chr(233)."]{1}[".chr(128)."-".chr(191)."]{1}[".chr(128)."-".chr(191)."]{1}){1}$/",$word) == true 
        || preg_match("/([".chr(228)."-".chr(233)."]{1}[".chr(128)."-".chr(191)."]{1}[".chr(128)."-".chr(191)."]{1}){2,}/",$word) == true) 
        {
            return true; 
        }       
        else 
        {
            return false;
        }
    }

	protected function getSeq()
	{
		return mt_rand();
	}

	protected function getInt($buffer, $start)
	{
		$ret = unpack("N", substr($buffer, $start, 4));
		return $ret[1];
	}
    
    protected function getInt64($buffer, $start)
	{
		$retData=0;
		for($i=0;$i<8;$i++) {
			$res = unpack("C", substr($buffer, $start+$i, 1));
			$retData |= $res[1]<<(7-$i)*8;	
		}
		return $retData;
	}

    protected function packInt64($num1 , $num2)
    {
        $ret = "";
        for($i=0;$i<4;$i++)
        {
            $tmp = ($num1>>$i*8)&0xff;
            $ret = pack("C" , $tmp) . $ret;
       }
        for($i=0;$i<4;$i++)
        {
            $tmp = ($num2>>$i*8)&0xff;
            $ret = pack("C" , $tmp) . $ret;
        }
        return $ret;
    }
    
	protected function getShort($buffer, $start)
	{
		$ret = unpack('n', substr($buffer, $start, 2));
		return $ret[1];
	}

	protected function getChar($buffer, $start)
	{
		$ret = unpack("C", substr($buffer, $start, 1));
		return $ret[1];
	}

	protected function getString($buffer, $start, $size)
	{
		return substr($buffer, $start, $size);
	}

	public function __construct($data) {}

    public function init($name)
	{
        $this->waeappid_len = strlen(Mod::app()->id);
        $this->waeappid = Mod::app()->id;

        if($this->enablePerformReport)
            $this->_report = new CphpPerReporter();

        if($name)
        {
            preg_match("/(^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}):([0-9]*)$/" , $name , $match);
            if(count($match))
            {
                $this->_ip = $match[1];
                $this->_port = $match[2];
            }
            else
            {
                $this->nameServiceKey = $name;
                $ret = Mod::app()->nameService->getHostByKey($this->nameServiceKey , $this->_ip , $this->_port);
                if(!$ret)
                {
                    //echo "getHostByName failed:" . $ret;
                    Mod::log("getHostByKey failed ret=".$ret.",name=".$this->nameServiceKey, CLogger::LEVEL_ERROR, __CLASS__);
                    return false;
                }
            }
        }

        return true;
	}

    public function getoidbip()
    {
        return $this->_ip;
    }

    public function getoidbport()
    {
        return $this->_port;
    }

	public function make_sdk_req()
	{
		$req_buf = '';
		$coidb = new CWaeOidb_proto;
        	$coidb->set_result($this->result);
        	$coidb->set_cmd($this->cmd);
        	$coidb->set_uin($this->uin);
        	$coidb->set_servicetype($this->servicetype);
        	$coidb->set_skey_len($this->skey_len);
        	if($this->skey_len)
            		$coidb->set_skey($this->skey);
        	$coidb->set_waetype_len($this->waetype_len);
        	if($this->waetype_len)
            		$coidb->set_waetype($this->waetype);
        $coidb->set_waeappid_len($this->waeappid_len);
        if($this->waeappid_len)
            $coidb->set_waeappid($this->waeappid);
        //$coidb->set_waeapppwd_len($this->waeapppwd_len);
        //if($this->waeapppwd_len)
        //    $coidb->set_waeapppwd($this->waeapppwd);
        	$coidb->set_data_len($this->data_len);
        	if($this->data_len)
            		$coidb->set_pdata($this->pdata);

        	$req_buf = $coidb->SerializeToString();

		return $req_buf;
	}
	
	public function parse_sdk_rsp($rsp_buf , $rsp_len)
	{
		$rep_arr = array();
        $coidb = new CWaeOidb_proto;
        	$coidb->ParseFromString($rsp_buf);
       		$rep_arr["result"] = $coidb->result();
        	$rep_arr["cmd"] = $coidb->cmd();
        	$rep_arr["uin"] = $coidb->uin();
        	$rep_arr["servicetype"] = $coidb->servicetype();
        	$rep_arr["skey_len"] = $coidb->skey_len();
        	if($rep_arr["skey_len"])
            		$rep_arr["skey"] = $coidb->skey();
        	$rep_arr["waetype_len"] = $coidb->waetype_len();
        		if($rep_arr["waetype_len"])
            	$rep_arr["waetype"] = $coidb->waetype();
        $rep_arr["waeappid_len"] = $coidb->waeappid_len();
        if($rep_arr["waeappid_len"])
            $rep_arr["waeappid"] = $coidb->waeappid();
        //$rep_arr["waeapppwd_len"] = $coidb->waeapppwd_len();
       	//if($rep_arr["waeapppwd_len"])
        //    $rep_arr["waeapppwd"] = $coidb->waeapppwd();
        	$rep_arr["data_len"] = $coidb->data_len();
        	if($rep_arr["data_len"])
            		$rep_arr["pdata"] = $coidb->pdata();

		return $rep_arr;
	}

	public function connect(&$errcode = 0 , &$msg = "")
	{
		//$zkhost = new ZkHost;
		//$ret = getHostByKey($name , $zkhost);

		$this->_sock = socket_create(AF_INET , SOCK_STREAM , SOL_TCP);
		if($this->_sock === false)
        {
            Mod::log("socket_create failed:".socket_strerror(), CLogger::LEVEL_ERROR, __CLASS__);
            $msg = "socket_create failed:".socket_strerror();
            $errcode = -1;
			return false;
		}

		if(socket_set_nonblock($this->_sock) == false)
			return false;
            
		if(!@socket_connect($this->_sock, $this->_ip , $this->_port))
		{
			if(socket_last_error($this->_sock) == 115 || 
            socket_last_error($this->_sock) == 11)	//EINPROGRESS || EAGAIN
			{
                $i = 0;
				for($i=0 ; $i<10 ; $i++)		//try 10 times
				{
                    $rarr = array($this->_sock);
                    $warr = array($this->_sock);
                    $earr = NULL;
                
					$ret = @socket_select($rarr, $warr , $earr , 0 , 500000);
					if($ret === false)
					{
						if(socket_last_error() == 4)	//EINTR
						{
							continue;
						}
                        
                        Mod::log("socket_select failed:".socket_strerror(), CLogger::LEVEL_ERROR, __CLASS__);
                        $msg = "socket_select failed:".socket_strerror();
                        $errcode = -2;
						return false;
					}
					else if($ret == 0)	//timeout
					{
                        Mod::log("socket_select timeout", CLogger::LEVEL_WARNING, __CLASS__);
                        $msg = "socket_select timeout";
                        $errcode = -3;
						return false;
					}
					else
					{
                        $val = socket_get_option($this->_sock , SOL_SOCKET ,SO_ERROR);
						//if(($val = socket_last_error($this->_sock))>0 && $val!=115 && $val!=11)
                        if($val === false)
                        {
                            
                            Mod::log("socket_get_option failed:".socket_strerror(socket_last_error($this->_sock)), CLogger::LEVEL_ERROR, __CLASS__);
                            return false;
                        }

                        if($val > 0)
						{
                            Mod::log("socket_select failed:".socket_strerror($val), CLogger::LEVEL_ERROR, __CLASS__);
                            $msg = "socket_select failed:".socket_strerror($val);
				$errcode = -2;
							return false;
						}
						
						//success
						break;
					}
				}

                if($i > 10)
                {
                    Mod::log("socket_select fail more then 10 times", CLogger::LEVEL_ERROR, __CLASS__);
                    $msg = "socket_select fail more then 10 times";
                    $errcode = -4;
					return false;
                }
			}
			else
			{
				//echo socket_strerror(socket_last_error($this->_sock));
				//echo socket_last_error($this->_sock);

                Mod::log("socket_connect failed:".socket_strerror(socket_last_error($this->_sock)), CLogger::LEVEL_ERROR, __CLASS__);
                $msg = "socket_connect failed:".socket_strerror(socket_last_error($this->_sock));
                $errcode = -5;
				return false;
			}
		}

		//set socket block
		if(socket_set_block($this->_sock) == false)
			return false;
        
        
		socket_set_option($this->_sock, SOL_SOCKET, SO_RCVTIMEO, array('sec' => 0, 'usec' => 500000));
		socket_set_option($this->_sock, SOL_SOCKET, SO_SNDTIMEO, array('sec' => 0, 'usec' => 500000));
        
		/*if(!socket_connect($this->_sock, $this->_ip , $this->_port))
        {
                Mod::log("socket_connect failed:".socket_strerror(socket_last_error($this->_sock)), CLogger::LEVEL_ERROR, __CLASS__);
				return false;
        }*/

		return true;
	}

	public function send($sendbuf , $len , &$errcode=0 , &$msg="")
	{
		if($this->_sock<0 || $len<=0)
        {
            Mod::log("sock invalid in send", CLogger::LEVEL_ERROR, __CLASS__);
			return false;
        }
		
		$ret = socket_write($this->_sock , $sendbuf , $len);
		if($ret === false)
		{
            Mod::log("socket_write failed:".socket_strerror(socket_last_error($this->_sock)), CLogger::LEVEL_ERROR, __CLASS__);
            $errcode = -1;
            $msg = "socket_write failed:".socket_strerror(socket_last_error($this->_sock));
			return false;
		}
		else if($ret != $len)
		{
            Mod::log("socket_write send len=$ret != buf len=$len", CLogger::LEVEL_ERROR, __CLASS__);
            $errcode = -2;
            $msg = "socket_write send len=$ret != buf len=$len";
			return false;
		}
		else
		{
			return $ret;
		}
	}

	public function recv(&$recvbuf , $len , &$recvlen , &$errcode=0 , &$msg="")
	{
		if($this->_sock<0 || $len<=0)
                        return false;

		for($i=0 ; $i<2 ; $i++)
		{
		$recvbuf = socket_read($this->_sock , $len , PHP_BINARY_READ);
		if($recvbuf === false)
		{
				if(socket_last_error($this->_sock) == 115 ||
				    socket_last_error($this->_sock) == 11)      //EINPROGRESS || EAGAIN)
				{
					continue;
				}
				else
				{
            Mod::log("socket_read failed:".socket_strerror(socket_last_error($this->_sock)), CLogger::LEVEL_ERROR, __CLASS__);
            $errcode = -1;
            $msg = "socket_read failed:".socket_strerror(socket_last_error($this->_sock));
			return false;
				}
			}
			else
			{
				break;
			}
		}

		$recvlen = strlen($recvbuf);
		
		return true;
	}

	public function access(&$result = 0)
	{
		$sdkreq_buf = $this->make_sdk_req();
		$this->seq = $this->getSeq();
		$csdata = array(
			"dwSeq" => $this->seq,
                        "dwBodyLen" => strlen($sdkreq_buf),
                        "cBody" => $sdkreq_buf
                );
		$cs = new WebdevCs($csdata);

		$req_buf = $cs->pack_web_cs();
		
        //if($this->enablePerformReport)
            //$this->_report->beginPerfReport(Mod::app()id , $this->_ip , $this->_port , false , '' , "");
		if(!$this->connect())
		{
			socket_close($this->_sock);
			return false;
		}
		
		$req_len = strlen($req_buf);
		if(($ret=$this->send($req_buf , $req_len)) <= 0)
		{
			socket_close($this->_sock);
			return false;
		}
		
		//$len = 10 * 1024;
		$rsp_buf = '';
		$rsp_len = 0;
        $unpack_ret = 10 * 1024;
        while(!is_array($unpack_ret))
        {
            $tmp_buf = '';
		    if(($ret = $this->recv($tmp_buf , $unpack_ret , $rsp_len)) === false)
		    {
			    socket_close($this->_sock);
			    return false;
		    }
            $rsp_buf .= $tmp_buf;

		    $unpack_ret = $cs->unpack_web_cs($rsp_buf , strlen($rsp_buf));
        }
		socket_close($this->_sock);

        $rsp_arr = $unpack_ret;

        if(!$rsp_arr)
        {
            Mod::log("unpack_web_cs failed", CLogger::LEVEL_ERROR, __CLASS__);
			return false;
        }
		if($rsp_arr["dwSeq"] != $this->seq)
        {
            Mod::log("send seq:".$rsp_arr["dwSeq"]."!=recv seq:".$this->seq, CLogger::LEVEL_ERROR, __CLASS__);
			return false;
        }

       	$rsp_arr1 = $this->parse_sdk_rsp($rsp_arr["cBody"] , strlen($rsp_arr["cBody"]));
        if(!$rsp_arr1)
        {
            Mod::log("parse_sdk_rsp failed", CLogger::LEVEL_ERROR, __CLASS__);
            return false;
        }
		$result = $rsp_arr1["result"];

		return $rsp_arr1["pdata"];
	}
}
?>
