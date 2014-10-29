<?php
$config['hooks']['public'] = Array(
	'index' => array('login', 'register', 'test2', 'index', 'forget', 'resetevent', 'guide'),
	'public'=> array('code', 'mobile_exists', 'hulaid_exists', 'school_code_exsits', 'get_area', 'resetevent'),
	'message' => '*',
	'download'=> '*',
);
$config['hooks']['user'] = Array(
	'index' => array('logout'),
	'school' => '*',
	'public' => '*'
);
$config['hooks']['school'] = Array(
	'index' => array('main', 'left'),
	'*' => array('do', 'ajax', 'select'),
	'event' => array('load'),
	'course'=> array('get_event'),
	'teacher' => array('create')
);
// 只鉴父级
$config['hooks']['priv'] = Array(
	'group' => '*',
	'grade' => '*',
	'manager' => '*'
);