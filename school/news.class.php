<?php
class News_Module extends School
{
	private $tpl = '';
	private $_perpage = 10;

	public function __construact(){}

	public function index_Action()
	{		
		extract($this->int_search());		
		$_Module = load_model('news');
		
		/*
		$dateStart = $this->monthStart();
		$dateEnd = $this->monthEnd();
		$this->assign('dateStart', $dateStart); // 机构科目
		//$this->assign('weekEnd', $weekEnd); // 机构科目
		$where['start'] = isset($where['start']) ? $where['start'] : $dateStart;
		$where['end'] = isset($where['end']) ? $where['end'] : DAY;//$weekEnd;		
		$this->assign('start', $where['start'] ); // 开始时间
		$this->assign('end', $where['end'] ); // 结束时间
		*/		
		
		$whereStr = $this->filter($where);		
		$total = $_Module->getCount($whereStr, 'count(*)');		
		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);		
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);
		$order = $_Module->getOrder($this->getOrder($order));	
		$limit = $_Module->getLimit($this->_perpage, $page);		
		$result = $_Module->getAll($whereStr, $limit, $order);
		array_walk($result, function(&$v){
			$v['create_time'] = $v['create_time'] ? date('Y-m-d H:i', $v['create_time']) : '';
		});
		$this->assign('result', $result);
		$this->display('school/news/news.index');
	}

	public function add_Action()
	{		
		if(Http::is_post())
		{
			$id = Http::post('id', 'int', 0);
			$title = Http::post('title', 'trim', '');
			$description = Http::post('description', 'trim', '');
			$status = Http::post('status', 'int', 0);			
			$creator = $this->uid;
			$id || $create_time = TM;
			$school = $this->school;			
			try
			{
				if($id)
				{
					$result = load_model('news')->getRow(array('id'=> $id, 'school' => $this->school));
					if(!$result) throw new HLPException('该资讯不存在或已删除！');
					load_model('news')->update(compact('title', 'description', 'status'), array('id'=> $id, 'school' => $this->school));
				}else{				
					$id = load_model('news')->insert(compact('title', 'description', 'status', 'creator', 'create_time', 'school'), $id);
					if(!$id) throw new HLPException('发布失败！');
				}
				Out(1, '发布成功！');
			}catch(HLPException $e)
			{
				Out(0, $e->getMessage());
			}
		}else{
			$id = Http::request('id', 'int', 0);
			$result = load_model('news')->getRow(array('id'=> $id, 'school' => $this->school));
			$this->assign('result', $result);
			$this->display('school/news/news.add');
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
					if(!$id) throw new HLPException('照片不存在！');
					is_array($id) || $id = explode(",", $id);
					$news = load_model('news')->getRow(array('id,in' => $id, 'school' => $this->school));
					if(!$news) throw new HLPException('内容不存在！');
					$res = load_model('news')->delete(array('id,in' => $id, 'school' => $this->school), true);
					if(!$res) throw new HLPException('删除失败！');
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
		if(empty($order)) $order['time'] = 1;	
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

	public function filter($param, $event=false)
	{
		$result = Array();		
		if(!empty($param['keyword']))
		{
			$result[] = "(`title` like '%{$param['keyword']}%' or `description` like '%{$param['keyword']}%')";			
		}
		$result[] = 'school=' . $this->school; 
		return join(" And ", $result);
	}
}