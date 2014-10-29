<?php
set_time_limit(0);
// EVENT
class Event_Module extends School
{
	private $tpl = '';
	private $_perpage = 50;

	public function __construact(){
	
	}
	
	public function index_Action(){		
		extract($this->int_search());		
		$_Event = load_model('event');		
		$dateStart = $this->monthStart();
		$dateEnd = $this->monthEnd();

		$this->assign('dateStart', $dateStart); // 机构科目
		$this->assign('dateEnd', $dateEnd); // 机构科目
        
		$where['start'] = isset($where['start']) ? $where['start'] : $dateStart;
		$where['end'] = isset($where['end']) ? $where['end'] : $dateEnd;	
		$this->assign('start', $where['start'] ); // 开始时间
		$this->assign('end', $where['end'] ); // 结束时间   
		
		$alias = ''; $sid = 0;
		if(!empty($where['sid']) && is_numeric($where['sid'])){
			$alias = 'e'; $sid = $where['sid'];
		}

		$whereStr = $this->filter($where, $alias);	
		
		$order = $_Event->getOrder($this->getOrder($order, $alias));
		$limit = $_Event->getLimit($this->_perpage, $page);

		if($sid) // 按学生查询
		{			
			$attend = isset($where['attend']) ? $where['attend'] : '';
			$total = $this->_student_course_count($whereStr, $sid, $attend);
			$result = $this->_student_course_result($whereStr, $sid, $attend, $limit);
			// 是否机构学生
			if(!load_model('school_student')->getRow(array('student' => $sid, 'school' => $this->school)))
			{
				$this->show_error("没有此学生", '/event');
			}
			$student = load_model('student')->getRow($sid, false, 'name');
			if(!$student) $this->show_error("没有此学生", '/event');
			$student = $student['name'];
			// 
			if(Http::get('export', 'int', 0) == 1)
			{
				$this->export($this->_student_course_result($where, $sid, $attend));			
			}
			$this->assign('sid', $sid);
			$this->assign('attend_status', array('未考勤', '出勤', '缺勤', '请假'));
		}else{
			$total = $_Event->getCount($whereStr, 'count(*)');
			$result = $_Event->getAll($whereStr, $limit, $order);
			if(Http::get('export', 'int', 0) == 1)
			{
				$this->export($_Event->getAll($whereStr, '', $order), $teachers, $courses);			
			}
		}
		
		!empty($where['student']) && $student = $where['student'];
		$this->assign('student', $student);

		foreach($result as $key => &$item)
		{
			$item['students'] = json_decode($item['students'], true);
			$item['teachers'] = json_decode($item['teachers'], true);
			$item['date'] = substr($item['start_date'], 0, 10);
			$item['time'] = substr($item['start_date'], 11, 5) . "-" . substr($item['end_date'], 11,5);
		}
		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);
		$this->assign('paginator', $paginator);
		$this->assign('record', $total);		
		$courses = $this->getCourses();
		$teachers = $this->getTeachers();
		$this->assign('courses', $courses); // 机构科目
		$this->assign('teachers', $teachers); // 机构老师	
		

			$this->assign('curDate', date('Y-m-d')); // 机构科目				
			$this->assign('minStart', date('Y-m-d', mktime(date('H'),date('m'),date('s'), date('n'), date('j'), date('Y')-1)));	
			$this->assign('maxEnd', date('Y-m-d', mktime(date('H'),date('m'),date('s'), date('n'), date('j'), date('Y')+2)));

		// 修改类型
		$this->assign('updateOptions', array(
			'text' => '课程名称及颜色', 
			'date' => '上课日期', 
			'time' => '课程时间', 
			'studentAdd' => '增加学生', 
			'studentRemove' => '删除学生', 
			'teacherAdd' => '增加老师', 
			'teacherRemove' => '删除老师'
		));
		$this->assign('result', $result);
		$this->display('school/event/index');
	}

	private function _student_course_sql($where, $student, $attend='', $limit='')
	{		
		$sql = "select {field} from t_course_student r left join t_event e on r.`event`=e.id ";
		$sql.= " Where r.student={$student}";
		if($attend == 1) // 出勤
		{
			$sql.= " And r.`attend`=1";
		}else if($attend == 2)  // 缺勤
		{
			$sql.= " And r.`absence`=1";
		}else if($attend == 3) // 请假
		{
			$sql.= " And r.`leave`=1";
		}else if($attend === 0) // 未考勤
		{
			$sql.= " And r.`attended`=0";
		}
		$where && $sql.= " And " . $where;	
		if($limit) $sql.= " Limit " . $limit;
		
		return $sql;
	}
	private function _student_course_count($where, $student, $attend='')
	{
		$field = "count(*)";
		$sql = $this->_student_course_sql($where, $student, $attend);
		return db()->fetchOne(str_replace("{field}", $field, $sql));	
	}
	private function _student_course_result($where, $student, $attend, $limit)
	{
		$field = 'e.id,e.course,e.text,e.pid,e.start_date,e.end_date,e.grade,e.students,e.teachers,e.color,e.attended,r.absence,r.`leave`,r.attend';
		$sql = $this->_student_course_sql($where, $student, $attend, $limit);
		return db()->fetchAll(str_replace("{field}", $field, $sql));		
	}

	private function export($source=Array(), $teachers=Array(), $course=Array())
	{
		$result = array();
		$title = Array(
			'id' => '编号',
			'text' => '课程名称',
			'course' => '课程',
			'date' => '日期',
			'time' => '日间',
			'teacher' => '老师',
			'students' => '学生人数'
		);
		foreach($source as $key => &$item)
		{
			$item['students'] = json_decode($item['students'], true);
			$item['teachers'] = json_decode($item['teachers'], true);
			
			$teachers = array();
			foreach($item['teachers'] as $k => $tval)
			{
				$teachers[] = $tval['name'];
			}			
			$item['date'] = substr($item['start_date'], 0, 10);
			$item['time'] = substr($item['start_date'], 11, 5) . "-" . substr($item['end_date'], 11,5);
			$result[] = Array(
				'id' => $item['id'],
				'text' => $item['text'],
				'course' => isset($courses[$item['course']]) ? $courses[$item['course']] : '',
				'date' => $item['date'],
				'time' => $item['time'],
				'teacher' => join(" ", $teachers),
				'student' => count($item['students'])
			);			
		}
		excelExport('课程导出', array_values($title), $result);
		Header('Location:' . Http::curl());
	}

	public function Schedule_Action()
	{
		extract($this->int_search());		
		$_Event = load_model('event');
		$courses = $this->getCourses();
		$teachers = $this->getTeachers();
		$this->assign('courses', $courses); // 机构科目
		$this->assign('teachers', $teachers); // 机构老师	
		$whereStr = $this->filter($where);
		$total = $_Event->getCount($whereStr, 'count(*)');
		$this->assign('record', $total);
		$this->display('school/event/schedule');
	}

	public function load_Action()
	{
		extract($this->int_search());
		$_Event = load_model('event');        
		$whereStr = $this->filter($where);       
		$result = $_Event->getAll($whereStr, '', '', false, false, 'id,start_date,end_date,text,teachers,students,color,commented');       
		array_walk($result, 'event_format', $this->getColors());	
		ob_clean();
		header("Content-type:text/xml");
		echo "<?xml version='1.0' ?>";
		echo "<data>";
		foreach($result as $key=> &$item)
		{
			echo $_Event->xml($item);
		}
		echo "</data>";
		exit;
	}
    
   
    
	public function add_Action()
	{        
		if(!Http::is_post())
		{
			$this->assign('curDate', date('Y-m-d')); // 机构科目
			$this->assign('curTimeStamp', time()); // 
			$this->assign('endTimeStamp', time() + 30*60); // 
			$this->assign('courses', $this->getCourses()); // 		
			$this->assign('colors', $this->getColors());
			$this->assign('repeatTypes', $this->repeatTypes());
			$this->assign('weekSelectors', $this->getWeeks());
			$this->assign('minStart', date('Y-m-d', mktime(date('H'),date('m'),date('s'), date('n'), date('j'), date('Y')-1)));	
			$this->assign('maxEnd', date('Y-m-d', mktime(date('H'),date('m'),date('s'), date('n'), date('j'), date('Y')+2)));		
			$this->display('school/event/add');
		}else{
			$this->_save();
		}
	}
	
	public function edit_Action()
	{		
		if(!Http::is_post())
		{
			$id = Http::request('id');
			if(!$id) throw new HLPException('课程不存在');
			$result = load_model('event')->getRow(array('id' => $id, 'school' => $this->school));
			$result['students'] = json_decode($result['students'], true);
			$result['teachers'] = json_decode($result['teachers'], true);
			$result = array_merge($result, load_model('event')->cut_repeat($result['rec_type']));
			if(!$result) throw new HLPException('课程不存在');	

			$this->assign('curDate', date('Y-m-d', strtotime($result['start_date'])));		
			$this->assign('minStart', date('Y-m-d', mktime(date('H'),date('m'),date('s'), date('n'), date('j'), date('Y')-1)));	
			$this->assign('maxEnd', date('Y-m-d', mktime(date('H'),date('m'),date('s'), date('n'), date('j'), date('Y')+1)));	
			
			$this->assign('curTimeStamp', $result['start_date']);
			$this->assign('endTimeStamp', $result['end_date']);
			$this->assign('courses', $this->getCourses());		
			$this->assign('colors', $this->getColors());
			$this->assign('repeatTypes', $this->repeatTypes());
			$this->assign('weekSelectors', $this->getWeeks());
			$this->assign('result', $result);
			$this->assign('refer', !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/event');
			$this->display('school/event/add');
		}else{
			$this->_save();
		}
	}	

	private function _save()
	{
		if(!Http::is_post()) throw new HLPException('非法操作！');		
		var_dump(Http::post());
		extract(Http::post());
		db()->begin();
		try{	
			$eventId = empty($id) ? 0 : $id;			
			$teachers = $students = Array();
			if(empty($teacher['id'])) throw new HLPException('老师不能为空！');
			if(empty($student['id'])) throw new HLPException('学生不能为空！');
			for($i=0; $i<count($teacher['id']); $i++)
			{
				$id = $priv = 0; $name = $en = '';
				$id = isset($teacher['id'][$i]) ? $teacher['id'][$i] : 0;
				if($id) // 验证
				{
					$name = isset($teacher['name'][$i]) ? $teacher['name'][$i] : '';
					$en = isset($teacher['en'][$i]) ? $teacher['en'][$i] : '';
					$priv = isset($teacher['priv'][$i]) ? $teacher['priv'][$i] : 0;
					$teachers[$id] = compact('id', 'name', 'en', 'priv');
				}			
			}
			for($i=0; $i<count($student['id']); $i++)
			{
				$id = 0; $name = $en = '';
				$id = isset($student['id'][$i]) ? $student['id'][$i] : 0;
				if($id)
				{
					$name = isset($student['name'][$i]) ? $student['name'][$i] : '';
					$en = isset($student['en'][$i]) ? $student['en'][$i] : '';					
					$students[$id] = compact('id', 'name', 'en');
				}			
			}		
			$start_date = $date ." " . $startHour . ":" . $startMinute;			
			$end_date = $date ." " . $endHour . ":" . $endMinute;
			$rec_type = '';			
			
			$childs = Array();
			$create_time = time();
			$is_loop = $length = 0;
			if(isset($repeat) && $repeat && !$event)
			{
				$length = (($endHour * 60 + $endMinute) - ($startHour * 60 + $startMinute)) * 60; // length
				import('repeat');
				$recArr = array('', '', '', '', '');			
				if($repeat == 1)
				{
					$recArr[0] = 'day';
					$recArr[1] = 1;
					$childs = Repeat::day($start_date, $classTimes, $length);
				}else if($repeat == 2)
				{
					$recArr[0] = 'week';
					$recArr[1] = 1;
					$recArr[4] = join(",", $week);
					if(count($week) < 1) throw new HLPException('未设置周'); // 写日志
					$childs = Repeat::week($start_date, $classTimes, $week, $length);
				}else if($repeat == 3)
				{
					$recArr[0] = 'week';
					$recArr[1] = 2;
					$recArr[4] = join(",", sort($week));
					if(count($week) < 1) throw new HLPException('未设置周'); // 写日志
					$childs = Repeat::week2($start_date, $classTimes, $week, $length);
				}
				$rec_type = join("_", $recArr) . "#" . $classTimes;
				$last = end($childs);
				$end_date = $last['end_date']; // repeat end	
				$is_loop = 1;
			}
			$class_time = floatVal($class_time);
			$school = $this->school;

			// 关联学生和老师	>>			
			$_TS = load_model('teacher_student')->createMultiRelation($teacher['id'], $student['id'], $this->school);

			$data = compact('course', 'text', 'start_date', 'end_date', 'rec_type', 'students', 'teachers', 'create_time', 'school', 'is_loop', 'color', 'length', 'class_time');           
			$data['teacher'] = current($teacher['id']);
			if(!$this->valid($data, $eventId)) throw new HLPException('数据验证失败', 4); // 写日志            
			$_Event = load_model('event');
			if($eventId && empty($childs))
			{
				$event = $_Event->getRow($eventId, false, 'id,course,text,start_date,end_date,color,rec_type,school,is_loop,students,teachers,class_time');				
				if(!$event) throw new HLPException('课程不存在'); // 写日志				
				$_teachers = $_Event->compare('teacher', $teachers, json_decode($event['teachers'], true));				
				$_students = $_Event->compare('student', $students, json_decode($event['students'], true));
				$_event = array_merge($event, $data);				
				// 老师变化	                 
				if(!empty($_teachers['new']))
				{
					$res = $_Event->createRelation($_event, $_teachers['new'], 'teacher');					
					if(!$res) throw new HLPException('非法操作！新增上课老师错误'); 
					$res = $_Event->logs($_event, 'add', 'teacher', array_keys($_teachers['new']), array(), 1);
					if(!$res) throw new HLPException('非法操作！新增上课老师错误'); 
					load_model('delete_logs')->delete(array('app' => 'event', 'ext' => $eventId, 'to,in' => array_keys($_teachers['new'])), true); // 新加回的老师原删除logs清除
				}                
				if(!empty($_teachers['lost']))
				{
					$res = load_model('course_teacher')->delete(array('event' => $event['id'], 'teacher,in' => array_keys($_teachers['lost'])), true);                   
					if(!$res) throw new HLPException('非法操作！删除上课老师错误');                    
					$res = $_Event->logs($_event, 'delete', 'teacher', array_keys($_teachers['lost']), $event, 1);
					if(!$res) throw new HLPException('非法操作！删除上课老师错误'); 
				}              
				// 学生变化
				if(!empty($_students['new']))
				{
					$res = $_Event->createRelation($event, $_students['new'], 'student');
					if(!$res) throw new HLPException('非法操作！新增上课学生错误'); 
					$res = $_Event->logs($_event, 'add', 'student', array_keys($_students['new']), array(), 1);
					if(!$res) throw new HLPException('非法操作！新增上课学生错误');
					load_model('delete_logs')->delete(array('app' => 'event', 'ext' => $eventId, 'student,in' => array_keys($_students['new'])), true); // 新加回的学生原删除logs清除
				}              
				if(!empty($_students['lost']))
				{					
					$res = load_model('course_student')->delete(array('event' => $event['id'], 'student,in' => array_keys($_students['lost'])), true);
					if(!$res) throw new HLPException('非法操作！删除上课学生错误'); 
					$res = $_Event->logs($_event, 'delete', 'student', array_keys($_students['lost']), $event, 1);
					if(!$res) throw new HLPException('非法操作！删除上课学生错误');
					// 更新统计
					$_Event->updateStat($eventId);
				}            
				
				$teacher_push = $student_push = $push = (strtotime($data['start_date']) != strtotime($event['start_date']) || strtotime($data['end_date']) != strtotime($event['end_date'])) ? 2 : 1; // 时间变化时才推送给已经存在的老师和学生
				($teacher_push != 2 || (!empty($_students['new']) || !empty($_students['lost']))) && $teacher_push = 1; // 有新学生，推送老师
				
				if(!empty($_teachers['keep']) && $teacher_push == 1) 
				{
					$res = $_Event->logs($_event, 'update', 'teacher', array_keys($_teachers['keep']), $event, 1);
					if(!$res) throw new HLPException('非法操作！修改上课老师错误');					
				}
				$student_push != 2 && (!empty($_teachers['new']) || !empty($_teachers['lost'])) && $student_push = 1; // 有新老师，推送学生				
				if(!empty($_students['keep']) && $student_push == 1)
				{
					$res = $_Event->logs($_event, 'update', 'student', array_keys($_students['keep']), $event, 1);
					if(!$res) throw new HLPException('非法操作！修改上课老师错误'); 
				}
				isset($data['students']) && $data['students'] = json_encode($data['students']);
				isset($data['teachers']) && $data['teachers'] = json_encode($data['teachers']);
				if($event['pid'])
				{
					$data['length'] = strtotime($data['start_date']);
				}
				$_Event->update($data, $eventId); // 更新
				db()->commit();
				$this->show_message('修改成功', 'succeed', array(
					'back' => array('title' => '返回', 'url' => '/event', 'default' => 1),
					'goon' => array('title' => '查看', 'url' => '/event/edit?id=' . $eventId)
				), 'open');				
			}else{				
				$data['id'] = $_Event->create($data);
				if(!$_Event->logs($data, 'add', 'student', $student['id'], array(), 1)) throw new HLPException('非法操作！', 4); // 写日志
				if(!$_Event->logs($data, 'add', 'teacher', $teacher['id'], array(), 1)) throw new HLPException('非法操作！', 4); // 写日志
				if($childs){					
					$this->rec_create($childs, $data);
				}else{
					db()->commit();
					$this->show_message('课程开设成功！', 'succeed', array(
						'back' => array('title' => '返回查看', 'url' => '/event', 'default' => 1), // 返回到原来的查询页？未处理
						'goon' => array('title' => '继续添加', 'url' => '/event/add')
					), 'open');
				}
			}
		}catch(HLPException $e)
		{
			$message = $e->getMessage();
			db()->rollback();
			$this->show_message($message, 'succeed', array(
				'back' => array('title' => '返回查看', 'url' => '/event', 'default' => 1),				
			), 'open');
		}
		// $this->display('school/event/result');
	}
	
	// 子课程生成	
	private function rec_create($childs = Array(), $event)
	{					
		if(!$childs) return;		
		$this->assign('width', $width = 500);
		$total = count($childs);
		$pix = $width / count($childs);		
		$_Event = load_model('event');
		if (ob_get_level() == 0) ob_start();
		echo "<script language=\"JavaScript\">window.top.progress_open();</script>\n";
		ob_flush();
        flush();
		foreach($childs as $key => $item)
		{
            unset($item['week']);
			$_data = array_merge($event, $item, array('pid' => $event['id'], 'is_loop' => 0, 'rec_type' => ''));
			unset($_data['id']);			
			$id = $_Event->create($_data);				
			if(!$id) throw new HLPException('课程生成失败！'); // 写日志				
			echo "<script language=\"JavaScript\">window.top.updateProgress(\"正在生成课程 ....\", " . min($width, intval(($key + 1) * $pix)) . ");</script>\n";			
			ob_flush();
			flush();
			usleep(500000);
			// if($total > 40) {usleep(100000); }else{ usleep(500000);}
		}			
		db()->commit();		
		$content = "课程发布成功！共生成{$total}节课程";
		echo "<script language=\"JavaScript\">window.top.closeAllDialog();</script>\n";
		ob_flush();
		flush();	
		echo "<script language=\"JavaScript\">window.top.art.dialog({title:'开课成功', content : '{$content}', icon : 'succeed', width:400, button:[{name:'返回', focus : true, callback : function(){window.top.right.location.href = '/event';}}, {name:'继续开课', focus : true, callback : function(){window.top.right.location.href = '/event/add';}}]});</script>\n";
		ob_flush();
		flush();
		ob_end_flush();		
	}	
	
	private function valid($data = Array(), $id=0)
	{
		extract($data);
		if(empty($text) /* && (strLen($text) < 2 || strLen($text) > 16)*/) throw new HLPException('课程标题不能为空！', 4);
		if(isset($course) && empty($course)) throw new HLPException('课程不能为空！', 4);
		if(isset($teachers) && (empty($teachers) || count($teachers) > 10)) throw new HLPException('没有选择上课老师，或上课老师不能大于10！', 4);
		if(isset($students) && (empty($students) || count($teachers) > 50)) throw new HLPException('请选择学生,学生人数不能超过50人！', 4);			
		if(isset($class_time) && (empty($class_time) || !is_float($class_time) || $class_time< 0 ||  $class_time > 10)) throw new HLPException('课时数据必须大于等于0.1，仅保留一位小数！', 4);		
		if(isset($start_date) && (empty($start_date) || (strtotime($end_date) - strtotime($start_date)) < 1800)) throw new HLPException('课程时间必须大于30分钟！', 4);
		return true;
	}

	public function ajax_Action()
	{	
		db()->begin();
		try{
			if(!Http::is_post())
			{				
				$action = Http::get('action');
				$event = Http::get('event');                
				is_array($event) || $event = explode(",", $event);
                
                $this->assign('curTimeStamp', time()); // 
                $this->assign('endTimeStamp', time() + 30*60); // 
                
				if(!load_model('event')->getAll(array('id,in' => $event, 'school' => $this->school))) throw new HLPException('请选择要修改的课程');
				$this->assign('event', join(",", $event));			
				$this->assign('action', $action);			
				$this->assign('colors', $this->getColors());
				$this->display('school/event/ajax');
			}else{
				extract(Http::post());				
				$action = Http::post('action', 'trim', '');
                $handle = Http::post('handle', 'trim', 'update');
				$event = Http::post('id', 'trim', '');			
				is_array($event) || $event = explode(",", $event);							
				$_Event = load_model('event');
				$events = $_Event->getAll(array('id,in' => $event));
				if(empty($events)) throw new HLPException('错误的操作,课程不存在或已被删除！');				
				switch($action)
				{
					case 'time':
						extract(Http::post());
						if(!$endHour || !$startMinute || !$endHour || !$endMinute) throw new HLPException('错误的操作');						
						if(intval($startHour) == intval($endHour) && (intval($endMinute) - intval($startMinute)) < 30) throw new HLPException('课程时间不得小于30分钟！');						
						$start = $startHour . ":" . $startMinute;
						$end = $endHour . ":" . $endMinute;						
						$res = $_Event->updateTime($events, $start, $end);
						break;					
					case 'text':
						$color = Http::post('color');
						if($color === '') unset($color);						
						$res = $_Event->AjaxUpdate($event, compact('text', 'color'));
						break;
					case 'date':
						$date = Http::post('date');
						$res = $_Event->updateDate($events, $date);	
						break;
					case 'teacher':	
                        $teachers = Http::post('src');
						if($handle == 'add')
						{
							$res = $_Event->addTeacher($events, $teachers);
						}else{
							$res = $_Event->removeTeacher($events, $teachers);
						}
						break;
					case 'student':
                        $students = Http::post('src');                       
						if($handle == 'add')
						{
							$res = $_Event->addStudent($events, $students);
						}else{
							$res = $_Event->removeStudent($events, $students);
						}
						break;
					default :
						throw new HLPException('错误的操作');
						break;
				}
				if(!$res) throw new HLPException('修改失败');
			}
			db()->commit();
			if($action == 'text' || $action == 'time')
			{
				echo "<script language=\"JavaScript\">window.top.art.dialog({title:'修改成功', content : '修改成功', icon : 'succeed', width:400},function(){window.top.right.location.reload(); window.top.closeAllDialog();});</script>\n";	
			}else
			{
				Out(1, '修改成功！');
			}			
		}catch(HLPException $e)
		{
			$message = $e->getMessage();
			db()->rollback();
			if($action == 'text' || $action == 'time')
			{
				echo "<script language=\"JavaScript\">window.top.art.dialog({title:'修改失败', content : '修改失败', icon : 'error', width:400},function(){window.history.go(-1);});</script>\n";	
			}else
			{
				out(0, $message);
			}
		}
	}	

	public function delete_Action()
	{
		db()->begin();
		try
		{
			$id = Http::post('id');
			is_array($id) || $id = explode(",", $id);
			$_Event = load_model('event');
			$events = $_Event->getAll(array('id,in' => $id, 'school' => $this->school), '', '', false, false, 'id,text,is_loop,rec_type,students,teachers,start_date,end_date,school');
			if(!$events) throw new HLPException('删除失败！');
			$_Teacher_relation = load_model('course_teacher');
			$_Student_relation = load_model('course_student');
			foreach($events as $key=>$event)
			{
				$students = json_decode($event['students'], true);
				$teachers = json_decode($event['teachers'], true);
				// 删除主课程
				$res = $_Event->delete($event['id'], true);
				if(!$res) throw new HLPException('删除失败！');
				// 删除关系
				// 通知
				unset($event['students'], $event['teachres']);			
				$relations = $_Teacher_relation->getRow(array('event' => $event['id']));
				if($relations && $teacher_keys = array_keys($teachers))
				{
					$res = $_Teacher_relation->delete(array('event' => $event['id']), true);
					if(!$res) throw new HLPException('删除失败！');
					if(!$_Event->logs($event, 'delete', 'teacher', $teacher_keys, $event, 1))
					throw new HLPException('课程删除失败！', 4); // 写日志
				}
				$relations = $_Student_relation->getRow(array('event' => $event['id']));
				if($relations && $student_keys = array_keys($students))
				{
					$res = load_model('course_student')->delete(array('event' => $event['id']), true);
					if(!$res) throw new HLPException('删除失败！');
					if(!$_Event->logs($event, 'delete', 'student', $student_keys, $event, 1))
						throw new HLPException('课程删除失败！', 4); // 写日志
				}
			}
			db()->commit();
			Out(1, '删除成功！');
		}catch(HLPException $e)
		{			
			db()->rollback();
			Out(0, $e->getMessage());
		}
	}
	
	protected function getOrder($order = array(), $alias='')
	{
		$result = Array();	
		$alias && $alias. ".";
		foreach($order as $key => $item)
		{
			switch($key)
			{
				case 'name':
					$key = $alias. 'text';
					break;
				case 'date' :// 上课日期
					$key = $alias. 'start_date';					
					break;
				case 'time': // 上课时间
					$key = "substring({$alias}start_date, 11, 5)";
					break;
				case 'pers': // 学生人数						
					break;				
				default : 
					break;
			}
			$result[$key] = $item;
		}		
		return $result;
	}

	public function filter($param, $alias='')
	{
		$result = Array();
		$alias && $alias.= ".";	
		if(!empty($param['keyword']))
		{
            import('ustring');
			$result[] = "({$alias}`text` like '%{$param['keyword']}%' or {$alias}`students` like '%". Ustring::topinyin($param['keyword']). "%')";
		}
		if(!empty($param['student']))
		{
            import('ustring');
			$result[] = "{$alias}`students` like '%". Ustring::topinyin($param['student']). "%'";
		}
		if(!empty($param['start']) && !empty($param['end']))
		{
			$result[] = sprintf("{$alias}start_date>'%s' And {$alias}end_date<'%s'", $param['start'] . " 00:00:00", $param['end'] . " 23:59:59");
		}else if(!empty($param['start'])){
			$result[] = sprintf("{$alias}.start_date>'%s'", $param['start'] . " 00:00:00");
		}else if(!empty($param['end'])){
			$result[] = sprintf("{$alias}.end_date<'%s'", $param['end'] . " 23:59:59");
		}
		if(!empty($param['course']))
		{
			$result[] = "{$alias}`course` = " . $param['course'];
		}
		if(!empty($param['teacher']))
		{
			$result[] = "{$alias}`teachers` like '%" . $param['teacher'] . "%'";
		}
		$result[] = "{$alias}school=" . $this->school;
		$result[] = "{$alias}`status`=0";
		$result[] = "{$alias}`is_loop`=0";
        // $result[]= "{$alias}rec_type=''";
		return join(" And ", $result);
	}

}
