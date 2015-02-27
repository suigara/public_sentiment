<?php
class WebdevCs
{
	const STX = 0x55;
	const ETX = 0xAA;

	public $dwLength;
	public $wType;
	public $cVersion;
	public $dwSeq;
	public $cResLen;
	public $cReserve;
	public $dwBodyLen;
	public $cBody;

	public function __construct($data)
	{
		if(!is_array($data))
			return false;

		$this->dwLength = $data["dwLength"] ? $data["dwLength"] : 0;
		$this->wType = $data["wType"] ? $data["wType"] : 0;
		$this->cVersion = $data["cVersion"] ? $data["cVersion"] : 0x10;
		$this->dwSeq = $data["dwSeq"] ? $data["dwSeq"] : 0;
		$this->cResLen = $data["cResLen"] ? $data["cResLen"] : 0;
		$this->cReserve = $data["cReserve"] ? $data["cReserve"] : "";
		$this->dwBodyLen = $data["dwBodyLen"] ? $data["dwBodyLen"] : 0;
		$this->cBody = $data["cBody"] ? $data["cBody"] : "";

		return true;
	}

	public function pack_web_cs()
	{
		$req_buf = '';
		$req_buf = pack("C" , self::STX);	//STX
		$this->dwLength = $this->dwBodyLen + $this->cResLen + 14;
		/*$req_buf .= pack("NnCNCa".$this->cResLen."a".$this->dwBodyLen , 
				$this->dwLength , $this->wType , $this->cVersion , $this->dwSeq ,
				$this->cResLen , $this->cReserve , $this->cBody);*/
        $req_buf .= pack("NnCNC" , $this->dwLength , $this->wType , $this->cVersion , $this->dwSeq , $this->cResLen);
        if($this->cResLen)
            $req_buf .= substr($this->cReserve , 0 , $this->cResLen);
        if($this->dwBodyLen)
            $req_buf .= substr($this->cBody , 0 , $this->dwBodyLen);

		$req_buf .= pack("C" , self::ETX);

		//var_dump($this->dwLength);
		//var_dump($this->dwBodyLen);
		//var_dump(strlen($req_buf));
		
		return $req_buf;
		
	}

	public function unpack_web_cs($rep_buf , $rep_len)
	{
		if(!$rep_buf || !$rep_len)
			return false;
		$stxarr = unpack("CSTX" , substr($rep_buf , 0 , 1));
		if($stxarr["STX"] != self::STX)
			return -1;

		$start = 1;	//STX
		$len = 4 + 2 + 1 + 4 + 1;	//dwLength,wType,cVersion,dwSeq,cResLen
		$rep_arr = unpack("NdwLength/nwTye/CcVersion/NdwSeq/CcResLen" , substr($rep_buf , $start , $len));
		if($rep_arr["dwLength"]>8192 || $rep_arr["dwLength"]<15)
			return -2;

		if($rep_arr["dwLength"] > $rep_len)
			return $rep_arr["dwLength"] - $rep_len;	//packet not complete

		$etxarr = unpack("CETX" , substr($rep_buf , $rep_arr["dwLength"]-1 , 1));
		if($etxarr["ETX"] != self::ETX)
			return -3;
		
		$start += $len;		//dwLength,wType,cVersion,dwSeq,cResLen
		$reslen = $rep_arr['cResLen'];
		if($reslen)
		{
			//$resarr = unpack("a".$reslen."cReserve" , substr($rep_buf , $start , $reslen));
			//$rep_arr['cReserve'] = $resarr["cReserve"];
			$rep_arr['cReserve'] = substr($rep_buf , $start , $reslen);
		}

		$start += $reslen;	//cReserve

		$rep_arr['dwBodyLen'] = $rep_arr["dwLength"] - 14 - $reslen;
	
		if($rep_arr['dwBodyLen'])
		{
			//$bodyarr = unpack("A".$rep_arr['dwBodyLen']."cBody" , substr($rep_buf , $start , $rep_arr['dwBodyLen']));
			//$rep_arr['cBody'] = $bodyarr["cBody"];
			$rep_arr['cBody'] = substr($rep_buf , $start , $rep_arr['dwBodyLen']);
		}
		
		return $rep_arr;
	}
}
?>
