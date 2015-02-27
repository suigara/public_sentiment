<?php
	/*
	 *为同一个业务分配不同id的api
	 *author pechspeng
	 *createtime 2013-12-17
	 */
    define('SHM_KEY',0x7c55aa);
    define('SEM_KEY',0x7c55ab);
    define('SHM_SIZE',10240);
    define('SURL','/interface/getid?appid=');
    define('ALARMID','idserver');
    class CWaeId extends CApplicationComponent{
    	private $_semid;
    	private $_shmid;
    	public $nameServiceKey;
    	public $ip;
    	public $port;
    	
    	public function init(){
    		parent::init();
			if(function_exists('sem_get'))
				$this->_semid = sem_get(SEM_KEY);
			else  throw new Exception('system v not installed,please compiler php with --enable-sysvsem');
			if(function_exists('shmop_open'))
				$this->_shmid = shmop_open(SHM_KEY, "c", 0666, SHM_SIZE);
			else  throw new Exception('shared memory not installed,please compiler php with --enable-shmop');	
    	}	
		
		public function getId($appid){
			$report = new CPhpPerfReporter();
            $report->beginPerfReport(Mod::app()->id,'' , '', false, '', "");
			//加锁访问，因为每次读并然伴随着写
			sem_acquire($this->_semid);
            $id = 0;
            $idarray = array();
			$idstring = shmop_read($this->_shmid, 0, SHM_SIZE);
			$idarray = json_decode(trim($idstring), true);
			if(!empty($idarray) && isset($idarray[$appid]) && $idarray[$appid]){
                $id = $idarray[$appid]['current_uuid'];
                $nid = $idarray[$appid]['base_uuid'] + $idarray[$appid]['current_step'];
                /*in order to protected on server down.but the recovery system cannot switch to the other immediately; 
                 *so we should request zkname before the num allocate used up; 
                 */
                $sid =  $nid - $idarray[$appid]['current_reserver']; 
                $idarray[$appid]['current_uuid']++;
                if($id < $nid){
                    //request zkname in advance
                    if($id > $sid){
                        $re = $this->getIdFromServer($idarray,$appid,true);
                        if($re == -1)
                            $this->writeArray($idarray);
                    }
                    else $this->writeArray($idarray);
                }
                else{
                    $id = $this->getIdFromServer($idarray,$appid);
                }
			}
			else {
                $id = $this->getIdFromServer($idarray,$appid);
			}
			sem_release($this->_semid);
			$recode = ($id != -1 ? 0 : -1);
			$report->endPerfReport($recode); 
            return $id;
		}
		
        public function writeArray($idarray){
            $idstring = json_encode($idarray);
            $idstring = str_pad($idstring,SHM_SIZE,' ');
            $wlen = shmop_write($this->_shmid, $idstring, 0);
			if($wlen < strlen($idstring)){
				$msg = 'write '.$idstring.' into shared memrory failed';
				Mod::log($msg, CLogger::LEVEL_ERROR, 'CWaeId.writeArray');
        		//马上告警
        		$this->sendAlarm($msg,ALARMID);	
        	}
        }
        
        public function getIdFromServer($idarray,$appid,$in=false){
            try{
                $obj = new CUrl();
                $obj->init();
                if(Mod::app()->nameService->getHostByKey($this->nameServiceKey, $this->ip, $this->port) != false){
        		}
        		else
        		{
        			$msg = 'get nameservice failed:'.$this->nameServiceKey;
        			Mod::log($msg, CLogger::LEVEL_ERROR, 'CWaeId.getIdFromServer');
        			$this->sendAlarm($msg,ALARMID);
        		}
        		$url = 'http://'.$this->ip.':'.$this->port.SURL.$appid; 
                $re = $obj->get($url);
                $re = json_decode($re, true);
            }
            catch(Exception $e){
            	 $obj->close();
            	 $msg = $e->getMessage().';'.$this->ip.':'.$this->port;
                 Mod::log($msg, CLogger::LEVEL_ERROR, 'CWaeId.getIdFromServer');
                 $this->sendAlarm($msg,ALARMID);
                 return -1;
            }
            if(!$re || !is_array($re)){
                $msg = 'get data from server '.$this->ip.':'.$this->port.' error '.var_export($re,true);
                Mod::log($msg, CLogger::LEVEL_ERROR, 'CWaeId.getIdFromServer');
                $this->sendAlarm($msg,ALARMID);
                return -1;
            }
            else if($re['code'] < 0){
                $obj->close();
                Mod::log($re['msg'], CLogger::LEVEL_ERROR, 'CWaeId.getIdFromServer');
                $this->sendAlarm($re['msg'],ALARMID);
                return -1;
            }
            else{
                $id = $re['data']['current_uuid'];
                if(!$in)
                    $re['data']['current_uuid']++;
                $idarray[$appid] = $re['data']; 
                $this->writeArray($idarray);
                $obj->close();
                return $id;
            }
        }
        
        public function sendAlarm($msg, $aid){
        	try{
        		$msg = urlencode($msg);
	        	$alarmurl = 'http://itil.webdev.com/php/sendAlarm.php?msg="'.$msg.'"&ip=&aid='.$aid;
                $obj = new CUrl();
	        	$re = $obj->get($alarmurl);
                $obj->close();
	        	return 0;
	        }
        	catch(Exception $e){
            	 $obj->close();
                 Mod::log($e->getMessage().' '.$alarmurl, CLogger::LEVEL_ERROR, 'CWaeId.sendAlarm');
                 return -1;
            }
        }
	}
?>
