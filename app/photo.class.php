<?php
class Photo_Module extends School
{
	private $tpl = '';
	private $_perpage = 10;

	public function __construact(){}

	public function index_Action()
	{		
		extract($this->int_search());
		$_Module = load_model('attach');		
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
		$order = $_Module->getOrder(array('ctime' => 1));		
		$result = $_Module->getAll($whereStr, $_Module->getLimit($this->_perpage, $page), $order);	
		foreach($result as $key => &$item)
		{
			$attach = $_Module->getAttachInfo($item);
			$item = array_merge($item, $attach);
		}
		$this->assign('result', $result);
		$this->display('school/photo/photo.index');
	}
	
	public function ajax_Action()
	{
		$action = Http::request('action', 'trim');
		try
		{
			switch($action)
			{
				case 'add':
					if(!Http::is_post()) throw new HLPException('错误的操作！');
					import('file');			
					$file = Files::upload('image', 'image', 0, 'upPhoto');
					if(!$file) throw new HLPException('上传失败');
					$data = array(
						'app_name' => 'photo',
						'school' => $this->school,
						'uid' => $this->uid,
						'ctime' => TM,
						'size' => $file['size'],
						'extension' => $file['extension'],
						'title' => Http::post('title', 'trim'),
						'save_path' => $file['save_path'],
						'save_name' => $file['save_name'],
						'type' => $file['type']
					);
					$id = load_model('attach')->insert($data);
					if(!$id) throw new HLPException('上传失败');
					$message = '上传成功！';
				break;
				case 'delete':
					$id = Http::post('id', 'trim', '');
					if(!$id) throw new HLPException('照片不存在！');
					is_array($id) || $id = explode(",", $id);
					$attach = load_model('attach')->getRow(array('attach_id,in' => $id, 'school' => $this->school));
					if(!$attach) throw new HLPException('照片不存在！');
					$res = load_model('attach')->delete(array('attach_id,in' => $id, 'school' => $this->school), true);
					if(!$res) throw new HLPException('删除失败！');
					$message = '删除成功！';
					break;
				case 'rename':
					$id = Http::post('id', 'int', 0);
					if(!$id) throw new HLPException('照片不存在！');
					$attach = load_model('attach')->getRow(array('attach_id' => $id, 'school' => $this->school));
					if(!$attach) throw new HLPException('照片不存在！');
					$title = Http::post('title', 'trim', '');
					if(!$title) throw new HLPException('照片名称不能为空!');
					load_model('attach')->update(array('title' => $title), $id);
					$message = '修改成功！';
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
		$result[] = "app_name='photo'";
		return join(" And ", $result);
	}
}