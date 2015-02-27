<?php
require_once("CWaeOidbComm.php");

class CWaeOidb0x9999 extends CWaeOidbComm
{
	public function __construct($data , $name='')
	{
		if(!is_array($data))
			return false;

        if(!$this->init($name))
            return false;

		/*The commen configure*/
		$this->cmd = 0x9999;	
        $this->uin = $data["uin"] ? $data["uin"] : 0;
        $this->servicetype = $data["servicetype"] ? $data["servicetype"] : 0;
        $this->skey_len = $data["skey_len"] ? $data["skey_len"] : 0;
        $this->skey = $data["skey"] ? $data["skey"] : "";
        $this->waetype = $data["waetype"] ? $data["waetype"] : "";
        //$this->waeappid = $data["waeappid"] ? $data["waeappid"] : 0;
        //$this->waeapppwd_len = $data["waeapppwd_len"] ? $data["waeapppwd_len"] : 0;
        //$this->waeapppwd = $data["waeapppwd"] ? $data["waeapppwd"] : "";

		/*0x9999 configure*/

		return true;
	}

	public function param2field($key)
	{
		return $this->_param2field[$key];
	}

	public function encode()
	{
		$oidbreq = "";

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
            $start = 0;
			$rsp_arr = array();
            $rsptype = $this->getChar($rsp_buf , $start);
            $start += 1;
            $rsplen = $this->getInt($rsp_buf , $start);
            $start += 4;
            $rsp_arr["qqhead"] = $this->getString($rsp_buf , $start , $rsplen);
		
            $ret_arr["data"] = $rsp_arr;
		}
        return $ret_arr;
	}

}
?>
