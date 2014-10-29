<?php
Abstract class School Extends Module
{
	
	public $app = '';
	public $act = '';
	
	public $uid = '';	
	public $user = '';
    
    public $__USER__ = '';
    public $__SCHOOL__ = '';
	
	public $school = '';
	public $school_code = '';
	public $school_name = '';
	public $school_priv = '';
	
	public $smarty = Null;

	public $module = "";

	public function __construct(){		
		$this->_initialize();
	}

	public function _initialize()
	{	
		if(Hooks::privValid() === false)
		{			
			switch(Hooks::$error)
			{
				case '404':
					$this->show_error('非法访问！', '/', 404, 0, 0);
					break;
				case 'user':
					$this->show_error('未登录', '/login', 0, 1, 3);
					break;
				case 'school':
					if($this->uid > 2) 
					{
						$url = '/school/change';
					}else{
						$url = '/school';
					}
					$this->show_error('', $url);
					break;
				case 'priv':
					// $this->show_error('无权限', '/school', 0, 1, 3);
					$this->jump('/', 1);
					break;
			}
		}
		$this->_user_init();		
		$this->_school_init();
	}
	
	protected function _user_init()
	{		
		if(!$this->uid)
		{
			$this->uid = Http::get_session(SESS_UID);
			$this->__USER__ = array(
				'id' => Http::get_session(SESS_UID),
				'account' => Http::get_session(SESS_ACCOUNT),
				'name' => Http::get_session(SESS_NAME),
				'hulaid' => Http::get_session(SESS_HULAID),
                'avatar' => imageUrl($this->uid, 1, 200)               
			);
            $this->assign('__USER__', $this->__USER__);
		}		
	}

	protected function _school_init()
	{
		if(!$this->school)
		{
			$this->school = Http::get_session(SCHOOL_ID);
            $this->__SCHOOL__ = array(
                'id' => $this->school,
				'code' => Http::get_session(SCHOOL_CODE),
				'name' => Http::get_session(SCHOOL_NAME),
                'priv' => Http::get_session(SCHOOL_PRIV),
				'creator' => Http::get_session(SCHOOL_CREATOR),
                'gid' => Http::get_session(SCHOOL_GID),
                'avatar' => imageUrl($this->school, 3, 200)
            );
			/*
			if(!Http::get_session(SCHOOL_LNG) && $location = $this->getLocation())
			{
				//list($lng, $lat) = $location;				
				//load_model('school')->update(compact('lng', 'lat'), $this->school);
				Http::set_session(SCHOOL_LNG, true);
			}
			*/
            $this->assign('__SCHOOL__', $this->__SCHOOL__);
		}		
	}

	protected function int_search(){
		$order = ''; $page = 0; $where = array();
		$page = Http::get('page', 'int', 1);
		$order = Http::get('order');
		$where = Http::get();       
		unset($where['order'], $where['page']);			
		return compact('where', 'order', 'page');
	}
	
	protected function getCourses()
	{
		$_Course = load_model('course');
		$resource = $_Course->getAll(array('school' => $this->school), '', '`sort` Asc');		
		$result = Array();		
		foreach($resource as $key=>$item)
		{
			$result[$item['id']] = $item['title'];
		}
		return $result;
	}

	protected function getTeachers()
	{
		$result = array();
		$res = load_model('school_teacher')->getTeacher('r.`school`=' . $this->school .' And r.`status`=0 And u.firstname!=""', array('u.firstname' => 0), '', 'u.id,u.firstname,u.lastname');		
		if($res)
		{
			foreach($res as $key=>$item){
				$item['id'] && $result[$item['id']] = $item['firstname'] . $item['lastname'];
			}
		}		
		return $result;
	}

	protected function startDate()
	{
		$w = date('N');
		return date("Y-m-d", mktime(0,0,0,date("m"), date("d") - date('N') + 1,date("Y")));
	}

	protected function endDate()
	{
		$w = date('w');
		return date("Y-m-d", mktime(0,0,0,date("m"), date("d") - $w + 7 ,date("Y")));
	}

	// 获取月第一天
	protected function monthStart($m=0, $y=0)
	{		
		$m || $m=date('m');
		$y || $y=date('Y');
		return date("Y-m-d", mktime(0,0,0,$m, 1, $y));
	}
	// 获取月最后一天
	protected function monthEnd($m=0, $y=0)
	{		
		$m || $m=date('m');
		$y || $y=date('Y');
		return date("Y-m-d", mktime(0,0,0, $m+1, 0, $y));
	}
	
	/*
	// 获取周第一天
	public function weekStart($w = 0, $y=0)
	{
		$w || $m=date('W'); // 一年中的第几周
		$y || $y=date('Y');
		$yearStart = mktime(0,0,0,1, 1,date("Y"));		
		return date("Y-m-d", mktime(0,0,0,date("m"), date("d") - $w + 1,date("Y")));
	}
	// 获取周最后一天
	public function weekEnd($m=date('m'), $y=date('Y'))
	{		
		return date("Y-m-d", mktime(0,0,0, $m+1, -1, $y));
	}
	*/

	protected function getColors()
	{
		return array("#3cbffd","#6086ff","#f96a20","#666666","#8f367d", "#ffcc2e", "#f0a1bb" , "#c4b9e3", "#86dcc6", "#c1df1a");
	}

	protected function repeatTypes()
	{
		return array('一次性课程', '每天', '每周', '每两周');
	}

	protected function getWeeks()
	{
		return array(1=> '周一', 2=> '周二', 3 => '周三', 4=> '周四', 5=> '周五', 6=> '周六', 0 => '周日');
	}

	protected function getSchoolTypes()
	{
		return array('机构类型', '私教', '品牌加盟', '品牌直营');
	}
	
	// 空获取全部 0 获取所有一级
	protected function getCourseTypes($pid = '')
	{		
		$cache_key = 'course_type' . ($pid !== '' ? $pid : '');		
		$result = cache()->get($cache_key);
		if($result === false)
		{
			$where = Array();
			if($pid !== '') $where['pid'] = $pid; 
			$res = load_model('course_type')->getAll($where, '', 'hot Desc');
			$result = Array();
			foreach($res as $key => $item)
			{
				$result[$item['id']] = $item['name'];
			}
			cache()->set($cache_key, $result, 3600);
		}
		return $result;
	}

	protected function convert($rule)
	{
		if(is_array($rule)&&count($rule) > 0)
		{
			$time = '';
			for($i=0 ;$i<count($rule) ;$i++)
			{
				$startHour = floor($rule[$i]['start']/60);
				$startMin =	$rule[$i]['start']%60 ;

				$endHour = floor($rule[$i]['end']/60);
				$endMin =	  $rule[$i]['end']%60; 
			
				$week = $this-> getWeek($rule[$i]['week']);

				if($startMin < 10) $startMin = '0'.$startMin;
				if($endMin < 10) $endMin = '0'.$endMin;
				if($startHour < 10) $startHour = '0'.$startHour;
				if($endHour < 10) $endHour = '0'.$endHour;

				if($i == 0)
					$time =  $week.' '.$startHour.':'.$startMin.'-'.$endHour.':'.$endMin;
				else
					$time =  $time.' ; '.$week.' '.$startHour.':'.$startMin.'-'.$endHour.':'.$endMin;
			}
			return $time;
		}
		return false;
	}

	protected function getWeek($week){
		if($week > 6)	return false;
		$weeks = array(1=> '周一', 2=> '周二', 3 => '周三', 4=> '周四', 5=> '周五', 6=> '周六', 0 => '周日');
		return $weeks[$week];
	}

	protected function getRelations()
	{
		return array(1 => '本人', 2 => '爸爸', 3 => '妈妈', 4 => '家长');
	}
    
    protected function getRemarkTypes()
	{
		return array('请选择类型', '试听课', '家长回访', '其他');
	}

	protected function getLeadSource()
	{
		return array('来源', '销售地推', '市场活动', '他人介绍', '呼啦派', '其他');
	}	
	protected function getLeadStatus()
	{
		return array('状态', '及时跟进', '定期联系', '暂无意向', '即将签约');
	}

	protected function getTargets()
	{
		return array('儿童', '成人');
	}
	protected function getForms()
	{
		return array('一对一', '精品小班');
	}

	// 消息
	public function show_message($message, $status=0, $handle = array(), $top = 'blank', $parent='')
	{		
		$this->assign('status', $status);
		$this->assign('message', $message);
		$this->assign('handle', $handle);		
		if($top == 'open')
		{
			$this->assign('top', 1);
			$this->assign('parent', $parent);
			$this->display('message/open');
		}
		$this->display('message/index');
	}
	/*
	 * @message 消息内容
	 * @handle 事件方法 back goon -1/-2
	 * @code 错误代码 1成功 200,404,500
	 * @target 目标框架
	 * @停留时间 0不自动跳转
	*/
	public function show_error($message='', $handle='', $code=0, $target=0, $time=0)
	{
		if($message == '')
		{
			$this->jump($handle, $target);
		}
		if(is_string($handle))
		{
			$handle = array(
				'default' => array('url' => $handle),
			);
		}else if(is_numeric($handle)){
			$handle = array(
				'default' => array('url' => $handle)
			);
		}
		$result = compact('message', 'handle', 'code', 'time', 'target');
		if($target) // 外层跳转
		{
			Http::set_session('message', $result);
			die("<script type=\"text/javascript\">window.top.location.href='/message';</script>");
		}else{
			$this->assign('result', $result);
			$this->display("message/" . $this->_error_tpl($code));
		}
	}

	protected function _error_tpl($code = 0)
	{
		if($code == 404)
		{
			return 'Error404';
		}else if($code == 5)
		{
			return 'Error500';
		}else{
			return 'message';
		}
	}

	protected function jump($url, $target = 0) // 无信息提示的跳转
	{
		if($target == 0)
		{
			Header('Location:' . $url);
			exit;
		}else{
			die("<script type=\"text/javascript\">window.top.location.href='{$url}';</script>");
		}		
	}
    
    protected function _refer()
    {
        if(isset($_SERVER['HTTP_REFERER']))
        {   
            $refer = $_SERVER['HTTP_REFERER'];
            $history = Http::get_session('query');
            if(empty($history) || $history['refer'] != $refer)
            {           
                $param = parse_url($refer);
                $path = str_replace('/event', '');
                if(!$path || $path='/schdule')
                {
                    Http::set_session('query', array(
                        'refer'=> $refer,
                        'path' => '/event',
                        'query'=> $param['query']
                    ));
                }
            }
        }
    }

	protected function getLocation()
	{
		$school = load_model('school')->getRow($this->school);		
		if(empty($school) || intVal($school['lng'])) return ;		
		load('map');
		if($school)
		{	
			if(in_array($school['province'], array('110000', '310000', '120000', '500000', '710000', '810000', '820000')))
			{
				$province = load_model('area')->getRow($school['province']);
				$city = $province['title'];
			}else{
				$city = load_model('area')->getRow($school['city']);
				$city = $city['title'];
			}
			if(preg_match("/[\x7f-\xff]/", $school['address'])) {  //判断字符串中是否有中文					
				return Map::getCoordsFromAddress($province['title'], $school['address']);
			}
		}
		return Map::getCoordsFromIp(Http::ip());
	}
}