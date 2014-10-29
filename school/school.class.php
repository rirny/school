<?php
class School_Module extends School
{
	public function __construact(){
		parent::__construact();
	}
	private $_perpage = 12;
	private $_pageRange = 10;
	
	public function index_Action()
	{		
		if($this->uid > 2)
		{
			$result = load_model('user')->getAllSchool($this->uid);
			if(!$result) $this->jump('/school/create');
			array_walk($result, function(&$v){
				$v['avatar'] = imageUrl($v['id'], 3, 200);
			});
			$this->assign('result', $result);			
			$this->display('school/school/school.ajax');			
		}else{
			extract($this->int_search());		
			$_School = load_model('school');
			$whereArr = Array();
			if(!empty($where['keyword']))
			{
				$whereArr[] = " (`name` like '%{$where['keyword']}%' or `code` like '%{$where['keyword']}%')";
			}	
			
			if(!empty($where['import']))
			{
				if($where['import'] == 1){ // 正常
					$whereArr[] = "creator<>2";
				}else{
					$whereArr[] = "creator=2"; // 导入
				}
			}

			if(!empty($where['valid'])) 
			{
				if($where['valid'] == 1){ // 待审核
					$whereArr[] = "valid=0";
				}else{
					$whereArr[] = "valid=1";// 审核
				}
			}

			if(!empty($where['status'])) 
			{
				if($where['status'] == 1)  // 正常
				{
					$whereArr[] = '`status`=0';
				}else if($where['status'] == 2) // 测试 
				{ 
					$whereArr[] = '`status`=2';
				}else{
					$whereArr[] = '`status`=1'; // 删除
				}
			}

			$this->assign('creator', $creator);
			$this->assign('creatorArr', array('1' => '正常', '2' => '导入'));
			$this->assign('validArr', array('1' => '待审核', '2' => '已审核'));
			$this->assign('statusArr', array('1' => '正常', '2' => '测试', 3 => '删除'));

			$this->assign('validSelect', array('待审核', '已审核'));
			$this->assign('statusSelect', array('正常', '删除', '测试'));
			$whereStr = join(' And ', $whereArr);
			$total = $_School->getCount($whereStr, 'count(*)');				
			$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);			
			$this->assign('paginator', $paginator);
			$this->assign('record', $total);			
			$limit = $_School->getLimit($this->_perpage, $page);			
			$order = $_School->getOrder($this->getOrder($order));						
			$result = $_School->getAll($whereStr, $limit, $order);
			$_Teacher = load_model('school_teacher');
			$_Student = load_model('school_student');
			array_walk($result, function(&$item) use($_Teacher, $_Student) {
				$item['students'] = $_Student->getCount(array('school' => $item['id']));
				$item['teachers'] = $_Teacher->getCount(array('school' => $item['id']));
				$item['create_time'] = date('Y-m-d', $item['create_time']);
			});
			$this->assign('result', $result);	
			$this->display('school/school/school');
		}		
	}

	public function default_Action()
	{
		try{
			if($this->uid <=2 ) throw new HLPException('设置错误');
			$id = Http::post('id', 'int', 0);
			$school = load_model('admin_user')->getRow(array('school' => $id, 'uid' => $this->uid));
			if(!$school) throw new HLPException('机构不存在，或您不是该机构管理员！');	
			if($school['default'] == 0)
			{
				$res = load_model('admin_user')->update(array('default' => 1), $school['id']);
				if(!$res) throw new HLPException('设置错误');
				load_model('admin_user')->update(array('default' => 0), array('id,!=' => $school['id'], 'uid' => $this->uid)); // 其他机构还原			
			}
			Out(1, '设置成功！');
		}catch(HLPException $e)
		{
			Out(0, $e->getMessage());
		}
	}
	
	public function Ajax_Action()
	{
		$action = Http::post('action', 'trim', 'status');
		$value = Http::post('value', 'int', 0);
		$school = Http::post('school', 'int', 0);
		try{
			if(!$school) throw new HLPException('机构不能为空！');
			if($action == 'status')
			{
				load_model('school')->update(array('status' => $value), $school);
			}else if($action == 'valid'){
				load_model('school')->update(array('valid' => $value), $school);
			}
			Out(1, '设置成功！');
		}catch(HLPException $e)
		{
			Out(0, $e->getMessage());
		}
	}

	public function create_Action()
	{
		if(Http::is_post())
		{
			db()->begin();
			try{
				$name = Http::post('name', 'trim', '');
				if(!$name) throw new HLPException('机构名称不能为空！');
				//$code = Http::post('code', 'trim', '');
				//if(!$code) throw new HLPException('机构号不能为空！');

				$type = Http::post('type', 'int', 0);
				if(!$type) throw new HLPException('机构类型为必选！');
				$contact = Http::post('contact', 'trim', '');
				if(!$contact) throw new HLPException('联系人为必填');
				$province = Http::post('province', 'int', 0);
				$city = Http::post('city', 'int', 0);
				$area = Http::post('area', 'int', 0);
				if(!$area) throw new HLPException('所在地区为必填');
				$phone = Http::post('phone', 'trim', '');
				$phone2 = Http::post('phone2', 'trim', '');
				if(!$phone && !$phone2) throw new HLPException('联系方式至少填写一个');
				$address = Http::post('address', 'trim', '');
				$description = Http::post('description', 'trim', '');
				$operator = $creator = $this->uid;
				$create_time = $avatar = TM;
                $web = Http::post('web', 'trim', '');
				$code = "SH_" . rand(10000, 99999);
				
				$data = compact('name', 'code', 'type', 'province', 'city', 'web', 'area', 'phone', 'phone2', 'contact', 'address', 
                        'description', 'operator', 'creator', 'create_time', 'avatar', 'web', 'lng', 'lat');

				$lng = $lat = '0.00000000';
				if($province && $address)
				{
					import('map');					
					$province = load_model('area')->getRow(array('id' => $province));
					if(preg_match("/[\x7f-\xff]/", $address)) {  //判断字符串中是否有中文	
						$x = Map::getCoordsFromAddress($province['title'], $address);
						print_r($x);
						 list($lng, $lat) = $x;
					}
					if(intVal($lng))
					{
						$data = array_merge($data ,compact('lng', 'lat'));
					}
				}						
				$id = load_model('school')->insert($data);				
				if(!$id) throw new HLPException('机构创建失败！');

				// 处理logo
				if(!empty($_FILES))
				{
					import('file');
					$logo = Http::post('logo', 'trim', '');
					$query = parse_url($logo);
					$logo = $query['path'];				
					$path = Files::get_save_path('school', $id);
					$root = Config::get('path', 'upload');
					$filePath = $root. "/" . $path;	
					Files::mkdir($filePath);				
					@copy($root. "/" .  $logo, $filePath . "original_100_100.jpg");
					@unlink($root. "/" .  $logo);
					@copy($root. "/" .  dirname($logo). "/original.jpg", $filePath . "original.jpg");
					@unlink($root. "/" .  dirname($logo). "/original.jpg");
					@copy($root. "/" .  dirname($logo). "/original_200_200.jpg", $filePath . "original_200_200.jpg");
					@unlink($root. "/" .  dirname($logo). "/original_200_200.jpg");
				}
				// 创建管理员
				$res = load_model('admin_user')->insert(array(
					'uid' => $this->uid,
					'school' =>	$id,
					'gid' => 2,
					'type'=> 'school',
					'enable' => '*'
				));
				if(!$res) throw new HLPException('管理员生成失败');
				db()->commit();
                $this->jump('/', 1);
			}catch(HLPException $e){
				db()->rollback();
				// Out(0, $e->getMessage());
				$this->show_message($e->getMessage(), 'error', array(
					'back' => array('title' => '返回查看', 'url' => '/school/create', 'default' => 1), // 返回到原来的查询页？未处理
					// 'goon' => array('title' => '继续添加', 'url' => '/event/add')
				), 'open');
			}
		}else{			
			$logo = imageUrl($_COOKIE['HLPSESS'], 3, 200);
			$this->assign('logo', $logo);
			$this->assign('types', $this->getSchoolTypes());
			$province_source = load_model('area')->getAll(array('pid' => 0), '', '`sort` asc', true, false, 'id,title');
			$province_ids = array_map(create_function('$item', 'return $item[\'id\'];'), $province_source);
			$province_vals = array_map(create_function('$item', 'return $item[\'title\'];'), $province_source);
			$provinces = array_combine($province_ids, $province_vals);
			$this->assign('provinces', $provinces);
			$this->assign('session_name', session_name());
			$this->display('school/school/register');
		}		
	}

	public function change_Action()
	{
		try{
			$id = Http::get('id', 'int', 0);
			if(!$id) $this->show_error('错误的操作', '/', 0, 1, 5);
			$privs = load_model('user')->get_user_priv($this->uid, $id);
			$school = load_model('school')->getRow($id, false, 'id,code,name,creator');
			if(!$school) $this->show_error('机构不存在，或您不是该机构管理员！', '/', 0, 1, 5);			
			if($this->uid == 2)
			{
				$school['priv'] = '*';
				$school['gid'] = 1;
			}else{
				$school['priv'] = load_model('user')->get_user_priv($this->uid, $id);
				$user = load_model('admin_user')->getRow(array('school' => $id, 'uid' => $this->uid));
				$school['gid'] = $user['gid'];
			}
			load_model('user')->set_school($school);			
			$this->jump('/', 1);
		}catch(HLPException $e)
		{
			$this->show_error('无权限', -1, 0, 1, 5);
		}
	}

	public function set_Action()
	{
		$school = http::post('school', 'int', 0);
		db()->begin();
		try
		{
			if(!$school) throw new HLPException('操作错误！');
			if($this->uid != 2) throw new HLPException('没有权限');
			$account = http::post('account', 'trim', '');
			$user = load_model('user')->getRow("`account`='{$account}' or hulaid='{$account}'", false, 'id');
			if(!$user) throw new HLPException('没有此用户');
			$res = load_model('school')->update(array('creator' => $user['id'], 'operator' => $this->uid), $school);
			if(!$res) throw new HLPException('操作错误！');
			$admin = array(
				'uid' => $user['id'],
				'gid' => 2,
				'school' => $school,
				'enable' => '*'
			);
			$id = load_model('admin_user')->insert($admin);
			if(!$id) throw new HLPException('操作错误！');
			db()->commit();
			Out(1, '成功');
		}catch(Exception $e)
		{
			db()->rollback();
			Out(0, $e->getMessage());
		}
	}

	protected function getOrder($order = array())
	{
		$result = Array();		
		foreach($order as $key => $item)
		{
			switch($key)
			{
				case 'name':
					$key = 'name';
					break;
				case 'date' :// 上课日期
					$key = 'create_time';					
					break;
				case 'code': // 上课时间
					$key = 'code';
					break;						
				default : 
					break;
			}
			$result[$key] = $item;
		}		
		return $result;
	}
	
	// 地区较对
	public function Location_compare_Action()
	{
		set_time_limit(0);
		$schools = load_model('school')->getAll('creator!=2 And `status`=0 And province=310000');
		foreach($schools as $key => &$item)
		{
			import('map');
			$lng = $lat = '0.00000000';
			if(preg_match("/[\x7f-\xff]/", $item['address'])) {  //判断字符串中是否有中文					
				list($lng, $lat) = Map::getCoordsFromAddress('上海市', $item['address']);
				if(intVal($lng) < 1 || intVal($lat)<1) continue;
				if($lag == $item['lag'] && $lat == $item['lat']) continue;
				echo $item['id'] . " " . $item['name']. " " . $item['lng']. " => " . $lng. " | " . $item['lat']. " => " . $lat;
				$res = load_model('school')->update(compact('lng', 'lat'), $item['id']);
				echo ($res ? ' True' : ' False');
				echo "<BR/>";
			}
		}
	}
}