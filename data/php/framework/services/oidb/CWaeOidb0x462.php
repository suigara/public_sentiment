<?php
require_once("CWaeOidbComm.php");

class CWaeOidb0x462 extends CWaeOidbComm
{
	public function __construct($data , $name='')
	{
		if(!is_array($data))
			return false;

        if(!$this->init($name))
            return false;

		/*The commen configure*/
		$this->cmd = 0x462;	
        $this->uin = $data["uin"] ? $data["uin"] : 0;
        $this->servicetype = $data["servicetype"] ? $data["servicetype"] : 0;
        $this->skey_len = $data["skey_len"] ? $data["skey_len"] : 0;
        $this->skey = $data["skey"] ? $data["skey"] : "";
        $this->waetype = $data["waetype"] ? $data["waetype"] : "";
        //$this->waeappid = $data["waeappid"] ? $data["waeappid"] : 0;
        //$this->waeapppwd_len = $data["waeapppwd_len"] ? $data["waeapppwd_len"] : 0;
        //$this->waeapppwd = $data["waeapppwd"] ? $data["waeapppwd"] : "";

		/*0x462 configure*/

		return true;
	}

	public function param2field($key)
	{
		return $this->_param2field[$key];
	}

	public function encode()
	{
		$oidbreq = "";

		$this->data_len = 0;
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
			$friend_num = $this->getShort($rsp_buf , $start);
			$start += 2;
			$rsp_arr["astFriend"] = array();
			for($i=0 ; $i<$friend_num && $i<3000 ; $i++)
			{
				$f_arr = array();
				$f_arr["dwFrdUin"] = $this->getInt($rsp_buf , $start);
				$start += 4;
				$f_arr["lFlag"] = $this->getInt($rsp_buf , $start);
				$start += 4;
				$id_num = $this->getShort($rsp_buf , $start);
				$start += 2;
				$id_arr = array();
				for($j=0 ; $j<$id_num && $j<10 ; $j++)
				{
					$id_arr[$j] = $this->getShort($rsp_buf , $start);
					$start += 2;
				}
				$f_arr["awId"] = $id_arr;
				$rsp_arr["astFriend"][] = $f_arr;
			}
		
			//return $rsp_arr;
            		$ret_arr["data"] = $rsp_arr;
		}
        	return $ret_arr;
	}

}
?>
