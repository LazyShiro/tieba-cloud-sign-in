<?php

include_once 'function.php';

if (is_cli()) {
	$opt = getopt('a:b:c:');
} else {
	$opt = $_GET;
}

@$appStatus = $opt['a'];

if (empty($appStatus) || $appStatus !== 'exec') {
	echo '.';
	sleep(1);
	echo '.';
	sleep(1);
	echo '.';
	sleep(1);
	echo '.';
	sleep(1);
	exit('数据库删除执行成功');
}

//打开账号列表
$db = file_get_contents("account");
//json解码
$accountList = json_decode($db, true);
//遍历每个账号
foreach ($accountList as $key => $account) {
	//开始签到
	startSign($account);
}

exit('done');
