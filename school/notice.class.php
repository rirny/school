<?php
class Notice_Module extends School
{
	private $tpl = '';
	private $_perpage = 10;
	private $_pageRange = 10;

	public function __construact(){
		parent::__construact();
	}
	
	// 学生统计
	public function index_Action()
	{		
		extract($this->int_search());		
		$_Notify = load_model('notify');
		/*
		$weekStart = $this->startDate();
		$weekEnd = $this->endDate();
		$this->assign('weekStart', $weekStart); // 
		$this->assign('weekEnd', $weekEnd); // 
		$where['start'] = !empty($where['start']) ? $where['start'] : $weekStart;
		$where['end'] = !empty($where['end']) ? $where['end'] : $weekEnd;	
		$this->assign('start', $where['start'] ); // 开始时间
		$this->assign('end', $where['end'] ); // 结束时间
		*/
		$whereStr = $this->filter($where);
		$teachers = $this->getTeachers();
		$this->assign('teachers', $teachers); // 机构老师	
		$total = $_Notify->getCount($whereStr, 'count(*)');		
		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);		
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);
		$order = $_Notify->getOrder($this->getOrder($order));
		$limit = $_Notify->getLimit($this->_perpage, $page);
		$result = $_Notify->getAll($whereStr, $limit, $order);		
		array_walk($result, function(&$v, $k){
			$teacher = json_decode($v['teacher'], true);			
			$teachers = load_model('user')->getColumn(array('id,in' => $teacher), 'name');			
			$v['teacher'] = join(" ", array_slice($teachers, 0, 2));			
			$v['teacher_title'] = join(" ", $teachers);
			$student = json_decode($v['student'], true);
			$student = load_model('student')->getColumn(array('id,in' => $student), 'name');
			$v['student'] = join(" ", array_slice($student, 0, 2));
			$v['student_title'] = join(" ", $student);
		});
		
		$this->assign('result', $result);		
		$this->display('school/notice/notice');
	}

	public function add_Action()
	{
		if(!Http::post())
		{
			$this->display('school/notice/add');
		}else{
			$this->_save();
		}
	}

	private $_logs = array(
		'hash' => '', 
		'app' => 'notify', 
		'act' => 'add', 
		'character'=> 'student',
		'creator' => '', 
		'target' => array(), 
		'ext' => array(),
		'source' => array(), 
		'data' => array(),
		'type'=>2,
	);	

	private function _save()
	{
		db()->begin();
		try
		{
			$teachers = Http::post('teacher');
			$students = Http::post('student');
			if(empty($teachers['id']) && empty($students['id'])) throw new HLPException('发送对象不能为空！');
			$student = json_encode($students['id']);
			$teacher = json_encode($teachers['id']);
			$creator = $this->uid;
			$create_time = TM;
			$content = Http::post('content');
			$school = $this->school;
			$id = load_model('notify')->insert(compact('student', 'teacher', 'creator', 'school', 'create_time', 'content'));
			if(!$id) throw new HLPException('发送失败！');

			//推送
			
			if(!empty($students['id']))
			{
				$res = logs('db')->add('notify', md5($id . rand(1000, 9999)), array_merge($this->_logs, array(
					'target'=>$student,																				
					'source' => array('notifyId'=>$id)							
				)));
				if(!$res) throw new HLPException('发送失败！');
			}
			
			if(!empty($teachers['id']))
			{
				$res = logs('db')->add('notify', md5($id . rand(1000, 9999)), array_merge($this->_logs, array(
					'character' => 'teacher',
					'target' => $teacher,
					'source' => array('notifyId'=>$id)
				)));
				if(!$res) throw new HLPException('发送失败！');
			}			
			db()->commit();
			$this->show_message('通知发送成功', 'succeed', array(
				'back' => array('title' => '返回查看', 'url' => '/notice', 'default' => 1),
				'goon' => array('title' => '继续发送', 'url' => '/notice/add')
			), 'open');

		}catch(HLPException $e){
			db()->rollback();
			$this->show_message($e->getMessage(), 'error', array(
				'back' => array('title' => '返回', 'url' => '/notice/add', 'default' => 1)				
			), 'open');
		}
	}

	public function view_Action()
	{
		try
		{
			$id = Http::get('id', 'int', 0);
			$notice = load_model('notify')->getRow($id);
			extract($this->int_search());
			$whereArr = array('pid' => $id, 'school' => $this->school);
			isset($where['status']) && $whereArr['status'] = $where['status'];
			if(!empty($order['time']))
			{
				$order = 'create_time Desc';
			}else{
				$order = 'create_time Asc';
			}
			if(!$notice) throw new HLPException('数据不存在');
			$this->assign('notice', $notice);	
			$result = load_model('message')->getAll($whereArr, '', $order);
			$this->assign('statuses', array('未读', '已读'));	
			$relations = $this->getRelations();
			array_walk($result, function(&$v, $k) use ($relations){
				if($v['student'])
				{
					$v['sender'] = current(load_model('student')->getRow($v['student'], false, 'name'));
					$rs = current(load_model('user_student')->getColumn(array('user' => $v['to'], 'student' => $v['student']), 'relation'));
					$v['sender'] .= $relations[$rs];
				}else{
					$v['sender'] = current(load_model('user')->getRow($v['to'], false, 'concat(firstname,lastname)'));
				}
			});
			$this->assign('result', $result);
			$this->assign('record', count($result));
			$this->display('school/notice/view');
		}catch(HLPException $e){			
			$this->show_message($e->getMessage(), 'error', array(
				'back' => array('title' => '返回', 'url' => ($this->refer ? $this->refer : '/notice'), 'default' => 1)				
			));
		}		
	}

	public function filter($param)
	{
		$result = Array();		
		if(!empty($param['keyword']))
		{
			$result[] = "(`content` like '%{$param['keyword']}%' or `student` like '%{$param['keyword']}%')";
		}
		if(!empty($param['start']) && !empty($param['end']))
		{
			$result[] = sprintf("create_time>'%s' And create_time<'%s'", strtotime($param['start'] . " 00:00:00"), strtotime($param['end'] . " 23:59:59"));
		}else if(!empty($param['start'])){
			$result[] = sprintf("create_time>'%s'", strtotime($param['start'] . " 00:00:00"));
		}else if(!empty($param['end'])){
			$result[] = sprintf("create_time<'%s'", strtotime($param['end'] . " 23:59:59"));
		}		
		if(!empty($param['teacher']))
		{
			$result[] = "`teacher` like '%\"" . $param['teacher'] . "\"%'";
		}
		$result[] = 'school=' . $this->school;	
		$result[] = 'vote=0';	
		return join(" And ", $result);
	}

	protected function getOrder($order = array())
	{
		$result = Array();		
		empty($order) && $order['time'] = 1;
		foreach($order as $key => $item)
		{
			$field = '';
			switch($key)
			{				
				case 'time':
					$field = 'create_time';
					break;				
			}
			$field && $result[$field] = $item;
		}		
		return $result;
	}

	public function ajax_Action()
	{
		$action = Http::request('action', 'trim', '');
        db()->begin();      
        try
        {
            switch ($action)
            {            
                case 'delete':                    
					$id = Http::post('id', 'trim');
					is_array($id) || $id = explode(",", $id);
					$result = load_model('notify')->getAll(array('id,in' => $id, 'school' => $this->school));					
					if(!$result) throw new HLPException('无数据');
					$res = load_model('notify')->delete(array('id,in' => $id, 'school' => $this->school), true);
					if(!$res) throw new HLPException('删除失败');
					break;
				default:
					break;
            }			
			db()->commit();
            Out(1, '成功！');
        }  catch (HLPException $e)
        {
            db()->rollback();
            Out(0, $e->getMessage());
        }
	}
}