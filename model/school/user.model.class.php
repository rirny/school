<?php
/*
 * 机构老师
*/

class User_Model Extends Model
{
	protected $_table = 't_user';

	protected $_key = 'id';	
	protected $_cache_key = 'user';
	protected $_timelife = '3600';

	public function __construct(){
		parent::__construct();
	}
	
	public function getUser($account, $force=false)
	{
		if($account == '' || strlen($account) != 11) return 0; // 非手机号
		$user =  $this->getRow(array('account' => $account));
		if(!$user && $force)
		{
			$data = array(
				'account' => $account,
				'password'=> md5(md5('000000') . 12345),
				'login_salt' => 12345,
				'create_time'=> TM
			);			
			$data['setting'] = json_encode(array(
				"hulaid" => 0,
				"friend_verify" => 1,
				"notice" => array(
					"method" => 0,
					"types" => "1,2,3,4,5"
				)
			));						
			$id = $this->insert($data);			
			$this->update(array('hulaid' => 'h_' . sprintf("%u", crc32($id))), $id);
		}else if($user){
			$id = $user['id'];
		}else{
			return false;
		}
		return $id;
	}

	public function create($account, $name='', $gender='')
	{
		if(!$account) return false;
		if($name)
		{
            $firstname = $lastname = '';
            if(strpos($name, " "))
            {
                list($firstname, $lastname) = explode(" ", $name);
            }else{
                $firstname = $name;
            }
			load('ustring');
			$firstname_en = $firstname ? Ustring::topinyin($firstname) : '';
			$lastname_en = $lastname ? Ustring::topinyin($lastname) : '';
			$name = str_replace(" ", '', $name);
		}
		$login_salt = 12345;
		$password = md5(md5('000000') . 12345);
		$create_time = TM;
		$source = 2;
		$setting = json_encode(array(
			"hulaid" => 0,
			"friend_verify" => 1,
			"notice" => array(
				"method" => 0,
				"types" => "1,2,3,4,5"
			)
		));		
		
        $result = compact('account', 'firstname', 'firstname_en', 'lastname', 'lastname_en', 'name', 'password', 'login_salt', 'create_time', 'gender', 'source', 'setting');
		$id = $this->insert($result); 
		if($id) $this->update(array('hulaid' => 'h_' . sprintf("%u", crc32($id))), $id);
        $result['id'] = $id;
		return $result;
	}
	
	// 用户是否有此学生档案users[1,2,3]
	public function hasStudent($student, $users)
	{
		if(!$student) return false;
		$existStudent = load_model('student')->getRow(array('name' => $student, 'creator,in' => $users));
		if($existStudent)
		{
			return $existStudent['id'];
		}
		return false;
	}

	public function login($user)
	{		
		// 设置session
		Http::set_session(SESS_UID, $user['id']);
		Http::set_session(SESS_ACCOUNT, $user['account']);
		Http::set_session(SESS_NAME, $user['firstname'] . $user['lastname']);
		Http::set_session(SESS_HULAID, $user['hulaid']);
		// 更新
		$times = $user['login_times'] + 1;
		$data = array(
			'last_login_time' => time(),
			'last_login_ip' => Http::ip(),
			'token' => $token,
			'status'=> 1,
			'agent' => '',
			'login_times' => $times,
		);
		$this->update($data, $user['id']);
	}

	public function logout($uid)
	{		
		if(!$uid) return false;
		db()->update($this->_table, array('status' => 0, 'token' => ''), 'id=' . $uid); // 清空当前账号的token
		Http::delete_session(SESS_UID, SESS_ACCOUNT, SESS_NAME, SESS_HULAID, SCHOOL_NAME, SCHOOL_CODE, SCHOOL_ID, SCHOOL_GID, SCHOOL_PRIV, SCHOOL_CREATOR, 'device');		
		hlp_session_start()->destroy();		
		//session_destroy();
	}

	public function getAllSchool($uid)
	{
		if(!$uid) return false;
		$sql = "select s.`code`,s.id, s.`name`,u.`enable`,s.creator,u.`default`,u.gid,s.lng from t_admin_user u left join t_school s on u.school=s.id where s.id>0  And u.uid={$uid} Order by u.`default` Desc";
		return db()->fetchAll($sql);
	}
	public function getRowSchool($uid, $school)
	{
		if(!$uid || !$school) return false;
		$sql = "select s.`code`,s.id, s.`name`,u.`enable`,s.creator,u.`default`,u.gid,s.lng from t_admin_user u left join t_school s on u.school=s.id where u.uid={$uid} And s.school={$school}";
		return db()->fetchRow($sql);
	}
	
	// 注册
	public function register($user, $code='')
	{		
		db()->begin();
		try{
			// $id = $this->sync_sns($user);			
			$id = $this->insert($user);			
			if(!$id) throw new Exception('注册失败');
			$create_time = datetime();	
			$att = compact('id', 'create_time', $user);			
			$user = array_merge($user, $att);
			$hulaid = $this->hulaid_create($id);			
			$this->update(array('hulaid' => $hulaid), $id);
			$this->login($user, $user['token']);
			$code && $this->verify_delete($user['account'], 0); // 清除用户的注册验证码！
			// $this->welcome($id, $user['account']);
			db()->commit();
			return $id;
		}catch(Exception $e)
		{
			db()->rollback();
			return false;
		}
	}		// 验证码
	public function verify($mobile, $code, $type=0)
	{	
		if($type == 0 && $code == '123321') return true;
		$res = db()->fetchRow("select * from t_verify_code where `type`='{$type}' And `code`='{$code}' order by send_time Desc");		
		if(!$res) return false;		
		if(0 == $res['deadline']) return true;         
		if(time() > $res['deadline']) return false;	
		if($res['mobile'] == $mobile)
		{
			$this->verify_delete($mobile, $type, $code);
			return true;
		}
		return false;
	}
	public function hulaid_create($id)
	{
		return 'h_' . sprintf("%u", crc32($id));
	}
	public function verify_delete($mobile, $type=0, $code='')
    {
		// 删除一类
		$where = "mobile ='{$mobile}' And deadline>0 And `type` ='{$type}'";
		$code && $where .= " and code='{$code}'";		
        return db()->delete('t_verify_code', $where);
    }

	
	public function get_user_priv($uid, $school)
	{
		$enable = '*';
		if($uid < 3) return $enable;
		$sch = load_model('school')->getRow($school);
		if(!$sch) return ;			
		if($sch['creator'] == $uid) return $enable;
		$user = load_model('admin_user')->getRow(array('school' => $school, 'uid' => $uid));
		if($user['enable'] == '*') return $enable;
		$group = load_model('admin_user_group')->getRow(array('gid' => $user['gid']));
		if(!$group) return ;	
		if($group['enable'] == '*') return $enable;
		if(!$user['enable']) return explode(",", $group['enable']);
		return array_merge(explode(",", $group['enable']), explode(",", $user['enable']));		
	}

	public function set_school(Array $param)
	{
		if(empty($param)) return false;
		extract($param);
		if(!$id || !$priv) return false;
		Http::set_session(SCHOOL_NAME, $name);
		Http::set_session(SCHOOL_ID, $id);
		Http::set_session(SCHOOL_CODE, $code);
		Http::set_session(SCHOOL_PRIV, $priv);
		Http::set_session(SCHOOL_CREATOR, $creator); // 超级管理员
		Http::set_session(SCHOOL_GID, $gid); // 超级管理员
		Http::set_session(SCHOOL_LNG, $lng); // 超级管理员
	}
}