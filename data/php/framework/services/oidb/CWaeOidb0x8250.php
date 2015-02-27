<?php
require_once("CWaeOidbComm.php");

class CWaeOidb0x8250 extends CWaeOidbComm
{
	public $acLongUrl;

	public function __construct($data , $name='')
	{
		if(!is_array($data))
			return false;

        if(!$this->init($name))
            return false;

		/*The commen configure*/
		$this->cmd = 0x8250;	
        $this->uin = $data["uin"] ? $data["uin"] : 0;
        $this->servicetype = $data["servicetype"] ? $data["servicetype"] : 0;
        $this->skey_len = $data["skey_len"] ? $data["skey_len"] : 0;
        $this->skey = $data["skey"] ? $data["skey"] : "";
        $this->waetype = $data["waetype"] ? $data["waetype"] : "";
        //$this->waeappid = $data["waeappid"] ? $data["waeappid"] : 0;
        //$this->waeapppwd_len = $data["waeapppwd_len"] ? $data["waeapppwd_len"] : 0;
        //$this->waeapppwd = $data["waeapppwd"] ? $data["waeapppwd"] : "";

		/*0x8250 configure*/
        $this->acLongUrl = $data["acLongUrl"];

		return true;
	}

	public function param2field($key)
	{
		return $this->_param2field[$key];
	}

	public function encode()
	{
		$oidbreq = "";
        //cFromFlag
        $oidbreq .= pack("C" , 24);
        //wLongUrl_len
        $url_len = strlen($this->acLongUrl);
        if($url_len<=0 || $url_len>1024)
        {
            return false;
        }
        $oidbreq .= pack("n" , $url_len);
        //acLongUrl
        $oidbreq .= $this->acLongUrl;
        //wTLVField_num
        $oidbreq .= pack("n" , 1);
        //astTLVField
        $oidbreq .= pack("n" , 102);    //wTid
        $oidbreq .= pack("n" , 4);      //wLen
        $oidbreq .= pack("N" , 1533259);//cValue

        $this->data_len = strlen($oidbreq);
        $this->pdata = $oidbreq;

        return $oidbreq;
	}

	public function decode($rsp_buf , $result)
	{
        $ret_arr = array(
            "result" => $result,
            "data" => ""
        );
		if($result == 0)
		{
			$rsp_arr = array();
			$start = 12;    //stTweetHead
            $rsp_arr["encResult"] = $this->getChar($rsp_buf , $start);
            $start += 1;
            if($rsp_arr["encResult"] == 0)
            {
                $urlsuffix_len = $this->getChar($rsp_buf , $start);
                $start += 1;
                $rsp_arr["acShortUrlSuffix"] = $this->getString($rsp_buf , $start , $urlsuffix_len);

            }
            else
            {
                $rsp_arr["cErrorCode"] = $this->getChar($rsp_buf , $start);
                $start += 1;
                $msg_len = $this->getChar($rsp_buf , $start);
                $start += 1;
                $rsp_arr["acErrorMsg"] = $this->getString($rsp_buf , $start , $msg_len);
                $start += $msg_len;
            }
		
			//return $rsp_arr;
            $ret_arr["data"] = $rsp_arr;
		}
        return $ret_arr;
	}

}
?>
