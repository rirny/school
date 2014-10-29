<?php
/*
 * 权限
 */
class Purview_Module extends School
{
	private $tpl = '';
	private $_perpage = 10;
	private $_pageRange = 10;

	public function __construact(){
		parent::__construact();
	}
    
	public function index_Action()
	{		
		extract($this->int_search());		
		$_Group = load_model('admin_user_group');		
		$result = $_Group->getAll(array('school,in' => array(0, $this->school), 'type' => 'school'));
		array_walk($result, function(&$v, $k){
			$v['count'] = load_model('admin_user')->getCount(array('school' => $this->school, 'gid' => $v['gid']), 'count(uid)');
		});
        $this->assign('selected', json_encode(load_model('admin_user')->getColumn(array('school' => $this->school), 'uid'))); 
		$this->assign('result', $result);		
		$this->display('school/purview/purview');
	}

	public function ajax_Action()
	{
        $handle = Http::request('handle', 'trim', '0');
        if(Http::post()) db()->begin();
        try{
            switch ($handle)
            {
                case 'add':
                    if(!Http::post())
                    {
                        $this->display('/school/purview/add.html');
                    }else{                        
                        $name= Http::post('name', 'trim', '');
                        if(!$name) throw new HLPException('用户名不能为空！');
                        $id = load_model('admin_user_group')->insert(array(
                            'type' => 'school',
                            'school'=>$this->school,
                            'enable'=>'',
                            'name' => $name
                        ));                       
                        if(!$id) throw new HLPException('添加失败！');
                        $result = array('id' => $id);
                    }                    
                    break;
                case 'rename':
                    $gid = Http::post('gid', 'int', 0);
                    $name= Http::post('name', 'trim', '');
                    if(!$gid || $gid < 3 || !$name) throw new HLPException('操作错误！');					
                    load_model('admin_user_group')->update(array('name' => $name), array('gid' => $gid));
                    break;
                case 'delete':
                    $gid = Http::post('gid', 'int', 0);
                    if(!$gid || $gid < 3) throw new HLPException('操作错误！');
                    $group = load_model('admin_user_group')->getRow(array('gid' => $gid, 'school' => $this->school));
                    if(!$group) throw new HLPException('组不存在或已被删除！');                
                    $res = load_model('admin_user_group')->delete(array('gid' => $gid, 'school' => $this->school), true);
                    if(!$res) throw new HLPException('删除失败！'); 
                    load_model('admin_user')->delete(array('gid' => $gid, 'school' => $this->school), true);
                    break;
                case 'select':
                    $user = Http::get('uid');
                    $gid = Http::get('gid', 2);
                    is_array($user) && join(",", $user);
                    
                    $result = load_model('admin_user_group')->getAll("school={$this->school} Or gid=2");
                    $this->assign('result', $result);
                    $this->assign('gid', $gid); 
                    $this->assign('user', $user);                   
                    $this->display('school/purview/ajax.list');           
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
    
	public function set_Action()
	{
		try{
            
            $uid = Http::request('uid', 'int', 0);
            $gid = Http::request('gid', 'int', 0);	
			$reback = $uid ? '/manager' : 'purview';
            if($uid)
            {	
				$user = load_model('admin_user')->getRow(array('uid' => $uid, 'school' => $this->school));
                if(!$user) throw new HLPException('用户不存在！');
                $group = load_model('admin_user_group')->getRow(array('gid' => $user['gid'])); 
				if($user)
                {
                    $user['enable'] == '*' || $user['enable'] = explode(",", $user['enable']);                    
                } 
            }else{
                $_Group = load_model('admin_user_group');
                $group = $_Group->getRow(array('gid' => $gid));
                if(!$group) throw new HLPException('用户组不存在！');
            }
			$group['enable'] == '*' || $group['enable'] = explode(",", $group['enable']);
			if(Http::is_post())
			{				
				$priv = Http::post('priv');
				$priv = array_filter($priv, create_function('$v', 'if(is_numeric($v)) return $v;'));
				$all = Http::post('all');
				if(!$all && count($priv) < 1)  throw new HLPException('请选择权限！');							
                if($uid)
                {
					$priv = array_filter($priv, function(&$v) use ($group){
						if($group['enable']!= '*' && !in_array($v, $group['enable'])) return $v; // 去除组权限						
					});
					$enable = $all ? '*' : join(",", $priv);					
					load_model('admin_user')->update(array('enable' => $enable), array('school' => $this->school, 'uid' => $uid));                    
                }else{
					$enable = $all ? '*' : join(",", $priv);					
                    load_model('admin_user_group')->update(array('enable' => $enable), array('school' => $this->school, 'gid' => $gid));
                }				
				$this->show_message('设置成功', 'succeed', array(
						'goon' => array('title' => '返回', 'url' => $reback, 'default' => 1),
						'back' => array('title' => '查看')
				), 'open');
			}else{               
				$this->assign('group', $group);
				$this->assign('user', $user);
				$modules = load_model('school_menu')->getAll(array('type' => 'module'));
				$items = load_model('school_menu')->getAll(array('type' => 'item'));
				array_walk($items, function(&$v, $k) use ($group, $user) {
					$v['checked'] = 0;
					if(($user && ($user['enable'] == '*' || in_array($v['id'], $user['enable']))) || 
						($group && ($group['enable'] == '*' || in_array($v['id'], $group['enable'])))
					)
					{
						$v['checked'] = 1;
					}					
				});				
				array_walk($modules, function(&$v, $k) use($items, $group, $user) {
					$module = $v['module'];
					$v['checked'] = 0;
					if(($user && ($user['enable'] == '*' || in_array($v['id'], $user['enable']))) || 
						($group && ($group['enable'] == '*' || in_array($v['id'], $group['enable'])))
					){
						$v['checked'] = 1;
					}
					// $v['checked'] = $enable == '*' || in_array($v['id'], $enable) ? 1 : 0;
					$v['items'] = array_filter($items, function($item) use ($module){
						if($item['module'] == $module) return $item;
					});
				});
				$this->assign('result', $modules);			
				$this->display('school/purview/set');
            }
		}catch(HLPException $e)
		{
			$this->show_message($e->getMessage(), 'succeed', array(
					'back' => array('title' => '返回', 'url' => $reback, 'default' => 1)				
			), 'open');
		}	
	}
}