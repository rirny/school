
<?php
class Schedule_Module extends School
{
	private $_perpage = 30;
	public function __construact(){
		parent::__construct();
	}
	
	public function index_Action(){		
		require_once(LIB . "/schedule.class.php");
		$start = date("Y-m-d",mktime(0,0,0,date("m"),date("d")-date("w")+1,date("Y")));
		$end = date("Y-m-d",mktime(23,59,59,date("m"),date("d")-date("w")+7,date("Y")));

		extract($this->int_search());	
		$where['keyword'] = Http::get('keyword', 'string', '');
		$whereStr = $this->exFilter($where);
		$schedule = load_model('schedule');
		$order = $this->getScOrder($order);
		//$limit = $schedule->getLimit($this->_perpage, $page);

		$sr = load_model('schedule_rule',NULL,true);
		//$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);
		//$this->assign('paginator', $paginator);
		$schedule = load_model('schedule', NULL, true);
		$rule = $schedule -> getRule($whereStr, $order , 's.title,r.schedule,r.id,r.start,r.end,r.week,r.period,c.name,s.sort');
		$teacher = $schedule -> getTeacher($this->school);
		$student = $schedule -> getStudent($this->school);
		$startTime = $endTime = $ctime ='';
		$studentCount =0;
		$result = array();
		$tname = array();
		$sid = array();
		$tid = array();
		$list = Schedule::resolve($rule,$start,$end);

		foreach($list as $key => &$item)
		{
			$studentCount=0;
			$tname = $sid = $tid = [];
			foreach($student as $skey => &$value)
			{
				if($item['schedule'] == $value['schedule'])
				{
					$sid[] = $value['assign'];
					$studentCount++;
				}
			}
			foreach($teacher as $tkey => &$items)
			{
				if($item['schedule'] == $items['schedule'])
				{
					if(!in_array($items['schedule'],$tname))
						$tname[] = $items['name'];
					$tid[] = $items['assign'];
				}
			}

			$startTime = date('H:i', $item['start']);
			$endTime = date('H:i', $item['end']);
			$ctime = $this->getWeek($item['week']).' '.$startTime.'-'.$endTime;
			$result[] = Array(
				'id'			 =>$item['id'],
				'schedule' =>$item['schedule'],
				'teachers' =>$tname,
				'title'		 => $item['title'],
				'course'	 => $item['name'],
				'date'		 => $item['date'],
				'start'       => $item['start'],
				'time'       => $ctime,
				'sid'		    => $sid,
				'tid'		    => $tid,
				'student'  => $studentCount
			);
		}
		
		$total = count($result);
		$this->assign('resultObject',json_encode($result));
		$this->assign('record', $total);	
		$this->assign('start', $start);
		$this->assign('end', $end);
		$this->assign('result', $result);	
		$this->display('school/event/index');
	}
	
	public function add_Action(){	
		if(!Http::is_post())
		{
			$this->assign('colors', $this->getColors());
			$this->assign('curTimeStamp', time()); // 
			$this->assign('endTimeStamp', time() + 30*60); // 
			$this->assign('repeatTypes', $this->repeatTypes());
			$this->assign('week', $this->getWeeks());	
			$this->display('school/schedule/add');
		}else{
			$this->_save();
		}
	}

	private function _save(){
		if(!Http::is_post()) throw new HLPException('非法操作！');	
		require_once(LIB . "/schedule.class.php");
		extract(Http::post());
		$create_time =  time();
		$school = $this ->school;
		$title = $text;
		$creator = $this -> uid;
		$rules = $timesAry = array();
		for($i =0; $i < count($rule); $i++)
		{
			if(isset($rule[$i]['check']))
			{
				array_push($timesAry, $rule[$i+3]['times']);
				array_push($rules, array('week' => $rule[$i]['check'] , 'start' => $rule[$i+1]['start'] , 'end' => $rule[$i+2]['end']));
			}
		}
		$sortAry=array();
		$sortAry = Schedule::getSort($rules);
		$sort = $sortAry[0]['sort'];
		$id =  load_model('schedule')
			->insert(compact(
			'title', 'course', 'create_time', 'school', 'creator','color','sort'			
		));

		if(!$id) throw new Exception("添加数据失败!");

		$schedule = $id;
		for($i = 0; $i < count($rules); $i++)
		{
			$period = $timesAry[$i];
			$start = $rules[$i]['start'];
			$end = $rules[$i]['end'];
			$week = $rules[$i]['week'];
			$result = load_model('schedule_rule')
				->insert(compact('schedule','start','end','week','period'));
			if(!$result) throw new Exception("添加数据失败!");
		}
		Out(1, '调课成功', '');
	}

	public function change_Action()
	{
		if(!Http::is_post()) throw new HLPException('非法操作！');
		extract(Http::post());
		$schedule_item = load_model('schedule_item', NULL, true);
		$schedule_rule = load_model('schedule_rule', NULL, true);
		$schedule_student =  load_model('schedule_student', NULL, true);
		$schedule_teacher = load_model('schedule_teacher', NULL, true);
		$res = $schedule_rule -> field('start,end,week')
			->where('id', $id)
			->Row();

		$bres = $schedule_item ->field('id')
			->where('ruler',$id)
			->where('index',$index)
			->Row();
		$res1 = 0;
		if(!$bres)
		{
			$res1 = $schedule_item -> insert(array('start_time' => $res['start'],'end_time' => $res['end'],'week' => $week,'schedule' =>$schedule,'ruler'=>$id, 'index'=> $index,'day'=>$day
			));
		}
		
		$res2 = $schedule_item -> insert(array('start_time' => $res['start'],'end_time' => $res['end'],'week' => $week,'schedule' =>$schedule,'ruler'=>$id, 'index'=> $index,'day'=>$day
		));

		if(!$res2)  throw new HLPException('非法操作！');
		for($i=0;$i<count($sid);$i++)
		{
			if(!$bres) 
			{	
				$schedule_student -> insert(array('student' => $sid[$i], 'school' => $this->school, 'status'=>0 ,'item'=>$res1,'schedule' => $schedule));
				$schedule_student -> insert(array('student' => $sid[$i], 'school' => $this->school, 'source'=>$res2, 'status'=>4 ,'item'=>$res1,'schedule' => $schedule));
			}
			else
				$schedule_student -> insert(array('student' => $sid[$i], 'school' => $this->school, 'source'=>$res2, 'status'=>4 ,'item'=>$bres['id'],'schedule' => $schedule));
		}
		for($i=0;$i<count($sid);$i++)
		{
			if(!$bres) 
			{	
				$schedule_teacher -> insert(array('teacher' => $tid[$i], 'item'=>$res1));
				$schedule_teacher -> insert(array('teacher' => $tid[$i],'item'=>$res1));
			}
			else
				$schedule_teacher -> insert(array('teacher' => $tid[$i], 'item'=>$bres['id']));
		}
		Out(1, 'success', '');
	}

	public function info_Action(){	
		extract($this->int_search());	
		$where['keyword'] = Http::get('keyword', 'string', '');
		$whereStr = $this->filter($where);
		$schedule = load_model('schedule');
		$order = $this->getOrder($order);
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
			$this->export($schedule->getList($result));
			exit;
		}
		$this->assign('record', $total);		
		$this->assign('result', $result);
		$this->display('school/schedule/set');
	}

	public function getinfo_Action(){
		if(!Http::is_post()) throw new HLPException('非法操作！');		
		$post = Http::post(); 
		$pid = $post['type'];
		$result = load_model('course_type',NULL,true)
			->field('id,name')
			->where('pid',$pid)
			->result();
		if(!$result) throw new Exception("获取数据失败!");
		Out(1, 'success', $result);
	}

	public function delete_Action(){
		$sid = Http::post('id');
		$schedule = load_model('schedule');
		$schedule_rule = load_model('schedule_rule');
		$res = $schedule->getRow(array('id'=>$sid),false,'status');
		if($res['status']==1) die(json_encode(array('state' => 0, 'message' =>  '删除失败')));
		$rres = $schedule_rule->delete(array('schedule'=>$sid),true);
		$sres = $schedule->delete(array('id'=>$sid), true);
		if($sres) Out(1,'删除成功！');
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

	public function exFilter($param)
	{
		$result = Array();		
		if(!empty($param['keyword']))
		{
			$result[] = "s.title like '%{$param['keyword']}%'";
		}

		$result[] = 's.school=' . $this->school;	
		return join(" AND ", $result);
	}

	protected function getOrder($order = array())
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

	private function export($source=Array())
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

	public function filter($param)
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
