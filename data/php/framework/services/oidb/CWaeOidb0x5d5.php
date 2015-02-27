<?php
require_once("CWaeOidbComm.php");

class CWaeOidb0x5d5 extends CWaeOidbComm
{
	public $mblogid;

	public function __construct($data , $name='')
	{
		if(!is_array($data))
			return false;

        if(!$this->init($name))
            return false;

		/*The commen configure*/
		$this->cmd = 0x5d5;	
        $this->uin = $data["uin"] ? $data["uin"] : 0;
        $this->servicetype = $data["servicetype"] ? $data["servicetype"] : 0;
        $this->skey_len = $data["skey_len"] ? $data["skey_len"] : 0;
        $this->skey = $data["skey"] ? $data["skey"] : "";
        $this->waetype = $data["waetype"] ? $data["waetype"] : "";
        //$this->waeappid = $data["waeappid"] ? $data["waeappid"] : 0;
        //$this->waeapppwd_len = $data["waeapppwd_len"] ? $data["waeapppwd_len"] : 0;
        //$this->waeapppwd = $data["waeapppwd"] ? $data["waeapppwd"] : "";

		/*0x5d5 configure*/
		$this->mblogid = is_array($data["mblogid"]) ? $data["mblogid"] : array();	

		return true;
	}

	public function param2field($key)
	{
		return $this->_param2field[$key];
	}

	public function encode()
	{
		$oidbreq = "";
		//dwID = 0
		$oidbreq .= pack("N" , 0);

        //cName_num
        $id_num = count($this->mblogid);
        if($id_num<=0 || $id_num>15)
                return false;
		$oidbreq .= pack("C" , $id_num);
		
		//astName
		for($i=0 ; $i<$id_num ; $i++)
		{
			//cNameType=2 means mblogid
			$oidbreq .= pack("C" , 2);
			//cName_len
			$cName_len = strlen($this->mblogid[$i]);
			$oidbreq .= pack("C" , $cName_len);
			//acName
			//$oidbreq .= pack("a".$cName_len , $this->mblogid[$i]);
            $oidbreq .= substr($this->mblogid[$i] , 0 , $cName_len);
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
			$rsp_arr["dwID"] = $this->getInt($rsp_buf , $start);
			$start += 4;
			$rsp_arr["cResult"] = $this->getChar($rsp_buf , $start);
			$start += 1;
			$info_num = $this->getChar($rsp_buf ,$start);
			$start += 1;
			$rsp_arr["astUinInfo"] = array();
			for($i=0 ; $i<$info_num && $i<15 ; $i++)
			{
				$info_arr = array();
				$info_arr["dwUin"] = $this->getInt($rsp_buf , $start);
				$start += 4;
				$info_arr["cNameType"] = $this->getChar($rsp_buf , $start);
				$start += 1;
				$name_len = $this->getChar($rsp_buf , $start);
				$start += 1;
				$info_arr["acName"] = $this->getString($rsp_buf , $start , $name_len);
				$start += $name_len;

				$rsp_arr["astUinInfo"][] = $info_arr;
			}
		
			//return $rsp_arr;
            		$ret_arr["data"] = $rsp_arr;
		}
        	return $ret_arr;
	}

}
?>
