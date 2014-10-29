<?php
class Vote_Module extends School
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
		$_Notify = load_model('vote');
		$weekStart = $this->startDate();
		$weekEnd = $this->endDate();
		$this->assign('weekStart', $weekStart); // 
		$this->assign('weekEnd', $weekEnd); // 
		$where['start'] = !empty($where['start']) ? $where['start'] : $weekStart;
		$where['end'] = !empty($where['end']) ? $where['end'] : $weekEnd;	
		$this->assign('start', $where['start'] ); // 开始时间
		$this->assign('end', $where['end'] ); // 结束时间
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
			$teacher = load_model('user')->getRow(array('id,in' => $teacher), false, 'concat(firstname,lastname)');
			$v['teacher'] = join(" ", array_slice($teacher, 0, 2));
			$v['teacher_title'] = join(" ", $teacher);
			$student = json_decode($v['student'], true);
			$student = load_model('student')->getColumn(array('id,in' => $student), 'name');
			$v['student'] = join(" ", array_slice($student, 0, 2));
			$v['student_title'] = join(" ", $student);
		});
		
		$this->assign('result', $result);		
		$this->display('school/vote/vote');
	}

	public function add_Action()
	{
		if(Http::is_post())
		{
			db()->begin();
			try
			{
				$options = Http::post('option');
				if(count($options) < 2) throw new HLPException('选项不得少于2项');
				$content = $title = Http::post('title', 'trim', '');
				if(!$title) throw new HLPException('问卷标题不能为空！');
				$start_time = Http::post('start', 'trim', '');
				if(!$start_time) throw new HLPException('请设置问卷开始时间！');
				$end_time = Http::post('end', 'trim', '');	
				if(!$end_time) throw new HLPException('请设置问卷结束日期！');
				$start_time = strtotime($start_time ." 00:00:00");
				$end_time = strtotime($end_time . " 23:59");				
				if($end_time < TM) throw new HLPException('问卷结束日期不能为过去日期！');				
				if($end_time < $start_time) throw new HLPException('问卷结束日期不能小于开始日期！');
				$multi = Http::post('multi', 'int', '1');
				$creator = $this->uid;
				$school = $this->school;
				$create_time = TM;
				$vote = load_model('vote')->insert(compact('title', 'multi', 'start_time', 'end_time', 'school', 'creator', 'create_time'));
				if(!$vote) throw new HLPException('问卷创建失败！');
				// 创建选项
				foreach($options as $key => $option)
				{
					if(!$option) continue;
					$op = load_model('vote_option')->insert(array('vote' => $vote,'title'=> $option, 'sort' => $key));
					if(!$op) throw new HLPException('问卷创建失败！');
				}
				// 发送
				$teachers = Http::post('teacher');
				$students = Http::post('student');
				$teacher = empty($teachers['id']) ? '' : json_encode($teachers['id']);
				$student = empty($students['id']) ? '' : json_encode($students['id']);
				if(!$student && !$teacher) throw new HLPException('请选择问卷接收者！');				
				$id = load_model('notify')->insert(compact('student', 'teacher', 'creator', 'school', 'create_time', 'vote', 'content'));				
				if(!$id) throw new HLPException('问卷发送失败！');
				
				//推送			
				if(!empty($students['id']))
				{
					$res = logs('db')->add('notify', md5($vote . rand(1000, 9999)), array_merge($this->_logs, array(
						'target'=> $student,
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
				$this->show_message('问卷发布成功', 'succeed', array(
					'back' => array('title' => '返回查看', 'url' => '/vote', 'default' => 1),
					'goon' => array('title' => '继续创建', 'url' => '/vote/add')
				), 'open');
			}catch(HLPException $e)
			{
				db()->rollback();
				$this->show_message($e->getMessage(), 'error', array(
					'back' => array('title' => '返回', 'url' => '/vote/add', 'default' => 1)				
				), 'open');
			}
		}else{
			$this->assign('curDate', DAY); // 机构科目
			$this->assign('multis', array(1=> '单选', 2=> '多选'));
			$this->display('school/vote/add');
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

	public function view_Action()
	{
		try
		{
			$id = Http::get('id', 'int', 0);
			$vote = load_model('vote')->getRow($id);			
			if(!$vote) throw new HLPException('数据不存在');
			$this->assign('vote', $vote);
			$options = load_model('vote_option')->getAll(array('vote' => $id), '', '`sort` Asc');
			$order = Http::post('order');
			$order = empty($order['time']) ? 'create_time Asc' : 'create_time Desc';
			$result = load_model('vote_record')->getAll(array('vote' => $id), '', $order);
			$count = count($result);
			//print_r($result);
			$this->assign('record', $count);
			$colors = $this->getColors();
			$this->assign('colors', $colors);
			
			array_walk($options, function(&$v, $k) use ($result, $count, $colors){	
				$opid = $v['id'];				
				$selected = array_filter($result, function($item) use ($opid) {					
					if($item['option'] == $opid) return $item;
				});
				$color = $k % count($colors);
				$v['color'] = $colors[$color];
				$v['num'] = count($selected);
				$v['rate'] = $count > 0 ? round($v['num'] / $count, 2) * 100 : 0;
			});
			$relations = $this->getRelations();
			array_walk($result, function(&$v, $k) use ($relations, $options){
				$opid = $v['option'];
				$v['option'] = current(array_filter($options, function($item) use ($opid) {					
					if($item['id'] == $opid) return $item['title'];
				}));				
				if($v['student'])
				{
					$v['sender'] = current(load_model('student')->getRow($v['student'], false, 'name'));
					$rs = current(load_model('user_student')->getColumn(array('user' => $v['user'], 'student' => $v['student']), 'relation'));
					$v['sender'] .= $relations[$rs];
				}else{
					$v['sender'] = current(load_model('user')->getRow($v['user'], false, 'concat(firstname,lastname)'));
				}
			});
			$this->assign('options', $options);
			$this->assign('result', $result);
			$this->display('school/vote/view');
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
			$result[] = "(`title` like '%{$param['keyword']}%')";
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
		$result['school'] = 'school=' . $this->school;	
		return join(" And ", $result);
	}

	protected function getOrder($order = array())
	{
		$result = Array();		
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
					$result = load_model('vote')->getAll(array('id,in' => $id, 'school' => $this->school));					
					if(!$result) throw new HLPException('无数据');
					$res = load_model('vote')->delete(array('id,in' => $id, 'school' => $this->school), true);
					if(!$res) throw new HLPException('删除失败');
					$res = load_model('vote_option')->delete(array('vote,in' => $id), true);
					if(!$res) throw new HLPException('删除失败');
					$res = load_model('vote_record')->delete(array('vote,in' => $id), true);
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