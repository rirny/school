<?php
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
import('http');
//session_start();
hlp_session_start();

if ( !ob_start( !DEBUGMODE ? 'ob_gzhandler' : '' ) ) { 
    ob_start(); 
}

$os = 'android';
$apiHOST = 'http://app.hulapai.com';
$sessId = Session_handle::get_session_id();
// 用户注册
$registerUser = array(
	'app' => 'index',
	'act' => 'register',
	'account' => '',
	'password'=> '123123',
	'verify_code' => '123321'
);
// 已存在用户
$registerUser['account'] = '14410000100';
echo testClient($registerUser, $os);
exit;
$registerUser['account'] = '141';
echo testClient($registerUser, $os);


/*
$app = Http::post('app', 'string');
$act = Http::post('act', 'string');
$app || $app = 'index';
$act || $act = 'index';

import('api');
$api = ucfirst($app) . "_Api";
$api_files = APP_PATH . '/' . $app . '.class.php';
try
{
	if(!file_exists($api_files)) throw new Exception("APP 不存在！");
	require_once($api_files);
	$api = new $api;
	$api->app = $app;
	$api->act = $act;
	if(!method_exists($api, $act))
	{    
		throw new Exception("ACTION模块不存在!");
	}
	$api->$act();	
	if($api->Error) throw new Exception($api->getError());
}catch(Exception $e)
{
	Out(0, $e->getMessage());
}
*/


function testClient($data, $os='ios')
{
	session_write_close();
	$agents = array(
		'android' => 'Android4.0.3,samsung GT-I9100,359778042915379',
		'ios' => '\U547c\U5566\U6d3e 1.0 (iPhone; iPhone OS 6.1.3; zh_CN),iPhone,iPhone OS 6.1.3,29E45E5C-8819-449A-AFF9-079526724CBE'
	);
	$ch = curl_init(); //初始化curl 
	curl_setopt($ch, CURLOPT_URL, ROOT);//设置链接 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//设置是否返回信息	
	$agent = $agents[$os];
	if($os == 'ios')
	{		
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('DEVICE:iPhone,iPhone OS 6.1.3,29E45E5C-8819-449A-AFF9-079526724CBE'));//设置HTTP头 				
	}
	curl_setopt($ch, CURLOPT_USERAGENT, $agent);//设置HTTP头			
	curl_setopt($ch, CURLOPT_POST, 1);//设置为POST方式 
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);//POST数据 
	curl_setopt($ch, CURLOPT_COOKIE,  session_name() ."=" . session_id());
	$res = curl_exec($ch);//接收返回信息
	
	$response = $data['app'] . "\t" . $data['act'] . "\t";
	
	if(curl_errno($ch)){//出错则显示错误信息 
		$response .= "Flase" . "\t" . curl_error($ch);
		return $response;
	}
	curl_close($ch); //关闭curl链接	

	$result = json_decode($res, true);	
	if($result['state'] != 1)
	{
		$response .= "False\t" . $result['message'] . "\n";
	}else{
		$response .= "True\n";
	}
	return $response;
}
?>
