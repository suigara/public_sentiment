<?php
require_once("CWaeOidbComm.php");

class CWaeOidb0x5eb extends CWaeOidbComm
{
	public $dwUin;
	public $uinlist;
	public $nametype;

	public function __construct($data , $name='')
	{
		if(!is_array($data))
			return false;
        
        if(!$this->init($name))
            return false;

		/*The commen configure*/
		$this->cmd = 0x5eb;	
        $this->uin = $data["uin"] ? $data["uin"] : 0;
        $this->servicetype = $data["servicetype"] ? $data["servicetype"] : 0;
        $this->skey_len = $data["skey_len"] ? $data["skey_len"] : 0;
        $this->skey = $data["skey"] ? $data["skey"] : "";
        $this->waetype = $data["waetype"] ? $data["waetype"] : "";
        //$this->waeappid = $data["waeappid"] ? $data["waeappid"] : 0;
        //$this->waeapppwd_len = $data["waeapppwd_len"] ? $data["waeapppwd_len"] : 0;
        //$this->waeapppwd = $data["waeapppwd"] ? $data["waeapppwd"] : "";

		/*0x5eb configure*/
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
		//wCutLen = 0
		$oidbreq .= pack("n" , 0);

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
		if((int)$result === 0)
		{
			$rsp_arr = array();
			$start = 12;	//stTweetHead
			$rsp_arr["wMiniBlogInfo_len"] = $this->getShort($rsp_buf , $start);
			$start += 2;
			$uininfo_num = $this->getShort($rsp_buf , $start);
			$start +=2;
			$uininfo_arr = array();
			for($i=0 ; $i<$uininfo_num ; $i++)
			{
				$t_uininfo = array();
				$t_uininfo["dwUin"] = $this->getInt($rsp_buf , $start);
				$start += 4;
				$field_num = $this->getShort($rsp_buf , $start);
				$start += 2;
				$field_arr = array();
				for($j=0 ; $j<$field_num ; $j++)
				{
					$field_arr["wFieldID"] = $this->getShort($rsp_buf , $start);
					$start += 2;
					$value_len = $this->getShort($rsp_buf , $start);
					$start += 2;
					//$field_arr["acVaule"] = $this->getString($rsp_buf , $start , $value_len);
                    			$field_arr["acVaule"] = substr($rsp_buf , $start , $value_len);
					$start += $value_len;
				}
				$t_uininfo["astField"] = $field_arr;
				$uininfo_arr[] = $t_uininfo;
			}	
			
			$rsp_arr["stMiniBlogInfo"] = $uininfo_arr;
			//return $rsp_arr;
            		$ret_arr["data"] = $rsp_arr;
		}
        	return $ret_arr;
	}

}
?>
