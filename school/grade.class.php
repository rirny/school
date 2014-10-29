<?php
class Grade_Module extends School
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
		$_Lead = load_model('grade');	
		$whereStr = $this->filter($where);
		$total = $_Lead->getCount($whereStr, 'count(id)');
		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);
		$order = $_Lead->getOrder($this->getOrder($order));
		$limit = $_Lead->getLimit($this->_perpage, $page);       
		$result = $_Lead->getAll($whereStr, $limit, $order);		
		foreach($result as $key => &$item)
		{			
			$item['num'] = load_model('grade_student')->getCount(array('grade' => $item['id']));
		}
		$this->assign('result', $result);
		$this->display('school/student/grade');
	}

	public function add_Action()
	{
		if(!Http::is_post())
		{
			$id = Http::get('id', 'int', 0);			
			$this->display('school/student/grade.add');
		}else{
			try
			{
				extract(Http::post());
				if(empty($name) || strlen($name) < 2 || strlen($name) > 40)
				{
					throw new HLPException('班级名称为必填，且须为2-20字', 4);
				}				
				$school = $this->school;
				if(!$id)
				{
					$creator = $this->uid;			
					$create_time = TM;
					$id = load_model('grade')->insert(compact('name',  'create_time', 'school'));
					if(!$id) throw new HLPException('提交失败');
					$this->show_message('班级添加成功', 'succeed', array(
						'back' => array('title' => '返回查看', 'url' => '/grade', 'default' => 1),
						'goon' => array('title' => '继续添加', 'url' => '/grade/add')
					), 'open', 'GradeEdit');
				}else{
					load_model('grade')->update(compact('name'), $id);
					$this->show_message('添加成功', 'succeed', array(
						'back' => array('title' => '返回查看', 'url' => '/grade', 'default' => 1),
						'goon' => array('title' => '创建学生', 'url' => '/grade/add?id=' . $id)
					), 'open', 'GradeEdit');
				}
				//db()->commit();
			}catch(HLPException $e)
			{
				$this->show_message($e->getMessage(), 'error', array(
					'back' => array('title' => '返回', 'url' => -1),
					// 'goon' => array('title' => '创建学生', 'url' => '/lead/add', 'default' => 1)
				), 'open', 'GradeEdit');
			}
		}
	}	

	public function delete_Action()
	{
		db()->begin();
		try
		{			
			$idArr = Http::post('id');
			is_array($idArr) || $idArr = explode(",", $idArr);
			if(count($idArr) < 1) throw new HLPException('错误的操作！');
			$grades = load_model('grade')->getAll(array('id,in' => $idArr, 'school' => $this->school));
			if(empty($grades))  throw new HLPException('错误的操作！');
			// 删除班级
			$res = load_model('grade')->delete(array('id,in' => $idArr, 'school' => $this->school), true);			
			if(!$res) throw new HLPException('错误的操作！C');
			// 删除班级下的学生
			load_model('grade_student')->delete(array('grade,in' => $idArr, 'school' => $this->school), true);			
			db()->commit();
			Out(1, '成功');
		}catch(HLPException $e)
		{
			db()->rollback();
			Out(0, $e->getMessage());			
		}
	}
    
    public function ajax_Action()
    {
        $action = Http::request('action', 'trim', '');
        db()->begin();
        $message = '';
        try
        {
            switch ($action)
            {            
                case 'list':
                    $this->_list();
                    break;
                case 'rename':
                    $id = Http::post('id', 'int', 0);
                    $name= Http::post('name', 'trim', '');
                    if(!$id || !$name) throw new HLPException('操作错误！');
                    $grade = load_model('grade')->getRow(array('school' => $this->school, 'id' => $id));
                    if(empty($grade)) throw new HLPException('班级不存在！');  
                    load_model('grade')->update(array('name' => $name), array('id' => $id, 'school' => $this->school));

                    break;
                case 'student':               
                    $handle = Http::request('handle', 'trim', 'add');
                    $students = Http::post('src');
                    is_array($students) || $students = explode(",", $students);
                    if(empty($students)) throw new HLPException('操作错误！');
                    $id = Http::post('id', 'int', 0);
                    $grade = load_model('grade')->getRow(array('school' => $this->school, 'id' => $id));                    
                    if(empty($grade)) throw new HLPException('班级不存在！');                  
                    if($handle == 'add')
                    {
                        foreach($students as $student)
                        {                           
                            $exist = load_model('grade_student')->getRow(array('school' => $this->school, 'grade' => $id, 'student' => $student));
                            if($exist) continue;
                            if(!load_model('school_student')->getRow(array('school' => $this->school, 'student' => $student)))
                            {
                                throw new HLPException('没有此学生！'); 
                            }                          
                            $res = load_model('grade_student')->insert($m= array(
                                'grade' => $id,
                                'school'=> $this->school,
                                'student' => $student,
                                'creator' => $this->uid,
                                'create_time' => TM
                            ));                           
                            if(!$res) throw new HLPException('添加失败！');  
                        }
                        $message = '添加成功！';
                    }else{ // remove
                        foreach($students as $student)
                        {
                            $exist = load_model('grade_student')->getRow(array('school' => $this->school, 'grade' => $id, 'student' => $student));
                            if(!$exist) throw new HLPException('没有此学生！'); 
                            // 学生课程
                            $res = load_model('grade_student')->delete($exist['id'], true);
                            if(!$res) throw new HLPException('删除失败！');  
                        }
                        $message = '删除成功！';
                    }
                    break;
                default:
                    break;
            }            
            db()->commit();
            Out(1, $message);
        }  catch (HLPException $e)
        {
            db()->rollback();
            Out(0, $e->getMessage());
        }
    }   

    public function do_Action()
	{
		try
		{
			if(!Http::is_post()) throw new HLPException('错误的操作！');		
			$name = Http::post('name', 'trim', '');
			if(!$name) throw new HLPException('班级名称不能为空！');
			$id = load_model('grade')->insert(array(
				'name' => $name, 
				'school' => $this->school,
				'creator'=> $this->uid,
				'create_time' => TM
			));
			if(!$id) throw new HLPException('班级添加失败！');
			Out(1, '添加成功！', array('id' => $id));// 返回此ID
		}catch(HLPException $e){
			Out(0, $e->getMessage());
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
				case 'time' :// 上课日期
					$key = 'create_time';					
					break;	
                case 'name':
                    $key = 'name';
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
		$result[] = 'school=' . $this->school;		
		return join(" And ", $result);
	}


}