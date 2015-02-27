<?php
require_once("CWaeOidbComm.php");

class CWaeOidb0x576 extends CWaeOidbComm
{
	public $dwGroupCode;
    public $acType = array();

	public function __construct($data , $name='')
	{
		if(!is_array($data))
			return false;
		
        if(!$this->init($name))
            return false;
        
		/*The commen configure*/
		$this->cmd = 0x576;	
        $this->uin = $data["uin"] ? $data["uin"] : 0;
        $this->servicetype = $data["servicetype"] ? $data["servicetype"] : 0;
        $this->skey_len = $data["skey_len"] ? $data["skey_len"] : 0;
        $this->skey = $data["skey"] ? $data["skey"] : "";
        $this->waetype = $data["waetype"] ? $data["waetype"] : "";

		/*0x576 configure*/
        $this->dwGroupCode = $data["dwGroupCode"];

		return true;
	}

	public function param2field($key)
	{
		return $this->_param2field[$key];
	}

	public function encode()
	{
		$oidbreq = "";

        //dwGroupCode
        $oidbreq .= pack("N" , $this->dwGroupCode);
        //cTypeNum
        $cnt = count($this->acType);
        if($cnt > 21)
            return -1;
        $oidbreq .= pack("C" , $cnt);
        //acType
        for($i=0 ; $i<$cnt && $i<21 ; $i++)
            $oidbreq .= pack("C" , $this->acType[$i]);

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
			$start = 0;
            $rsp_arr["dwGroupUin"] = $this->getInt($rsp_buf , $start);
            $start += 4;
            $rsp_arr["dwGroupCode"] = $this->getInt($rsp_buf , $start);
            $start += 4;
            $rsp_arr["cRole"] = $this->getChar($rsp_buf , $start);
            $start += 1;
            if($start < strlen($rsp_buf))
            {
                $rsp_arr["cTypeNum"] = $this->getChar($rsp_buf , $start);
                $start += 1;
                $rsp_arr["astTLV"] = array();
                for($i=0 ; $i<$rsp_arr["cTypeNum"] && $i<21 ; $i++)
                {
                    $tlv = array();
                    $tlv["cType"] = $this->getChar($rsp_buf , $start);
                    $start += 1;
                    $clen = $this->getChar($rsp_buf , $start);
                    $start += 1;
                    if($clen<0 || $clen>160)
                        break;
                    $tlv["acValue"] = $this->getString($rsp_buf , $start , $clen);
                    $start += $clen;

                    $rsp_arr["astTLV"][] = $tlv;
                }
            }
		
			//return $rsp_arr;
            $ret_arr["data"] = $rsp_arr;
		}
        return $ret_arr;
	}

}
?>
