<?php
// 学生
class Student_Module extends School
{
	private $tpl = '';
	private $_perpage = 10;

	public function __construact(){
	
	}
	
	public function index_Action(){		
		extract($this->int_search());		
		$_Relation = load_model('school_student');
		$where['keyword'] = Http::get('keyword', 'string', '');
		// $where['group'] = $group;
		empty($where['status']) || $where['status'] = 2;
		$whereStr = $this->filter($where);
		$total = $_Relation->getStudentCount($whereStr, 'count(*) as n');		
		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);		
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);

		$order = $this->getOrder($order);
		$limit = $_Relation->getLimit($this->_perpage, $page);
		
		if(Http::get('export', 'int', 0) == 1)
		{
			$this->export($_Relation->getStudent($whereStr, $order, '', 's.id,s.name,s.name_en,s.create_time,r.no,r.source,r.`status`'));
			exit;
		}
		$result = $_Relation->getStudent($whereStr, $order, $limit, 's.id,s.name,s.name_en,s.create_time,r.no,r.source,r.`status`,s.creator');
		// 班级
		$_gradesRes = load_model('grade')->getAll(array('school' => $this->school), '', '', false, false, 'id, name');
		$grades = Array();
		foreach($_gradesRes as $gVal)
		{
			$grades[$gVal['id']] = $gVal['name'];
		}		
		$_Group = load_model('grade_student');
		foreach($result as $key => &$item)
		{
			$item['grade'] = $_Group->getColumn(array('student' => $item['id'], 'school' => $this->school), 'grade', 'grade Asc');
			$item['gradeShow'] = $item['grade'];
			$item['gradeShow'] && array_walk($item['gradeShow'], create_function('&$v,$k,$source', '$v=$source[$v];'), $grades);
			$item['gradeShow'] = join(" ", $item['gradeShow']);
			$parent = load_model('user')->getRow($item['creator']);
			//print_r($parent);
			$item['login_times'] = ($parent && $parent['login_times'] > 0) ? 1 : 0;
		}

		$this->assign('groups', $group);
		$this->assign('result', $result);
		$this->assign('session_name', session_name());
		$this->display('school/student/student');
	}
	
	private function export($source=Array(), $teachers=Array(), $course=Array())
	{
		$result = array();
		$title = Array(
			'no' => '学号',
			'name' => '姓名',			
			'grade' => '班级',			
			'create_time' => '加入时间',
			//'source' => '类型',
			'status' => '状态'
		);
		$_gradesRes = load_model('grade')->getAll(array('school' => $this->school), '', '', false, false, 'id, name');
		$grades = Array();
		foreach($_gradesRes as $gVal)
		{
			$grades[$gVal['id']] = $gVal['name'];
		}
		$_Group = load_model('grade_student');		
		foreach($source as $key => &$item)
		{
			$item['create_time'] = date('Y-m-d', $item['create_time']);
			$item['grade'] = $_Group->getColumn(array('student' => $item['id'], 'school' => $this->school), 'grade', 'grade Asc');
			$item['grade'] && array_walk($item['grade'], create_function('&$v,$k,$source', '$v = $source[$v];'), $grades);			
			$result[] = Array(
				'no' => $item['no'],
				'name' => $item['name'],		
				'grade' => join(" ", $item['grade']),			
				'create_time' => $item['create_time'],
				//'source' => $item['source'] == 2 ? '导入' : '正常',
				'status' => $item['status'] == 0 ? '正常' : '冻结'
			);			
		}		
		excelExport('学生导出', array_values($title), $result);
		Header('Location:' . Http::curl());
	}

	private function rmExport($source=Array())
	{
		$title = Array(
			'name' => '姓名',
			'title' => '课程名称',
			'start_date' => '开始日期',			
			'end_date' => '结束日期',			
			'times' => '总课次',
			'remain' => '剩余课次',
			'phone' => '联系方式'
		);
		foreach($source as $key => &$item)
		{
			$result[] = Array(
				'name' => $item['name'],
				'title' => $item['title'],		
				'start_date' => $item['start_date'],	
				'end_date' => $item['end_date'],	
				'times' => $item['times'],						
				'remain' => $item['remain'],
				'phone' => $item['phone'] 
			);			
		}	
		excelExport('剩余课程导出', array_values($title), $result);
		Header('Location:' . Http::curl());
	}

	public function remain_Action()
	{
		extract($this->int_search());		
		$where['keyword'] = Http::get('keyword', 'string', '');
		$whereStr = $this->filter($where);
		$school_student = load_model('school_student');
	
		$order = $this->getOrder($order);
		$limit = $school_student->getLimit($this->_perpage, $page);
		if(Http::get('export', 'int', 0) == 1)
		{
			$this->rmExport($school_student->getRemain($whereStr, $order, '', 's.id,s.name,s.phone,c.title,sa.start_date,sa.end_date,sa.times,sa.attend,sa.absence,sa.leave,sa.remain,sa.delay'));
			exit;
		}
		$result = $school_student ->getRemain($whereStr, $order, $limit,'s.id,s.name,s.phone,c.title,sa.start_date,sa.end_date,sa.times,sa.attend,sa.absence,sa.leave,sa.remain,sa.delay');

		$total = $school_student ->getRemainCount($whereStr, 'count(*) as n');		
		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);		
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);
		$this->assign('result', $result);
		$this->display('school/student/remain');
	}

	public function import_Action()
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
    
    public function toGrade_Action()
	{
		db()->begin();
		try
		{
			if(!Http::post())
			{
				$id = Http::request('id');
				$this->assign('id', $id);
				$result = load_model('grade')->getAll(array('school' => $this->school));				
				$selected = load_model('grade_student')->getColumn(array('school' => $this->school, 'student' => $id), 'grade');				
				$selected && array_walk($result, function(&$v) use ($selected){
					$v['check'] = in_array($v['id'], $selected);
				});				
				$this->assign('result', $result);
				$this->display('school/student/grade.ajax');
			}else{				
				$id = Http::post('id');			
				if(!$id) throw new HLPException('错误的操作');
				$groups = Http::post('group');
				if(count($groups) < 1) throw new HLPException('请选择分组');
				is_array($id) || $id = explode(",", $id);
				$old = load_model('grade_student')->getAll(array('school' => $this->school, 'student,in' => $id));
				if($old)
				{
					$res = load_model('grade_student')->delete(array('school' => $this->school, 'student,in' => $id), true); // 清除原先分组
					if(!$res) throw new HLPException('分组失败！'); // 未分组
				}
				$sql = "";
				foreach($id as $u)
				{
					foreach($groups as $g)
					{
						if(load_model('grade_student')->getRow(array('student' => $u, 'school' => $this->school, 'grade' => $g))) continue;
						$id = load_model('grade_student')->insert(array('student' => $u, 'school' => $this->school, 'grade' => $g));
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

	public function view_Action()
	{
		$id = Http::get('id', 'int', 0);
		$relation = load_model('school_student')->getRow(array('school' => $this->school, 'student' => $id));
		if(!$relation) $this->show_error('学生不存在！',  $this->refer);        
		//学生档案
		$result = load_model('student')->getRow(array('status' => 0, 'id' => $id));
		if(!$result) $this->show_error('学生档案不存在或已删除！', $this->refer);
		$parents = load_model('user_student')->getAll(array('student' => $id));       
		$this->assign('relations', $this->getRelations());
		array_walk($parents, function(&$v){
			$user = load_model('user')->getRow($v['user']);
			$v['account'] = $user['account'];
			$v['name'] = $user['firstname'] . $user['lastname'];
			$v['account'] = $user['account'];
		});		
		$result['avatar'] = imageUrl($id, 2);
        $this->assign('relation', $relation);
		$this->assign('parents', $parents);
		$this->assign('result', $result);

		// 备注
		$page = Http::get('page', 'int', 0);
		$_Remark = load_model('remark');
		$where = array('target' => $id, 'type' => 'student', 'school' => $this->school);
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

		//列表 
		require_once(LIB . "/schedule.class.php");
		$sql = "select c.id,c.title,sa.start_date,sa.end_date,sa.times,sa.attend,sa.absence,sa.leave,sa.delay,sa.remain from t_schedule_assign As sa INNER JOIN t_schedule c On c.id = sa.`schedule` Where sa.status=0 AND sa.type=0 AND sa.school=".$this->school." AND sa.assign=".$id;
		$db = db();
		$res = $db ->fetchAll($sql);
		$rules = array();
		$result = array();
		$data = array();
		
		foreach($res as $key => &$items)
		{		
			$rsql = "select id,start,end,week,period from t_schedule_rule where schedule=".$items['id'];
			$schedule_rule = $db ->fetchAll($rsql);
			foreach($schedule_rule as $key => &$item)
			{
				$rules[] = Array(
					'id' => $item['id'],
					'start' => $item['start'],		
					'end' => $item['end'],			
					'week' => $item['week'],
					'period' => $item['period']
				);
			}
			
			$sortAry = Schedule::resolve($rules,$items['start_date'], '', $items['times']);
			/*$ssql = "select item,source from t_schedule_student where status=4 and schedule=".$items['id']." and student=".$id;
			$schedule_student = $db ->fetchAll($ssql);

			if(!empty($schedule_student))
			{
				foreach($schedule_student as $key => &$item)
				{
					$current = $db ->fetchOne("select rule,start_time,end_time,day,week source from t_schedule_item where  id=".$item['item']);
					$change = $db ->fetchOne("select start_time,end_time,day,week source from t_schedule_item where  id=".$item['source']);
					$data[] = Array(
						'current' => $current,
						'change' => $change
					);
				}
			}*/

			foreach($sortAry as $key => &$item)
			{
				/*if(!empty($data))
				{
					foreach($data as $key => &$value)
					{
						if($item['id']==$value['current']['rule']&&$item['start']==$value['current']['start_time']&&$item['end']==$value['current']['end_time']&&$item['date']==$value['current']['day']&&$item['week']==$value['current']['week'])
						{
							$item['start'] = $value['change']['start_time'];
							$item['end'] = $value['change']['end_time'];
							$item['date'] = $value['change']['day'];
							$item['week'] = $value['change']['week'];
						}
					}
				}*/
				$item['week'] = $this -> getWeek($item['week']);
			}
	
			$result[] = Array(
				'id' => $items['id'],
				'title' => $items['title'],
				'start_date' => $items['start_date'],
				'end_date' => $items['end_date'],
				'times' => $items['times'],
				'attend' => $items['attend'],
				'absence' => $items['absence'],
				'leave' => $items['leave'],
				'delay' => $items['delay'],
				'remain' => $items['remain'],
				'rule' =>$sortAry
			);
		}	
		$this->assign('sid', $id);
		$this->assign('result', $result);
		$this->display('school/student/view');
	}
    
	public function select_Action()
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
			$this->display('school/student/schedule');	
		}else{
			$this -> _save();
		}
	}

	private function _save()
	{		
		if(!Http::is_post()) throw new HLPException('错误的操作');			
		extract(Http::post());
		$schedule_assign = load_model('schedule_assign', NULL, true);		
		$sc = load_model('schedule');
		for($i = 0 ;$i < count($student); $i++)
		{
			$result = $schedule_assign -> insert(array('schedule' => $schedule , 'assign' => $student[$i] , 'type' => 0 , 'start_date' => $start , 'times' => $times , 'school' => $this->school, 'remain'=> $times));
			if(!$result) throw new HLPException("排课失败！");   
		}
	
		$res = $schedule_assign -> field('id')
			->where('schedule', $schedule)
			->where('type', 1)
			->result();
		if($res)
		{			
			$scres = $sc -> update(array('status'=>1), array('id' => $schedule));

			if(!$scres) throw new HLPException("排课失败！");
		}
		Out(1, 'success', $student);
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
