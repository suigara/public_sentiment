<?php
/**
 * 常驻进程，执行Task
 */
declare(ticks=1);
class CTaskDaemonCommand extends CConsoleCommand
{
	
	/**
	 * @var int 子进程数量，默认1个
	 */
	public $childNumber = 1;
	/**
	 * @var int 每个子进程最多处理10万个task后退出
	 */
	public $maxTaskPerChild = 100000;
	/**
	 * @var array 所有子进程的pid数组，记录给各个子进程分配的index编号
	 */
	protected $childPids = array();
	/**
	 * @var int 父进程pid
	 */
	protected $parentPid;
	/**
	 * @var int 子进程pid
	 */
	public $childPid;
	/**
	 * @var int 当前进程在进程组中的index编号
	 */
	public $childIndex;
	/**
	 * @var float 执行一次task后，休息的时间，防止cpu占用太高，单位是秒。
	 */
	public $sleepTime = 0.01;
	/**
	 * @var bool 是否使用加锁形式运行Task
	 */
	public $lockRunTask = false;
	/**
	 * @var string 锁组件id
	 */
	public $lockComponentId = 'dbLock';
	
	/**
	 * 1、创建子进程；2、监控子进程。
	 * @param array $args 命令行参数
	 * @return bool
	 */
	public function init($args)
	{
		//调用父类的初始化函数
		parent::init($args);
		
		//修改sleep的时间单位，秒=>微秒(一秒等于一百万微秒)
		$this->sleepTime *= 1000000;
		
		//设置父进程pid
		$this->parentPid = posix_getpid();
		
		//创建子进程
		for($index=0; $index<$this->childNumber; ++$index)
		{
			$this->createChild($index);
			//子进程直接返回
			if($this->childPid>0)
				return true;
		}
		
		$createChildCnt = $this->childNumber;
		
		//监控子进程
		while(1)
		{
			$childPid =  pcntl_wait($status);
			if($childPid<=0)
				continue;
			//默认级别是info
			Mod::log("child exit, childPid=$childPid, status=$status");
			
			$childIndex = array_search($childPid, $this->childPids);
			if($childIndex===false)
			{
				Mod::log("child index not exist, childPid=$childPid");
				continue;
			}
			$this->createChild($childIndex);
			
			//子进程直接返回
			if($this->childPid>0)
				return true;
			
			//记录创建子进程的数量，记录内存占用情况
			++$createChildCnt;
			if($createChildCnt%100==0)
			{
				$memoryUsage = round(Mod::getLogger()->getMemoryUsage()/1048576, 2);
				Mod::log("createChildCnt=$createChildCnt, memoryUsage={$memoryUsage}M",CLogger::LEVEL_INFO,"application");
			}
		}
	}

	/**
	 * 创建子进程，给子进程分配index编号
	 * @param int $index 子进程编号
	 * @return int -1创建子进程失败；0父进程返回；1子进程返回。
	 */
	public function createChild($index)
	{
		//创建子进程前，将所有的日志写入硬盘，防止污染子进程的日志文件。
		Mod::getLogger()->flush(true);
		
		//创建子进程
		$pid = pcntl_fork();
		if($pid==-1)
		{
			Mod::log('fork fail',CLogger::LEVEL_ERROR,"application");
			return -1;
		}
		else if($pid)
		{
			$this->childPids[$index] = $pid;	//记录子进程的index编号 
			Mod::log("create child success, pid=$pid, index=$index, ppid={$this->parentPid}");
			return 0;
		}
		else
		{
			//子进程日志分开
			$this->setLogSuffix(".$index");
			//初始化子进程
			$this->childPid = posix_getpid();
			$this->childIndex = $index;
			Mod::log("start run child, pid={$this->childPid}, index=$index, ppid={$this->parentPid}",CLogger::LEVEL_INFO,"application");
			return 1;
		}
	}

	/**
	 * 1、循环执行任务
	 * 2、监控父进程是否存在
	 * @param array $args
	 */
	final public function run($args)
	{
		Mod::log("start run pid={$this->childPid}, index={$this->childIndex}, ppid={$this->parentPid}");
		//如果需要加锁，需要提前获取“锁组件”
		if($this->lockRunTask)
		{
			$className = get_class($this);
			$lockComponent = Mod::app()->{$this->lockComponentId};
		}
		//循环执行Task
		for($i=1; $i<=$this->maxTaskPerChild; ++$i)
		{
			//每执行1000条task，记录一次日志
			if($i%1000==0)
				Mod::log("process task count=$i, pid={$this->childPid}, index={$this->childIndex}, ppid={$this->parentPid}");
			
			//执行任务
			if($this->lockRunTask)
			{
				//加锁，支持多个子进程
				try
				{
					$lockComponent->lock($className.$this->childIndex);
					$this->runTask($args);
					$lockComponent->unlock($className.$this->childIndex, true);
				}
				catch(CDbException $e)
				{
					$lockComponent->unlock($className.$this->childIndex, false);
				}
			}
			else
				$this->runTask($args);
			
			//父进程是否存在，如果父进程不存在，子进程应该退出
			if(posix_getppid()!=$this->parentPid)
			{
				Mod::log("parent not exist, pid={$this->childPid}, index={$this->childIndex}, ppid={$this->parentPid}");
				break;
			}
			
			//休息一下
			if($this->sleepTime)
			{
				if($this->sleepTime <= 1000000*2)
					usleep($this->sleepTime);
				//如果是长时间休息，则拆分成效的时间段，从而可以监控父进程
				else
				{
					for($j=0,$sum=$this->sleepTime/1000000; $j<$sum; ++$j)
					{
						sleep(1);
						if(posix_getppid()!=$this->parentPid)
						{
							Mod::log("parent not exist, pid={$this->childPid}, index={$this->childIndex}, ppid={$this->parentPid}");
							break;
						}
					}
				}
			}
            //run one task,flush the child log to the disk
            Mod::getLogger()->flush(true);
		}
		$memoryUsage = round(Mod::getLogger()->getMemoryUsage()/1048576, 2);
		Mod::log("end run, memoryUsage={$memoryUsage}M, pid={$this->childPid}, index={$this->childIndex}, ppid={$this->parentPid}");
	}

	/**
	 * 执行一次的Task，子类必须实现该方法
	 * @param array $args 命令行参数
	 */
	public function runTask($args)
	{
		throw new CException('请在子类'.get_class($this).'中实现runTask()方法');
	}

	/**
	 * 设置日志文件的后缀名，将各个子进程的日志文件分开，这样便于查找和定位问题。
	 * @param string $suffix
	 */
	public function setLogSuffix($suffix)
	{
		$logRouters = Mod::app()->log->getRoutes();
		foreach($logRouters as $logRouter)
		{
			if($logRouter instanceof CFileLogRoute)
				$logRouter->setLogFileNameSuffix($suffix);
		}
	}
}