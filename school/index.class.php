<?php
set_time_limit(0);
class Index_Module extends School
{
	public function __construact(){		
		parent::__construact();		
	}
	
	public function index_Action()
	{	
		if($refer = Http::get_session('refer'))
		{		
			Http::delete_session('refer');
		}else{
			$refer = array(
				'module' => 'base',
				'app' => 'index',
				'act' => 'info',
				'param' => ''
			);
		}
		$schools = load_model('user')->getAllSchool($this->uid);
		$this->assign('schools', count($schools));		
		if(!$this->uid) $this->jump('/login', 1);//$this->show_error('未登录！', '/login', 0, 1, 5);		
		if($this->uid < 2) $this->jump('/school');//$this->show_error('您是系统管理员，请选择机构进入', '/school');		
		if(!$this->school) $this->_set_school($this->uid);		
		if($this->__SCHOOL__['priv'] != '*')
		{			
			$modules = load_model('school_menu')->getAll(array('type' => 'module', 'display' => 1, 'id,in' => $this->__SCHOOL__['priv']), '', '`sort` asc');
		}else{
			$modules = load_model('school_menu')->getAll(array('type' => 'module', 'display' => 1), '', '`sort` asc');
		}		
		$this->assign('modules', $modules);// base item url
		$this->assign('refer', $refer);// base item url		
		$this->display('school/index');
	}

	public function login_Action(){
		if(!Http::is_post())
		{			
			if($this->uid) $this->jump('/', 1);
			$this->assign('account', Http::get('account', 'trim', ''));
			$this->display('school/login');
		}else{
			$username = Http::post('username', 'trim', '');
			$password = Http::post('password', 'trim', '');
			if($this->uid) $this->show_error('已登录！', $this->refer);
			if(!$username || !$password) $this->show_error('登录错误！', '/login');
			$_User = load_model('user');
			$user = $_User->getRow("account='{$username}' or hulaid='{$username}'");
			if(!$user) $this->show_error('用户不存在！', '/login', 1);				
			if($user['password'] != md5(md5($password).$user['login_salt'])) $this->show_error('密码错误！', '/login');				
			$_User->login($user);			
			$this->_set_school($user['id']); // 设置机构			
			$this->jump('/');	
		}
	}
	
	private function _set_school($uid, $school=0)
	{
		if($uid > 2)
		{
			$schools = load_model('user')->getAllSchool($uid);
			if(!$schools) 
			{
				$this->show_error('您还不是机构管理员，请创建或添加机构！', array(
					'default' => array('name' => '创建', 'url' => '/school/create'),
					//array('name' => '申请', 'url' => '/school/apply')
				), 1);
			}			
			$this->assign('schools', $schools);// 用户所有机构
			$default = array();
			if($school)
			{
				$default = array_filter($schools, function(&$v) use ($school){ if($v['id'] == $school) return $v;}); 
				if(empty($default))	$this->show_error('您不是此机构的管理员，请与管理员联系！', -1);				
			}			
			empty($default) && $default = array_filter($schools, function($v){ if($v['default'] == 1) {echo $v['default']; return $v;}}); // 已设置默认
			empty($default) && $default = array_filter($schools, function($v) use($uid){ if($v['creator'] == $uid) {echo $v['creator']; return $v;}}); // 创建者
			empty($default) && $default = $schools;
			$default = current($default);			
		}else if($uid){
			if($school)
			{
				$default = load_model('school')->getRow($school, false, 'id,code,name,creator');			
				if(!$default) $this->show_error('机构不存在或已删除！', -1);
				$default['enable'] = '*';
			}else{
				$this->jump('/school', 1);
			}
		}else{			
			$this->jump('/login', 1);			
		}
		$default['priv'] = load_model('user')->get_user_priv($uid, $default['id']);		
		load_model('user')->set_school($default);		
        $this->_school_init();
		return true;
	}

	public function register_Action()
	{
		if($this->uid) $this->jump('/');
		if(Http::is_post())
		{
			$account = Http::post('mobile', 'string', '');		
			$password = Http::post('password', 'string', '');			
			$verify = Http::post('code', 'string', '');			
			try
			{			
				if(!$account) throw new HLPException('手机号不能为空！');
				if(!$password) throw new HLPException('密码不能为空！');
				if(!$verify) throw new HLPException('验证码不能为空！');
				$_User = load_model('user');		
				if($_User->getRow(array('account' => $account))) throw new HLPException('用户已存在，您可以通过忘记密码找回或重设您的密码!');
				if(!$_User->verify($account, $verify)) throw new HLPException('验证码不正确！');
				$login_salt = rand(10000,99999);
				$password = md5(md5($password) . $login_salt);
				$agent = 0;
                $create_time = TM;
				$user = compact('account', 'password', 'gender', 'nickname', 'login_salt', 'agent', 'create_time');		
				$user['setting'] = json_encode(array(
						"hulaid" => 0,
						"friend_verify" => 1,
						"notice" => array(
							"method" => 0,
							"types" => "1,2,3,4,5"
						)
				));
				load('ustring');				
				$id = $_User->register($user, $verify);	
				if(!$id) throw new HlpException('注册失败！');
				Out(1, '注册成功！');
				// $this->jump('/');
			}catch(HlpException $e)
			{
				Out(0, $e->getMessage());
			}
		}else{
			$this->display('school/register');
		}		
	}
    
	public function forget_Action()
    {
        if(Http::is_post())
		{
			$account = Http::post('mobile', 'string', '');		
			$password = Http::post('password', 'string', '');			
			$verify = Http::post('code', 'string', '');
				if(!$account) $this->show_error('验证码不能为空！', -1);
				if(!$password) $this->show_error('修改成功,请登录！', -1);
				if(!$verify) $this->show_error('修改成功,请登录！', -1);
				$_User = load_model('user');		
				if(!$_User->getRow(array('account' => $account))) throw new HLPException('用户已不存在');
				if(!$_User->verify($account, $verify, 1)) throw new HLPException('验证码不正确！');
				$login_salt = rand(10000,99999);
				$password = md5(md5($password) . $login_salt);
				$_User->update(array('password' => $password, 'login_salt' => $login_salt), array('account' => $account));
				$this->show_error('修改成功,请登录！', '/login', 0, 1, 3);
        }else{
			$this->display('school/forget');
		}		
    }
    
	public function logout_Action(){
		load_model('user')->logout($this->uid);
		$this->jump('/login');
	}

	public function main_Action()
	{
		$this->display('school/main');
	}

	public function left_Action()
	{
		$module = Http::get('module', 'base');
		$_Menu = load_model('menu');
		$items = $_Menu->getAll(array('module' => $module, 'type' => 'item', 'display' => 1), '', '`sort` Asc');		
		$this->assign('items', $items);
		$this->display('school/left');
	}	

	public function guide_Action()
	{
		$result = load_model('cms')->getList();
		$this->assign('result', $result);
		$this->display('guide');
	}

	public function test2_Action()
	{
		ini_set ('memory_limit', '1024M');
		$_Event = load_model('event');
		$_Student_course = load_model('course_student');
		$_Teacher_course = load_model('course_teacher');
		$events = $_Event->getAll(array('status' => 0, 'id,>' => '121232', 'is_loop' => 0));
		ob_start();
		foreach($events as $key=>$item)
		{
			$students = db()->fetchAll('select s.id,s.name,s.name_en as en from t_course_student r left join t_student s on r.student=s.id where r.`event`=' . $item['id']);
			$teachers = db()->fetchAll('select s.`user` id,concat(u.firstname,u.lastname) name,concat(u.firstname_en,u.lastname) en,priv from t_course_teacher r left join t_teacher s on r.teacher=s.`user` Left join t_user u on u.id=r.teacher  where r.`event`=' . $item['id']);
			if($students)
			{
				$student_ids = $students;
				array_walk($student_ids, function(&$v){$v=$v['id'];});
				$students = array_combine($student_ids, array_values($students));
			}
			if($teachers)
			{
				$teacher_ids = $teachers;
				array_walk($teacher_ids, function(&$v){$v=$v['id'];});
				$teachers = array_combine($teacher_ids, array_values($teachers));
			}
			$_Event->update(array('students' => json_encode($students), 'teachers' => json_encode($teachers)), $item['id']);
			echo "${$id} \n";
			ob_flush();
			flush();
		}
		ob_end_flush();
	}
}