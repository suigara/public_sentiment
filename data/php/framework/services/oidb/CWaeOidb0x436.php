<?php
require_once("CWaeOidbComm.php");

class CWaeOidb0x436 extends CWaeOidbComm
{
	public $uinlist;

	public function __construct($data , $name='')
	{
		if(!is_array($data))
			return false;
	    
        if(!$this->init($name))
            return false;
    
		/*The commen configure*/
		$this->cmd = 0x436;	
        $this->uin = $data["uin"] ? $data["uin"] : 0;
        $this->servicetype = $data["servicetype"] ? $data["servicetype"] : 0;
        $this->skey_len = $data["skey_len"] ? $data["skey_len"] : 0;
        $this->skey = $data["skey"] ? $data["skey"] : "";
        $this->waetype = $data["waetype"] ? $data["waetype"] : "";
        //$this->waeappid = $data["waeappid"] ? $data["waeappid"] : 0;
        //$this->waeapppwd_len = $data["waeapppwd_len"] ? $data["waeapppwd_len"] : 0;
        //$this->waeapppwd = $data["waeapppwd"] ? $data["waeapppwd"] : "";

		/*0x436 configure*/
		$this->uinlist = is_array($data["uinlist"]) ? $data["uinlist"] : array();

		return true;
	}

	public function param2field($key)
	{
		return $this->_param2field[$key];
	}

	public function encode()
	{
		$oidbreq = "";
		//cBodyVer
		$oidbreq .= pack("C" , 1);
		//wUin_num
		$uincount = count($this->uinlist);
		if($uincount<=0 || $uincount>50)
		{
			return false;
		}
		$oidbreq .= pack("n" , $uincount);
		//adwUin
		for($i=0 ; $i<$uincount && $i<50 ; $i++)
		{
			$oidbreq .= pack("N" , $this->uinlist[$i]);
		}

		$this->data_len = strlen($oidbreq);
		$this->pdata = $oidbreq;
		return $oidbreq;
	}

	public function decode($rsp_buf , $result)
	{
	        $ret_arr = array(
	            "result" => $result,
	            "data" => ''
	        );
		if($result == 0)
		{
			$rsp_arr = array();
			$start = 0;
			$rsp_arr["cBodyVer"] = $this->getChar($rsp_buf , $start);
			$start += 1;
			$rsp_arr["dwNextUin"] = $this->getInt($rsp_buf , $start);
			$start += 4;
			$uininfo_num = $this->getShort($rsp_buf , $start);
			$start += 2;
			$rsp_arr["astUinInfo"] = array();
			for($i=0 ; $i<$uininfo_num && $i<50 ; $i++)
			{
				$u_arr = array();
				$u_arr["dwUin"] = $this->getInt($rsp_buf , $start);
				$start += 4;
				$u_arr["cIsMember"] = $this->getChar($rsp_buf , $start);
				$start += 1;
				$u_arr["cMemberLevel"] = $this->getChar($rsp_buf , $start);
				$start += 1;
				$rsp_arr["astUinInfo"][] = $u_arr;
			}
		
            		$ret_arr["data"] = $rsp_arr;
			//return $rsp_arr;
		}

        	return $ret_arr;
	}

}
?>
