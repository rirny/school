<?php
class Apply_Module extends School
{
	private $tpl = '';
	private $_perpage = 10;

	public function __construact(){
	
	}
	
	public function teacher_Action(){
		$where = array(
			'to' => $this->school, 
			'status' => 0, 
			'type' => 4	
		);
		$this->_list($where);		
		$this->display('school/apply/apply.teacher');
	}
	public function student_Action()
	{			
		$where = array(
			'to' => $this->school, 
			'status' => 0, 
			'type' => 6	
		);	
		$this->_list($where);
		$this->display('school/apply/apply.student');
	}

	private function _list($where)
	{		
		//extract($this->int_search());
		$param = $this->int_search();
		empty($param['where'])	|| array_merge($where, $param['where']);
		$page = $param['page'];
		$order = $param['order'];
		$this->assign('where', $where);
		$_Apply = load_model('apply');
		$whereStr = $_Apply->whereExp($where);			
		$total = $_Apply->getCount($whereStr, 'count(*)');		
		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);		
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);	
		$order = $_Apply->getOrder($this->getOrder($order));
		$limit = $_Apply->getLimit($this->_perpage, $page);		
		$result = $_Apply->getAll($whereStr, $limit, $order);		
		array_walk($result, function(&$v) use ($where){
			if($where['type'] == 4)
			{
				$_tmp =  load_model('user')->getRow($v['from'], false, 'firstname,lastname');                
				$v['name'] = $_tmp['firstname'] . $_tmp['lastname'];
            }else if($where['type'] == 6)
            {               
				$v['name'] =  current(load_model('student')->getColumn($v['student'], 'name'));
			}
		});		
		$this->assign('result', $result);		
	}	

	public function add_Action()
	{
		$type = Http::get('type', 'trim', 'teacher');
		$this->assign('character', $type);
		$this->display('school/apply/add');
	}

	public function do_Action()
	{	
		db()->begin();
		try
		{            
			if(!Http::is_post()) throw new HLPException('错误的操作！');
			$handle = Http::post('handle', 'trim', 'add');					
			switch($handle)
			{
				case 'add':
					$message = $this->_add();
					break;
				case 'pass':
					$message = $this->_pass();
					break;
				case 'refuse':
					$message = $this->_refuse();
					break;				
			}
			db()->commit();
			Out(1, $message);
		}catch(HLPException $e)
		{
			db()->rollback();
			Out(0, $e->getMessage());			
		}
	}

	public function _add()
	{
		$character = Http::post('character', 'trim', 'teacher');
		$account = Http::post('account', 'trim', '');
		if(!$account) throw new HLPException('请错误的输入！');
		$from = $this->school;
		$user = load_model('user')->getRow("`account`='{$account}' Or hulaid='{$account}'");
		if(!$user) throw new HLPException('用户不存在');
		// 是否存在关系
		$type = 3;
		$to = $user['id'];
		if($character == 'student')
		{
			$type = '7';
			$student = load_model('user_student')->getRow(array('creator' => $user['id']));
			if(!$student) throw new HLPException('该用户没有学生档案！');
			// 多个学生
        }else{
            $teacher = load_model('teacher')->getRow(array('user' => $user['id']));
            if(!$teacher) throw new HLPException('该用户没有老师档案！');
            $rs = load_model('school_teacher')->getRow(array('teacher' => $user['id'], 'school' => $this->school));
            if($rs) throw new HLPException('已是本构老师！');
        }
		// 是否已申请
		$apply = load_model('apply')->getRow(array('from' => $from, 'to' => $to, 'type' => $type));
		if($apply) throw new HLPException('已申请请不要重复申请！');
		$create_time = DATETIME;
		$creator = $this->uid;
		$id = load_model('apply')->insert(compact('from', 'to', 'type', 'create_time', 'creator'));
		if(!$id) throw new HLPException('申请失败！');

		// 推送
		$_from = load_model('school')->getRow($from, false, 'id school, code,name,avatar');
		$push = array(
			'app' => 'apply',	'act' => 'add',		'from' => $from,	'to' => $to,			
			'character' => 'school', 'type' => 2, 
			'ext' => array(
				'id' => $id,
				'type' => $type,
				'from' => $_from,
				'to' => $to,
				'create_time' => $create_time
			)
		);
		$res = push('db')->add('H_PUSH', $push);
		if(!$res)  throw new HLPException('申请失败！');
		return '添加成功！等待用户验证！';
	}
    
    private function _refuse()
	{
        $idArr = Http::post('id');        
		if(!$idArr)  throw new HLPException('错误的操作！');
		is_array($idArr) || $idArr = explode(",", $idArr);       
		$character = Http::post('character', 'trim', 'teacher');       
		$_Apply = load_model('apply');        
		$applies = $_Apply->getAll(array('id,in' => $idArr, 'to' => $this->school));         
		if(!$applies) throw new HLPException('错误的操作！');       
        if(!$_Apply->delete(array('id,in' => $idArr, 'to' => $this->school), true)) throw new HLPException('操作失败！');
        return '操作成功！';
    }
	private function _pass()
	{
		$idArr = Http::post('id');        
		if(!$idArr)  throw new HLPException('错误的操作！');
		is_array($idArr) || $idArr = explode(",", $idArr);       
		$character = Http::post('character', 'trim', 'teacher');
		$_Apply = load_model('apply');        
		$applies = $_Apply->getAll(array('id,in' => $idArr, 'to' => $this->school));      
		if(!$applies) throw new HLPException('错误的操作！');       
		if($character == 'teacher')
		{
			$_Relation = load_model('school_teacher');
			foreach($applies as $item)
			{			
				// 关联               
				$id = $_Relation->insert($r = array(
					'teacher' => $item['from'],	
					'school' => $this->school,
					'create_time' => TM,
					'operator' => $this->uid
				));     
                // print_r($r);
				if(!$id) throw new HLPException('操作失败！');               
				// push
				$push = array(
					'app' => 'school',	'act' => 'add',		'from' => $this->school,	'to' => $item['from'],			
					'character' => 'school', 'type' => 2, 
					'ext' => $item,
					'message' => '您的机构老师申请已经通过！' //某机构已通过您的老师申请
				);
				$res = push('db')->add('H_PUSH', $push);
				if(!$res)  throw new HLPException('操作失败！');
				// 删除
				if(!$_Apply->delete($item['id'], true)) throw new HLPException('操作失败！');
			}
		}else{
			$_Relation = load_model('school_student');
			foreach($applies as $item)
			{		
                if($_Relation->getRow(array('student' => $item['student'], 'school' => $this->school))) throw new HLPException('学生已存在！');
				// 关联
				$id = $_Relation->insert(array(
					'student' => $item['student'],	
					'school' => $this->school,
					'create_time' => TM,
					'operator' => $this->uid
				));              
				if(!$id) throw new HLPException('操作失败！');
				// push
				$push = array(
					'app' => 'school',	'act' => 'add',		'from' => $this->school,	'to' => $item['from'],			
					'character' => 'school', 'type' => 2, 'student' => $item['student'],
					'ext' => $item,
					'message' => '您的机构学生申请已经通过！' //某机构已通过您的老师申请
				);               
				$res = push('db')->add('H_PUSH', $push);
				if(!$res)  throw new HLPException('操作失败！');               
				// 删除
				if(!$_Apply->delete($item['id'], true)) throw new HLPException('操作失败！');
			}
		}
		return '操作成功！';
	}

	protected function getOrder($order = array())
	{
		$result = Array();		
		foreach($order as $key => $item)
		{
			switch($key)
			{				
				case 'time' :// 上课日期
					$key = 'create_time';					
					break;				
			}
			$result[$key] = $item;
		}		
		return $result;
	}

}