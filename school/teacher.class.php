<?php
// 学生
class Teacher_Module extends School
{
	private $tpl = '';
	private $_perpage = 10;

	public function __construact(){
	
	}
	
	public function index_Action(){		
		extract($this->int_search());		
		$_Relation = load_model('school_teacher');
		$where['keyword'] = Http::get('keyword', 'string', '');

		// $where['group'] = $group;
		empty($where['status']) || $where['status'] = 2;		
		$this->assign('statusArr', array('正常', '冻结'));
		// 分组
		$groupId = $groupName = $groupArr = load_model('school_group')->getAll(array('school' => $this->school));		
		array_walk($groupId, create_function('&$v', '$v=$v[\'id\'];'));
		array_walk($groupName, create_function('&$v', '$v=$v[\'name\'];'));
		$groupArr = array_combine($groupId, $groupName);
		$this->assign('groupArr', $groupArr);
		
		$whereStr = $this->filter($where);
		$total = $_Relation->getTeacherCount($whereStr, 'count(*) as n');		
		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);		
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);
		
		$order = $this->getOrder($order);
		$limit = $_Relation->getLimit($this->_perpage, $page);
		$export = Http::get('export', 'int', 0);
		if($export)
		{
			$result = $_Relation->getTeacher($whereStr, $order, '', 'u.id, concat(u.firstname,u.lastname) as \'name\',concat(u.firstname_en,u.lastname_en) As en,r.create_time,u.account,r.`status`,r.recomm');			
		}else{
			$result = $_Relation->getTeacher($whereStr, $order, $limit, 'u.id,concat(u.firstname,u.lastname) as \'name\',concat(u.firstname_en,u.lastname_en) As en,r.create_time,u.account,r.`status`,r.recomm,u.login_times');	
		}
		$groupTeacher = load_model('school_group_teacher')->getAll(array('school' => $this->school), '', '', false, false, 'teacher,`group`');
		array_walk($result, function(&$item) use ($groupArr, $groupTeacher){
			$id = $item['id'];
			$group = array_filter($groupTeacher, function($v) use ($id){if($v['teacher'] == $id){unset($v['teacher']);return $v;}});
			array_walk($group, function(&$g) use($groupArr){
				$g = $groupArr[$g['group']];
			});
			$item['group'] = join(" ", array_values($group));
		});
		//print_r($result);
		$export && $this->_export($result);
		$this->assign('result', $result);
		$this->assign('session_name', session_name());
		$this->display('school/teacher/teacher');
	}
	
	private function _export($source=Array(), $teachers=Array(), $course=Array())
	{
		$result = array();
		$title = Array(
			'id' => '编号',
			'name' => '姓名',
			'group'=> '分组',
			'account' => '联系方式',
			'create_time' => '加入时间',
			'status' => '状态'
		);
		foreach($source as $key => &$item)
		{
			$item['create_time'] = date('Y-m-d', $item['create_time']);
			$result[] = Array(
				'id' => $item['id'],
				'name' => $item['name'],
				'group' => $item['group'],
				'account' => $item['account'],
				'create_time' => $item['create_time'],
				'status' => $item['status'] ? '冻结' : '正常'
			);			
		}
		excelExport('老师导出', array_values($title), $result);
		Header('Location:' . Http::curl());
	}

	public function import_Action()
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

	// 老师详情
	public function view_Action()
	{
		$id = Http::get('id', 'int', 0);
		$relation = load_model('school_teacher')->getRow(array('school' => $this->school, 'teacher' => $id));
		if(!$relation) $this->show_error('老师不存在！', $this->refer);
		$this->assign('relation', $relation);
		
		//学生档案
		$result = load_model('user')->getRow($id);
        
		if(!$result) $this->show_error('老师不存在或已删除！', $this->refer);
        
		array_walk($parents, function(&$v){			
			$v['account'] = $user['account'];
			$v['name'] = $user['firstname'] . $user['lastname'];			
		});		
		$result['avatar'] = imageUrl($id, 1);
       
		$this->assign('result', $result);
		// 备注
		$page = Http::get('page', 'int', 0);
		$_Remark = load_model('remark');
		$where = array('target' => $id, 'type' => 'teacher', 'school' => $this->school);
		$total = $_Remark->getCount($where, 'count(*)');		
		$paginator = paginator($page, $total, 5, 5);		
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);
		$order = 'create_time Desc'; //$_Remark->getOrder(array(''));
		$limit = $_Remark->getLimit($this->_perpage, $page);	
		$remarks = $_Remark->getAll($where, $limit, $order);
		$this->assign('remarks', $remarks);
        $sources = $this->getRemarkTypes();
        $this->assign('sources', $sources);
		$this->assign('tid', $id);
		$this->display('school/teacher/view');
	}

	// 删除检查
	public function check_Action()
	{
		try{
			if(!Http::is_post()) throw new HLPException('错误的操作！');			
			$id = Http::post('id');
			is_array($id) || $id = explode(",", $id);
			if(empty($id)) throw new HLPException('没有选择要删除的老师！');
			$sql = "select count(e.id) from t_course_teacher r left join t_event e on r.`event`=e.id where r.teacher in(" . join(",", $id) . ") And e.school={$this->school} And e.`status`=0 And r.`status`=0";
			$result = db()->fetchOne($sql);
			if($result) throw new HLPException('已有课程的老师不能删除！');
			$manager = load_model('admin_user')->getRow(array('school' => $this->school, 'teacher,in' => $id));
			if($manager) throw new HLPException('机构管理员不能删除！');				
			Out(1);
		}catch(HLPException $e){
			Out(0, $e->getMessage());
		}		
	}
	
	public function schedule_Action()
	{	
		if(!Http::is_post())
		{
			if(Http::get('id'))
			{
				$idArr = array();
				$id = Http::get('id');
				if(strripos($id, ','))
				{
					$idArr = explode(',' , $id);
				}else{
					$idArr[0] = $id;
				}
				$this->assign('idArr', $idArr);
			}
			extract($this->int_search());
			$where['keyword'] = Http::get('keyword', 'string', '');
			$whereStr = $this->scFilter($where);
			$schedule = load_model('schedule');
			$order = $this->getScOrder($order);
			$limit = $schedule->getLimit($this->_perpage, $page);

			$result = $schedule ->getList($whereStr, $order, $limit,'s.id,c.name,s.title,s.sort');
			$total = $schedule ->getCount($whereStr, 'count(*) as n');		
			$sr = load_model('schedule_rule',NULL,true);
			$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);
			$this->assign('paginator', $paginator);

			for($i = 0;$i < count($result);$i++)
			{
				$rule = $sr -> field('start,end,week,period')
					->where('schedule',$result[$i]['id'],true)
					->result();
				$result[$i]['time'] = $this-> convert($rule);
				$result[$i]['times'] = $rule[0]['period'];
			}
			if(Http::get('export', 'int', 0) == 1)
			{
				$this->scExport($schedule->getList($result));
				exit;
			}

			$this->assign('start', date('Y-m-d', time()));	
			$this->assign('record', $total);		
			$this->assign('result', $result);
			$this->display('school/teacher/schedule');		
		}else{
			$this -> _save();
		}
	}

	private function _save(){
		if(!Http::is_post()) throw new HLPException('错误的操作！');
		extract(Http::post());
		$schedule_assign = load_model('schedule_assign', NULL, true);
		$schedule = load_model('schedule', NULL, true);
		for($i = 0 ;$i < count($teacher); $i++)				
		{
			$result = $schedule_assign -> insert(array('schedule' => $schedule , 'assign' => $teacher[$i] , 'type' => 1 , 'start_date' => $start , 'times' => 0 , 'school' =>	$this->school));
			if(!$result) throw new HLPException("排课失败！");   
		} 
		$res = $schedule_assign -> field('id')
			->where('schedule', $schedule)
			->where('type', 0)
			->result();
		/*if($res)
		{
			$scres =  $schedule -> update(array('status'=>1));
			if(!$scres) throw new HLPException("排课失败！");
		}*/
		Out(1, 'success', $teacher);
	}

	// 删除老师将从分组，管理员权限组，管理员中删除
	public function delete_Action()
	{
		try{
			if(!Http::is_post()) throw new HLPException('错误的操作！');			
			$id = Http::post('id');
			is_array($id) || $id = explode(",", $id);
			if(empty($id)) throw new HLPException('没有选择要删除的老师！');
			$sql = "select count(e.id) from t_course_teacher r left join t_event e on r.`event`=e.id where r.teacher in(" . join(",", $id) . ") And e.school={$this->school} And e.`status`=0 And r.`status`=0";
			$result = db()->fetchOne($sql);
			if($result) throw new HLPException('已有课程的老师不能删除！');
			$manager = load_model('admin_user')->getRow(array('school' => $this->school, 'uid,in' => $id));
			if($manager) throw new HLPException('机构管理员不能删除！');
			// 删除与机构关联
			load_model('school_teacher')->delete(array('school' => $this->school, 'teacher,in' => $id), true);
			// 删除分组
			load_model('school_group_teacher')->delete(array('school' => $this->school, 'teacher,in' => $id), true);
			// 删除管理员
			// load_model('admin_user')->delete(array('school' => $this->school, 'teacher,in' => $id), true);
			Out(1, '成功删除');		
		}catch(HLPException $e){
			Out(0, $e->getMessage());
		}
	}
	
	 public function ajax_Action()
    {
        $action = Http::request('action', 'trim', '');
        switch ($action)
        { 
			case 'freeze' : //冻结
				$id = Http::post('id', 'trim'); 
				$status = Http::post('status', 'int', 0);				
                db()->begin();
                try
                {   
                    is_array($id) || $id = explode(",", $id);                   
                    $res = load_model('school_teacher')->getAll(array('teacher,in' => $id, 'school' => $this->school));                   
                    if(!$res) throw new HLPException("老师不存在！");                    					
                    $res = load_model('school_teacher')->update(array('status' => $status), array('teacher,in' => $id, 'school' => $this->school));
                    if(!$res) throw new HLPException("操作失败！");
                    db()->commit();
                    Out(1, '操作成功！');
                }catch(HLPException $e)
                {
                    db()->rollback();
                    Out(0,$e->getMessage());
                }     
				break;
			case 'recomm': // 推荐
				$teacher = Http::request('teacher', 'int', 0);
				$id = Http::request('id', 'int', 0);
				$status = Http::request('status', 'int', 0);
				if(Http::is_post())
				{
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
				}else{
					$result = load_model('teacher_rank')->getRow(array('teacher' => $teacher, 'school' => $this->school, 'type' => 0));
					$this->assign('result', $result);
					$this->assign('status', $status);
					$this->assign('teacher', $teacher);			
					$this->assign('courses', $this->getCourses()); //
					$this->display('school/teacher/recomm');
				}
				break;
            default:
                break;
        }
    }

	public function toGroup_Action()
	{
		db()->begin();
		try
		{
			if(!Http::post())
			{
				$id = Http::request('id');
				$this->assign('id', $id);
				$result = load_model('school_group')->getAll(array('school' => $this->school));				
				$selected = load_model('school_group_teacher')->getColumn(array('school' => $this->school, 'teacher' => $id), 'group');				
				$selected && array_walk($result, function(&$v) use ($selected){
					$v['check'] = in_array($v['id'], $selected);
				});				
				$this->assign('result', $result);
				$this->display('school/teacher/group');
			}else{				
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
			}
		}catch(HLPException $e){
			db()->rollback();
			Out(0, $e->getMessage());
		}
	}

	public function select_Action()
	{
		// $this->school = 24;
		$keyword = Http::get('keyword', 'string', '');
		$id = Http::get('id', 'string', '');
		$priv = Http::get('priv', 'int', 0);
		$this->assign('priv', $priv);
		$privs = Http::get('privs');
		$this->assign('privs', explode(",", $privs));
		$offset = Http::get('offset', 'int', 0);
		$offset || $offset = 0;
		$this->assign('offset', $offset);
        
        $group_show = Http::get('group', 'trim', 'show'); //是否显示班组
		$group_show || $group_show = 'show';
		$this->assign('group_show', $group_show); 
        $selected_hide = Http::get('selected', 'trim', 'show');  //是否显示已选      
		$selected_hide || $selected_hide  = 'show';
        $this->assign('selected_hide', $selected_hide);
		$handle = Http::get('handle', 'trim', ''); // 操作
		$this->assign('handle', $handle);
        
        $gid = Http::get('gid', 'int', 0);
		$this->assign('gid', $gid);
        
		$src = Http::get('src', 'trim', '');
		$this->assign('src', $src);// group event privgroup

		$id = $id ? explode(",", $id) : Array();
		$where = Array('status' => 0, 'keyword' => $keyword);
		$whereStr = $this->filter($where);
		$_Relation = load_model('school_teacher');
		$order = $_Relation->getOrder(array('u.firstname' => 0));		
		$source = $_Relation->getTeacher($whereStr . " And r.`status`=0 And u.id>0 And u.firstname<>''", $order, '', 'u.id,concat(u.firstname,u.lastname) name,concat(u.firstname_en,u.lastname_en) As en'); 
        
		$groups = load_model('school_group')->getAll(array('school' => $this->school), '', '', false, false, 'id, name');
		$selected = Array();
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
		$groupSource = Array();
		$sourceGroup = load_model('school_group_teacher')->getAll(array('school' => $this->school));
		foreach($sourceGroup as $gitem)
		{
			$groupSource[$gitem['group']][] = $gitem['teacher'];
		}		
		$this->assign('groupSource', json_encode($groupSource, JSON_HEX_QUOT));
		$this->assign('selected', $selected);
		$this->assign('groups', $groups);		
		$this->assign('count', $count = count($source));        
		if($count > 20)
		{
			$source = load_model('school_student')->sorts($source);
		}        
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
	protected function getScOrder($order = array())
	{
		$result = Array();		
		foreach($order as $key => $item)
		{
			switch($key)
			{
				case 'title'://课程名称
					$key = 'title';
					break;
				case 'course' :// 科目
					$key = 'name';					
					break;
				case 'time': // 上课时间
					$key = 'sort';
					break;						
				default : 
					break;
			}
			$result[$key] = $item;
		}		
		return $result;
	}

	private function scExport($source=Array())
	{
		$title = Array(
			'title' => '课程名称',
			'name' => '科目',			
			'time' => '上课时间',			
			'times' => '课时/次'
		);
		foreach($source as $key => &$item)
		{
			$result[] = Array(
				'title' => $item['title'],	
				'name' => $item['name'],
				'time' => $item['time'],	
				'times' => $item['times']
			);			
		}	
		excelExport('剩余课程导出', array_values($title), $result);
		Header('Location:' . Http::curl());
	}

	public function scFilter($param)
	{
		$result = Array();		
		if(!empty($param['keyword']))
		{
			$result[] = "s.title like '%{$param['keyword']}%'";
		}

		$result[] = 's.school=' . $this->school;	
		return join(" AND ", $result);
	}
}