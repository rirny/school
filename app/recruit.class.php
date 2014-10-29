<?php
class Recruit_Module extends School
{
	private $tpl = '';
	private $_perpage = 10;

	public function __construact(){
	
	}
	
	public function index_Action(){		
		extract($this->int_search());		
		$_Event = load_model('recruit');
		$dateStart = $this->monthStart();
		$dateEnd = $this->monthEnd();
		$this->assign('dateStart', $dateStart);
		$this->assign('dateEnd', $dateEnd);
		
		$where['start'] = !empty($where['start']) ? $where['start'] : $dateStart;
		$where['end'] = !empty($where['end']) ? $where['end'] : $dateEnd;		
		
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

		$limit = $_Event->getLimit($this->_perpage, $page);		
		$result = $_Event->getAll($whereStr, $limit, $order);
		$targets = $this->getTargets();		
		$forms = $this->getForms();
		foreach($result as $key => &$item)
		{
			$item['teachers'] = json_decode($item['teacher'], true);
			$item['date'] = substr($item['start_date'], 0, 10);
			$item['time'] = substr($item['start_date'], 11, 5) . "-" . substr($item['end_date'], 11,5);
			$item['price'] = $item['lb_price'] . "-" .  $item['ub_price'];
			$item['form'] = explode(',', $item['form']);
			array_walk($item['form'], function(&$v) use($forms){
				$v = isset($forms[$v]) ? $forms[$v] : '';
			});
			$item['form'] = join(" ", $item['form']);
			$item['target'] = explode(',', $item['target']);			
			array_walk($item['target'], function(&$v) use($targets){
				$v = isset($targets[$v]) ? $targets[$v] : '';
			});
			$item['target'] = join(" ", $item['target']);
		}		
		$this->assign('result', $result);
		$this->display('school/recruit/recruit.index');
	}

	public function add_Action()
	{	
		if(!Http::is_post())
		{
			$this->assign('curDate', date('Y-m-d')); // 机构科目

			$this->assign('courses', $this->getCourses()); //
			$this->assign('targets', $_targets = $this->getTargets());
			$this->assign('forms', $_forms = $this->getForms());
			
			$id = Http::request('id', 'int', 0);	
			$start_time = time();
			$end_time = time() + 30*60;
			if($id){
				$result = load_model('recruit')->getRow(array('id' => $id, 'school' => $this->school));
				if(!$result) throw new HLPException('课程不存在');
				$result['teachers'] = json_decode($result['teacher'], true);	
				$result['target'] = explode(",", $result['target']);
				$result['form'] = explode(",", $result['form']);
				$start_time = DAY . " " . $result['start_time'];
				$end_time = DAY . " " . $result['end_time'];				
			}else{
				$result['target'] = array_keys($_targets);
				$result['form'] = array_keys($_forms);
			}

			$this->assign('result',$result);
			$this->assign('minStart', date('Y-m-d'));	
			$this->assign('maxEnd', date('Y-m-d', mktime(date('H'),date('m'),date('s'), date('n'), date('j'), date('Y')+2)));
			
			$this->assign('start_time', $start_time); // 
			$this->assign('end_time', $end_time); //
			$this->display('school/recruit/recruit.add');

		}else{
			try
			{
				$course = Http::post('course', 'int', 0);
				$text = Http::post('text', 'trim', '');
				if($text == '') throw new HLPException('课程名称不能为空！');
				$teacher = Http::post('teacher');
				for($i=0; $i<count($teacher['id']); $i++)
				{
					$tid = 0; $name = $en = '';
					$tid = isset($teacher['id'][$i]) ? $teacher['id'][$i] : 0;
					if($tid) // 验证
					{
						$name = isset($teacher['name'][$i]) ? $teacher['name'][$i] : '';
						$en = isset($teacher['en'][$i]) ? $teacher['en'][$i] : '';					
						$teachers[$tid] = compact('id', 'name', 'en');
					}				
				}
				$teacher = json_encode($teachers);
				$times = Http::post('times', 'int', 0);
				$target = Http::post('target');
				$target && is_array($target) && $target = join(",", $target); // 
				$form = Http::post('form');
				$form && is_array($form) && $form = join(",", $form);

				$always = Http::post('always', 'int', 0);
				if($always === 1)
				{
					$start_date = $end_date = '0000-00-00';
				}else{
					$start_date = Http::post('start_date', 'trim', '');
					$end_date = Http::post('end_date', 'trim', '');
				}		
				
				$startHour = Http::post('startHour', 'trim', '');
				$startMinute = Http::post('startMinute', 'trim', '');
				$start_time = $startHour . ":" . $startMinute;
				$endHour = Http::post('endHour', 'trim', '');
				$endMinute = Http::post('endMinute', 'trim', '');
				$end_time = $endHour . ":" . $endMinute;
				$lb_price = Http::post('lb_price', 'float', '0.00', 2);				
				$ub_price = Http::post('ub_price', 'float', '0.00', 2);
				$description = Http::post('description', 'trim', '');
				$id = Http::post('id', 'int', 0);
				//$status = Http::post('status', 'int', 0);
				$data['operator'] = $this->uid;
				$data = compact('course', 'text', 'teacher', 'times', 'target', 'form', 'always', 
					'lb_price', 'ub_price',
					'start_date', 'end_date', 'start_time', 'end_time', 'description', 'operator'
				);
				if($id)
				{
					load_model('recruit')->update($data, $id);
				}else{
					$data['school'] = $this->school;
					$data['creator'] = $this->uid;
					$data['create_time'] = TM;
					$id = load_model('recruit')->insert($data);
					if(!$id) throw new HLPException('发布失败');
				}
				Out(1, '发布成功！');
			}catch(HLPException $e)
			{
				out(0, $e->getMessage());
			}
		}
	}
	
	public function ajax_Action()
	{
		$action = Http::request('action', 'trim');
		try
		{
			switch($action)
			{				
				case 'delete':
					$id = Http::post('id', 'trim', '');
					if(!$id) throw new HLPException('内容不存在！');
					is_array($id) || $id = explode(",", $id);
					$recruit = load_model('recruit')->getRow(array('id,in' => $id, 'school' => $this->school));
					if(!$recruit) throw new HLPException('内容不存在！');
					$res = load_model('recruit')->delete(array('id,in' => $id, 'school' => $this->school), true);
					if(!$res) throw new HLPException('内容失败！');
					$message = '删除成功！';
					break;
			}
			out(1, $message);
		}catch(HLPException $e)
		{
			out(0, $e->getMessage());
		}		
	}

	protected function getOrder($order = array())
	{
		$result = Array();	
		empty($order) && $order = array('date' => 1);
		foreach($order as $key => $item)
		{
			switch($key)
			{
				case 'name':
					$key = 'text';
					break;
				case 'date' :// 上课日期
					$key = 'create_time';					
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
			$result[] = "(`text` like '%{$param['keyword']}%')";
		}
		if(!empty($param['start']) && !empty($param['end']))
		{
			$result[] = sprintf("start_time>'%s' And start_time<'%s'", $param['start'] . " 00:00:00", $param['end'] . " 23:59:59");
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
			$result[] = "`teacher` like '%" . $param['teacher'] . "%'";
		}
		$result['school'] = 'school=' . $this->school;
		$result['status'] = '`status`=0';
		return join(" And ", $result);
	}

}