<?php
class Remark_Module extends School
{
	private $tpl = '';
	private $_perpage = 10;

	public function __construact(){
	
	}
	
	public function index_Action(){
		$where = array(
			'to' => $this->school, 
			'status' => 0, 
			'type' => 4	
		);
		$this->_list($where);		
		$this->display('school/remark/remark.teacher');
	}

	public function ajax_Action()
	{	
        $action = Http::request('action', 'trim', ''); // teacher/student/event       
        $handle = Http::request('handle', 'trim', ''); // list/add/edit/delete/  
        db()->begin();
        $message = '';
        $result = array();
        try
        {
            switch ($handle)
            {            
                case 'list':
                    $page = Http::get('page', 'int', 0);  
                    $src = Http::get('src', 'int', 0);
                    $perpage = Http::get('perpage', 'int', 10);
                    $message = $this->_list($handle, $src, $page);                    
                    break;
                case 'add':
                    if(Http::is_post())
                    {
                        $action = Http::post('action', 'trim', '');
                        $description = Http::post('description', 'trim', '');
                        $create_time = Http::post('create_time', 'trim', '');
						$id = Http::post('id', 'int', '');
                        $data = array(
                            'school' => $this->school,
                            'create_time' => strtotime($create_time),                    
                            'remark' => $description,
							"$action" => $id
                        );
					
                        $data['id'] = load_model('school_'.$action.'_remark')->insert($data);
                        if(!$data['id']) throw new HlpException('操作失败！');
                        $result = $data;

						db()->commit();
						echo "<script>window.top.right.location.reload();</script>";
						exit;

                    }else{   
                        $this->assign('curDate', date('Y-m-d H:i')); // 机构科目
                        $id = Http::get('id', 'int', 0);
                        $type = Http::get('type', 'int', 0);
                        $sources = $this->getRemarkTypes();
                        $this->assign('action', $action);
                        $this->assign('id', $id);
                        $this->assign('sources', $sources);
                        $this->display('school/remark/add');
                    }
                    break;
				 case 'delete':
					$id = Http::post('id', 'trim', 0);
                    $idArr = is_array($id) ? $id : explode(',', $id);
                    $_Remark = load_model('school_'.$action.'_remark');					
                    $res = $_Remark->delete(array('id,in' => $idArr, 'school' => $this->school), true);					
                    if(!$res) throw new HLPException('错误的操作！');
					$message = "删除成功！";
                    break;
            }
            db()->commit();
            Out(1, $message, $result);
        }  catch (HLPException $e)
        {
            db()->rollback();
            Out(0, $e->getMessage());
        }
	}
    
    public function _list($type='student', $src=0, $page=1, $perpage=10)
    {        
		$_Remark = load_model('remark');
		$where = array('target' => $src, 'type' => $type, 'school' => $this->school);
		$total = $_Remark->getCount($where, 'count(*)');		
		$paginator = paginator($page, $total, $perpage, 10);
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);
        $sources = $this->getRemarkTypes();       
        $this->assign('sources', $sources);
		$order = 'create_time Desc'; //$_Remark->getOrder(array(''));
		$limit = $_Remark->getLimit($this->_perpage, $page);	
		$remarks = $_Remark->getAll($where, $limit, $order);
		$this->assign('remarks', $remarks);
        return $this->fetch('/school/remark/list');
    }
}