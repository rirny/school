<?php
/*
 * 课程关系
*/

import('schedule');

class Schedule_relation_Model Extends Model_New
{
	protected $_table = 't_schedule_rule';

	protected $_key = 'id';	
	protected $_cache_key = 't_schedule';
	protected $_timelife = '3600';

	private static $students = Array();
	private static $teachers = Array();

	public function __construct(){
		parent::__construct();
	}
	
	public function resolve($schedule, $start, $end, $times=0)
	{
		
		$rule = load_model('schedule_rule')->where('schedule', $schedule, true)->Result(true);
		return Schedule::resolve($rule, $start, $end, $times);
	}

	// 一节课
	public function item($schedule, $start)
	{
		$item = load_model('schedule_item')
			->where('schedule', $schedule, true)
			->where('index', $start)
			->Row();
		if($item) return $item;
		$schedules = $this->resolve($schedule, date('Y-m-d', $start), '', 1);
		return current($schedules);
	}

	// 一节课下的学生
	public function get_student($schedule, $start)
	{
		$index = $schedule . $start;
		if(isset($this->students[$index])) return $this->students[$index];
		$item = load_model('schedule_item')
			->where('schedule', $schedule, true)
			->where('status', 0)
			->where('index', $start)
			->Row();
		if($item)
		{
			$students = load_model('schedule_student', Null, true)->where('item', $item);
		}
	}
	// 一节课下的老师	
	
	// 获取学生课程
	public function student_schedule($schedule, $student, $start='', $end='')
	{
		if(!$schedule || !$student) return ;
		$assign = load_model('schedule_assign', Null, true)
			->where('type', 1, true)
			->where('assign', $student)
			->Result(true);
		if(!$assign) return ;
		$start = strtotime($start);
		$end = strtotime($end);
		$schedule = load_model('schedule', Null, true)->where('id', $schedule, true)->Row();
		$rules = load_model('schedule_rule', Null, true)->where('schedule', $schedule, true)->Result();
		if(empty($schedule) || empty($rules)) return ;

		$result = Array();
		foreach($assign as $key => $item)
		{
			$itemStart = strtotime($item['start_date']);
			$itemEnd = strtotime($item['end_date']));
			if( ($start && $end) && ($start > strtotime($item['end_date']) || $end < strtotime($item['start_date'])) ) continue;
			$start > $itemStart || $start = $itemStart;
			$end > $itemEnd && $end = $itemEnd;
			foreach($rules as $rule)
			{
				$tmp = Schedule::resolve($rule, date('Y-m-d', $start), date('Y-m-d', $end));
				$result = array_merge($result, $tmp);
			}
		}

		foreach($result as &$val)
		{
			$_V = Array(
				'schedule' => $schedule['id'],
				'title' => $schedule['title'],
				'date' => $val['date'],
				'start' => $val['start'],
				'week' => $val['week'],
				'color' => $schedule['color'],
				'status'=> 0 // 未考勤
				'remark'=> ''
			);
			$sche = load_model('schedule_item', Null, true)->where('schedule', $schedule['id'], true)->where('index', $val['start'])->Row();			
			if(!empty($sche))
			{
				$record = load_model('schedule_student', Null, true)->where('schedule', $schedule['id'], true)->where('item', $sche['start'])->Row();
				$_V['status'] = $record['status'];
				$_V['remark'] = $record['remark'];
				// 调课了
				/*
				if($record['status'] == 4)
				{
					
				}
				*/
			}
			$val = $_V;
		}

		return $result;
	}

	// 获取学生课程
	public function teacher_schedule($schedule, $teacher, $start='', $end='')
	{
		if(!$schedule || !$student) return ;
		$assign = load_model('schedule_assign', Null, true)
			->where('type', 0, true)
			->where('assign', $student)
			->Result(true);
		if(!$assign) return ;
		$start = strtotime($start);
		$end = strtotime($end);
		$schedule = load_model('schedule', Null, true)->where('id', $schedule, true)->Row();
		$rules = load_model('schedule_rule', Null, true)->where('schedule', $schedule, true)->Result();
		if(empty($schedule) || empty($rules)) return ;

		$result = Array();
		foreach($assign as $key => $item)
		{
			$itemStart = strtotime($item['start_date']);			
			if($item['end_date'] == '0000-00-00')
			{
				if($end > strtotime($item['start'])) continue;				
			}else{
				$itemEnd = strtotime($item['end_date']));
				if( $start > strtotime($item['end_date']) || $end < strtotime($item['start_date'])) continue;				
				$end > $itemEnd && $end = $itemEnd;
			}
			$start > $itemStart || $start = $itemStart;		
			
			foreach($rules as $rule)
			{
				$tmp = Schedule::resolve($rule, date('Y-m-d', $start), date('Y-m-d', $end));
				$result = array_merge($result, $tmp);
			}
		}

		foreach($result as $k => &$val)
		{
			$_V = Array(
				'schedule' => $schedule['id'],
				'title' => $schedule['title'],
				'date' => $val['date'],
				'start' => $val['start'],
				'week' => $val['week'],
				'color' => $schedule['color'],
				'status'=> 0 // 未考勤
				'remark'=> ''
			);
			$sche = load_model('schedule_item', Null, true)->where('schedule', $schedule['id'], true)->where('index', $val['start'])->Row();			
			if(!empty($sche))
			{
				$record = load_model('schedule_student', Null, true)->where('schedule', $schedule['id'], true)->where('item', $sche['start'])->Row();
				$_V['status'] = $record['status'];
				$_V['remark'] = $record['remark'];
				// 调课了
				/*
				if($record['status'] == 4)
				{
					
				}
				*/
			}
			$val = $_V;
		}

		return $result;
	}
}