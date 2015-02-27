<?php
require_once("CWaeOidbComm.php");

class CWaeOidb0x6e4 extends CWaeOidbComm
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

		$this->cmd = 0x6e4;	
        $this->uin = $data["uin"] ? $data["uin"] : 0;
        $this->servicetype = $data["servicetype"] ? $data["servicetype"] : 0;
        $this->skey_len = $data["skey_len"] ? $data["skey_len"] : 0;
        $this->skey = $data["skey"] ? $data["skey"] : "";
        $this->waetype = $data["waetype"] ? $data["waetype"] : "";
        //$this->waeappid = $data["waeappid"] ? $data["waeappid"] : 0;
        //$this->waeapppwd_len = $data["waeapppwd_len"] ? $data["waeapppwd_len"] : 0;
        //$this->waeapppwd = $data["waeapppwd"] ? $data["waeapppwd"] : "";

		$this->dwUin = $data['dwUin'];
		$this->uinlist = $data['uinlist'];
		$this->nametype = $data['nametype'];

		return true;
	}

	public function param2field($key)
	{
		return $this->_param2field[$key];
	}

	public function encode()
	{
		$oidbreq = "";
		//dwId = 0
		$oidbreq .= pack("N" , 0);

		//cUin_num
		$cnt = count($this->uinlist);
		if($cnt<=0 || $cnt>50)
			return -1;
		$oidbreq .= pack("C" , $cnt);

		//adwUin[i]
		for($i=0 ; $i<$cnt ; $i++)
		{
			$oidbreq .= pack("N" , $this->uinlist[$i]);
		}
	
		//cNameType_num
		$cnt = count($this->nametype);
		if($cnt<=0)
			return -1;
		$oidbreq .= pack("C" , $cnt);

		//acNameType
		for($i=0 ; $i<$cnt ; $i++)
                {
                        $oidbreq .= pack("c" , $this->nametype[$i]);
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
            $rsp_arr["cResult"] = $this->getChar($rsp_buf , $start);
            if(!$rsp_arr["cResult"])
            {
                $start += 1;
                $rsp_arr["dwID"] = $this->getInt($rsp_buf , $start);
                $start += 4;
                $rsp_arr["dwNextUin"] = $this->getInt($rsp_buf , $start);
                $start += 4;
                $nameinfo_num = $this->getChar($rsp_buf , $start);
                $start += 1;
                $nameinfo = array();
                for($i=0 ; $i<$nameinfo_num ; $i++)
                {
                    $userinfo = array();
                    $name_num = $this->getChar($rsp_buf , $start);
                    //var_dump($name_num);
                    $start += 1;
                    for($j=0 ; $j<$name_num ; $j++)
                    {
                        $cNameType = $this->getChar($rsp_buf , $start);
                        $start += 1;
                        $cName_len = $this->getChar($rsp_buf , $start);
                        $start += 1;
                        if($cNameType == 1)
                        {
                            $userinfo["uin"] = $this->getInt($rsp_buf , $start);
                            $userinfo["isregister"] = $this->getChar($rsp_buf , $start+4);
                        }
                        else if($cNameType == 2)
                        {
                            $userinfo["mblogid"] = $this->getString($rsp_buf , $start , $cName_len);
                        }
                        else if($cNameType = 4)
                        {
                            $userinfo["mblognick"] = $this->getString($rsp_buf , $start , $cName_len);
                        }
                        $start += $cName_len;
                    }
                    $nameinfo[] = $userinfo;
                }
                $rsp_arr['nameinfo'] = $nameinfo;
            }

            //return $rsp_arr;
            $ret_arr["data"] = $rsp_arr;
		}
        return $ret_arr;
	}

}
?>
