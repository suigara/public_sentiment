<?php
require_once("CWaeOidbComm.php");

class CWaeOidb0x49d extends CWaeOidbComm
{
	public $uinlist;
	public $fieldlist;

	public function __construct($data , $name='')
	{
		if(!is_array($data))
			return false;

        if(!$this->init($name))
            return false;

		/*The commen configure*/
		$this->cmd = 0x49d;	
        $this->uin = $data["uin"] ? $data["uin"] : 0;
        $this->servicetype = $data["servicetype"] ? $data["servicetype"] : 0;
        $this->skey_len = $data["skey_len"] ? $data["skey_len"] : 0;
        $this->skey = $data["skey"] ? $data["skey"] : "";
        $this->waetype = $data["waetype"] ? $data["waetype"] : "";
        //$this->waeappid = $data["waeappid"] ? $data["waeappid"] : 0;
        //$this->waeapppwd_len = $data["waeapppwd_len"] ? $data["waeapppwd_len"] : 0;
        //$this->waeapppwd = $data["waeapppwd"] ? $data["waeapppwd"] : "";

		/*0x49d configure*/
		$this->fieldlist = $data["fieldlist"];
		$this->uinlist = $data['uinlist'];

		return true;
	}

	public function param2field($key)
	{
		return $this->_param2field[$key];
	}

	public function encode()
	{
		$oidbreq = "";
		//cNickCut = 0
		$oidbreq .= pack("C" , 0);

		//wField_num
		$cnt = count($this->fieldlist); 
		$oidbreq .= pack("n" , $cnt);

		//awField[i]
		for($i=0 ; $i<$cnt ; $i++)
		{
			$oidbreq .= pack("n" , $this->fieldlist[$i]);
		}
	
		//wUin_num
		$cnt = count($this->uinlist);
		if($cnt<=0)
			return -1;
		$oidbreq .= pack("n" , $cnt);

		//acNameType
		for($i=0 ; $i<$cnt ; $i++)
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
	            "data" => ""
	        );
		if($result == 0)
		{
			$rsp_arr = array();
			$start = 0;	
			$rsp_arr["cNickCut"] = $this->getChar($rsp_buf , $start);
			$start += 1;
			$rsp_arr["dwNextUin"] = $this->getInt($rsp_buf , $start);
			$start += 4;
			$info_num = $this->getShort($rsp_buf , $start);
			$start += 2;
	
			$rsp_arr["astSimpleInfo"] = array();
			for($i=0 ; $i<$info_num ; $i++)
			{
				$info_arr = array();
				$info_arr["dwUin"] = $this->getInt($rsp_buf , $start);
				$start += 4;
				$field_num = $this->getShort($rsp_buf , $start);
				$start += 2;
				$field_arr = array();
				for($j=0 ; $j<$field_num ; $j++)
				{
					$field_arr["wFieldID"] = $this->getShort($rsp_buf , $start);
					$start += 2;
					$vallen = $this->getShort($rsp_buf , $start);
					$start += 2;
					$field_arr["acValue"] = $this->getString($rsp_buf , $start , $vallen);
					$start += $vallen;
				}
				$info_arr["astField"] = $field_arr;
				$rsp_arr["astSimpleInfo"][] = $info_arr;
			}
		
			//return $rsp_arr;
            		$ret_arr["data"] = $rsp_arr;
		}
        	return $ret_arr;
	}

}
?>
