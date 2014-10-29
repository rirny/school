<?php
class Comment_Module extends School
{
	private $tpl = '';
	private $_perpage = 10;

	public function __construact(){
		parent::__construact();
	}
	
	// 学生评价
	public function student_Action()
	{		
		extract($this->int_search());		
		$_Comment = load_model('comment');
		$dateStart = $this->monthStart();
		$dateEnd = $this->monthEnd();
		$this->assign('dateStart', $dateStart); // 机构科目
		//$this->assign('weekEnd', $weekEnd); // 机构科目
		$where['start'] = isset($where['start']) ? $where['start'] : $dateStart;
		$where['end'] = isset($where['end']) ? $where['end'] : DAY;//$weekEnd;		
		$this->assign('start', $where['start'] ); // 开始时间
		$this->assign('end', $where['end'] ); // 结束时间
		$where['event'] = 0;		
		$where['pid'] = 0;
		$whereStr = $this->filter($where);
		$sql = "select {field} from t_comment c left join t_student s on c.student=s.id";
		$sql.= " Where " . $whereStr . " And c.student>0 And c.`character`!='student'";
		$total = db()->fetchOne(str_replace("{field}", 'count(*) n', $sql));
		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);		
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);	
		$order = $_Comment->getOrder($this->getOrder($order));
		$order && $sql.= " Order by " . $order;
		$limit = $_Comment->getLimit($this->_perpage, $page);
		$limit && $sql.= " Limit " . $limit;
		$result = db()->fetchAll(str_replace("{field}", 'c.*,s.name', $sql));
		$courses = $this->getCourses();
		$teachers = $this->getTeachers();

		$this->assign('courses', $courses); // 机构科目
		$this->assign('teachers', $teachers); // 机构老师	

		$_Student = load_model('student');
		$_User = load_model('user');
		foreach($result as $key => &$item)
		{			
			$item['student'] = current($_Student->getColumn($item['student'], 'name'));	// 缓存
			$teacher = $_User->getRow($item['teacher'], false, 'firstname,lastname');			
			$teacher && $item['teacher'] = $teacher['firstname'] . $teacher['lastname'];
			$item['replies'] = $_Comment->getCount(array('pid' => $item['id']), 'count(id)');
		}
		$this->assign('result', $result);
		$this->display('school/comment/student');
	}

	// 课程点评
	public function event_Action()
	{		
		extract($this->int_search());		
		$_Comment = load_model('comment');
		$weekStart = $this->startDate();
		$weekEnd = $this->endDate();
		$this->assign('weekStart', $weekStart); // 机构科目
		$this->assign('weekEnd', $weekEnd); // 机构科目
		$where['start'] = isset($where['start']) ? $where['start'] : $weekStart;
		$where['end'] = isset($where['end']) ? $where['end'] : DAY;//$weekEnd;		
		$this->assign('start', $where['start'] ); // 开始时间
		$this->assign('end', $where['end'] ); // 结束时间		
		$where['pid'] = 0;
		$whereStr = $this->filter($where, true);
		$sql = "select {fields} from t_comment c left join t_student s on c.student=s.id";
		$sql.= " Left Join t_event e on c.`event`=e.id";
		$sql.= " Where " . $whereStr . " And c.student>0 And c.`character`!='student'";	
		$total = db()->fetchOne(str_replace("{fields}", 'count(*) n', $sql));
		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);		
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);	
		$order = $_Comment->getOrder($this->getOrder($order));
		$order && $sql.= " Order by " . $order;
		$limit = $_Comment->getLimit($this->_perpage, $page);
		$limit && $sql.= " Limit " . $limit;		
	
		$result = db()->fetchAll(str_replace("{fields}", 'c.*,s.name,e.text', $sql));
		$courses = $this->getCourses();
		$teachers = $this->getTeachers();

		$this->assign('courses', $courses); // 机构科目
		$this->assign('teachers', $teachers); // 机构老师	
		$this->assign('event', true); // 机构科目

		$_Student = load_model('student');
		$_User = load_model('user');
		foreach($result as $key => &$item)
		{			
			$item['student'] = current($_Student->getColumn($item['student'], 'name'));	// 缓存
			$teacher = $_User->getRow($item['teacher'], false, 'firstname,lastname');			
			$teacher && $item['teacher'] = $teacher['firstname'] . $teacher['lastname'];
			$item['replies'] = $_Comment->getCount(array('pid' => $item['id']), 'count(id)');
		}
		$this->assign('result', $result);
		$this->display('school/comment/event');
	}

	// 用户评价
	public function user_Action()
	{		
		extract($this->int_search());		
		$_Comment = load_model('comment');		
		$where['event'] = 0;		
		$where['school'] = $this->school;
		$whereStr = $_Comment->whereExp($where);
		$sql = "select %s from t_comment c";
		$sql.= " Where " . $whereStr . " And pid=0 And ((student=0 And `character`='teacher') or (teacher=0 And `character`='student'))";
		$total = db()->fetchOne(sprintf($sql, 'count(*) n'));
		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);		
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);	
		$order = $_Comment->getOrder($this->getOrder($order));
		$order && $sql.= " Order by " . $order;
		$limit = $_Comment->getLimit($this->_perpage, $page);
		$limit && $sql.= " Limit " . $limit;		
		$result = db()->fetchAll(sprintf($sql, '*'));
		array_walk($result, function(&$v, $k){
			$v['sender'] = current(load_model('user')->getColumn($v['creator'], 'account'));
			$v['sender'] = substr($v['sender'], 0 ,5) . "******";// . substr($v['sender'], -4);
		});
		$this->assign('result', $result);
		$this->display('school/comment/school');
	}


	
	public function view_Action()
	{		
		$id = Http::get('id', 'int', 0);
		$comment = load_model('comment')->getRow($id);
		if(empty($comment)) throw new HLPException('数据不存在或已被删除！');				
		$this->assign('from', $comment);
		$this->assign('event', $comment['event']);			
		$reply = load_model('comment')->getAll(array('pid' => $id));
		$comment['teacher'] && $comment['teacher'] = load_model('user')->getRow($comment['teacher'], false, 'firstname,lastname');
		$comment['student'] && $comment['student'] = load_model('student')->getRow($comment['student'], false, 'name');
		$comment['relation'] = $comment['charactor'] == 'student' ? load_model('user_student')->getRow(array('student' => $comment['student'], 'user'=> $comment['creator'])) : 0;
		$comment['attach'] && $comment['attach'] = load_model('attach')->getRow(array('attach_id' => $comment['attach']), false, 'concat(save_path,save_name) as src');
		$this->assign('comment', $comment);
		foreach($reply as $key=>&$item)
		{
			$item['teacher'] && $item['teacher'] = load_model('user')->getRow($item['teacher'], false, 'firstname,lastname');
			$item['relation'] = $item['character'] == 'student' ? current(load_model('user_student')->getRow(array('student' => $item['student'], 'user'=> $item['creator']), false, 'relation')) : 0;
			$item['student'] && $item['student'] = load_model('student')->getRow($item['student'], false, 'name');	
		}
		$this->assign('relations', $this->getRelations());
		$this->assign('reply', $reply);	
		$this->display('school/comment/view');
	}

	public function reply_Action()
	{
		$pid = Http::post('pid', 'int', 0);
		if(!$pid) throw new HLPException('错误的操作！', 4);
		$event = Http::post('event', 'int', 0);
		$content = Http::post('content', 'trim', '');
		$student = Http::post('student', 'int', '');
		$school = $this->school;
		$creator = $this->uid;
		$character= 'school';
		$create_time = DATETIME;
		$id = load_model('comment')->insert(compact('pid', 'event', 'content', 'student', 'school', 'creator', 'character', 'create_time'));
		if($id) throw new HLPException('提交失败！', 4);
		// <script></script>
	}

	protected function getOrder($order = array())
	{
		$result = Array();	
		if(empty($order)) $order['time'] = 1;	
		foreach($order as $key => $item)
		{
			switch($key)
			{				
				case 'time' :// 上课日期
					$key = 'c.create_time';					
					break;
				case 'teacher' :
					$key = 'c.teacher';
					break;
				case 'student':
					$key = 's.name';
					break;
			}
			$result[$key] = $item;
		}		
		return $result;
	}

	public function filter($param, $event=false)
	{
		$result = Array();
		if(!empty($param['event']))
		{
			unset($param['start'], $param['end']);
		}
		if(!empty($param['keyword']))
		{
			if($event)
			{
				$result[] = "(s.`name` like '%{$param['keyword']}%' or e.`students` like '%{$param['keyword']}%' or e.`text` like '%{$param['keyword']}%')";
			}else{
				$result[] = "(s.`name` like '%{$param['keyword']}%' or c.`content` like '%{$param['keyword']}%')";
			}
		}		
		if(!empty($param['start']) && !empty($param['end']))
		{
			$result[] = sprintf("c.create_time>'%s' And c.create_time<'%s'", $param['start'] . " 00:00:00", $param['end'] . " 23:59:59");
		}else if(!empty($param['start'])){
			$result[] = sprintf("c.create_time>'%s'", $param['start'] . " 00:00:00");
		}else if(!empty($param['start'])){
			$result[] = sprintf("c.create_time>'%s'", $param['end'] . " 23:59:59");
		}		
		if(!empty($param['course']))
		{
			$result[] = "`course` = " . $param['course'];
		}
		if(!empty($param['teacher']))
		{
			$result[] = "c.`teacher` = " . $param['teacher'];
		}		
		if(!empty($param['student']))
		{
			$result[] = "c.`student` = " . $param['student'];
		}
		if(!empty($param['event']))
		{
			$result[] = "c.`event` = " . $param['event'];
		}
		if(isset($param['pid']))
		{
			$result[] = "c.`pid` = 0";
		}
		$result[] = 'c.school=' . $this->school;
		if($event)
		{
			$result[] = 'c.`event`>0';
		}else{
			$result[] = 'c.`event`=0';
		}		
		return join(" And ", $result);
	}


}