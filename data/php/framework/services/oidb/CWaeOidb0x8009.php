<?php
require_once("CWaeOidbComm.php");

class CWaeOidb0x8009 extends CWaeOidbComm
{
	public $dwToUin;
    public $dwStartIndex;
    public $wReqNum;
    public $cFilterType;

	public function __construct($data , $name='')
	{
		if(!is_array($data))
			return false;

        if(!$this->init($name))
            return false;

		/*The commen configure*/
		$this->cmd = 0x8009;	
        $this->uin = $data["uin"] ? $data["uin"] : 0;
        $this->servicetype = $data["servicetype"] ? $data["servicetype"] : 0;
        $this->skey_len = $data["skey_len"] ? $data["skey_len"] : 0;
        $this->skey = $data["skey"] ? $data["skey"] : "";
        $this->waetype = $data["waetype"] ? $data["waetype"] : "";
        //$this->waeappid = $data["waeappid"] ? $data["waeappid"] : 0;
        //$this->waeapppwd_len = $data["waeapppwd_len"] ? $data["waeapppwd_len"] : 0;
        //$this->waeapppwd = $data["waeapppwd"] ? $data["waeapppwd"] : "";

		/*0x8009 configure*/
        $this->dwToUin = $data["dwToUin"];
        $this->dwStartIndex = $data["dwStartIndex"] ? $data["dwStartIndex"] : 0;
        $this->wReqNum = $data["wReqNum"] ? $data["wReqNum"] : 100;

		return true;
	}

	public function param2field($key)
	{
		return $this->_param2field[$key];
	}

	public function encode()
	{
		$oidbreq = "";
        //dwToUin
        $oidbreq .= pack("N" , $this->dwToUin);
        //dwStartIndex
        $oidbreq .= pack("N" , $this->dwStartIndex);
        //wReqNum
        $oidbreq .= pack("n" , $this->wReqNum);
        //cReserved
        $oidbreq .= pack("a6" , "");

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
                $rsp_arr["dwToUin"] = $this->getInt($rsp_buf , $start);
                $start += 4;
                $rsp_arr["cOver"] = $this->getChar($rsp_buf , $start);
                $start += 1;
                $rsp_arr["dwTotal"] = $this->getInt($rsp_buf , $start);
                $start += 4;
                $rsp_arr["dwStartIndex"] = $this->getInt($rsp_buf , $start);
                $start += 4;
                $friendnum = $this->getShort($rsp_buf , $start);
                $start += 2;
                $uin_arr = array();
                for($i=0 ; $i<$friendnum && $i<500 ; $i++)
                {
                    $uin_arr[] = $this->getInt($rsp_buf , $start);
                    $start += 4;
                }
                $rsp_arr["adwFriendUin"] = $uin_arr;
                /*$urlsuffix_len = $this->getChar($rsp_buf , $start);
                $start += 1;
                $rsp_arr["acShortUrlSuffix"] = $this->getString($rsp_buf , $start , $urlsuffix_len);
                */
            }
            /*else
            {
                $rsp_arr["cErrorCode"] = $this->getChar($rsp_buf , $start);
                $start += 1;
                $msg_len = $this->getChar($rsp_buf , $start);
                $start += 1;
                $rsp_arr["acErrorMsg"] = $this->getString($rsp_buf , $start , $msg_len);
                $start += $msg_len;
            }*/
		
			//return $rsp_arr;
            $ret_arr["data"] = $rsp_arr;
		}
        return $ret_arr;
	}

}
?>
