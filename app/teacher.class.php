<?php
/*
 * 老师管理
 * 
*/
class Teacher_Module extends School
{
	private $_perpage = 30;

	public function __construact(){
		parent::__construct();
	}
	
	public function Index_Action()
	{		
		$param = $this->int_search();
		$param['where']['school'] = $this->school;
		extract($param);
		extract($where);		
		$_Model = load_model('school_teacher', Null, true);
		$total = $_Model->get_count($param);
		if(!empty($export))
		{
			$_Model->export($param);
		}
		$param['perpage'] = $this->_perpage;
		$result = $_Model->get_list($param);
	
		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);
		$this->assign('paginator', $paginator);
		$this->assign('record', $total);

		foreach($result as &$item)
		{
			$item['group'] = $_Model->get_teacher_group($item['id'], $this->school);			
		}
		$this->assign('result', $result);
		$this->assign('session_name', session_name());		
		$this->display('school/teacher/teacher');
	}
	
	// 创建
	public function Create_Action()
	{
		$account = Http::post('account', 'trim', '');
		$firstname = Http::post('name', 'trim', '');
		$lastname = Http::post('lastname', 'trim', '');
		// 是否存在
		db()->begin();
		try
		{
			$_User = load_model('user');
			$_Teacher = load_model('teacher');
			if(strlen($account) != 11) throw new Exception('账号格式不正确！');
			$user = $_User->getRow(array('account' => $account));
			if(!$user)
			{
				$user = $_User->create($account, $firstname." ".$lastname);
			}
			if(empty($user)) throw new Exception('创建失败！');
			$teacher = $_Teacher->getRow(array('user' => $user['id']));           
			if(!$teacher)
			{
				$teacher = $_Teacher->insert(array('user' => $user['id'], 'create_time' => TM, 'source' => 2));               
			}
			if(empty($teacher)) throw new Exception('创建失败！');
			$relation = load_model('school_teacher')->getRow(array('school' => $this->school, 'teacher' => $user['id']));
			if(!empty($relation['status'])) 
			{
				$update =  load_model('school_teacher')->update(array('status' => 0), array('school' => $this->school, 'teacher' => $user['id']));
			}else if(isset($relation['status'])){
				throw new Exception('老师已经存在');
			}else{
				$res = load_model('school_teacher')->insert(array(
					'school' => $this->school, 
					'teacher' => $user['id'], 
					'create_time' => TM, 
					'source' => 2
				));
				if(!$res) throw new Exception('创建失败！');
			}			
			db()->commit();
			Out(1, '创建老师成功！');	
		}catch(Exception $e)
		{
            db()->rollback();
			Out(0, $e->getMessage());			
		}
	}
	
	// 导入
	public function Import_Action()
	{		
        db()->begin();
		try{
			if(!Http::is_post()) throw new HLPException('错误的操作！');	
			if(empty($_FILES) || $_FILES['upfile']['error'] !=0 ) throw new HLPException('文件格式错误！');				
			$file = $_FILES['upfile']['tmp_name']; // 验证				
			$source = loadExcel($file, false);				
			if(empty($source['data'][1])) throw new HLPException('文件格式错误！');
			list($name, $phone, $sex) = $source['data'][1];
			if($name != '姓名' || $phone != '联系方式' || $sex != '性别')
			{
				throw new HLPException('Excel格式错误，请下载标准的Excel文件！');
			}			
			$result = load_model('school_teacher')->import($source['data'], $this->school);           
			$message = "<h3>导入成功</h3>";
			$message.= "<div>成功导入：" . $result['count'] . "条记录；";
			$message.= "用户：<span>" . $result['users'] . "</span>个；";
			$message.= "生成老师档案<span>" . $result['teachers'] . "</span>个<div>";				
			if($result['error'])
			{
				foreach($result['error'] as $key => $item)
				{
					$message.= "<div>第{$key}行：{$item}<div>";
				}					
			}
            db()->commit();
			Out(1, $message, $result);			
		}catch(HLPException $e)
		{
            db()->rollback();
			Out(0, $e->getMessage());			
		}
	}
	
	// 分组
	public function Group_Action()
	{
		if(!Http::post())
		{
			$id = Http::get('id');
			$this->assign('id', $id);
			$result = load_model('school_group')->getAll(array('school' => $this->school));				
			$selected = load_model('school_group_teacher')->getColumn(array('school' => $this->school, 'teacher' => $id), 'group');
			$selected && array_walk($result, function(&$v) use ($selected){
				$v['check'] = in_array($v['id'], $selected);
			});				
			$this->assign('result', $result);
			$this->display('school/teacher/group');
		}else{			
			db()->begin();
			try
			{
				$id = Http::post('id');			
				if(!$id) throw new HLPException('错误的操作');
				$groups = Http::post('group');
				if(count($groups) < 1) throw new HLPException('请选择分组');
				is_array($id) || $id = explode(",", $id);
				$old = load_model('school_group_teacher')->getAll(array('school' => $this->school, 'teacher,in' => $id));
				if($old)
				{
					$res = load_model('school_group_teacher')->delete(array('school' => $this->school, 'teacher,in' => $id), true); // 清除原先分组
					if(!$res) throw new HLPException('分组失败！'); // 未分组
				}
				$sql = "";
				foreach($id as $u)
				{
					foreach($groups as $g)
					{
						if(load_model('school_group_teacher')->getRow(array('teacher' => $u, 'school' => $this->school, 'group' => $g))) continue;
						$id = load_model('school_group_teacher')->insert(array('teacher' => $u, 'school' => $this->school, 'group' => $g));
						if(!$id) throw new HLPException('分组失败！');
					}
				}
				db()->commit();
				Out(1, '分组成功！');
			}catch(HLPException $e){
				db()->rollback();
				Out(0, $e->getMessage());
			}
		}
	}
	
	// 删除
	public function Delete_Action()
	{
		db()->begin();
		try{
			if(!Http::is_post()) throw new HLPException('错误的操作！');			
			$id = Http::post('id');
			is_array($id) || $id = explode(",", $id);
			if(empty($id)) throw new HLPException('没有选择要删除的老师！');
			$manager = load_model('admin_user')->getRow(array('school' => $this->school, 'gid' => 2, 'uid,in' => $id));
			if($manager) throw new HLPException('机构管理员不能删除！');
			// 删除与机构关联
			$res = load_model('school_teacher')->delete(array('school' => $this->school, 'teacher,in' => $id)); // 逻辑删除
			if(!$res) throw new HLPException('删除失败！');
			// 删除分组
			load_model('school_group_teacher')->delete(array('school' => $this->school, 'teacher,in' => $id), true);
			// 删除管理员
			load_model('admin_user')->delete(array('school' => $this->school, 'uid,in' => $id), true);
			// 结算课程  所有已排课程结算到今天23：59
			$end_date = date('Y-m-d', time()+18600);
			$where = "school={$this->school} And `type`=1 And assigner in('" .join(",", $id). "')";
			load_model('assign')->delete($where . " And start_date>={$end_date}", true); // 删除未开始的
			load_model('assign')->update(array('end_date' => $end_date), $where . " And (`status`=0 or end_date>={$end_date})"); // 其他结算
			db()->commit();
			Out(1, '成功删除');
		}catch(HLPException $e){			
			db()->rollback();
			Out(0, $e->getMessage());
		}
	}
	
	// 推荐|取消推荐
	public function Recom_Action()
	{		
		if(!Http::is_post())
		{
			$teacher = Http::get('teacher', 'int', 0);
			$result = load_model('teacher_rank')->getRow(array('teacher' => $teacher, 'school' => $this->school, 'type' => 0));
			$this->assign('teacher', $teacher);			
			//$this->assign('courses', $this->getCourses()); //
			$this->display('school/teacher/recomm');
		}else{
			$teacher = Http::post('teacher', 'int', 0);
			$id = Http::post('id', 'int', 0);
			$status = Http::post('status', 'int', 0);
			db()->begin();
			try
			{   
				$course = Http::post('course', 'int', 0);
				$description = Http::post('description', 'trim', '');						
				$res = load_model('school_teacher')->getAll(array('teacher' => $teacher, 'school' => $this->school));
				if(!$res) throw new HLPException("老师不存在！");
				$result = load_model('teacher_rank')->getRow(array('teacher' => $teacher, 'school' => $this->school, 'type' => 0));
				if($status == 1 && $id)  // 修改
				{
					$res = load_model('teacher_rank')->update(array(
						'course' => $course,
						'description' => $description,
						'sort' => TM
					), $id);
					// if(!$res) throw new HLPException("推荐失败");
				}else if($status == 1) // 增加
				{
					if($result) throw new HLPException("请不要重复推荐！");
					$res = load_model('teacher_rank')->insert(array(
						'course' => $course,
						'teacher'=> $teacher,
						'school' => $this->school,
						'type' => 'sch',
						'description' => $description,
						'sort' => TM
					));
					if(!$res) throw new HLPException("推荐失败");
					$res = load_model('school_teacher')->update(array('recomm' => $status), array('teacher' => $teacher, 'school' => $this->school));
					if(!$res) throw new HLPException("推荐失败！");
				}else{
					if(!$result) throw new HLPException("错误的操作！");
					$res = load_model('teacher_rank')->delete(array('teacher' => $teacher, 'school' => $this->school, 'type' => 'sch'), true);
					if(!$res) throw new HLPException("取消失败");
					$res = load_model('school_teacher')->update(array('recomm' => $status), array('teacher' => $teacher, 'school' => $this->school));
					//if(!$res) throw new HLPException("操作失败！");
				}						
				db()->commit();
				Out(1, '操作成功！');
			}catch(HLPException $e)
			{
				db()->rollback();
				Out(0,$e->getMessage());
			}
		}
	}

	public function View_Action(){
		$param = $this->int_search();
		$param['school'] = $this -> school;
		extract($param);
		extract($where);
		$this -> assign('teacherId', $assigner);
		$this -> assign('date',date('Y-m-d', time()));
		$info = load_model('teacher', Null, true)->field('create_time')->where('user', $assigner)->Row();
		$user = load_model('user', Null, true)->field('name,account,birthday,gender,avatar')->where('id', $assigner)->Row();
		$this->assign('info',$info);
		$this->assign('user',$user);
		$_Series = load_model('series', Null, true);
		$course = load_model('course_type', NULL, true)
			->field('name,id')
			->where('pid,>',0)
			->result();
		$rules = Array();

		$param['school'] = $this->school;
		$teachers = $_Series -> get_teacher_series($param);
		import('schedule');
		foreach($teachers as &$item)
		{
			$rules = json_decode($item['rule'],true);
			sort($rules);
			$item['rule'] = Array();
			foreach($rules as $val)
			{
				$item['rule'][] = Schedule::ruleToString($val);
			}
			$item['rule']  = implode('；', $item['rule']);
			foreach($course as $key => $value)
			{
				if($value['id'] == $item['course'])
					$item['course'] = $value['name'];
			}
		}
		$this -> assign('list',$teachers);

		// 备注 || 分页
		$page = Http::get('page', 'int', 0);
		$_Remark = load_model('school_teacher_remark', Null, true);
		$remark = $_Remark->where('school', $this->school)->where('teacher', $id)->Result();
		$total = $_Remark->field('*')->Count();
		$paginator = paginator($page, $total, 10, 1);		
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);
		$this->assign('remark', $remark);
		//评论
		$_Comment =  load_model('comment', Null, true);
		$comment = $_Comment->field('id,creator,content,create_time')->where('school', $this->school)->where('character','student')
			->where('target','teacher')
			->where('to',$assigner)
			->Result();
		$comm_total =  $_Comment->field('*')
			->Count();    
		$this->assign('comm_total', $comm_total);
		$this->assign('comment', $comment);
		//意见
		$_Feedback =  load_model('feedback', Null, true);
		$feedback = $_Feedback->field('id,student,content,create_time')->where('school', $this->school)->where('type', 2)
			->where('to',$assigner)
			->Result();
		$feed_total =  $_Feedback->field('*')->Count();
		$this->assign('feed_total', $feed_total);
		$this->assign('feedback', $feedback);
		$this->display('school/teacher/view');
	}

	public function close_Action()
	{
		$teacher = Http::post('teacher', 'int', 0);
		$sid = Http::post('sid', 'int', 0);
		$date = Http::post('date', 'trim');
		$min = mktime(0,0,0, date('n'), date('j'), date('Y'));
		db()->begin();
		try{
			$time = strtotime($date);
			if(!$teacher || !$sid) throw new HLPException('参数错误！');
			if($time < $min) throw new HLPException('结课日期不能早于今天！');
			$time = strtotime('+1 day',strtotime($date));
			$date = date('Y-m-d',$time);
			$assign = load_model('assign', Null, true)
				->where('sid', $sid)
				->where('type', 1)
				->where('assigner', $teacher)
				->Row();
			if(!$assign) throw new HLPException('无权限或参数不匹配！');
			if($assign['end_date'] != '0000-00-00' ) 
			{	
				 if(strtotime($assign['end_date']) < $time)  
					 throw new HLPException('课程已结束不能结课！');
			}
			// 所有未发生的记录删除
			load_model('schedule_record', Null, true)
				->where('sid', $sid)->where('type', 1)
				->where('assigner', $teacher)
				->where('index,>', $time)
				->delete(true);
			// 所有结束时间 > time的结束时间
			$test = load_model('assign', Null, true)
				->where('sid', $sid)->where('type', 1)
				->where('assigner', $teacher)
				->update(array('end_date' => $date, 'status' => 2));
			// 相关评论
			load_model('comment', Null, true)
				->where('sid', $sid)
				->where('teacher', $teacher)
				->where('index,>', $time)
				->where('pid', 0)
				->delete(true);
			db()->commit();
		}catch(HLPException $e){			
			db()->rollback();
			Out(0, $e->getMessage());
		}	
		Out(1, '结课成功');
	}
	

	public function select_Action()
	{
		$keyword = Http::get('keyword', 'string', '');
		$id = Http::get('id', 'string', '');
		$offset = Http::get('offset', 'int', 0);
		$offset || $offset = 0;
		$this->assign('offset', $offset);
        $this->assign('sid',  Http::get('sid', 'int', ''));
		$this->assign('index', Http::get('index', 'int', 0));
        $this->assign('date', Http::get('date', 'string', ''));
		$this->assign('title', Http::get('title', 'string', ''));
        $selected_hide = Http::get('selected', 'trim', 'show');  //是否显示已选      
		$selected_hide || $selected_hide  = 'show';
        $this->assign('selected_hide', $selected_hide);
      
		$id = $id ? explode(",", $id) : Array();
		$selected = Array();
		$_Series  = load_model('school_teacher',NULL,true);
		$param = array();
		$param['where']['keyword'] = $keyword;
		$param['where']['school'] = $this->school;
		$param['where']['close'] = 0;
		$source = $_Series ->get_list($param,true);
		foreach($source as &$item)
		{
			if(in_array($item['id'], $id))
			{
				$selected[] = $item;
				$item['checked'] = 1;
			}else{
				$item['checked'] = 0;
			}
		}	
        if($selected_hide == 'hide')
        {
            $source = array_filter($source, function($vl){
                if($vl['checked'] == 0) return $vl;
            });
        }        
		// 分组
		$this->assign('selected', $selected);	
		$this->assign('count', $count = count($source));         
		$this->assign('result', $source);
		$this->display('school/teacher/select');
	}
	
	protected function getOrder($order = array())
	{
		$result = Array();		
		foreach($order as $key => $item)
		{
			switch($key)
			{
				case 'name':
					$key = 'u.firstname';
					break;
				case 'date' :// 上课日期
					$key = 'r.create_time';							
				default : 
					$key = 'r.modify_time';
					break;
			}
			$result[$key] = $item;
		}		
		return $result;
	}

	protected function filter($param)
	{
		$result = Array();		
		if(!empty($param['keyword']))
		{
			$result[] = "u.`name` like '%{$param['keyword']}%'";
		}
		if(isset($param['status']))
		{
			$result[] = 'r.`status`=' . (empty($param['status']) ? 0 : $param['status']);
		}	
		$result[] = 'r.school=' . $this->school;		
		return join(" And ", $result);
	}
}
