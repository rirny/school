<?php
/*
 * 学生
 * 
*/
class Student_Module extends School
{
	private $_perpage = 30;

	public function __construact(){
		parent::__construct();
	}

	/*
	 * index
	 * @
	*/
	public function Index_Action()
	{		
		$param = $this->int_search();
		$param['where']['school'] = $this->school;
		extract($param);
		extract($where);
		$_Model = load_model('school_student', Null, true);
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
			$item['grade'] = $_Model->get_student_grade($item['id'], $this->school);
			$concators = $_Model->get_student_concat($item['id']);
			$item['concator'] = '';
			foreach($concators as $val)
			{
				$item['concator'] && $item['concator'] .= " ";
				$item['concator'] .= $val['account'];
			}			
		}
		$this->assign('result', $result);
		$this->assign('session_name', session_name());		
		$this->display('school/student/student');
	}	

	/*
	 * 导入
	 * @
	*/
	public function Import_Action()
	{
		db()->begin();
        try{
			if(!Http::is_post()) throw new HLPException('错误的操作！');	
			if(empty($_FILES) && $_FILES['upfile']['error'] !=0) throw new HLPException('文件格式错误！');				
			$file = $_FILES['upfile']['tmp_name']; // 验证				
			$source = loadExcel($file, false);				
			if(empty($source['data'][1])) throw new HLPException('文件格式错误！');	
            array_walk($source['data'][1], create_function('&$v', '$v = trim($v);'));
            list($no, $name , $mother, $father, $else, $birthday, $sex) = $source['data'][1];
            if(trim($no) != '学号' || $name != '姓名' || $mother != '妈妈手机' || $father != '爸爸手机' || $else != '其他手机' || $sex != '性别' || $birthday != '生日')
            {
                throw new HLPException('不是标准的Excel文件！');
            }
			$result = load_model('school_student')->import($source['data'], $this->school);            
			$message = "<h3>导入成功</h3>";
            $message.= "<div>成功导入：" . $result['count'] . "条记录；";
            $message.= "用户：<span>" . $result['users'] . "</span>个；";
            $message.= "生成学生档案<span>" . $result['students'] . "</span>个<div>";					
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

	/*
	 * 创建
	 * Ajax
	*/
	public function Create_Action()
	{		
		$name = Http::post('name', 'trim', ''); // 学生名
		$tmp = Http::post('parents','trim', ''); // array('relation', 'account', 'name')
		$parents = Array();

		for($i=0;$i<count($tmp['relation']);$i++)
		{
			$parents[$i]['relation'] = $tmp['relation'][$i];
			$parents[$i]['account'] = $tmp['account'][$i];
			$parents[$i]['name'] = '';
		}
		
		db()->begin();
		try
		{
			if(!$name || empty($parents)) throw new HLPException('创建失败！');
			$_User = load_model('user');
			$_Student = load_model('student');
			
			$parentArr = Array();
			foreach($parents as &$parent)
			{				
				$user = $_User->getRow(array('account' => $parent['account']));
				if(!$user)
				{
					$user = $_User->create($parent['account'], '', 0);
				}
				$parentArr[] = $user['id'];
				$parent['id'] = $user['id'];				              
			}			
			$student = $_User->hasStudent($name, $parentArr); // 是否已经有此学生档案
			if(!$student)
			{
				$student = $_Student->create(compact('name', 'birthday', 'gender')); // 没有则创建档案	
				$creatorParent = current($parentArr);
				$_Student->update(array('creator' => $creatorParent), $student);
				$result['students']++;
			}
			// 创建关联
			foreach($parents as $val)
			{
				if(!in_array($val['relation'], array(1,2,3,4))) $val['relation'] = 4;
				$res = load_model('user_student')->createRelation($val['id'], $student, $val['relation']); // 创建家长关系
				if(!$res) throw new HLPException('创建失败！');
			}
			// 创建机构关联
			$res = load_model('school_student')->insert(array('school' => $this->school, 'student' => $student, 'create_time' => TM, 'source' => 2));
			if(!$res) throw new HLPException('创建失败！');
			db()->commit();
			echo "<script>window.top.art.dialog({'content' : '创建成功', 'lock' : true, 'icon' : 'succeed', 'ok' : function(){window.top.right.location.href='/student';}});</script>";	
		}catch(HLPException $e)
		{
            db()->rollback();
			Out(0, $e->getMessage());			
		}
	}
	
	// 分班
	public function Grade_Action()
	{
		if(!Http::post())
		{
			$id = Http::get('id');
			$this->assign('id', $id);
			$result = load_model('grade')->getAll(array('school' => $this->school));				
			$selected = load_model('grade_student')->getColumn(array('school' => $this->school, 'student' => $id), 'grade');
			$selected && array_walk($result, function(&$v) use ($selected){
				$v['check'] = in_array($v['id'], $selected);
			});				
			$this->assign('result', $result);
			$this->display('school/student/grade.ajax');
		}else{			
			db()->begin();
			try
			{
				$id = Http::post('id');			
				if(!$id) throw new HLPException('错误的操作');
				$grades = Http::post('grade');
				if(count($grades) < 1) throw new HLPException('请选择班级');
				is_array($id) || $id = explode(",", $id);
				$res = load_model('grade_student')->delete(array('school' => $this->school, 'student,in' => $id), true); // 清除原先分组
				foreach($id as $student)
				{
					foreach($grades as $grade)
					{
						if(load_model('grade_student')->getRow(array('student' => $student, 'school' => $this->school, 'grade' => $grade))) continue;
						$res = load_model('grade_student')->insert(array('student' => $student, 'school' => $this->school, 'grade' => $grade));
						if(!$res) throw new HLPException('分组失败！');
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
		db() ->begin();
		try{
			if(!Http::is_post()) throw new HLPException('错误的操作！');			
			$id = Http::post('id');
			is_array($id) || $id = explode(",", $id);
			if(empty($id)) throw new HLPException('没有选择要删除的学生！');			
			// 删除与机构关联
			$res = load_model('school_student')->delete(array('school' => $this->school, 'student,in' => $id)); // 逻辑删除
			if(!$res) throw new HLPException('删除失败！');
			// 删除分组
			load_model('grade_student')->delete(array('school' => $this->school, 'student,in' => $id), true);
			
			// 结算课程  所有已排课程结算到今天23：59
			$end_date = date('Y-m-d', time()+18600);
			$where = "school={$this->school} And `type`=1 And assigner in('" .join(",", $id). "')";
			load_model('assign')->delete($where . " And start_date>={$end_date}", true); // 删除未开始的
			load_model('assign')->update(array('end_date' => $end_date, 'status' => 1), $where . " And end_date>={$end_date}"); // 其他结算
			db()->commit();
			Out(1, '成功删除');
		}catch(HLPException $e){			
			db()->rollback();
			Out(0, $e->getMessage());
		}
	}
	
	/*
	* 学生详情
	*/
	public function View_Action()
	{
		// 学生信息
		$id = Http::get('id');
		$this -> assign('date',date('Y-m-d', time()));
		$student = load_model('student', Null, true)->where('id', $id)->Row();
		$_Relation = load_model('school_student', Null, true);
		$student['create_time'] = $_Relation->field('create_time')
			->where('student', $id)
			->where('school', $this->school)			
			->limit(1)
			->Column();
		// $grade = load_model('school_student', Null, true)->get_student_grade($id, $this->school);
		$concator = $_Relation->get_student_concat($id);
		$relations = $this->getRelations();
		foreach($concator as &$item)
		{
			isset($relations[$item['relation']]) && $item['relation'] = $relations[$item['relation']];
		}		
		$student['concator'] = $concator;
		$this->assign('student', $student);
		// 备注 || 分页
		$page = Http::get('page', 'int', 0);
		$remark = load_model('school_student_remark', Null, true)->where('school', $this->school)->where('student', $id)->limit(20, 1)->Result();
		$total =  load_model('school_student_remark', Null, true)->where('school', $this->school)->where('student', $id)->Count();

		$paginator = paginator($page, $total, 5, 5);		
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);
		$this->assign('sid', $id);
		$this->assign('remark', $remark);
		// schedule
		$where['school'] = $this->school;
		$where['assigner']= $id;
		$where['order']['time'] = 1;
		$where['cache'] = true;
		$series = load_model('series', Null, true)->get_student_series($where);
		$schedules = Array();
		import('schedule');
		$_Record = load_model('schedule_record', Null, true);

		foreach($series as $key => $item)
		{
			// count
			//$change = $_Record->where('sid',$item['id'])->where('assign',$id)->where('type',0)->where('protype', 'change')->result();
			$times = $attend = $absence = $leave = $pass = 0;			
			if(empty($schedules[$item['id']])) // 合并
			{
				$schedules[$item['id']] = $item;
				$times = $item['times'];
			}else{
				$times += $item['times'];
			}	
			// 记录
			$records = $_Record->where('sid', $item['id'], true)->where('assigner',$id)->Result();

			$attendRes = $remarkRes = $changeRes = $delayRes = Array();
			foreach($records as $val)
			{				
				if($val['protype'] == 'attend') $attendRes[$val['index']] = $val['value']; // 考勤
				if($val['protype'] == 'remark') $remarkRes[$val['index']] = $val['value']; // 备注
				if($val['protype'] == 'change') $changeRes[$val['index']] =$val['value']; // 调课
				if($val['protype'] == 'delay') $delayRes[$val['index']] =$val['value']; // 顺延
			}			
			$rules = json_decode($item['rule'], true);
			$tmp = Array();
			$sches = Schedule::resolve($rules, $item['start_date'], $item['end_date']);
					
			$events = $index = Array();		
			
			foreach($sches as $key => &$sche)
			{	
				if(isset($changeRes[$sche['start']]))
				{
					$sche['start'] = $changeRes[$sche['start']];
				}
				if(isset($delayRes[$sche['start']]))
				{
					unset($sche[$key]);
					continue;
				}
				$data = Array();
				$data['index'] = $sche['start'];
				if($data['index'] > time()) $pass++;
				$data['end'] = $sche['end'];
				$data['times'] = $sche['times'];
				$data['date'] = date('n月j号', $sche['start']);				
				$data['week'] = $this->getWeek(date('w', $sche['start']));
						
				if(isset($attendRes[$data['index']]))
				{
					$data['attend'] =  $attendRes[$data['index']];
					switch($data['attend'])
					{
						case 0:
							$attend ++;
							break;
						case 1:
							$absence ++;
							break;
						case 2:
							$leave ++;
							break;
					}
				}else{
					$data['attend'] = 0;
				}
				$data['remark'] = isset($remarkRes[$data['index']]) ? $remarkRes[$data['index']] : '';
				$index[] = $data['index'];

				$events[] = $data;
			}
			$remain = $times - $attend - $absence - $leave;
			$count = compact('times', 'attend', 'absence', 'leave','remain');
			array_multisort($index, SORT_ASC, $events);

			$schedules[$item['id']]['events'] = $events;
			$schedules[$item['id']]['count'] = $count;
			sort($rules);
			foreach($rules as $val)
			{
				$tmp[] = Schedule::ruleToString($val);
			}
			$schedules[$item['id']]['rule']  = implode('；', $tmp);	
		}

		$this->assign('schedule',$schedules);
		$this->display('school/student/view');		
	}


	public function close_Action()
	{
		$student = Http::post('student', 'int', 0);
		$sid = Http::post('sid', 'int', 0);
		$date = Http::post('date', 'trim');
		$min = mktime(0,0,0, date('n'), date('j'), date('Y'));
		db()->begin();
		try{
			$time = strtotime($date);
			if(!$student || !$sid ) throw new HLPException('参数错误！');
			if($time < $min) throw new HLPException('结课日期不能早于今天！');
			$time = strtotime('+1 day',strtotime($date));
			$date = date('Y-m-d',$time);
			$assign = load_model('assign', Null, true)
				->where('sid', $sid)
				->where('type', 0)
				->where('assigner', $student)
				->Row();
			if(!$assign) throw new HLPException('无权限或参数不匹配！');
			if($assign['end_date'] != '0000-00-00' ) 
			{	
				 if(strtotime($assign['end_date']) < $time)  
					 throw new HLPException('课程已结束不能结课！');
			}
			// 所有未发生的记录删除
			load_model('schedule_record', Null, true)
				->where('sid', $sid)->where('type', 0)
				->where('assigner', $student)
				->where('index,>', $time)
				->delete(true);
			// 所有结束时间 > time的结束时间
			load_model('assign', Null, true)
				->where('sid', $sid,true)->where('type', 0)
				->where('assigner', $student)
				->where('end_date,>', $date)
				->update(array('end_date' => $date, 'status' => 2));
			// 相关评论
			load_model('comment', Null, true)
				->where('sid', $sid)
				->where('student', $student)
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
	
	// 续课
	public function Longer_Action()
	{
		$student = Http::post('student', 'int', 0);
		$sid = Http::post('sid', 'int', 0);
		$times = Http::post('times', 'int', 0);
		
		db()->begin();
		try{
			$time = strtotime($date);
			if(!$student || !$sid ||  $times < 1) throw new HLPException('参数错误！');
			$_Assign = load_model('assign', Null, true); 
			$assign = $_Assign->where('sid', $sid)
				->where('type', 0)
				->where('assigner', $student)
				->order('end_date', 'Desc') // 最大的
				->Row();
			if(!$assign) throw new HLPException('无权限或参数不匹配！');
			$series = load_model('series', Null, true)->where('id', $sid)->where('status,<', 2)->Row(); // 已经结束的课程不能再续
			if(!$series) throw new HLPException('此课程已结束，不能续课');
			$start =  strtotime($assign['end_date']);
			$start = date('Y-m-d',strtotime("+1 day", $start));
			$rules = json_decode($series['rule'], true);
			if(empty($rules)) throw new HLPException('错误的课程');
			import('schedule');
			$last = Schedule::resolve($rules, $start, '', $times);
			$end = end($last);
			$res = $_Assign->where('sid',$sid)->where('type',0)->where('assigner',$student)->update(array('end_date' => $end['date']));
			$_Assign->where('assigner',$student,true) ->where('sid',$sid)->where('type',0)->increment('times',$times);
			$_Assign->where('assigner',$student,true) ->where('sid',$sid)->where('type',0)->increment('remain',$times);
			if(!$res) throw new HLPException('错误的课程');
			
			db()->commit();
		}catch(HLPException $e){			
			db()->rollback();
			Out(0, $e->getMessage());
		}
		Out(1, "续课成功");
	}

	public function Select_Action()
	{
		$keyword = Http::get('keyword', 'string', '');
		$id = Http::get('id', 'string', '');		
		$id = $id ? explode(",", $id) : Array();
		$where = Array('status' => 0, 'keyword' => $keyword);
		$offset = Http::get('offset', 'int', 0);
		$offset || $offset = 0;
		$this->assign('offset', $offset);
        
        $group_show = Http::get('group', 'trim', 'show');
		$group_show || $group_show = 'show';
		$this->assign('group_show', $group_show);
        $selected_hide = Http::get('selected', 'trim', 'show');     
		$selected_hide || $selected_hide = 'show';
        $this->assign('selected_hide', $selected_hide);
		$handle = Http::get('handle', 'trim', '');
		$this->assign('handle', $handle);
        
		$src = Http::get('src', 'trim', '');
		$this->assign('src', $src);// group event privgroup		
		
		$whereStr = $this->filter($where);
		$source = load_model('school_student')->getStudent($whereStr . " And r.`status`=0 And s.name_en<>''", array('s.name_en' => 0), '', 's.id,s.name,s.name_en en');
		$group = load_model('grade')->getAll(array('school' => $this->school), '', '', false, false, 'id, name');      
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
		$sourceGroup = load_model('grade_student')->getAll(array('school' => $this->school));
		foreach($sourceGroup as $sItem)
		{
			$groupSource[$sItem['grade']][] = $sItem['student'];
		}		
		$this->assign('groupSource', json_encode($groupSource, JSON_HEX_QUOT));

		$this->assign('selected', $selected);
		$this->assign('groups', $group);
		$this->assign('count', $count = count($source));

		if($count > 20)
		{
			$source = load_model('school_student')->sorts($source);            
		}
		$this->assign('result', $source);
		$this->display('school/student/select');
	}
	
	// 修改学号
	public function Update_Action()
	{
		$id = http::get('id', 'int', 0);
		$field = http::get('field', 'string', '');
		$value = http::get('field');
		$fileds = Array('no');
		if(in_array($field, $fields)) Out(0, '修改失败');
		$res = load_model('school_student', Null, true)->where('id', $id)->update(array($field => $value));
		Out(1, '修改成功！');
	}
	
	// 弹层学生列表
	public function Ajax_list()
	{
		
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
				case 'no': // 上课时间
					$key = 'no';
					break;						
				default : 
					break;
			}
			$result[$key] = $item;
		}		
		return $result;
	}

	public function filter($param)
	{
		$result = Array();		
		if(!empty($param['keyword']))
		{
			$result[] = "s.`name` like '%{$param['keyword']}%'";
		}
		
		if(!empty($param['group']))
		{
			$result[] = "`course` = " . $param['course'];
		}
		if(isset($param['status']))
		{
			$result[] = 'r.`status`=' . $param['status'];
		}
		if(!empty($param['remain']))
		{
			$param['remain'] += 1;
			$result[] = "sa.`remain` < ".$param['remain'];
		}
		$result[] = 'r.school=' . $this->school. ' AND r.status=0';	
		return join(" AND ", $result);
	}

	//剩余课时查询 
	public function Remain_Action()
	{
		$param = $this->int_search();
		$param['school'] = $this->school;
		 extract($param);
		 extract($where);
		$school_student = load_model('school_student', Null, true);
		if(!empty($export))
		{
			$school_student->rmExport($param);
		}
		
		$result = $school_student ->get_student_remain($param);
		foreach($result as &$item)
		{
			$concators = $school_student->get_student_concat($item['id']);
			$item['concator'] = '';
			foreach($concators as $val)
			{
				$item['concator'] && $item['concator'] .= " ";
				$item['concator'] .= $val['account'];
			}			
		}
		$total = $school_student ->get_remain_count($param);
		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);		
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);
		$this->assign('result', $result);
		$this->display('school/student/remain');
	}
	
	//学生详情 考勤备注
	public function Attend_Action()
	{
		if(!Http::is_post()) throw new HLPException('非法操作!');	
		extract(Http::post());
		$_Record = load_model('schedule_record',Null,true);
		$_Assign = load_model('assign', Null, true);
		$_Schedule = load_model('schedule',Null,true);
		$field = ['0' =>'attend','1'=>'absence','2'=>'leave'];
		$end = date('H:i',$end);
		$start = date('H:i',$index);
		db()->begin();
		try{
			$schedule = $_Schedule -> field('id')->where('sid',$sid) ->where('index',$index) -> Row();
			if($schedule&&$schedule == 0)
				$_Schedule -> where('id',$schedule['id']) ->where('index',$index) -> update(array('attended'=>1,));
			else if(!$schedule)
				$_Schedule -> insert(array('attended'=>1,'sid'=>$sid,'absence'=> 0,'commented'=>0,'class_room'=>0,'attend'=>0,'leave'=>0,'status'=>0,'index'=>$index,'class_times'=>$times,'start'=>$start,'end'=>$end));

			$result = $_Record->field('id,value')
				->where('type', $character,true)->where('school', $this->school)->where('assigner',$assigner)->where('protype','attend')->where('sid',$sid)->where('index',$index)
				->Row();
			$record = array();
			if($result) 
			{
				$record = $_Record ->where('id', $result['id'],true) ->update(array('value' => $value));
				$_Assign ->where('assigner',$assigner,true) ->where('sid',$sid)->where('type',0)-> increment($field[$value]);
				$_Assign ->where('assigner',$assigner,true) ->where('sid',$sid)->where('type',0)-> decrement($field[$result['value']]);
				$_Schedule -> where('index',$index,true) ->where('sid',$sid)->increment($field[$value]);
				$_Schedule -> where('index',$index,true) ->where('sid',$sid)->decrement($field[$result['value']]);
			}else{
				$_Record ->insert(array('value' => $value,'assigner'=>$assigner,'protype'=>'attend','sid'=>$sid,'school'=> $this->school,'create_time'=>time(),'type'=>0,'index'=>$index));

				$_Schedule ->where('index',$index,true) ->where('sid',$sid)->increment($field[$value]);
				$_Assign ->where('assigner',$assigner,true) ->where('sid',$sid)->where('type',0)-> increment($field[$value]);
				$_Assign ->where('assigner',$assigner,true) ->where('sid',$sid)->where('type',0)-> decrement('remain');
			}
			db()->commit();
		}catch(HLPException $e){			
			db()->rollback();
			Out(0, $e->getMessage());
		}
		Out(1,"考勤成功！");
	}
	//考勤备注
	public function Record_Action()
	{
		if(!Http::is_post()) throw new HLPException('非法操作!');	
		extract(Http::post());
		$result = load_model('schedule_record',NULL,true)
			->field('id')
			->where('type', 0)->where('assigner',$assigner)->where('protype','remark')->where('sid',$sid)
			->Row();
		$record = array();
		if($result) 
		{
			$record = load_model('schedule_record',NULL,true)
				->where('id', $result['id'],true)
				->update(array('value' => $value));
		}else{
			$record = load_model('schedule_record',NULL,true)
				->insert(array('value' => $value,'assigner'=>$assigner,'protype'=>'remark','sid'=>$sid,'school'=> $this->school,'create_time'=>time(),'type'=>0,'index'=>$index));
		}	
		
		if(!$record) throw new HLPException("获取数据失败!");
		Out(1, '修改成功');
	}


	public function ajax_Action()
    {
        $action = Http::request('action', 'trim', '');
        switch ($action)
        {            
            case 'list':
                $this->_list();
                break;
            case 'reno':
                $student = Http::post('student', 'int', 0);
                $no = Http::post('no', 'trim', '');
                db()->begin();
                try
                {                   
                    $res = load_model('school_student')->getRow(array('student' => $student, 'school' => $this->school));                      
                    if(!$res) throw new HLPException("错误的操作！");
                    load_model('school_student')->update(array('no' => $no), array('student' => $student, 'school' => $this->school));                    
                    db()->commit();
                    Out(1, '成功！');
                }catch(HLPException $e)
                {
                    db()->rollback();
                    Out(0,$e->getMessage());
                }                
                break;
            case 'delete':
                $id = Http::post('id', 'trim');                
                db()->begin();
                try
                {   
                    is_array($id) || $id = explode(",", $id);                   
                    $res = load_model('school_student')->getAll(array('student,in' => $id, 'school' => $this->school));                   
                    if(!$res) throw new HLPException("学生不存在！");                    
                    $res = db()->fetchAll("select e.id from t_course_student r left join t_event e on r.`event`=e.id where e.school={$this->school} and r.student in(". join(',', $id) . ")");
                    if($res) throw new HLPException("该学生已有课程暂不能删除！");
                    // 学生删除 课程删除
                    $res = load_model('school_student')->delete(array('student,in' => $id, 'school' => $this->school), true);
                    if(!$res) throw new HLPException("删除失败！");
                    db()->commit();
                    Out(1, '成功！');
                }catch(HLPException $e)
                {
                    db()->rollback();
                    Out(0,$e->getMessage());
                }            
                break;
			case 'freeze' : //冻结
				$id = Http::post('id', 'trim');
				$status = Http::post('status', 'int', 0);
                db()->begin();
                try
                {   
                    is_array($id) || $id = explode(",", $id);                   
                    $res = load_model('school_student')->getAll(array('student,in' => $id, 'school' => $this->school));                   
                    if(!$res) throw new HLPException("学生不存在！");
                    // 学生删除 课程删除
                    $res = load_model('school_student')->update(array('status' => $status), array('student,in' => $id, 'school' => $this->school));
                    if(!$res) throw new HLPException("操作失败！");
                    db()->commit();
                    Out(1, '操作成功！');
                }catch(HLPException $e)
                {
                    db()->rollback();
                    Out(0,$e->getMessage());
                }     
				break;
            default:
                break;
        }
    }

	public function _list()
	{ 
        $module = Http::get('module', 'trim', '');	
		$page = Http::get('page', 'int', 1);       
		$perpage = 6;

        switch ($module)
        {            
            case 'event': // 课程下的学生
                $event_id = http::get('event', 'int', 0);                
                $event = load_model('event')->getRow($event_id);
                $this->assign('event', $event);
                $this->assign('action', 'event');
                $handle = http::get('handle', 'int', 1);
                $this->assign('handle', $handle);
                if(!$event)  throw new HLPException('数据错误！');
                $students = !empty($event['students']) ? $res = json_decode($event['students'], true) : array();
                $offset = ($page - 1) * $perpage;
                $total = count($students);
                $result = array_slice($students, $offset, $perpage);                
                break;
            case 'grade': // 班级学生
                $grade_id = http::get('grade', 'int', 0); 
                $grade = load_model('grade')->getRow($grade_id); 
                $this->assign('grade', $grade);
                $this->assign('action', 'grade');
				$handle = http::get('handle', 'int', 1);
				$this->assign('handle', $handle);
                $where = array('grade' => $grade_id);               
                $total = load_model('grade_student')->getCount($where, 'count(*)');  
				$limit = load_model('grade_student')->getLimit($perpage, $page);
                $result = load_model('grade_student')->getAll($where, $limit, '', false, false, 'student'); 
                break;
            default : // 学生列表
                $where = array('school' => $this->school);                
                $total = load_model('school_student')->getCount($where, 'count(*)');
				$limit = load_model('school_student')->getLimit($perpage, $page);
                $res = load_model('school_student')->getAll($where, '', '',  false, false, 'student');
                break;                
        }
		
        $this->assign('paginator', $paginator);
        array_walk($result, function(&$v){           
            empty($v['id']) && $v['id'] = $v['student'];
            $v['avatar'] = imageUrl($v['id'], 2);
            empty($v['name']) && $v['name'] = current(load_model('student')->getRow($v['id'], false, 'name'));
        });
        $paginator = paginator($page, $total, $perpage, 0);       
        $this->assign('paginator', $paginator);
        $this->assign('result', $result);
        $this->assign('record', $total);		
		$this->display('school/student/ajax.list');
	}
}