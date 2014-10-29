<?php

class Manager_Module extends School
{
	private $tpl = '';
	private $_perpage = 10;

	public function __construact(){
        
	}
    
    private function _get_group($form = 'id', $multi=false, $vname=false){
         // 分组
		$res = load_model('admin_user_group')->getAll("school={$this->school} Or gid=2 or gid=3");
        if(!$form) return $res;
        $result = Array();     
        array_walk($res, function($v,$key) use($multi, $form, &$result){                
            if($multi){
                $result[$v[$form]][] = $v['name'];
            }else{
                $result[$v[$form]] = $v['name'];
            }
        });
        return $result;
    }
    
	public function index_Action(){
        extract($this->int_search());		
		$_Admin = load_model('admin_user');
        $groups = $this->_get_group('gid');
        $this->assign('groups', $groups);
		$where['school'] = $this->school;
        if(empty($where['gid'])) unset($where['gid']);
        $whereStr = $_Admin->whereExp($where);
		$total = $_Admin->getCount($whereStr, 'count(*)');	       
		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);		
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);
		$limit = $_Admin->getLimit($this->_perpage, $page);
        if($order && $order['name'] == 1)
        {
            $order = 'uid DESC';
        }else{
            $order = 'uid Asc';            
        }
		$result = $_Admin->getAll($whereStr, $limit, $order);
        $this->assign('selected', json_encode($_Admin->getColumn($whereStr, 'uid')));      
        array_walk($result, function(&$v) use($groups){            
            $user = load_model('user')->getRow($v['uid'], false, 'firstname,lastname,account');        
            $v['name'] = $user['firstname'].$user['lastname'];
            $v['name'] || $v['name'] = $user['account'];
            $v['group'] = $groups[$v['gid']];
        });       
        $this->assign('result', $result);		
		$this->display('school/manager/index');      
    }
    
    public function ajax_Action()
	{
        $handle = Http::request('handle', 'trim', '0');
        db()->begin();
        try{
            switch ($handle)
            {
                case 'add':
                    if(!Http::post())
                    {
                        $this->display('/school/manager/add.html');
                    }else{
                        $users = Http::post('user', 'trim', '');
                        $group = Http::post('group', 'int', 3);
                        if(!$users) throw new HLPException('请选择用户！');
                        is_array($users) || $users = explode(',', $users);
                        $result = Array();                       
                        foreach($users as $user)
                        {
                            if($res = load_model('admin_user')->getRow(array('school'=> $this->school, 'uid' => $user)))
                            {                                
                                if(!load_model('admin_user')->update(array('gid' => $group), $res['id']))
                                       throw new HLPException('分组失败！');
                                continue;
                            }                            
                            $id = load_model('admin_user')->insert(array(
                                'type' => 'school',
                                'school'=> $this->school,
                                'gid'=> $group, // 默认分组
                                'uid' => $user
                            ));                          
                            if(!$id) throw new HLPException('添加失败！');
                            $result[] = $id;
                        }
                        $message = '成功';
                    }                    
                    break;               
                case 'delete': // 
                    $uid = Http::post('uid', 'int', 0);
                    $user = load_model('admin_user')->getRow(array('uid' => $uid, 'school' => $this->school));                    
                    if(!$user) throw new HLPException('用户不存在或已被删除！');                
                    $res = load_model('admin_user')->delete(array('uid' => $uid, 'school' => $this->school), true);
                    if(!$res) throw new HLPException('删除失败！');                     
                    break;                
                default:                    
                    break;
            }
            db()->commit();
            empty($message) && $message = '';
            empty($result) && $result = array();
            Out(1, $message, $result);
        }catch(HLPException $e)
		{
            db()->rollback();
			Out(0, $e->getMessage());
		}	
	}
 }