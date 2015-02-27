<?php
require_once("CWaeOidbComm.php");

class CWaeOidb0x5c0 extends CWaeOidbComm
{
	public $dwSeq;
    public $cType;
    public $cContentType;
    public $strContent;
	public $fieldlist;

	public function __construct($data , $name='')
	{
		if(!is_array($data))
			return false;

        if(!$this->init($name))
            return false;

		/*The commen configure*/
		$this->cmd = 0x5c0;	
        $this->uin = $data["uin"] ? $data["uin"] : 0;
        $this->servicetype = $data["servicetype"] ? $data["servicetype"] : 0;
        $this->skey_len = $data["skey_len"] ? $data["skey_len"] : 0;
        $this->skey = $data["skey"] ? $data["skey"] : "";
        $this->waetype = $data["waetype"] ? $data["waetype"] : "";
        //$this->waeappid = $data["waeappid"] ? $data["waeappid"] : 0;
        //$this->waeapppwd_len = $data["waeapppwd_len"] ? $data["waeapppwd_len"] : 0;
        //$this->waeapppwd = $data["waeapppwd"] ? $data["waeapppwd"] : "";

		/*0x5c0 configure*/
		$this->dwSeq = $this->getSeq();
        $this->cType = $data["cType"];
        $this->cContentType = $data["cContentType"];
        $this->strContent = $data["strContent"];
		//$this->fieldlist = is_array($data["fieldlist"]) ? $data["fieldlist"] : array();	
        switch($this->cContentType)
        {
            case 1: //text microblog
            {
                $fldarr = array();
                $fldarr[0]["wFieldId"] = 2;
                $fldarr[0]["wBufFieldValue_num"] = 4;
                $fldarr[0]["acBufFieldValue"] = pack("CCCC" , $this->cType , $this->cContentType , 17 , 0);
                $fldarr[1]["wFieldId"] = 102;
                $describe = "ÌÚÑ¶Î¢²©\t\nhttp://t.qq.com";
                $t_len = strlen($describe);
                $describe = pack("C" , $t_len) . $describe;
                $fldarr[1]["acBufFieldValue"] = pack("Na12" , 1533259 , '') . $describe;
                $fldarr[1]["wBufFieldValue_num"] = strlen($fldarr[1]["acBufFieldValue"]);
                $fldarr[2]["wFieldId"] = 20001;
                $fldarr[2]["wBufFieldValue_num"] = strlen($this->strContent) < 420 ? strlen($this->strContent) : 417;
                $fldarr[2]["acBufFieldValue"] = substr($this->strContent , 0 , $fldarr[2]["wBufFieldValue_num"]);
                $this->fieldlist = $fldarr;
            }
            default:
                return false;
        }

		return true;
	}

	public function param2field($key)
	{
		return $this->_param2field[$key];
	}

	public function encode()
	{
		$oidbreq = "";
		//dwSeq
		$oidbreq .= pack("N" , $this->dwSeq);
		//wField_num
		$cnt = count($this->fieldlist);
		if($cnt<=0 || $cnt>50)
			return false;
		$oidbreq .= pack("n" , $cnt);
		//astField
		for($i=0 ; $i<$cnt ; $i++)
		{
			//wFieldId
			$oidbreq .= pack("n" , $this->fieldlist[$i]["wFieldId"]);
			//wBufFieldValue_num
			$value_num = strlen($this->fieldlist[$i]["acBufFieldValue"]);
			$oidbreq .= pack("n" , $value_num);
			//acBufFieldValue
			//$oidbreq .= pack("a".$value_num , $this->fieldlist[$i]["acBufFieldValue"]);
            $oidbreq .= $this->fieldlist[$i]["acBufFieldValue"];
		}

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
			$start = 12;
			$rsp_arr["dwSeq"] = $this->getInt($rsp_buf , $start);
			$start += 4;
			if($rsp_arr["dwSeq"] != $this->dwSeq)
				return false;
			$rsp_arr["encResult"] = $this->getChar($rsp_buf , $start);
			$start += 1;
			if($rsp_arr["encResult"] == 0)
			{
				$rsp_arr["dwUpdataSequence"] = $this->getInt($rsp_buf , $start);
				$start += 4;
				$rsp_arr["ddwTweetId"] = $this->getInt64($rsp_buf , $start);
				$start += 8;
				$rsp_arr["dwTime"] = $this->getInt($rsp_buf , $start);
				$start += 4;
			}
			else
			{
				$other_len = $this->getChar($rsp_buf , $start);
				$start += 1;
				$rsp_arr["acOther"] = $this->getString($rsp_buf , $start , $other_len);
				$start += $other_len;
			}
		
			//return $rsp_arr;
            $ret_arr["data"] = $rsp_arr;
		}
        	return $ret_arr;
	}

}
?>
