<?php
require_once("CWaeOidbComm.php");

class CWaeOidb0x668 extends CWaeOidbComm
{
	public $adwUin;

	public function __construct($data , $name='')
	{
		if(!is_array($data))
			return false;
		
        if(!$this->init($name))
            return false;
        
		/*The commen configure*/
		$this->cmd = 0x668;	
        $this->uin = $data["uin"] ? $data["uin"] : 0;
        $this->servicetype = $data["servicetype"] ? $data["servicetype"] : 0;
        $this->skey_len = $data["skey_len"] ? $data["skey_len"] : 0;
        $this->skey = $data["skey"] ? $data["skey"] : "";
        $this->waetype = $data["waetype"] ? $data["waetype"] : "";
        //$this->waeappid = $data["waeappid"] ? $data["waeappid"] : 0;
        //$this->waeapppwd_len = $data["waeapppwd_len"] ? $data["waeapppwd_len"] : 0;
        //$this->waeapppwd = $data["waeapppwd"] ? $data["waeapppwd"] : "";

		/*0x668 configure*/
		$this->adwUin = is_array($data["uinlist"]) ? $data["uinlist"] : array();	

		return true;
	}

	public function param2field($key)
	{
		return $this->_param2field[$key];
	}

	public function encode()
	{
		$oidbreq = "";

                //wUin_num
                $uin_num = count($this->adwUin);
                if($uin_num<=0 || $uin_num>70)
                        return false;
                $oidbreq .= pack("n" , $uin_num);
                //adwUin
                for($i=0 ; $i<$uin_num ; $i++)
                {
                        $oidbreq .= pack("N" , $this->adwUin[$i]);
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
			$start = 0;
			$user_num = $this->getShort($rsp_buf , $start);
			$start += 2;
			$rsp_arr["astMUser"] = array();
			for($i=0 ; $i<$user_num && $i<70 ; $i++)
			{
				$user_arr = array();
				$user_arr["dwUin"] = $this->getInt($rsp_buf , $start);
				$start += 4;
				$user_arr["wLevel"] = $this->getShort($rsp_buf , $start);
				$start += 2;
				$user_arr["dwDays"] = $this->getInt($rsp_buf , $start);
				$start += 4;

				$rsp_arr["astMUser"][] = $user_arr;
			}
		
			//return $rsp_arr;
	           	 $ret_arr["data"] = $rsp_arr;
		}
	        return $ret_arr;
	}

}
?>
