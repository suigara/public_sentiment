<?php

require_once("Socket.php");
class HexProtocol
{	
	const STX	= 0x02;
	const STX2 	= 0x0a;
	const ETX	= 0x03;

	protected function getSeq()
	{
		return mt_rand();
	}

	protected function getInt($buffer, $start)
	{
		$ret = unpack("N", substr($buffer, $start, 4));
		return $ret[1];
	}
        protected function getInt64($buffer, $start)
	{
		$retData=0;
		for($i=0;$i<8;$i++) {
			$res = unpack("C", substr($buffer, $start+$i, 1));
			$retData |= $res[1]<<(7-$i)*8;	
		}
		return $retData;
	}
	protected function getShort($buffer, $start)
	{
		$ret = unpack("n", substr($buffer, $start, 2));
		return $ret[1];
	}

	protected function getChar($buffer, $start)
	{
		$ret = unpack("C", substr($buffer, $start, 1));
		return $ret[1];
	}

	protected function getString($buffer, $start, $size)
	{
		return substr($buffer, $start, $size);
	}

	protected function packString($str, $size)
	{
		if(strlen($str) >= $size)
		{
			return substr($str, 0, $size);
		}
		return str_pad($str, $size, "\0");
		//return $str;
	}
	protected function hexCompress($s){    
        $r = '';
	for ($i = 0; $i < strlen($s); $i += 2)
	{
		if(ord($s[$i]) >= ord('0') && ord($s[$i]) <= ord('9'))   
		{
			$tmp1 = ord($s[$i]) - ord('0');
		} 
        else if(ord($s[$i]) >= ord('a') && ord($s[$i]) <= ord('f')) 
		{
			$tmp1 = ord($s[$i]) - ord('a') + 10;
		}
        else if(ord($s[$i]) >= ord('A') && ord($s[$i]) <= ord('F'))   
		{
			$tmp1 = ord($s[$i])  - ord('A') +10;
		}
        else 
        {
			return -1;
		}

        if(ord($s[$i + 1]) >= ord('0') && ord($s[$i + 1]) <= ord('9'))   
		{
			$tmp2 = ord($s[$i + 1]) - ord('0');
		} 
        else if(ord($s[$i + 1]) >= ord('a') && ord($s[$i + 1]) <= ord('f')) 
		{
			$tmp2 = ord($s[$i + 1]) - ord('a') + 10;
		}
        else if(ord($s[$i + 1]) >= ord('A') && ord($s[$i + 1]) <= ord('F'))   
		{
			$tmp2 = ord($s[$i + 1])  - ord('A') + 10;
		}
        else 
        {
			return -1;
		}

        $r .= pack("C", (($tmp1 << 4) | ($tmp2 & 0xf))); 

    }
    return $r;
}

}
class TransPkgHead extends HexProtocol
{
	public $wLength = 0;
	public $wVersion = 0;
	public $wCommand = 0;
	public $dwSequence = 0;
	public $dwUserId = 0;
	public $acDevId = 0;
	public $wBizType = 0;
	public $cResult = 0;
	public $dwReserved;

	public function decode($buffer)
	{
		$_pos =0;
		$this->dwLength = $this->getInt($buffer, $_pos);
		$_pos +=4;
		$this->wType = $this->getShort($buffer, $_pos);
		$_pos +=2;
		$this->cversion = $this->getChar($buffer, $_pos);
		$_pos +=1;
		$this->wSeq = $this->getInt($buffer, $_pos);
		$_pos +=4;
		$this->cResLen = $this->getChar($buffer, $_pos);
		$_pos +=1;
		//echo "pos:".$_pos."\n";echo "dwLength:".$this->dwLength."\n";echo "wType:".$this->wType."\n";
		return $_pos;
	}

	public function encode()
	{
		//var_dump($this);
		$pkg .= pack("n", $this->wType);
		$pkg .= pack("c", $this->cversion);
		$pkg .= pack("N", $this->wSeq);
		$pkg .= pack("c", $this->cResLen);

		$len = pack("N", $this->dwLength + strlen($pkg) + 6); //6 = int + 2
		return $len . $pkg;
	}
}

class Accessor
{
	private $sock = null;
	public $errMsg = "";
	private $config;

	public function __construct($ip, $port)
	{
		$timeout = 5;
		$socketConfig=array(
			'host'=> $ip,
			'port'=> $port,
			"timeout"=>$timeout
		);
		$socket=new Model_Push_Socket($socketConfig);

		$this->sock = $socket;
	}

	public function __destruct()
	{
		if($this->sock !== null)
		{
			$this->sock = null;
		}
	}
	public function access($pkg)
	{
		if($this->sock !== null)
		{
			$pkg_len = strlen($pkg);
			$ret = $this->sock->write($pkg);
			if($ret == $pkg_len)
			{}
			else
			{
				$this->errMsg = "socket Write Failed";
				return false;
			}

			$len = 1024;//期望收到的最大包长度
			$ret = $this->sock->read($len);
			if($ret !== false)
			{
				return $ret;
			}
			else
			{
				$this->errMsg = "socket Read Failed";
				return false;
			}
		}
		else
		{
			$this->errMsg = "socket Not Inited";
			return false;
		}
	}
}
