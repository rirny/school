<?php
/*
 * 课程模型
*/
class Series_Model_New Extends Model_New
{
	
	protected $_db = NULL;
	protected $_table = 'series';
	protected $_key = 'id';

	public function __Construct()
	{
		parent::__construct();
	}	

	// 结课
	public function close($id, $date)
	{
		// 状态更新
		$res = $this->update(array('status' => 2, 'end_date' => $date))->where('id', $id);
		// 删除之后所有课程
		$res = load_model('schedule')->where('sid', $id)->where('index,>', strtotime($date))->delete(true);
		// 删除考勤、点评记录
		$res = load_model('schedule_record')->where('sid', $id)->where('index,>', strtotime($date))->delete(true);
		// 更新课程关系schedule
		// 未发生的删除
		$_Assign = load_model('assign', Null, true);
		$res = load_model('assign')->where('sid', $id)->where('start_date,>', $date)->delete(true); // 未开始的删除
		// 已发生但未结
		$res = $_Assign->where('sid', $id, true)->or_where(array('end_date,>' => $date, 'end_date' => '0000-00-00'))
			->update(array('end_date' => $date, 'status' => 1));
		
		// 已结束再结
		// $res = $_Assign->where('sid', $id, true)->where('end_date,>=', $date)->where('status', 1)->update(array('end_date' => $date));
		return true;
	}	
	
	/*
	 * @param keyword,week,order
	 * @school
	*/
	public function get_series(Array $param, $paginator = false)
	{
		$result = Array();
		if(empty($param)) return false;
		extract($param);
		extract($where);
		if(empty($school)) return false;
		$_where['rule,!='] = '';
		$_where['school'] = $school;
		empty($page) && $page = 1;
		empty($perpage) && $perpage = 20;		
		empty($keyword) || $_where['title,like'] = $keyword;
		isset($week) && $_where['week,like'] = $week;
		// isset($status) || $_where['status'] = 1; // 已排课
		$this->where($_where, Null);
		$paginator && $total = $this->Count();
		empty($order) && $order['time'] = 1;	
		switch(key($order))
		{
			case 'time':
				$order_key = 'create_time';
				break;
			default:
				$order_key = key($order);
		}
		$order_value = current($order) ? 'Desc' : 'Asc';
		$this->Order($order_key, $order_value);
		if($paginator) $this->limit($perpage, $page);	
		import('schedule');
		$data = $this->field('*')->Result();	
		if($paginator) $paginator = paginator($page, $total, $perpage, 10);
		foreach($data as $key=>&$item)
		{			
			$rules = json_decode($item['rule'], true);
			sort($rules);
			$item['rule'] = Array();
			foreach($rules as $val)
			{
				$item['rule'][] = Schedule::ruleToString($val);
			}
		}
		if($paginator) 
		{
			return compact('paginator', 'data');	
		}
		return $data;
	}
	
	public function get_teacher_series($param, $order=Array())
	{		
		extract($param);
		extract($where);
		if(empty($assigner)) return false;
		//$cache_key = 'teacher_series_' . $assigner . md5(json_encode($param));
		//$result = cache()->get($cache_key);
		//empty($cache) && $cache = true; // true 不起cache
		empty($order) || $order['time'] = 0;
		//if($result === false || $cache == true)
		//{			
			$sql = "select s.*, a.start_date, a.end_date, a.status as 'close' from t_assign a left join t_series s on s.id=a.sid Where a.`type`=1 And a.assigner={$assigner}";
			if(isset($start) || isset($end))
				$sql.= " And a.start_date<='{$end}' And (a.`status`=0 Or a.end_date>='{$start}')";
			isset($school) && $sql.= " And s.school={$school}";
			isset($course) && $sql.= " And s.`course`={$course}";
			isset($keyword) && $sql.= " And s.`title` like '%{$keyword}%'";
			isset($week) && $sql.= " And s.week like '%{$week}%'";		
			switch(key($order))
			{
				case 'time':
					$order_key = 's.sort';
					break;				
				case 'start':
					$order_key = 'a.start_date';
					break;
				case 'end':
					$order_key = 'a.end_date';
					break;
				default:
					$order_key = "s.sort";
			}
			$sql .= " Order by {$order_key} " .( current($order) ? 'Desc' : 'Asc');
			//$sql .= " Group by s.id";
			$result = db()->fetchAll($sql);
			//cache()->set($cache_key, $result, 600); // 十分钟缓存
		//}
		return $result;
	}

	public function get_student_series($param, $group = false)
	{		
		extract($param);		
		if(empty($assigner))  return false;
		$cache_key = 'student_series_' . $assigner . md5(json_encode($param));
		$result = cache()->get($cache_key);
		empty($cache) || $cache = true; // true 不起cache
		empty($order) || $order['time'] = 0;
		if($result === false || $cache == true)
		{			
			$sql = "select s.*,a.start_date,a.end_date,a.times,a.attend,a.absence,a.leave,a.remain,a.delay,a.status as 'close' from t_assign a left join t_series s on s.id=a.sid Where a.`type`=0 And a.assigner={$assigner}";
			isset($end) && $sql.= " And a.start_date<='{$end}'";
			isset($start) && $sql.= " And a.end_date>='{$start}'";
			isset($school) && $sql.= " And s.school={$school}";
			isset($course) && $sql.= " And s.`course`={$course}";
			isset($keyword) && $sql.= " And s.`title` like '%{$course}%'";
			isset($week) && $sql.= " And s.week like '%{$keyword}%'";
			switch(key($order))
			{
				case 'time':
					$order_key = 's.sort';
					break;				
				case 'start':
					$order_key = 'a.start_date';
					break;
				case 'end':
					$order_key = 'a.end_date';
					break;
				default:
					$order_key = "s." . $order_key;
			}
			$sql .= " Order by {$order_key} " .( current($order) ? 'Desc' : 'Asc');
			$group && $sql .= " Group by s.id";
			$result = db()->fetchAll($sql);
			cache()->set($cache_key, $result, 600); // 十分钟缓存
		}
		return $result;
	}
}
