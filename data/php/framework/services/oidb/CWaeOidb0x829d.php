<?php
require_once("CWaeOidbComm.php");

class CWaeOidb0x829d extends CWaeOidbComm
{
	public $dwSeq;
    public $toUin;
    public $cContentType;
    public $strContent;
    public $stUrl;

	public function __construct($data , $name='')
	{
		if(!is_array($data))
			return false;

        if(!$this->init($name))
            return false;

		/*The commen configure*/
		$this->cmd = 0x829d;	
        $this->uin = $data["uin"] ? $data["uin"] : 0;
        $this->servicetype = $data["servicetype"] ? $data["servicetype"] : 0;
        $this->skey_len = $data["skey_len"] ? $data["skey_len"] : 0;
        $this->skey = $data["skey"] ? $data["skey"] : "";
        $this->waetype = $data["waetype"] ? $data["waetype"] : "";
        //$this->waeappid = $data["waeappid"] ? $data["waeappid"] : 0;
        //$this->waeapppwd_len = $data["waeapppwd_len"] ? $data["waeapppwd_len"] : 0;
        //$this->waeapppwd = $data["waeapppwd"] ? $data["waeapppwd"] : "";

		/*0x829d configure*/
        $this->dwSeq = $this->getSeq();
        $this->cContentType = $data["cContentType"];
        $this->strContent = $data["strContent"];
        $this->toUin = $data["toUin"];
        $this->stUrl = $data["stUrl"];

        $fldarr = array();
        $fldarr[0]["wFieldId"] = 2;
        $fldarr[0]["wLen"] = 4;
        $fldarr[0]["acBufFieldValue"] = pack("CCCC" , 3 , $this->cContentType , 3 , 0);
        $fldarr[1]["wFieldId"] = 10;
        $fldarr[1]["wLen"] = 24;
        $fldarr[1]["acBufFieldValue"] = pack("a20N" , "" , $this->toUin);
        $fldarr[2]["wFieldId"] = 102;
        $describe = "ìú???￠2?\t\nhttp://t.qq.com";
        $t_len = strlen($describe);
        $describe = pack("C" , $t_len) . $describe;
        $fldarr[2]["acBufFieldValue"] = pack("Na12" , 1533259 , '') . $describe;
        $fldarr[2]["wLen"] = strlen($fldarr[2]["acBufFieldValue"]);
        $fldarr[3]["wFieldId"] = 20001;
        $fldarr[3]["wLen"] = strlen($this->strContent) < 420 ? strlen($this->strContent) : 417;
        $fldarr[3]["acBufFieldValue"] = substr($this->strContent , 0 , $fldarr[3]["wLen"]);
        $fldarr[4]["wFieldId"] = 20504;
        $fldarr[4]["acBufFieldValue"] = $this->packInt64(1,0) . pack("CNa20n" ,  0 , 0 , "" , 0);
        $fldarr[4]["wLen"] = strlen($fldarr[4]["acBufFieldValue"]);
        switch($this->cContentType)
        {   
            case 1:
            {
                break;
            }
            case 4:
            {
                $urllen = strlen($this->stUrl);
                if($urllen > 0)
                {
                    $fldarr[5]["wFieldId"] = 20005;
                    $strUrlElement = pack("n" , $urllen) . $this->stUrl . pack("n" , 0);
                    $fldarr[5]["acBufFieldValue"] = pack("C" , strlen($strUrlElement)) . $strUrlElement;
                    $fldarr[5]["wLen"] = strlen($fldarr[5]["acBufFieldValue"]);
                }
                break;
            }
            default:
            {
                break;
            }
        } 
        $this->fieldlist = $fldarr;

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
        $oidbreq .= pack("n" , count($this->fieldlist));
        //astField
        for($i=0 ; $i<count($this->fieldlist) ; $i++)
        {
            //wFieldId
            $oidbreq .= pack("n" , $this->fieldlist[$i]["wFieldId"]);
            //wbufFieldValue_num
            $oidbreq .= pack("n" , $this->fieldlist[$i]["wLen"]);
            //acbufFieldValue
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
			$start = 12;    //stTweetHead
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
                $err_len = $this->getChar($rsp_buf , $start);
                $start += 1;
                $rsp_arr["acErrocde"] = $this->getString($rsp_buf , $start , $err_len);
                $start += $err_len;
            }
		
			//return $rsp_arr;
            $ret_arr["data"] = $rsp_arr;
		}
        return $ret_arr;
	}

}
?>
