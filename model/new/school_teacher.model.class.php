<?php
/*
 * 老师
*/
class School_teacher_Model_New Extends Model_New
{
	
	protected $_db = NULL;
	protected $_table = 'school_teacher';
	protected $_key = 'id';

	public function __Construct()
	{
		parent::__construct();
	}	
	
	public function get_list($param, $export=false)
	{
		$sql = $this->_query($param, false, $export);
		$res = db()->fetchAll($sql);
		return $res;
	}

	// 老师查询
	private function _query($param, $count=false, $export=false){
		// From Model
		extract($param);
		extract($where);
		if(empty($school)) return false;		
		$countField = 'count(u.id)';
		$field = "u.id,concat(u.firstname,u.lastname) as 'name',u.account,r.create_time,r.recomm";
		$sql = "select {field} from t_school_teacher r";
		$sql.= " Left Join t_user u on u.id=r.teacher";
		$sql.= " Left Join t_teacher t on t.user=r.teacher";
		isset($close) || $close = 0;
		$sql.= " where r.school={$school} And r.`status`={$close}";
		$keyword && $sql.= " And u.`name` like '%{$keyword}%'";	
		if($count)
		{
			return str_replace("{field}", 'count(u.id) n', $sql);
		}else{
			empty($order) && $order['time'] = 1;
			$sql .= " Order by " . $this->_order($order);
			if(!$export)
			{
				empty($page) && $page = 1;
				empty($perpage) && $perpage = 20;
				$sql .= " Limit " . $this->_limit($page, $perpage);
			}
			$sql = str_replace("{field}", 'u.id,u.name,t.create_time,u.account', $sql);
		}
		return $sql;
	}
	
	public function get_count($param)
	{
		$sql = $this->_query($param, true);
		return db()->fetchOne($sql);
	}

	private function _limit($page, $perpage)
	{
		empty($page) && $page = 1;
		empty($perpage) && $perpage = 20;
		$start = ($page - 1) * $perpage;
		return "{$start},$perpage";
	}
	
	private function _order(Array $order)
	{
		empty($order) && $order['time'] = 1;
		switch(key($order))
		{
			case 'time':
				$order_key = 'r.create_time';
				break;
			case 'name':
				$order_key = 'u.firstname_en';
				break;
			default:
				$order_key = 'r.create_time';
				break;
		}
		$order_value = current($order) ? 'ASC' : 'DESC';
		return "{$order_key} {$order_value}";
		
	}
	// 导出
	public function export($param)
	{		
		extract($param);
		extract($where);
		$source = $this->get_list($param, true);

		if(empty($school)) return false;
		$result = Array();
		foreach($source as $item)
		{
			$group = $this->get_teacher_group($item['id'], $school);			
			$result[] = array(
				'name' => $item['name'],
				'group'=> $group,
				'account' => $item['account'],
				'create_time' => date('Y-m-d', $item['create_time'])
			);		
		}
		$title = Array(
			'name' => '姓名',
			'group'=> '分组',
			'account' => '联系方式',
			'create_time' => '加入时间'
		);	
		excelExport('teacher', array_values($title), $result);
	}
	

	
	static $_groups = Array();
	static $_teachers = Array();
	// 教师组	
	public function get_group_form_key($school)
	{
		if(!empty(self::$_groups)) return self::$_groups;
		if(!$school) return false;
		$source = load_model('school_group', Null, true)->where('school', $school)->field('id,name')->Result();
		foreach($source as $v)
		{
			self::$_groups[$v['id']] = $v['name'];
		}
		return self::$_groups;
	}

	// 教师所在组
	public function teacher_group($school)
	{
		if(!empty(self::$_teachers)) return self::$_teachers;
		$result = Array();
		$dataSource = load_model('school_group_teacher', Null, true)->where('school', $school)->field('teacher,group')->Result();
		$groups = $this->get_group_form_key($school);
		foreach($dataSource as $v)
		{
			isset(self::$_teachers[$v['teacher']]) || self::$_teachers[$v['teacher']] = Array();
			$id = $v['group'];
			if(isset($groups[$id]) && $name=$groups[$id])
			{
				self::$_teachers[$v['teacher']][] = compact('id', 'name');
			}			
		}
		
		return self::$_teachers;
	}	
	
	public function get_teacher_group($teacher, $school)
	{
		$groups = $this->get_group_form_key($school);
		$teachers = $this->teacher_group($school);
		$result = '';
		if(empty($teachers[$teacher])) return '';
		foreach($teachers[$teacher] As $item)
		{
			$result && $result .= " ";
			$result .= $item['name'];
		}
		return $result;
	}
}