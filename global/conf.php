<?php
$config['database'] = array(
	'default' => array(
		'master' => array(
			'host' => '192.168.0.200',
			'charset' => 'utf8',
			'dbname' => 'huladb',
			'username' => 'root',
			'password' => 'zaDUvXzYwfQd3czS',
		),
		'slave' => array(
			'host' => '192.168.0.200',
			'charset' => 'utf8',
			'dbname' => 'huladb',
			'username' => 'root',
			'password' => 'zaDUvXzYwfQd3czS',
		)
	),

	'cms' => array(
		'master' => array(
			'host' => '192.168.0.200',
			'charset' => 'utf8',
			'dbname' => 'phpcms',
			'username' => 'root',
			'password' => 'zaDUvXzYwfQd3czS',
		),
		'slave' => array(
			'host' => '192.168.0.200',
			'charset' => 'utf8',
			'dbname' => 'phpcms',
			'username' => 'root',
			'password' => 'zaDUvXzYwfQd3czS',
		)
	),
);

$config['sns'] = array(
	'domain' => 'http://www.hulapai.com',
	'database' => 'thinksns'
);

$config['memcache'] = array(	
	'master' => array(
		'host' => '127.0.0.1',
		'port' => '11211'		
	),
	'slave'=> array(
		'host' => '127.0.0.1',
		'port' => '11211'
	)
);

$config['redis'] = array(	
	'host' => '192.168.0.200',
	'port' => '6379'
);

$config['xmpp'] = array(		
	'server' => 'hulapai.com',
	'host' => '192.168.0.200',
	'port' => 5222,
	'user' => 'admin',
	'password' => '123123',
	'res' => 'hulapai'
);


$config['sms'] = array(	
	'url' => 'http://sdkhttp.eucp.b2m.cn/sdk/SDKService?wsdl',	//  http://sdkhttp.eucp.b2m.cn/sdk/SDKService?wsdl
	/*
	'serialNumber' => '0SDK-EAA-0130-NDSMM',	
	'password' => '2339222',
	'sessionKey' => '233922',
	*/	
	'serialNumber' => '3SDK-EMY-0130-QCTOT',
	'password' => '017678',
	'sessionKey' => 'FaHByLwKdw',

	'timeout' => 2,
	'response_timeout' => 10,
	'proxyhost' => false,
	'proxyport' => false,
	'proxyusername' => false,
	'proxypassword' => false,
	'outgoingEncoding' => 'UTF8'
);

$config['notice'] = array(
	'register' => '您的验证码为：{code}，您可以进行下一步，完成注册啦！我们仅以此确认您的身份。退订回复TD。【呼啦派】',
	'forget' => '您的验证码为：{code}，您可以通过下一步验证找回您的呼啦派账号。退订回复TD。【呼啦派】',
	'welcome'=> '亲爱的{user}用户，欢迎您注册呼啦派，您可以完善您的资料，并创建老师档案或学生档案以便于更好地使用本系统。'
);

$config['error'] = array(
	'sms' => array(		
		'0' => '成功',
		'-2'=> '重复发送',
		'-1' => '系统异常',
		'-101' => '命令不被支持',
		'-102' => '用户信息删除失败',
		'-103' => '用户信息更新失败',
		'-104' => '指令超出请求限制',
		'-111' => '企业注册失败',
		'-117' => '发送短信失败',
		'-118' => '获取MO失败',
		'-119' => '获取Report失败',
		'-120' => '更新密码失败',
		'-122' => '用户注销失败',
		'-110' => '用户激活失败',
		'-123' => '查询单价失败',
		'-124' => '查询余额失败',
		'-125' => '设置MO转发失败',
		'-127' => '计费失败零余额',
		'-128' => '计费失败余额不足',
		'-1100' => '序列号错误,序列号不存在内存中,或尝试攻击的用户',
		'-1102' => '序列号正确,Password错误',
		'-1103' => '序列号正确,Key错误',
		'-1104' => '序列号路由错误',
		'-1105' => '序列号状态异常 未用1',
		'-1106' => '序列号状态异常 已用2 兼容原有系统为0',
		'-1107' => '序列号状态异常 停用3',
		'-1108' => '序列号状态异常 停止5',
		'-113' => '充值失败',
		'-1131' => '充值卡无效',
		'-1132' => '充值卡密码无效',
		'-1133' => '充值卡绑定异常',
		'-1134' => '充值卡状态异常',
		'-1135' => '充值卡金额无效',
		'-190' => '数据库异常',
		'-1901' => '数据库插入异常',
		'-1902' => '数据库更新异常',
		'-1903' => '数据库删除异常',

		'-9000' => '数据格式错误,数据超出数据库允许范围',
		'-9001' => '序列号格式错误',
		'-9002' => '密码格式错误',
		'-9003' => '唯一码格式错误',
		'-9004' => '设置转发格式错误',
		'-9005' => '公司地址格式错误',
		'-9006' => '企业中文名格式错误',
		'-9007' => '企业中文名简称格式错误',
		'-9008' => '邮件地址格式错误',
		'-9009' => '企业英文名格式错误',
		'-9010' => '企业英文名简称格式错误',
		'-9011' => '传真格式错误',
		'-9012' => '联系人格式错误',
		'-9013' => '联系电话',
		'-9014' => '邮编格式错误',
		'-9015' => '新密码格式错误',
		'-9016' => '发送短信包大小超出范围',
		'-9017' => '发送短信内容格式错误',
		'-9018' => '发送短信扩展号格式错误',
		'-9019' => '发送短信优先级格式错误',
		'-9020' => '发送短信手机号格式错误',
		'-9021' => '发送短信定时时间格式错误',
		'-9022' => '发送短信唯一序列值错误',
		'-9023' => '充值卡号格式错误',
		'-9024' => '充值密码格式错误',
		'-9025' => '超时'
	)
);
