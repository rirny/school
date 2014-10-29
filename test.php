<?php
set_time_limit(0);
header("Content-type: text/html; charset=utf-8");
header("cache-control:no-cache,must-revalidate");
date_default_timezone_set('Asia/Shanghai');
define('ROOT_PATH', getcwd());
define('SYS', dirname(ROOT_PATH));
define('LIB', SYS . "/library");
define('MODEL', SYS . "/model");
define('ENTITY', SYS . "/entity");
define('CONF', ROOT_PATH . "/conf");
define('LOG_PATH', ROOT_PATH . "/logs");
define('APP_PATH', ROOT_PATH . "/app");

define('SESS_UID', 'H_uid');
define('SESS_ACCOUNT', 'H_account');
define('SESS_NAME', 'H_name');
define('SESS_HULAID', 'H_hulaid');

require(SYS.'/comm/comm.php');
define('ROOT', 'http://' . $_SERVER['SERVER_NAME']);

import('config');
$debug = Config::get('debug', 'system', null, false);


/*
import('repeat');


$res = Repeat::resolve('2013-11-19 07:00:00', '2014-01-21 08:00:00', 'week_1___2#', 3600);  
print_r($res);
exit;

if ($debug)
{
	error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
	ini_set('display_errors', 1);
}
else
{
	error_reporting(0);
	ini_set('display_errors', 0);
}

//$res = SMS()->sendSMS(array('13681880418'), str_replace('{code}', '123333', "{code}" . date('Y-m-d H:i:s')));
//echo "发送结果". $res ."\n";
$balance = SMS()->getBalance();
echo "余额:". $balance ."\n";
*/