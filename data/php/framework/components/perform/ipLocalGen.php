<?php
if(!file_exists('/tmp/ipLocal.php'))
{
	//判断ifcfg-eth1文件是否存在，tlinux的文件路径需要区别对待
	if(file_exists('/etc/sysconfig/network/ifcfg-eth1'))
		$ifcfgEth1 = '/etc/sysconfig/network/ifcfg-eth1';
	else if(file_exists('/etc/sysconfig/network-scripts/ifcfg-eth1'))
		$ifcfgEth1 = '/etc/sysconfig/network-scripts/ifcfg-eth1';
	else
		exit('ifcfg-eth1 not exist, generate ip local fail.');
	
	//从ifcfg-eth1文件解析出本机ip
	$data = file($ifcfgEth1);
	foreach($data as $line)
	{
		list($tmp1, $tmp2) = explode('=', $line);
		if($tmp1=='IPADDR')
		{
			$ipLocal = str_replace(array('\'', "\n"), '', $tmp2);
			if(!file_put_contents('/tmp/ipLocal.php', "<?php\n\treturn '$ipLocal';\n"))
				exit('generate ipLocal.php fail.');
			else
				return define('IP_LOCAL', $ipLocal);
		}
	}
	
	//解析出本机ip失败
	exit('ifcfg-eth1 not exist, generate ip local fail.');
}
else
	define('IP_LOCAL', require('/tmp/ipLocal.php'));
