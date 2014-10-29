<?php
/*
 * 课程模型
*/
class Schedule_Model_New Extends Model_New
{
	public function __Construct()
	{
		parent::__Construct();
	}

	protected $_db = NULL;
	protected $_table = 'schedule';
	protected $_key = 'id';
	
	public function get_event_list(Array $param)
	{
		$cache_key = 'teacher_event_' . md5(json_encode($param));
		$result = cache()->get($cache_key);
		empty($cache) || $cache = true; // true 不起cache
		empty($cache) || $cache = true; // true 不起cache
		if($result === false || $cache == true)
		{
			$series = load_model('series', Null, true)->get_series($param);
			import('schedule');
			foreach($series as $key => $item)
			{
				$rules = json_decode($item['rule'], true);
				foreach($rules as &$rule)
				{
					$rule['schedule'] = $item['id'];
					$rule['title'] = $item['title'];
					$rule['rule'] = $item['rule'];
				}
				$result = array_merge($result, Schedule::resolve($rules, $start, $end_date));
				cache()->set($cache_key, $result, 600); // 十分钟缓存
			}
		}
		return $result;	
	}	
	
	/*
	 * 获取老师的课程
	*/
	public function get_teacher_event_list($teacher, Array $param)
	{
		if(!$teacher) return false;		
		extract($param);
		if(empty($start)) return false;
		if(empty($end)) return false;
		// $order course, sort
		// 取所有series
		$param['assigner'] = $teacher;
		$cache_key = 'teacher_event_' . $teacher . md5(json_encode($param));
		$result = cache()->get($cache_key);
		empty($cache) || $cache = true; // true 不起cache
		if($result === false || $cache == true)
		{			
			$series = load_model('series', Null, true)->get_teacher_series($param);
			$result = Array();
			import('schedule');
			foreach($series as $key => $item)
			{
				$rules = json_decode($item['rule'], true);
				foreach($rules as &$rule)
				{
					$rule['schedule'] = $item['id'];
					$rule['title'] = $item['title'];
					$rule['rule'] = $item['rule'];
				}
				$end_date = $item['close'] && $item['end_date'] < $end ? $item['end_date'] : $end;
				$result = array_merge($result, Schedule::resolve($rules, $start, $end_date));
				cache()->set($cache_key, $result, 600); // 十分钟缓存
			}
		}
		return $result;
	}

	/*
	 * 获取老师的课程
	*/
	public function get_student_event_list($student, Array $param)
	{
		if(!$teacher) return false;		
		extract($param);
		if(empty($start)) return false;
		if(empty($end)) return false;
		// $order course, sort
		// 取所有series
		$param['assigner'] = $student;
		$cache_key = 'student_event_' . $student . md5(json_encode($param));
		$result = cache()->get($cache_key);
		empty($cache) || $cache = true; // true 不起cache
		if($result === false || $cache == true)
		{			
			$series = load_model('series', Null, true)->get_student_series($param);
			$result = Array();
			import('schedule');
			foreach($series as $key => $item)
			{
				$rules = json_decode($item['rule'], true);
				foreach($rules as &$rule)
				{
					$rule['schedule'] = $item['id'];
					$rule['title'] = $item['title'];
					$rule['rule'] = $item['rule'];
				}
				$end_date = $item['end_date'] < $end ? $item['end_date'] : $end;
				$result = array_merge($result, Schedule::resolve($rules, $start, $end_date));
				cache()->set($cache_key, $result, 600); // 十分钟缓存
			}
		}
		return $result;
	}
	
	// 单节课下的老师
	public function getTeacher($sid, $index)
	{
		if(!$sid && !$index) return false;
		$date = date('Y-m-d', $index);
		
		$revise = load_model('schedule_record', Null, true)
			->where('sid', $sid, true)->where('index', $index)->where('protype','replace')->where('type',1)
			->field('value')->Row(); // protype = 'replace', value = teacher
		if($revise)
			$teachers = json_decode($revise['value']);
		else
			$teachers = load_model('assign', Null, true)->getTeacher($sid, $date, $date);		
		return $teachers;
	}
	// 单节课下的学生
	public function getStudent($sid, $index)
	{
		if(!$sid && !$index) return false;
		$date = date('Y-m-d', $index);
		$students = load_model('assign', Null, true)->getStudent($sid, $date, $date);
		return $students;
	}

	public function create($sid, $index)
	{
		if(!$sid || !$index) return false;
		$series = load_model('series', Null, true)->where('id', $sid, true)->Row();
		if(empty($series)) return false;		
		import('schedule');
		$date = date('Y-m-d');
		$schedule = Schedule::resolve($series['rule'], $date, $date);
		$class_time = $schedule['times'];
		$status = 0;
		$data = compact('sid', 'index', 'class_time', 'status');
		$id = $this->insert($data);
		if(!$id) return false;
		return $data;
	}
	
	public function get($sid, $index, $create=false)
	{
		if(!$sid || !$index) return false;
		$data = $this->where('sid', $sid, true)->where('index', $index)->field('sid,index,class_time,status')->Row();
		if($data) return $data;
		if($create == true && empty($data))
		{
			return $this->create($sid, $index);
		}
		return false;
	}	
	
	public function entity_row($sid, $index)
	{
		if(!$sid || !$index) return false;
		$result = $this->where('sid', $sid, true)->where('index', $index)->Row();
		if($result) return $result;
		$result = $this->virtual_row($sid, $index);
		//unset($result['title'], $result['school'], $result['course'], $result['color']);		
		$result['id'] = $this->insert($result);		
		return $result;
	}

	public function virtual_row($sid, $index, $full=false)
	{
		$series = load_model('series', Null, true)->field('*')->where('id', $sid, true)->Row();
		if(empty($series)) return false;		
		import('schedule');
		$date = date('Y-m-d', $index);		
		$sches = Schedule::resolve(json_decode($series['rule'], true), $date, $date);
		if(!$sches) return false;
		
		// 一天内多节
		$schedule = $this->getOne($sches, $index);
		if(!$schedule) return false;

		return array(
			'sid' => $sid,
			'index' => $index,
			'start' => date('H:i', $schedule['start']),
			'end' => date('H:i', $schedule['end']),			
			'class_times' => $schedule['times']
		);
		$full && $result = array_merge($result, array(
			'title' => $series['title'],
			'date' => date('Y-m-d', $schedule['start']),
			'color' => $series['color'],
			'school' => $series['school'],
			'course' => $series['course']
		));
	}

	public function attend($data)
	{
		extract($data);
		if(!$sid || !$index || !$assigner) return false;
		$_Record = load_model('schedule_record', Null, true);
		$record = $_Record->where('sid', $sid, true)->where('index', $index)->where('type', 0)->where('assigner', $assigner)->Row();
		if(!$record)
		{
			return $_Record->insert($data); 
		}else if($record['value'] != $value){
			return $_Record->update(array('value' => $value)); 
		}
		$this->where('sid', $sid, true)->where('index', $index)->update(array('attended' => 1));
		return true;
	}

	private function getOne($sches, $index)
	{
		foreach($sches as $sche)
		{
			if($sche['index'] == $index)
			{
				return $sche;
			}
		}
	}

}