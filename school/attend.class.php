<?php
set_time_limit(0);
// EVENT
class Attend_Module extends School
{
	private $tpl = '';
	private $_perpage = 10;

	public function __construact(){
	
	}
	
	public function index_Action(){		
		extract($this->int_search());		
		$_Event = load_model('event');
		$weekStart = $this->startDate();
		$weekEnd = $this->endDate();
		$this->assign('weekStart', $weekStart);
		// $this->assign('weekEnd', TM);
		
		$where['start'] = !empty($where['start']) ? $where['start'] : $weekStart;
		$where['end'] = !empty($where['end']) ? $where['end'] : DAY;//$weekEnd;		
		$this->assign('start', $where['start'] ); // 开始时间
		$this->assign('end', $where['end']); // 结束时间
		if(empty($where['attend']))	$where['attend'] = 0;
		$this->assign('attendStatus', $where['attend']);

		$whereStr = $this->filter($where);		
		$total = $_Event->getCount($whereStr, 'count(*)');		
		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);		
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);
		$order = $_Event->getOrder($this->getOrder($order));
		$courses = $this->getCourses();
		$teachers = $this->getTeachers();
		$this->assign('courses', $courses); // 机构科目
		$this->assign('teachers', $teachers); // 机构老师	
		$this->assign('attendStatuses', array('未考勤', '已考勤')); // 机构老师	

		if(Http::get('export', 'int', 0) == 1)
		{
			$this->export($_Event->getAll($whereStr, '', $order), $teachers, $courses);			
		}

		$limit = $_Event->getLimit($this->_perpage, $page);		
		$result = $_Event->getAll($whereStr, $limit, $order);
		
		foreach($result as $key => &$item)
		{
			$item['students'] = json_decode($item['students'], true);
			$item['teachers'] = json_decode($item['teachers'], true);
			$item['date'] = substr($item['start_date'], 0, 10);
			$item['time'] = substr($item['start_date'], 11, 5) . "-" . substr($item['end_date'], 11,5);
		}		
		$this->assign('result', $result);
		$this->display('school/event/attend.list');
	}

	public function do_Action()
	{	
		db()->begin();
		try
		{	
			$event = Http::request("event", 'int', 0);
			$result = load_model('event')->getRow(array('id' => $event, 'school' => $this->school));
			if(!$result) throw new HLPException('课程不存在！');
			if(Http::is_post())
			{	
				$ids = Http::post('ids');					
				$data = Http::post('attend');
				
				$attend = Http::post('attends');
				$absence = Http::post('absences');
				$leave = Http::post('leaves');
				$attends = $leaves = $absences = Array();
				$attended = 1;
				
				load_model('event')->update(compact('attend', 'absence', 'leave', 'attended'), $result['id']);
				$attends = array_filter($data, create_function('$v', 'if($v==1) return $v;'));				
				$absences = array_filter($data, create_function('$v', 'if($v==2) return $v;'));	
				$leaves = array_filter($data, create_function('$v', 'if($v==3) return $v;'));
				if($attends)
					load_model('course_student')->update(array('attend' => 1, 'absence' => 0, 'leave' => 0, 'attended' => 1), array('id,in' => array_keys($attends)));
				if($absences)
					load_model('course_student')->update(array('attend' => 0, 'absence' => 1, 'leave' => 0, 'attended' => 1), array('id,in' => array_keys($absences)));
				if($leaves)
					load_model('course_student')->update(array('attend' => 0, 'absence' => 0, 'leave' => 1, 'attended' => 1), array('id,in' => array_keys($leaves)));			
				
				db()->commit();
				$this->show_message('考勤成功', 'succeed', array(
					'back' => array('title' => '返回', 'url' => '/attend', 'default' => 1),
					'goon' => array('title' => '查看', 'url' => '/attend/do?event=' . $event)
				), 'open');

			}else{				
				$result['students'] = json_decode($result['students'], true);
				$students = $result['students'];				
				array_walk($students, create_function('&$v,&$k', '$v=$v[\'id\'];'));
				$students = array_combine($students, $result['students']);				
				$this->assign('attendes', array(1 => '出勤', 2 => '缺勤', 3=> '请假'));
				$events = load_model('course_student')->getAll(array('event' => $result['id'], 'status' => 0), '', '', false, false, 'id,student,`attend`,`leave`,`absence`');				
				array_walk($events, function(&$v, $k, $attended=0) use ($students, $result){						
					if($result['attended']==0) 
					{
						$v['attend'] = 1;
					}else{
						$v['leave'] && $v['attend'] = 3;
						$v['absence'] && $v['attend'] = 2;
					}
					$v['name'] = $students[$v['student']]['name'];
					unset($v['leave'], $v['absence']);
				});
				$this->assign('event', $result);
				$this->assign('result', $events);
				$this->display('school/event/attend.do');
			}
		}catch(HLPException $e)
		{
			db()->rollback();
			$this->show_message($e->getMessage(), 'error', array(
				'back' => array('title' => '返回', 'url' => -1)				
			));
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
					$key = 'text';
					break;
				case 'date' :// 上课日期
					$key = 'start_date';					
					break;
				case 'time': // 上课时间
					$key = 'substring(start_date, 11, 5)';
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

	public function filter($param)
	{
		$result = Array();		
		if(!empty($param['keyword']))
		{
			load('ustring');
			//$result[] = "(`text` like '%{$param['keyword']}%' or `students` like '%{$param['keyword']}%')";
			$result[] = "(`text` like '%{$param['keyword']}%' or `students` like '%". Ustring::topinyin($param['keyword']). "%')";
		}
		if(!empty($param['start']) && !empty($param['end']))
		{
			$result[] = sprintf("start_date>'%s' And end_date<'%s'", $param['start'] . " 00:00:00", $param['end'] . " 23:59:59");
		}else if(!empty($param['start'])){
			$result[] = sprintf("start_date>'%s'", $param['start'] . " 00:00:00");
		}else if(!empty($param['start'])){
			$result[] = sprintf("end_date>'%s'", $param['end'] . " 23:59:59");
		}
		if(!empty($param['course']))
		{
			$result[] = "`course` = " . $param['course'];
		}
		if(!empty($param['teacher']))
		{
			$result[] = "`teachers` like '%" . $param['teacher'] . "%'";
		}		
		if(!empty($param['attend']))
		{
			$result[] = "`attended`=" . $param['attend'];
		}else{
			$result[] = "`attended`=0";
		}
		$result['school'] = 'school=' . $this->school;
		$result['status'] = '`status`=0';
		$result['is_loop'] = '`is_loop`=0';
		return join(" And ", $result);
	}

}