<?php
require_once("CWaeOidbComm.php");

class CWaeOidb0xfff extends CWaeOidbComm
{
	public $uinlist;

	public function __construct($data , $name='')
	{
		if(!is_array($data))
			return false;
	    
        if(!$this->init($name))
            return false;
 
		/*The commen configure*/
		$this->cmd = 0xfff;	
		//$this->cmd = 1078;	
        $this->uin = $data["uin"] ? $data["uin"] : 0;
        $this->servicetype = $data["servicetype"] ? $data["servicetype"] : 0;
        $this->skey_len = $data["skey_len"] ? $data["skey_len"] : 0;
        $this->skey = $data["skey"] ? $data["skey"] : "";
        $this->waetype = $data["waetype"] ? $data["waetype"] : "";
        //$this->waeappid = $data["waeappid"] ? $data["waeappid"] : 0;
        //$this->waeapppwd_len = $data["waeapppwd_len"] ? $data["waeapppwd_len"] : 0;
        //$this->waeapppwd = $data["waeapppwd"] ? $data["waeapppwd"] : "";

		/*0xfff configure*/

		return true;
	}

	public function param2field($key)
	{
		return $this->_param2field[$key];
	}

	public function encode()
	{
		$oidbreq = "";
        return $oidbreq;
	}

	public function decode($rsp_buf , $result)
	{
        $ret_arr = array(
            "result" => $result,
            "data" => ''
        );

        return $ret_arr;
	}

}
?>
