<?php
class Lead_Module extends School
{
	private $tpl = '';
	private $_perpage = 10;

	public function __construact(){
		parent::__construact();
	}
	
	// 学生评价
	public function index_Action()
	{		
		extract($this->int_search());		
		$_Lead = load_model('student_resource');
		$dateStart = $this->monthStart();
		$dateEnd = $this->monthEnd();
		$this->assign('dateStart', $dateStart);
		$this->assign('dateEnd', $dateEnd);
		$where['start'] = isset($where['start']) ? $where['start'] : $dateStart;
		$where['end'] = isset($where['end']) ? $where['end'] : $dateEnd;		
		$this->assign('start', $where['start'] ); // 开始时间
		$this->assign('end', $where['end'] ); // 结束时间
		$this->assign('ages', array('3岁以下', '3-5岁', '6-12岁', '13-16岁', '17以上')); // 结束时间
		$this->assign('genders', array(1=> '男', 2=> '女')); // 结束时间
		$this->assign('statues', $this->getLeadStatus()); // 状态
		$this->assign('sources', $this->getLeadSource()); // 来源

		$whereStr = $this->filter($where);
		$total = $_Lead->getCount($whereStr, 'count(id)');
		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);

        $order = $_Lead->getOrder($this->getOrder($order));
		$limit = $_Lead->getLimit($this->_perpage, $page);
		$result = $_Lead->getAll($whereStr, $limit, $order);
		$courses = $this->getCourses();
		$this->assign('courses', $courses); // 机构科目
		$this->assign('teachers', $teachers); // 机构老师	
		$this->assign('relations', $this->getRelations()); // 机构老师	
		$_Student = load_model('student');
		$_User = load_model('user');
		foreach($result as $key => &$item)
		{			
			$item['age'] = birthday_to_age($item['birthday']);
			$item['parents'] = json_decode($item['parents'], true);
		}
		$this->assign('result', $result);
		$this->display('school/student/lead');
	}

	public function add_Action()
	{
		if(!Http::is_post())
		{
			$id = Http::get('id', 'int', 0);			
			$this->assign('ages', array('3岁以下', '3-5岁', '6-12岁', '13-16岁', '17以上'));
			$this->assign('genders', array(1=> '男', 2=> '女'));
			$this->assign('statues', $this->getLeadStatus()); // 状态
			$this->assign('sources', $this->getLeadSource());
			$this->assign('relations', $this->getRelations());
			$result = load_model('lead')->getRow($id);           
            if($result)
            {
                $result['contactors'] = json_decode($result['parents'], true);	
                $course = json_decode($result['course'], true);
                $result['course'] = load_model('course')->getAll(array('id,in' => $course, 'school' => $this->school), '','',false,  FALSE, 'id,title');                
            }
			$this->assign('result', $result);
			$this->assign('curDate', $curDate = $result ? date('Y-m-d', strtotime($result['birthday'])) : DAY);
			$this->assign('gender', $result ? $result['gender'] : 1);
			$this->display('school/student/lead.add');
		}else{
			try
			{
				extract(Http::post());
				if(empty($name) || strlen($name) < 2 || strlen($name) > 21)
				{
					throw new HLPException('学生姓名为必填，且须为2-20字符', 4);
				}
				if(empty($contactor['phone']) || count($contactor['phone']) < 1)
				{
					throw new HLPException('联系人必须填写一个', 4);				
				}
				$parents = Array();
				foreach($contactor['phone'] as $key => $item)
				{
					$parents[] = array(
						'relation' => $contactor['relation'][$key],
						'name' => $contactor['name'][$key],
						'phone' => $contactor['phone'][$key]	
					);				
				}
				$parents = json_encode($parents);
				if(!empty($course))
				{
					$course = json_encode($course['id']);
				}
				$school = $this->school;
				if(!$id)
				{
					$creator = $this->uid;		
                    $create_time = TM;
					$id = load_model('student_resource')->insert(compact('name', 'parents', 'course', 'source', 'status', 'gender', 'birthday', 'desc', 'school', 'create_time', 'creator'));
					if(!$id) throw new HLPException('提交失败');
					$this->show_message('添加成功', 'succeed', array(
						'back' => array('title' => '返回查看', 'url' => '/lead', 'default' => 1),
						'goon' => array('title' => '继续添加', 'url' => '/lead/add')
					), 'open');
				}else{
					load_model('student_resource')->update(compact('name', 'parents', 'course', 'source', 'status', 'gender', 'birthday', 'desc'), $id);
					$this->show_message('修改成功', 'succeed', array(
						'back' => array('title' => '返回查看', 'url' => '/lead', 'default' => 1),
						'goon' => array('title' => '创建学生', 'url' => '/lead/add?id=' . $id)
					), 'open');
				}
				//db()->commit();
			}catch(HLPException $e)
			{
				$this->show_message($e->getMessage(), 'error', array(
					'back' => array('title' => '返回', 'url' => -1),
					// 'goon' => array('title' => '创建学生', 'url' => '/lead/add', 'default' => 1)
				), 'open');
			}
		}
	}
	
	public function view_Action()
	{		
		$id = Http::get('id', 'int', 0);
		$comment = load_model('comment')->getRow($id);
		
		$from = Http::get('from', 'trim', 'event');
		$this->assign('from', $comment);

		if(empty($comment)) throw new HLPException('数据不存在或已被删除！');
		$reply = load_model('comment')->getAll(array('pid' => $id));

		$comment['teacher'] && $comment['teacher'] = load_model('user')->getRow($comment['teacher'], false, 'firstname,lastname');
		$comment['student'] && $comment['student'] = load_model('student')->getRow($comment['student'], false, 'name');
		$comment['relation'] = $comment['charactor'] == 'student' ? load_model('user_student')->getRow(array('student' => $comment['student'], 'user'=> $comment['creator'])) : 0;		
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
    
    public function ajax_Action()
    {
        $action = Http::request('action', 'trim', '');
        db()->begin();
        try{
            switch ($action)
            {            
                case 'sign':
                    $id = Http::post('id', 'trim', 0);
                    $this->_sign($id);
                    break;
                case 'delete':
					$id = Http::post('id', 'trim', 0);
                    $idArr = is_array($id) ? $id : explode(',', $id);
                    $_Lead = load_model('student_resource');					
                    $res = $_Lead->delete(array('id,in' => $idArr, 'school' => $this->school), true);					
                    if(!$res) throw new HLPException('错误的操作！');
                    break;
                default:
                    break;
            }
            db()->commit();
            Out(1, '成功');
        }catch (HLPException $e)
        {
            db()->rollback();
            Out(0, $e->getMessage());
        }
    }
    public function _sign($leadId)
    {        
        if(!Http::is_post()) throw new HLPException('非法操作！');       
        if(!$leadId) throw new HLPException('错误的操作！');
        $_Lead = load_model('student_resource');
        $idArr = is_array($leadId) ? $leadId : explode(',', $leadId);
        $_User = load_model('user');
        $_Student = load_model('student');
        foreach ($idArr as $id)
        {
            $lead = $_Lead->getRow(array('id'=> $id, 'school' => $this->school));
            if(!$lead) throw new HLPException('错误的操作！');
			if($lead['student'] == 0)
			{
				$parents = json_decode($lead['parents'], true);             
				if(empty($parents)) throw new HLPException('学生签约失败！');
				$parentArr = array();
				foreach($parents as &$parent)
				{				
					$user = $_User->getRow(array('account' => $parent['phone']));                
					if(empty($user))
					{
						$user = $_User->create($parent['phone'], $parent['name'], 0);					
					}
					$parent['id'] = $parentArr[] = $user['id'];
				}           
				$student = $_User->hasStudent($lead['name'], $parentArr); // 是否已经有此学生档案
				if(!$student && $lead['student'] == 0)
				{
					$student = $_Student->create($m = array(
						'name' => $lead['name'],
						'gender' => $lead['gender'],
						'birthday' => $lead['birthday'],
						'creator' => $parentArr[0],
						'operator'=> $this->uid
					)); 
				}            
				if(!$student) throw new HLPException('学生档案生成失败！'); 
				foreach($parents as $key => $val)
				{                
					$res = load_model('user_student')->createRelation($val['id'], $student, $val['relation']); // 创建家长关系
					if(!$res) throw new HLPException('学生档案生成失败！');
				}
				$res = load_model('school_student')->getRow(array('school' => $this->school, 'student' => $student));            
				if($res) throw new HLPException('该学生已存在！');	
			}else{
				$student = $lead['student'];
			}
			if(!load_model('school_student')->getRow(array('school' => $this->school, 'student' => $student)))
			{
				$sid = load_model('school_student')->insert(array('school' => $this->school, 'student' => $student, 'create_time' => TM, 'source' => 1));
				if(!$sid) throw new HLPException('学生关联失败！');
			}           
            $_Lead->update(array('sign' => 1), $id);
        }            
    }
    
    protected function getOrder($order = array())
	{
		$result = Array();	
		if(empty($order)) $order['time'] = 1;	
		foreach($order as $key => $item)
		{
			switch($key)
			{				
				case 'date' :// 上课日期
					$key = 'modify_time';					
					break;
				case 'time' :// 上课日期
					$key = 'modify_time';					
					break;
				case 'age' :
					$key = 'birthday';
					break;
				case 'student':
					$key = 's.name';
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
			$result[] = "`name` like '%{$param['keyword']}%'";
		}       
		if(isset($param['age']) && is_numeric($param['age']))
		{
			$lbound = 0;
			$ubound = 60;
			switch($param['age'])
			{
				case 0:
					$ubound = 3;				
					break;
				case 1:
					$lbound = 4;
					$ubound = 5;
					break;
				case 2:
					$lbound = 6;
					$ubound = 12;
					break;
				case 3:
					$lbound = 13;
					$ubound = 16;
					break;
				case 4:
					$lbound = 17;
			}
			$ubYear = mktime(0,0,0,0,0, date('Y')-$lbound);
			$lbYear = mktime(0,0,0,0,0, date('Y')-$ubound);
			$result[] = "(birthday < '" .date('Y-m-d', $ubYear). "' And birthday>'". date('Y-m-d',$lbYear) ."')";
           
		}
		if(!empty($param['start']) && !empty($param['end']))
		{
			$result[] = sprintf("create_time>'%s' And create_time<'%s'", strtotime($param['start'] . " 00:00:00"), strtotime($param['end'] . " 23:59:59"));
		}else if(!empty($param['start'])){
			$result[] = sprintf("create_time>'%s'", strtotime($param['start'] . " 00:00:00"));
		}else if(!empty($param['start'])){
			$result[] = sprintf("create_time>'%s'", strtotime($param['end'] . " 23:59:59"));
		}		
		if(!empty($param['gender']))
		{
			$result[] = "`gender` = " . $param['gender'];
		}
		if(!empty($param['status']))
		{
			$result[] = "status = " . $param['status'];
		}
		$result[] = 'school=' . $this->school;		
		return join(" And ", $result);
	}


}