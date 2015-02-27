<?php
require_once("CWaeOidbComm.php");

class CWaeOidb0x4b9 extends CWaeOidbComm
{
	public $cType;
	public $dwSequence;

	public function __construct($data, $name='')
	{
		if(!is_array($data))
			return false;

        if(!$this->init($name))
            return false;

		/*The commen configure*/
		$this->cmd = 0x4b9;	
        $this->uin = $data["uin"] ? $data["uin"] : 0;
        $this->servicetype = $data["servicetype"] ? $data["servicetype"] : 0;
        $this->skey_len = $data["skey_len"] ? $data["skey_len"] : 0;
        $this->skey = $data["skey"] ? $data["skey"] : "";
        $this->waetype = $data["waetype"] ? $data["waetype"] : "";
        //$this->waeappid = $data["waeappid"] ? $data["waeappid"] : 0;
        //$this->waeapppwd_len = $data["waeapppwd_len"] ? $data["waeapppwd_len"] : 0;
        //$this->waeapppwd = $data["waeapppwd"] ? $data["waeapppwd"] : "";

		/*0x4b9 configure*/
		$this->cType = $data["cType"] ? $data["cType"] : 0;
		$this->dwSequence = $data["dwSequence"] ? $data["dwSequence"] : 0;

		return true;
	}

	public function param2field($key)
	{
		return $this->_param2field[$key];
	}

	public function encode()
	{
		$oidbreq = "";
		//cType
		$oidbreq .= pack("C" , $this->cType);
		//dwSequence
		$oidbreq .= pack("N" , $this->dwSequence);

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
			$rsp_arr["dwSequence"] = $this->getInt($rsp_buf , $start);
			$start += 4;
			$group_num = $this->getChar($rsp_buf , $start);
			$start += 1;
			$rsp_arr["astGroupInfo"] = array();
			for($i=0 ; $i<$group_num ; $i++)
			{
				$g_arr = array();
				$g_arr["cGruopId"] = $this->getChar($rsp_buf , $start);
				$start += 1;
				$g_arr["cSortId"] = $this->getChar($rsp_buf , $start);
				$start += 1;
				$name_len = $this->getChar($rsp_buf , $start);
				$start += 1;
				$g_arr["acGroupName"] = $this->getString($rsp_buf , $start , $name_len);
				$start += $name_len;
				$rsp_arr["astGroupInfo"][] = $g_arr;
			}
		
			//return $rsp_arr;
            		$ret_arr["data"] = $rsp_arr;
		}
        	return $ret_arr;
	}

}
?>
