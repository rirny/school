<?php
class Course_Module extends School
{
	private $tpl = '';
	private $_perpage = 10;

	private $unValid = array('get_event'); // 不用鉴权 39 49 50

	public function __construact(){
		
	}
	
	public function index_Action(){		
		extract($this->int_search());		
		$_Course = load_model('course');
		$whereStr = $_Course->whereExp(array('school' => $this->school, 'status' => 0));
		$total = $_Course->getCount($whereStr, 'count(*)');		
		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);		
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);
		$types = $this->getCourseTypes();		
		$this->assign('types', $types); // 机构科目
		$order = $_Course->getOrder($this->getOrder($order));
		$limit = $_Course->getLimit($this->_perpage, $page);		
		$result = $_Course->getAll($whereStr, $limit, $order);
		foreach($result as $key => &$item)
		{
			$item['type_name'] = $types[$item['type']];
		}
		$this->assign('result', $result);
		$this->display('school/course/index');
	}
	
	public function select_Action()
	{
		// 单选 // 多选
		extract($this->int_search());
		$this->assign('multi', $where['multi']);		
		$this->assign('page', $page);
		$this->assign('selected', explode(",", $where['id']));		
		$_Course = load_model('course');
		$whereStr = $_Course->whereExp(array('school' => $this->school, 'status' => 0));
		$total = $_Course->getCount($whereStr, 'count(*)');		
		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);
		$this->assign('paginator', $paginator);
		$this->assign('record', $total);
		$order = $_Course->getOrder($this->getOrder($order));
		$limit = $_Course->getLimit($this->_perpage, $page);		
		$result = $_Course->getAll($whereStr, $limit, $order, false, false, 'id,title');		
		$this->assign('result', $result); // 机构科目
		$this->display('school/course/ajax');
	}
	
	public function add_Action()
	{		
		if(Http::post())
		{			
			$this->_save();
		}else{
			$this->assign('types', $types = $this->getCourseTypes(0));		
			$this->display('school/course/add');	
		}			
	}

	private function _save()
	{
		extract(Http::post());
		try{	
			$type = Http::post('type', 'int', 0);
			$type2 = Http::post('type2', 'int', 0);
			if($type && $type !=10) $type = $type2;
			if($type == '') throw new HLPException('科目为必选！');
			$title = Http::post('title', 'trim', '');
			if($id = Http::post('id', 'int', 0))
			{
				load_model('course')->update(compact('type', 'title'), array('school' => $this->school, 'id' => $id));			
			}else{
				$school = $this->school;
				$create_time = TM;
				$creator = $this->uid;
				$id = load_model('course')->insert(compact('type', 'title', 'school', 'creator', 'create_time'));
				if(!$id) throw new HLPException('保存失败！');
			}
			echo "<script>window.top.art.dialog.alert('成功'); window.top.art.dialog.opener.right.location.reload(); window.top.art.dialog({id:'CourseEdit'}).close();</script>";
		}catch(HLPException $e)
		{
			$message = $e->getMessage();
			$this->assign('result', json_encode(array('status' => 1, 'message' => $message)));
			echo "<script>window.top.art.dialog({icon : 'error', width:300, content:'失败', resize:false, drag : false, title : false},function(){});</script>";		
		}		
	}

	public function delete_Action()
	{		
		$id = Http::post('id');
		$state = false;
		$message = '删除失败！';
		if($id)
		{
			is_array($id) || $id = explode(",", $id);
			// 只能删除无课程的科目
			$events = load_model('event')->getAll(array('course,in' => $id));
			if(!$events)
			{
				load_model('course')->delete(array('id,in' => $id), true);
				$state = true;
				$message = '成功删除！';
			}
		}
		Out($state, $message);
	}
	
	public function edit_Action()
	{
		if(Http::post())
		{			
			$this->_save();
		}else{
			$id = Http::get('id', 'int', 0);
			if(!$id) throw new HLPException('课程不存在');
			$result = load_model('course')->getRow(array('id' => $id, 'school' => $this->school));
			$this->assign('types', $this->getCourseTypes(0));
			$result['pid'] = $result['type'] ? current(load_model('course_type')->getColumn($result['type'], 'pid')) : '';	
			$this->assign('result', $result);			
			$this->display('school/course/add');
		}
	}

	protected function getOrder($order = array())
	{
		$result = Array();	
		if(empty($order)) $order['time'] = 1;
		foreach($order as $key => $item)
		{
			$field = '';
			switch($key)
			{
				case 'name':
					$field = 'title';
					break;
				case 'time':
					$field = 'modify_time';
					break;
				case 'type' :// 上课日期
					$field = 'type';					
					break;				
			}
			$result[$field] = $item;
		}		
		return $result;
	}
	
	public function get_event_Action()
	{
		$id = Http::get('id');
		is_array($id) || $id = explode(",", $id);
		$state = 0;
		$result = array();
		if($id)
		{
			$result = load_model('event')->getAll(array('course,in' => $id));
			if(count($result) > 0) $state = 1;
		}
		Out($state);
	}
}