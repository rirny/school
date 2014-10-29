<?php
class Public_Module extends School
{
	public function __construact(){
		
	}

	public function index_Action()
	{
		die('Not Found!');
	}
	
	// 
	public function get_area_Action()
	{		
		$id = Http::get('pid', 'int', 0);
		$source = load_model('area')->getAll(array('pid' => $id), '', '`sort` asc', true, false, 'id,title');	
		$ids = array_map(create_function('$item', 'return $item[\'id\'];'), $source);
		$vals = array_map(create_function('$item', 'return $item[\'title\'];'), $source);
		$result = array_combine($ids, $vals);		
		die(json_encode($result));
	}

	public function get_course_type_Action()
	{		
		$id = Http::get('pid', 'int', 0);
		$source = $this->getCourseTypes($id);
		die(json_encode($source));
	}

	public function upload_Action()
	{		
		$type = Http::post('type', 'image');		
		import('file');
		$tm = time();	
		// print_r($_COOKIE);
		try{
			if($type == 'school')
			{
				$school = Http::post('school');
				if(!$school) // 注册临时上传
				{					
					$tm = $_COOKIE['HLPSESS']; //('timestamp', 'trim', 0);
					if(!$tm) throw new HLPException('错误的操作！');					
					$info = Files::upload('school', 'image', $tm, 'upfile');
					$result = array(
						'info' => $info,
						'filepath' => $info['save_path'].$info['save_name']
					);
				}else{
					if($school != $this->school) throw new HLPException('无权限！');			
					$info = Files::upload('school', 'image', $school);			
					$res = load_model('school')->update(array('avatar' => $tm), $school);
					if(!$res) throw new HLPException('上传失败');
					$result = array(
						'info' => $info,
						'filepath' => $info['save_path'].$info['save_name']
					);
				}			
				out(1, '成功', $result);
			}else{
				throw new HLPException('上传失败');
			}
		}catch(HlpException $e)
		{
			out(0, $e->getMessage());
		}
	}

	public function excel_Action()
	{
		$type = Http::get('type', 'trim', 'teacher');
		if($type == 'teacher')
		{
			$title = '老师导出样本';
			$header = array('注：1、老师姓与名之间保留空格；2、联方式必须为手机且必填；3、请保持文件格式不变，删除样例数据，从第3行插入数据');
			$data = array(
				array('姓名','联系方式','性别'), 
				array('王 老师', '13200001111', '男')
			);
		}else{
			$title = '学生导出样本';
			$header = array('注：1、学生姓名为必填；2、联系人手机必须有一个；3、生日格式(2010-11-12)；4、请保持文件格式不变，删除样例数据，从第3行插入数据');
			$data = array(
				array('学号','姓名','妈妈手机','爸爸手机','其他手机','生日','性别'), 
				array('001', '王小二', '13200001111', '13200001112', '13200001113', '2010-11-12', '女')
			);
		}
		excelExport($title, array_values($header), $data, '2007', true);
	}

	public function code_Action()
	{
		$mobile = Http::post('mobile', 'string');
        $type = Http::post('type', 'int', 0); // 0 注册 1、找回密码        
        db()->begin();
		try{	
            if(!$mobile) throw new HLPException('请输入手机号！');	
            $message = '';
            if($type == 0)
            {
                if(load_model('user')->getRow(array('account' => $mobile))) throw new HLPException ('用户已存在，您可以通过忘记密码找回或重设您的密码!');
                $message = Config::get('register', 'notice', Null, Null);
            }else if($type == 1)
            {
                if(!load_model('user')->getRow(array('account' => $mobile))) throw new HLPException('用户不存在！');
                $message = Config::get('forget', 'notice', Null, Null);
            }
            $_Verify = load_model('verify');
				
			$res = $_Verify->send($mobile, $type, $message);
			if(is_array($res))
			{
				db()->commit();
				Out(1, '成功', array('code' => $res['code']));
			}else
			{
				$Errors = config::get('sms', 'error', Null, '');
				$error = isset($Errors[$res]) ? $Errors[$res] : '发送失败';				
				Out($res, $error);
			}
		}catch(HLPException $e)
		{
			db()->rollback();
			Out(0, $e->getMessage());
		}
	}

	public function mobile_exists_Action()
	{
		$mobile = Http::request('mobile');
		if(!$mobile) throw new HlpException('手机号码不能为空！');
		$user = load_model('user')->getRow(array('account' => $mobile));
		if($user) Out(2, '存在');
		Out(1, '正常');
	}
	public function hulaid_Action()
	{
		$hulaid = Http::request('hulaid');
		if(!$hulaid) throw new HlpException('手机号码不能为空！');
		$user = load_model('user')->getRow(array('account' => $hulaid));
		if($user) Out(2, '存在');
		Out(1, '正常');
	}
	public function school_code_exists_Action()
	{
		$code = Http::request('code');
		if(!$code) throw new HlpException('机构号不能为空');
		$id = Http::request('id');
		if($id)
		{
			$res = load_model('school')->getRow($id);
			if(!$res) throw new HlpException('机构不存在！');
			if($res['code_set'] == 1) throw new HlpException('机构只能设置一次！');			
			if($res['code'] == $code) Out(1, '正常！');
		}
		$school = load_model('school')->getRow(array('code' => $code));
		if($school) Out(2, '存在');
		Out(1, '正常');
	}


	public function resetEvent_Action()
	{
		$_Event = load_model('event');
		$_Student_course = load_model('course_student');
		$_Teacher_course = load_model('course_teacher');
		$events = $_Event->getAll(array('status' => 0));
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
			flush();
		}
		ob_end_flush();
	}

	public function student_pin_Action()
	{
		$res = load_model('student')->getAll("name_en=''");
		load('ustring');
		foreach($res as $key=>$item)
		{			
			$name_en = Ustring::topinyin($item['name']);
			load_model('student')->update(array('name_en' => $name_en), $item['id']);
			echo $name . "\t" . $name_en . "\n";
			flush();
			ob_flush();
		}		
	}

	public function relation_check_Action()
	{
		set_time_limit(0);
		$start = Http::get('start', 'int', 0);
		$start = $start * 1000;
		$events = load_model('event')->getAll(array('school,>' => 0, 'status' => 0), "30000,150000", '');
		foreach($events as $event)
		{
			echo $event['id'] . "<Br/>";
			$teachers = load_model('course_teacher')->getColumn(array('event' => $event['id']), 'teacher');			
			$students = load_model('course_student')->getColumn(array('event' => $event['id']), 'student');
			if($teachers && $students)
				load_model('teacher_student')->createMultiRelation($teachers, $students, $event['school']);			
		}		
	}

	public function Rank_import_Action()
	{
		$config = Config::get(Null, 'rank', 'rank');
		$users = $config['users'];
		$teachers = $config['teachers'];
		$comments = $config['comments'];
		
		$rand = array_rand($users, 1);
		
		db()->begin();
		//Array('97','12234','谢谢老师的关心，我们作为家长很放心把孩子交给你们。','2014/4/29 21:24'),
		foreach($comments as $key => $item)
		{
			if(!$item[2]) continue;
			$time = date('Y-m-d H:i', strtotime($item[3]));
			$rand = array_rand($users, 1);
			$creator = $users[$rand];
			if(!$time) continue;
			$data = array(
				'pid' =>0,
				'school' => $item[0], 
				'teacher' => $item[1], 
				'content' => $item[2],
				'create_time' => $time,
				'creator' => $creator,
				'character' => 'student',
				'ext' => 'import'
			);
			print_r($data);
			$res = load_model('comment')->insert($data);
		}		

		foreach($teachers as $teacher)
		{
			$rdata = array(
				'type' => 'qf',
				'school' => $teacher['school'], 
				'teacher' => $teacher['teacher'], 
				'sort' => time(),
				'term' => 1
			);	
			print_r($rdata);
			$res = load_model('teacher_rank')->insert($rdata);
		}

		db()->commit();
	}
}