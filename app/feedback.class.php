<?php
class Feedback_Module extends School
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
		$_Feedback = load_model('feedback');
		$whereStr = array('school' => $this->school);

		$total = $_Feedback->getCount($whereStr, 'count(*)');		
		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);		
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);		
		if(isset($order['name']))
		{
			$order = '`from` ' . ($order['name'] == 1 ? ' Desc' : ' ASC');
		}else if(isset($order['time']))
		{
			$order = '`create_time` ' . ($order['time'] == 1 ? ' Desc' : ' ASC');
		}else{
			$order = 'create_time Desc';
		}
		$limit = $_Feedback->getLimit($this->_perpage, $page);
		$result = $_Feedback->getAll($whereStr, $limit, $order);
		$relations = $this->getRelations();
		array_walk($result, function(&$v, $k) use ($relations){
			if($v['anonymous'])
			{
				$v['sender'] = '【匿名】';
			}else{
				if($v['student'])
				{
					$v['sender'] = current(load_model('student')->getRow($v['student'], false, 'name'));
					$rs = current(load_model('user_student')->getColumn(array('user' => $v['from'], 'student' => $v['student']), 'relation'));
					$v['sender'] .= $relations[$rs];
				}else{
					$v['sender'] = current(load_model('user')->getRow($v['from'], false, 'concat(firstname,lastname)'));
				}
			}
		});

		$this->assign('result', $result);
		
		$this->display('school/feedback');
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
					$result = load_model('feedback')->getAll(array('id,in' => $id, 'school' => $this->school));					
					if(!$result) throw new HLPException('无数据');
					$res = load_model('feedback')->delete(array('id,in' => $id, 'school' => $this->school), true);
					if(!$res) throw new HLPException('删除失败');
					$message = '删除成功！';
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
}