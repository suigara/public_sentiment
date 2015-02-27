<?php
return array(
	 'components'=>array(
			'db'=>array(
					'class'=>'CDbConnection',
					'charset' => 'utf8',
					'connectionString'=>'mysql:host={{host}};port={{port}};dbname=d_nest',
					'nameServiceKeyMaster'=>'m1801_275.product.cdb.com',
					'username' => 'd_nest',
					'password' => '619950470',
			),
	),
    'params'=>array(
			'useProxy'=>true,
	)

);
?>
