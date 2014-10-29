<?php
/*
 * 机构老师
*/

class School_teacher_Model Extends Model
{
	protected $_table = 't_school_teacher';

	protected $_key = 'id';	
	protected $_cache_key = 'school_teacher';
	protected $_timelife = '3600';

	public function __construct(){
		parent::__construct();
	}
	
	public function getTeacher($where='', $order=array(), $limit="", $fields="*", $cache=false)
	{       
		$sql = "select {$fields} from " . $this->_table . " As r";
		$sql.= " Left join t_teacher t On r.teacher=t.`user`";
		$sql.= " Left join t_user u On r.teacher=u.`id`";		
		$where && $sql .= " Where " . $where;
		$order = $this->getOrder($order);
		$order && $sql.= " Order by " . $order;
		$limit = $this->getLimit($limit);
		$limit && $sql .= " Limit " . $limit;
      
		$res = db()->fetchAll($sql);
		return $res;
	}

	public function getTeacherCount($where='', $fields='count(*)')
	{
		$sql = "select {$fields} from " . $this->_table . " As r";
		$sql.= " Left join t_teacher t On r.teacher=t.`user`";
		$sql.= " Left join t_user u On r.teacher=u.`id`";		
		$where && $sql .= " Where u.id>0 And " . $where;
		return db()->fetchOne($sql);		
	}
	
	/*
	  $data['en']
	*/
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
		$result = array('count' => 0, 'users' => 0, 'teachers' => 0, 'error' => array());     
		if(empty($data) || !$school) return $result;
		$_User = load_model('user');
		$_Teacher = load_model('teacher');
        $_tmp = array();
		for($i=2; $i< count($data); $i++)
		{
			$item = $data[$i];
			if(empty($item[0])){$result['error'][$i] = '姓名不能为空!'; continue;}
			if(empty($item[1])){$result['error'][$i] = '联系方式不能为空!'; continue;}
			if(strlen($item[1]) != 11 || !is_numeric($item[1])){$result['error'][$i] = '联系方式必须为手机!'; continue;}
            if(in_array($item[1], $_tmp)){$result['error'][$i] = '重复数据!'; continue;}
            $_tmp[] = $item[1];
			$item[2] = str_replace(array('男', '女', 'M', 'F'), array(1,2,1,2), $item[2]);
			$gender = $item[2] ? $item[2] : 0;
			$user = $_User->getRow(array('account' => $item[1]));
			if(!$user)
			{
				$user = $_User->create($item[1], $item[0], $gender);
                if(empty($user)){$result['error'][$i] = '用户生成失败!'; continue;} 
				$result['users']++;
			}           
			$teacher = $_Teacher->getRow(array('user' => $user['id']));            
			if(!$teacher)
			{
				$teacher = $_Teacher->insert(array('user' => $user['id'], 'create_time' => TM, 'source' => 2));
                if(empty($teacher)){$result['error'][$i] = '老师档案生成失败！'; continue;}
				$result['teachers']++;
			}
			$relation = $this->getRow(array('school' => $school, 'teacher' => $user['id']));
			if($relation)
			{
				$result['error'][$i] = "已存在";
				continue;
			}
			$result['count'] ++;
			$this->insert(array('school' => $school, 'teacher' => $user['id'], 'create_time' => TM, 'source' => 2));
		}
		return $result;
	}

	// 统计
	public function getSumResult($school, $where=array(), $order=array(), $limit='', $group='')
	{	
		$cache_key = 'school_teacher_stat_result_' . $this->get_cache_key($school, $where, $order, $limit, $group);
		$result = cache()->get($cache_key);		
		if($result === false)
		{
			$sql = $this->getSumSql($school, $where, $order, $limit, $group);
			$result = db()->fetchAll($sql);
			cache()->set($cache_key, $result, 1800);
		}
		return $result;
	}
	// 统计
	public function getSumCount($school, $where=array(), $group='')
	{	
		$cache_key = 'school_teacher_stat_count_' . $this->get_cache_key($school, $where, $group);	
		$result = cache()->get($cache_key);
		if($result === false)
		{
			$sql = $this->getSumSql($school, $where, array(), '', $group);
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
		$sqlStr = "select %s from t_course_teacher cs left Join t_event e on e.id=cs.`event` where cs.teacher=r.teacher And e.school={$school}";
		empty($whereStr) || $sqlStr .= " And ". $whereStr;
		$courseSql = sprintf($sqlStr, "count(cs.id)"); // 总课次
		$courseTimeSql = sprintf($sqlStr, "sum(e.class_time)"); // 总课时数
		// 学生人次
		$studentSql = "select count(s.student) from t_course_student s inner join t_course_teacher cs on cs.`event`=s.`event`";
		$studentSql.= " Left join t_event e on s.`event`=e.id";
		$studentSql.= " Where s.student>0 And cs.teacher=r.teacher And s.attend=1 And e.school={$school}";
		empty($whereStr) || $studentSql .= " And ". $whereStr;
		$studentSql.= " Group by cs.teacher";
		$teacherSql = "select CONCAT(firstname,lastname) from t_user where id=r.teacher";
		// 点评次数
		$commentSql = "select count(id) from t_comment where teacher=r.teacher And school={$school} And pid=0 And `character`='teacher'";
		empty($where['start']) || $commentSql.= " And create_time>'" . $where['start'] . " 00:00:00'";
		empty($where['end'])  || $commentSql.= " And create_time<'" . $where['end'] . " 23:59:59'";
		// 回复次数
		$replaySql = "select count(id) from t_comment where teacher=r.teacher And school={$school} And pid>0 And `character`='student'";
		empty($where['start']) || $replaySql.= " And create_time>'" . $where['start'] . " 00:00:00'";
		empty($where['end']) || $replaySql.= " And create_time<'" . $where['end'] . " 23:59:59'";

		$result = "select r.*,({$teacherSql}) as 'name',({$courseSql}) as 'course', ({$courseTimeSql}) as 'classtime', ({$studentSql}) as 'student', ({$commentSql}) as 'comment',({$replaySql}) as 'reply'";
		$result.= " From " . $this->_table . " r where r.school={$school} And r.teacher>0" . ($where['teacher'] ? " And r.teacher={$where['teacher']}" : "");
		$order = $this->getSumOrder($order);
		$order && $result.= " Order by ". $order;
		$limit && $result.= " Limit " . $limit;
		return $result;
	}

	private function getSumOrder($order){
		$result = Array();	
		if(empty($order)) $order['name'] = 0;
		foreach($order as $key => $item)
		{
			$field = '';
			switch($key)
			{
				case 'name':					
				case 'course' :	
				case 'student':
				case 'classtime': // 学生人数	
				case 'comment':
				case 'reply':
					$field = $key;
					break;
			}
			$field && $result[] = "`" . $field . "` " . ($item ? 'Desc' : 'Asc');
		}		
		return join(",", $result);
	}

	private function getSumWhere($param=array()){
		$result = Array();		
		if(!empty($param['event']))
		{
			$result['event'] = "`text` like '%{$param['event']}%'";
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