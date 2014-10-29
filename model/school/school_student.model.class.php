<?php
/*
 * 机构老师
*/

class School_student_Model Extends Model
{
	protected $_table = 't_school_student';

	protected $_key = 'id';	
	protected $_cache_key = 'school_student';
	protected $_timelife = '3600';

	public function __construct(){
		parent::__construct();
	}
	
	public function getStudent($where='', $order=array(), $limit="", $fields="*", $cache=false)
	{
		$sql = "select {$fields} from " . $this->_table . " As r";
		$sql.= " Left join t_student s On r.student=s.`id`";				
		$where && $sql .= " Where " . $where;
		$order = $this->getOrder($order);
		$order && $sql.= " Order by " . $order;
		$limit = $this->getLimit($limit);
		$limit && $sql .= " Limit " . $limit;
		$res = db()->fetchAll($sql);
		return $res;
	}

	public function getStudentCount($where='', $fields='count(*)')
	{
		$sql = "select {$fields} from " . $this->_table . " As r";
		$sql.= " Left join t_student s On r.student=s.`id`";			
		$where && $sql .= " Where " . $where;		
		return db()->fetchOne($sql);		
	}

	public function getRemain($where='', $order=array(), $limit="", $fields="*", $cache=false)
	{
		$sql = "select {$fields} from " . $this->_table . " As r";
		$sql.= " INNER JOIN t_student s ON  s.id = r.student LEFT JOIN t_schedule_assign sa ON sa.assign=s.id LEFT JOIN t_schedule c ON sa.schedule=c.id where sa.status=0 AND sa.type=0";		
		$where && $sql .= " AND " . $where;
		$order = $this->getOrder($order);
		$order && $sql.= " Order by " . $order;
		$limit = $this->getLimit($limit);
		$limit && $sql .= " Limit " . $limit;
		$res = db()->fetchAll($sql);
		return $res;
	}

	public function getRemainCount($where='', $fields='count(*)')
	{
		$sql = "select {$fields} from ". $this->_table ." As r";
		$sql.= " INNER JOIN t_student s ON s.id = r.student LEFT JOIN t_schedule_assign sa  ON sa.assign=s.id LEFT JOIN t_schedule c  ON sa.schedule=c.id where sa.status=0 AND sa.type=0";			
		$where && $sql .= " AND " . $where;	
		return db()->fetchOne($sql);
	}

	public function sorts(array $data, $selected = array())
	{
		if(empty($data)) return false;
		$result = $source = Array();
		foreach($data as $key => &$item)
		{
			$index = strtoupper(substr($item['en'], 0, 1));
			in_array($index, range('A', 'Z')) || $index = 'else';
			$source[$index][] = $item;
		}		
		$start = $end = 65;
		$_tmp = Array();		
		for($i=65;$i<91;$i++)
		{			
			$_index = chr($i);			
			$_data = isset($source[$_index]) ? $source[$_index] : Array();	
			if(empty($_data))
			{
				// if(empty($_tmp)) {$start = $i; $end = $start;}
				$end++;  
				// continue;
			}else if(count($_data) > 10)
			{
				if(!empty($_tmp))
				{
					$result[chr($start) . "-" . chr($end)] = $_tmp;
					$_tmp = Array();				
				}
				$result[$_index] = $_data;
				$start = $i;
				$start++;
				$end = $start;							
			}else{				
				if((count($_tmp) + count($_data)) > 10)
				{
					$result[chr($start) . "-" . chr($end)] = $_tmp;
					// $_tmp = Array();
					$_tmp = $_data;
					$start = $end = $i;
				}else{					
					$_tmp = array_merge($_tmp, $_data);
					$end = $i;
				}				
			}
			unset($source[$_index]);
		}
		if($_tmp)
		{
			$result[chr($start) . "-" . chr($end)] = $_tmp;
		}	
		empty($source['else']) || $result['其他'] = $source['else'];	
		return $result;
	}
	


	public function import($data, $school)
	{
		$result = array('count' => 0, 'users' => 0, 'students' => 0, 'error' => array());
		if(empty($data) || !$school) return $result;
		$_User = load_model('user');
		$_Student = load_model('student');
        $_tmp = array();
		for($i=2; $i< count($data); $i++)
		{			
			$item = Array();			
			array_walk($data[$i], create_function('&$v,$k', '$v=trim($v);'));				
			$item = array_pad($item, 8, "");
			$item[0] = $data[$i][0];
			$item[1] = $data[$i][1];
			$item[2] = $data[$i][2];
			$item[3] = $data[$i][3];
			$item[4] = $data[$i][4];						
			$item[6] = $data[$i][5] ? date('Y-m-d', strtotime($data[$i][5])) : '';
			$item[7] = $data[$i][6];
			if($item[1] == '') {$result['error'][$i] = "学生姓名不能为空"; continue;}
			if($item[2] == '' && $item[3] == '' && $item[4] == '') {$result['error'][$i] = "联系方式不能为空"; continue;}			
            if(in_array(join($item), $_tmp)){$result['error'][$i] = "重复数据"; continue;}
            $_tmp[] = join($item);
			$item = $this->importFormat($item);           
			extract($item);
			foreach($parents as &$parent)
			{				
				$user = $_User->getRow(array('account' => $parent));
				if(!$user)
				{
					$user = $_User->create($parent, '', 0);
					$result['users']++;
				}
				$parent = $user;
			}
			$parentArr = array_values($parents);           
            array_walk($parentArr, create_function('&$v', '$v=$v[\'id\'];'));
			reset($parentArr);
			$student = $_User->hasStudent($name, $parentArr); // 是否已经有此学生档案
			if(!$student)
			{
				$student = $_Student->create(compact('name', 'birthday', 'gender')); // 没有则创建档案	
				$creatorParent = current($parentArr);
				if(!$creatorParent)
				{
					$result['error'][$i] = "学生家长不存在！"; 
					continue;
				}
				$_Student->update(array('creator' => $creatorParent), $student);
				$result['students']++;
			}
			if($student)
			{
				$usRelation = 0; // 学生家长关系
				foreach($parentArr as $key => $val)
				{
					if(!in_array($key, array(1,2,3,4))) $key = 4;
					$res = load_model('user_student')->createRelation($val, $student, $key); // 创建家长关系
					if($res) $usRelation++;
				}
				if($usRelation < 1)
				{
					$result['error'][$i] = "学生家长关联失败"; 
					continue;
				}
				$res = $this->getRow(array('school' => $school, 'student' => $student));
				if(!$res)
				{
					$result['count'] ++; 
					$sid = $this->insert(array('school' => $school, 'student' => $student, 'create_time' => TM, 'source' => 2));
                    if(!$sid) {$result['error'][$i] = "学生档案生成失败";continue;}
                    if(!$no) $no = $sid;
                    $this->update(array('no' => $no), $sid);
				}else{
					$result['error'][$i] = '已存在';
				}			
			}else{
				$result['error'][$i] = "学生档案生成失败"; 
				continue;
			}			
		}
		return $result;
	}


	// 导入数据格式化
	private function importFormat($item = Array())
	{
		$data = array(
			'no' => '', // 学号
			'name' => '', // 姓名
			'parents' => array(
				// 1 => '', // 本人
				2 => '', // 爸爸
				3 => '', // 妈妈
				4 => '' // 其他
			),
			'birthday' => '0000-00-00', // 生日
			'gender' => 0 // 性别
		);		
		extract($data);
		$no = $item[0];
		$name = $item[1];		
		if(strlen($item[2]) == 11 && is_numeric($item[2])) $parents[3] = $item[2]; // 妈妈
		if(strlen($item[3]) == 11 && is_numeric($item[3])) $parents[2] = $item[3]; // 爸爸
		if(strlen($item[4]) == 11 && is_numeric($item[4])) $parents[4] = $item[4]; // 其他		
		// if(strlen($item[5]) == 11 && is_numeric($item[5])) $parents[1] = $item[5]; // 本人			
		$parents = array_filter($parents);		
		empty($item[6]) || $birthday = $item[6];
		$gender = str_replace(array('M', 'F', '男', '女'), array(1,2,1,2), $item[7]);	
		return compact('no', 'name', 'parents', 'birthday', 'gender');			
	}
	
	// 统计
	public function getSumResult($school, $where=array(), $order=array(), $limit='')
	{	
		$cache_key = 'school_student_stat_result_' . $this->get_cache_key($school, $where, $order, $limit);
		$result = cache()->get($cache_key);		
		if($result === false)
		{
			$sql = $this->getSumSql($school, $where, $order, $limit);
			$result = db()->fetchAll($sql);
			cache()->set($cache_key, $result, 1800);
		}
		return $result;
	}
	// 统计
	public function getSumCount($school, $where=array())
	{	
		$cache_key = 'school_student_stat_count_' . $this->get_cache_key($school, $where);	
		$result = cache()->get($cache_key);		
		if($result === false)
		{
			$sql = $this->getSumSql($school, $where);
			$result = db()->fetchAll($sql);
			$result = count($result);
			cache()->set($cache_key, $result, 1800);
		}
		return $result;
	}

	private function getSumSql($school, $where=array(), $order=array(), $limit='')
	{
		if(!$school) return false;		
		$whereStr = $this->getSumWhere($where);
		$sqlStr = "select %s from t_course_student cs left Join t_event e on e.id=cs.`event` where cs.student=r.student And e.school={$school} And e.is_loop=0";
		empty($whereStr) || $sqlStr .= " And ". $whereStr;
		$courseSql = sprintf($sqlStr, "count(cs.id)"); // 总课数据统计		
		$unattendSql = sprintf($sqlStr . " And cs.attended=0", "count(cs.id)"); // 未考勤
		$attendSql = sprintf($sqlStr . " And cs.attend=1", "count(cs.id)"); // 出勤
		$absenceSql = sprintf($sqlStr . " And cs.absence=1", "count(cs.id)"); // 缺勤
		$leaveSql = sprintf($sqlStr . " And cs.leave=1", "count(cs.id)"); // 请假
		$courseTimeSql = sprintf($sqlStr, "sum(e.class_time)"); // 课时数		
		$studentSql = "select name from t_student where id=r.student";
		$result = "select r.*,s.`name`,({$courseSql}) as 'course', ({$unattendSql}) as 'unattend', ({$attendSql}) as 'attend', ({$absenceSql}) as 'absence', ({$leaveSql}) as 'leave', ({$courseTimeSql}) as 'classtime'";
		$result.= " From " . $this->_table . " r left join t_student s on r.student=s.id where r.school={$school}" . ($where['keyword'] ? " And name like '%{$where['keyword']}%'" : "");
		$order = $this->getSumOrder($order);
		$order && $result.= " Order by ". $order;
		$limit && $result.= " Limit " . $limit;
		return $result;
	}

	private function getSumOrder($order){
		$result = Array();	
		//$orderKey = current(array_keys($order));
		if(empty($order)) $order['name'] = 0;
		foreach($order as $key => $item)
		{
			if(!$key) $key = 'no';
			if($item != 1) $item = 0;
			$field = '';
			switch($key)
			{
				case 'name':
                    $field = 's.`'. $key . '`';
                    break;
                case 'no':
                    $field = 'r.`'. $key . '`';
                    break;
				case 'course' :	
				case 'unattend':				
				case 'attend':
				case 'absence':
				case 'leave':
                    $field = '`'. $key . '`';
                    break;
				case 'classtime': // 学生人数	
					$field = $key;
					break;
				default : 
					$field = 'r.`no`';
					// break;
			}			
			$field && $result[] = $field  . ($item ? ' Desc' : ' Asc');
		}		
		return join(",", $result);
	}

	private function getSumWhere($param=array()){
		$result = Array();		
		if(!empty($param['text']))
		{
			$result['text'] = "r.`name` like '%{$param['event']}%'";
		}		
		if(!empty($param['start']) && !empty($param['end']))
		{
			$result[] = sprintf("e.start_date>'%s' And e.end_date<'%s'", $param['start'] . " 00:00:00", $param['end'] . " 23:59:59");
		}else if(!empty($param['start'])){
			$result[] = sprintf("e.start_date>'%s'", $param['start'] . " 00:00:00");
		}else if(!empty($param['end'])){
			$result[] = sprintf("e.end_date<'%s'", $param['end'] . " 23:59:59");
		}			
		return join(" And ", $result);
	}
}