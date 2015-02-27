<?
/**
 * author pechspeng
 * 邮件内容标题编码必须是utf8
 */
class CWaeMail extends CApplicationComponent
{
	const WSURL = "http://ws.oss.com/MessageService.svc?wsdl";//描述文件
	const  NS = "http://www.w3.org/2001/XMLSchema-instance";
	const NSNODE = "http://schemas.datacontract.org/2004/07/Tencent.OA.Framework.Context";//命名空间
	const SENDER="itil_webdev_com";
	private $_client;
	public $adckey;
	public function __construct(){
		
	}
	private function setClient(){
		$appkeyvar = new SoapVar("<Application_Context xmlns:i=\"".self::NS."\"><AppKey xmlns=\"".self::NSNODE."\">".$this->adckey."</AppKey></Application_Context>",XSD_ANYXML);
		$this->_client = new SoapClient(self::WSURL);
		$header = new SoapHeader(self::NS, "Application_Context", $appkeyvar);
		$this->_client->__setSoapHeaders(array($header));//设置soap头
		
	}
	public function send($sendinfo,$type=3){//$type=1 rtx 2 sms 3 email
		$this->setClient();
		if(is_array($sendinfo)&&in_array($type,array(1,2,3))){
			switch($type){
				case 1:
					return $this->_sendRtx($sendinfo);
					break;
				case 3:
					return $this->_sendEmail($sendinfo);
					break;		
			}
		}
	}
	
	private function _sendRtx($sendinfo){
		$msg = (object)array(
				'Sender'=>isset($sendinfo['sender'])?$sendinfo['sender']:self::SENDER,
				'Receiver'=>$sendinfo['recvuser'],
				'Title'=>$sendinfo['title'],
				'MsgInfo'=>$sendinfo['msg'],
				'Priority'=>'Normal'
				);
		$param = array("message"=>$msg);
		$result = $this->_client->SendRTX($param);
		return $result->SendRTXResult;
	}
	
	private function _sendEmail($sendinfo){
		if(isset($sendinfo['attachments']))
		{
			$attachments = array();
			for($i=0 ; $i<count($sendinfo['attachments']) ; $i++)
			{
				$t_file = $sendinfo['attachments'][$i];
				$t_fname = basename($t_file);
				$t_fcont = file_get_contents($t_file);
				
				$attachments[] = (object)array(
					'FileContent' => $t_fcont,
					'FileName' => $t_fname
				);
			}
		}
		$msg = (object)array(
				'Attachments'=>(isset($sendinfo['attachments'])?$attachments:NULL),
				'Bcc'=>"",
				'BodyFormat'=>'Html',
				'CC'=>$sendinfo['ccusers'],
				'Content'=>$sendinfo['msg'],
				'EmailType'=>$sendinfo['mailtype'],
				'EndTime'=>date('c'),
				'From'=>isset($sendinfo['sender'])?$sendinfo['sender']:self::SENDER,
				'Location'=>'',
				'MessageStatus'=>'Queue',
				'Organizer'=>'',
				'Priority'=>'Normal',
				'StartTime'=>date('c'),
				'Title'=>$sendinfo['title'],
				'To'=>$sendinfo['recvuser']
				);
		//var_dump($msg);
		$param = array("mail"=>$msg);
		//print_r($param);
		$result = $this->_client->SendMail($param);
		return $result->SendMailResult;
	}
}
?>
