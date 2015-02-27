<?php
require_once("CWaeOidbComm.php");

class CWaeOidb0x513 extends CWaeOidbComm
{
	public $adwFriendUin;

	public function __construct($data , $name='')
	{
		if(!is_array($data))
			return false;

        if(!$this->init($name))
            return false;

		/*The commen configure*/
		$this->cmd = 0x513;	
        $this->uin = $data["uin"] ? $data["uin"] : 0;
        $this->servicetype = $data["servicetype"] ? $data["servicetype"] : 0;
        $this->skey_len = $data["skey_len"] ? $data["skey_len"] : 0;
        $this->skey = $data["skey"] ? $data["skey"] : "";
        $this->waetype = $data["waetype"] ? $data["waetype"] : "";
        //$this->waeappid = $data["waeappid"] ? $data["waeappid"] : 0;
        //$this->waeapppwd_len = $data["waeapppwd_len"] ? $data["waeapppwd_len"] : 0;
        //$this->waeapppwd = $data["waeapppwd"] ? $data["waeapppwd"] : "";

		/*0x513 configure*/
		$this->adwFriendUin = is_array($data["adwFriendUin"]) ? $data["adwFriendUin"] : array();	

		return true;
	}

	public function param2field($key)
	{
		return $this->_param2field[$key];
	}

	public function encode()
	{
		$f_num = count($this->adwFriendUin);
		if($f_num<=0 || $f_num > 30)
                        return false;

                //wFriendUin_num
                $oidbreq .= pack("n" , $f_num);
                //adwFriendUin
                for($i=0 ; $i<$f_num ; $i++)
                {
                        $oidbreq .= pack("N" , $this->adwFriendUin[$i]);
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
			$rsp_arr["cResult"] = $this->getChar($rsp_buf , $start);
			$start += 1;
			if($rsp_arr["cResult"] == 0)
			{
				$rsp_arr["astUinName"] = array();
				$name_num = $this->getShort($rsp_buf , $start);
				$start += 2;
				for($i=0 ; $i<$name_num && $i<30 ; $i++)
				{
					$name_arr = array();
					$name_arr["dwUin"] = $this->getInt($rsp_buf , $start);
					$start += 4;
					$name_len = $this->getChar($rsp_buf , $start);
					$start += 1;
					$name_arr["acName"] = $this->getString($rsp_buf , $start , $name_len);
					$start += $name_len;

					$rsp_arr["astUinName"][] = $name_arr;
				}
			}
			else
			{
				$rsp_arr["dwErrorType"] = $this->getInt($rsp_buf , $start);
				$start += 4;
				$err_len = $this->getShort($rsp_buf , $start);
				$start += 2;
				$rsp_arr["acErrmsg"] = $this->getString($rsp_buf , $start , $err_len);
				$start += $err_len;
			}
		
			//return $rsp_arr;
            		$ret_arr["data"] = $rsp_arr;
		}
        	return $ret_arr;
	}

}
?>
