<?php
/*
 * 课程模型
*/
class School_student_Model_New Extends Model_New
{
	
	protected $_db = NULL;
	protected $_table = 'school_student';
	protected $_key = 'id';

	public function __Construct()
	{
		parent::__construct();
	}	

	

	public function get_list($param, $export=false)
	{
		$sql = $this->_query($param, false, $export);
		$res =  db()->fetchAll($sql);
		return $res;
	}

	// 学生查询
	private function _query($param, $count=false, $export=false){
		// From Model
		extract($param);
		extract($where);

		if(empty($school)) return false;		
		$countField = 'count(s.id)';
		$field = "s.id,s.name,r.no,r.create_time";
		$sql = "select {field} from t_school_student r";
		$sql.= " Left Join t_student s on s.id=r.student";
		isset($close) || $close = 0;
		$sql.= " where r.school={$school} And r.`status`={$close}";
		isset($keyword) && $sql.= " And s.`name` like '%{$keyword}%'";
		if($count)
		{
			return str_replace("{field}", 'count(s.id) n', $sql);
		}else{
			empty($order) && $order['time'] = 1;
			$sql .= " Order by " . $this->_order($order);
			if(!$export)
			{
				empty($page) && $page = 1;
				empty($perpage) && $perpage = 20;
				$sql .= " Limit " . $this->_limit($page, $perpage);
			}		
			return str_replace("{field}", $field, $sql);
		}
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
				$order_key = 's.name_en';
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

		$source = $this->get_list($param, true);
		extract($param);	
		extract($where);
		if(empty($school)) return false;	
		$result = Array();
		foreach($source as $item)
		{
			$grade = $this->get_student_grade($item['id'], $school);

			$concat = $this->get_student_concat($item['id']);

			$item['concat'] = '';
			foreach($concat as $val)
			{
				$item['concat'] && $item['concat'] .= " ";
				$item['concat'] .= $val['account'];
			}
			$result[] = array(
				'no' => $item['no'],
				'name' => $item['name'],
				'grade'=> $grade,
				'concat' => $item['concat'],
				'create_time' => date('Y-m-d', $item['create_time'])
			);			
		}
		$title = Array(
			'no' => '学号',
			'name' => '姓名',
			'grade'=> '班级',
			'concat' => '联系方式',
			'create_time' => '加入时间'
		);		
		excelExport('student', array_values($title), $result);
	}
	

	
	static $_grades = Array();
	static $_students = Array();
	// 获取学生班级	
	public function get_grade_form_key($school)
	{		
		if(!empty(self::$_grades)) return self::$_grades;
		if(!$school) return false;
		$source = load_model('grade', Null, true)->where('school', $school)->field('id,name')->Result();		
		foreach($source as $v)
		{
			self::$_grades[$v['id']] = $v['name'];
		}		
		return self::$_grades;
	}

	// 学生所在班级
	public function student_grade($school)
	{
		if(!empty(self::$_students)) return self::$_students;
		$result = Array();
		$dataSource = load_model('grade_student', Null, true)->where('school', $school)->field('student,grade')->Result();		
		$grades = $this->get_grade_form_key($school);
		
		foreach($dataSource as $v)
		{
			isset(self::$_students[$v['student']]) || self::$_students[$v['student']] = Array();
			$id = $v['grade'];
			if(isset($grades[$id]))
			{
				$name=$grades[$id];				
				self::$_students[$v['student']][] = compact('id', 'name');
			}			
		}
		return self::$_students;
	}

	public function get_student_grade($student, $school)
	{
		$grades = $this->get_grade_form_key($school);		
		$students = $this->student_grade($school);
		$result = '';
		if(empty($students[$student])) return '';
		foreach($students[$student] As $item)
		{
			$result && $result .= " ";
			$result .= $item['name'];
		}
		return $result;
	}
	
	/*
	 * 学生联系方式
	*/
	public function get_student_concat($student)
	{
		$cache_key = 'student_concat_' . $student;
		$result = cache()->get($cache_key);
		
		if($result === false)
		{
			$sql = "select account,relation from t_user_student r left join t_user u on u.id=r.`user` where r.student={$student}";
			$result = db()->fetchAll($sql);
			cache()->set($cache_key, $result, 3600);
		}
		return $result;
	}

	/*
		学生剩余课程查询
	*/
	private function remain_sql($param, $count=false, $export=false)
	{
		extract($param);
		extract($where);
		if(empty($school)) return false;		
		$countField = 'count(s.id)';
		$field = "s.id,s.name,s.phone,c.title,r.create_time,sa.start_date,sa.end_date,sa.times,sa.remain";
		$sql = "select {field} from t_school_student r";
		$sql.= " LEFT JOIN t_student s ON  s.id = r.student LEFT JOIN t_assign sa ON sa.assigner=s.id LEFT JOIN t_series c ON sa.sid=c.id";
		isset($close) || $close = 0;
		$sql.= " where r.school={$school} And r.`status`={$close} AND sa.status=0 AND sa.type=0";
		(isset($keyword)&& !empty($keyword)) && $sql.= " And s.`name` like '%{$keyword}%'";
		if(!empty($remain) || $remain === '0') $sql.= " And sa.remain <= {$remain}";
		if($count)
		{
			return str_replace("{field}", 'count(*) n', $sql);
		}else{
			empty($order) && $order['time'] = 1;
			switch(key($order))
			{
				case 'time':
					$order_key = 'r.create_time';
					break;
				case 'title':
					$order_key = 'c.title';
					break;
				case 'remain':
					$order_key = 'sa.remain';
					break;
				default:
					$order_key = 'r.create_time';
					break;
			}
			$order_value = current($order) ? 'ASC' : 'DESC';
			$sql .= " Order by " . "{$order_key} {$order_value}";
			if(!$export)
			{
				empty($page) && $page = 1;
				empty($perpage) && $perpage = 20;
				$sql .= " Limit " . $this->_limit($page, $perpage);
			}		
		}
		return str_replace("{field}", $field, $sql);	
	}

	public function get_student_remain($param)
	{
		$sql = $this->remain_sql($param);
		return db()->fetchAll($sql);
	}

	public function get_remain_count($param)
	{
		$sql = $this->remain_sql($param, true);
		return db()->fetchOne($sql);
	}

	public function rmExport($param)
	{
		$sql = $this->remain_sql($param,false,true);
		$source = db()->fetchAll($sql);
		$title = Array(
			'name'=>'学生名',
			'title' => '课程名称',	
			'start_date' => '开始时间',	
			'end_date' => '结束时间',	
			'times' => '总课次',
			'remain' => '剩余课次'
		);
		foreach($source as $key => &$item)
		{
			$result[] = Array(
				'name' => $item['name'],
				'title' => $item['title'],	
				'start_date' => $item['start_date'],	
				'end_date' => $item['end_date'],	
				'times' => $item['times'],
				'remain' => $item['remain']
			);			
		}	
		excelExport('剩余课程导出', array_values($title), $result);
		Header('Location:' . Http::curl());
	}
}



