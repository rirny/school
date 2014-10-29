<?php
set_time_limit(0);
class Stat_Module extends School
{
	private $tpl = '';
	private $_perpage = 10;
	private $_pageRange = 10;

	public function __construact(){
		parent::__construact();		
	}

	// 课程统计
	public function Index_Action()
	{
		// 有分配的
		$refresh = Http::get('refresh', 'int', 0);
		$year = Http::get('year', 'int', date('Y'));
		$month = Http::get('month', 'int', date('n'));
		$keyword = Http::get('keyword', 'trim', '');
		$page = Http::get('page', 'int', 1);
		$export = Http::get('export', 'int', 0);

		$perpage = 20;
		$period = $this->period($year, $month, false);		
		$cache_key = "stat_schedule_{$this->school}_{$year}_$month";
		$result = cache()->get($cache_key);
		if($result === false || $refresh)
		{
			$sql.= "select s.id,s.title,c.`name` course,i.index,i.class_times,r.value from t_schedule_record r";
			$sql.= " Left join t_schedule i on r.sid=i.sid And r.index=i.index";
			$sql.= " Left join t_series s On s.id=i.sid";
			$sql.= " Left join t_course_type c On s.course=c.id"; 
			$sql.= " Where r.protype='attend' And s.id>0 And s.school={$this->school}";
			$sql.= " And r.`index`>='{$period['start']}'";
			$sql.= " And r.`index`<='{$period['end']}'";			
			$keyword && $sql .= " And s.title like '%{$keyword}%'";
			$records = db()->fetchAll($sql);
			$data = Array();
			$attend = $expend = $real = $rate = 0;
			foreach($records as $item)
			{	
				if(!isset($data[$item['id']]))
				{
					$_tmp = Array(
						// 'id' => $item['id'],
						'title' => $item['title'],
						'course' => $item['course'],
						'expend' => 0,
						'attend' => 0,
						'real' => 0
					);
				}else{
					$_tmp = $data[$item['id']];
				}
				$expend += $item['class_times'];
				$_tmp['expend'] += $item['class_times'];
				if($item['value'] == 0)
				{
					$_tmp['attend']++;
					$_tmp['real'] += $item['class_times'];
					$attend++;
					$real += $item['class_times'];
				}
				$data[$item['id']] = $_tmp;
			}
			array_walk($data, function(&$v){
				$v['rate'] = $v['expend'] > 0 ? 100 * round($v['real']/$v['expend'], 4) : 0;				
			});			
			$expend && $rate = 100 * round(($real/$expend), 4);
			$total = compact('attend', 'expend', 'real', 'rate');
			$result = compact('data', 'total');
			if($data)
			{
				cache()->set($cache_key, $result);
			}
		}

		if($export && !empty($result['data']))
		{
			$this->_export_event("统计-课程-{$year}-{$month}", $result['data']);			
		}

		if(isset($result['data']))
		{
			$data = $result['data'];
			$paginator = paginator($page, count($data), $perpage, 10);
			$this->assign('paginator', $paginator);
			$data = array_slice($data, ($page-1)*$perpage, $perpage);
			$this->assign('result', $data);
			$this->assign('total', $result['total']);
		}
		$this->display('school/stat/event');
	}

	// 学生统计
	public function Student_Action()
	{
		$refresh = Http::get('refresh', 'int', 0);
		$year = Http::get('year', 'int', date('Y'));
		$month = Http::get('month', 'int', date('n'));
		$page = Http::get('page', 'int', 1);
		$perpage = 20;
		$offset = ($page-1) * $perpage;
		$export = Http::get('export', 'int', 0);

		$period = $this->period($year, $month, false);
		$cache_key = "stat_student_{$this->school}_{$year}_$month";
		$result = cache()->get($cache_key);
		if($result === false || $refresh)
		{
			$sql.= "select e.id,sr.no,e.`name`,s.id as sid,s.title,s.course,r.`value` from t_schedule_record r";
			$sql.= " Left join t_series s On s.id=r.sid";
			$sql.= " Left join t_school_student sr On sr.school=s.school And sr.student=r.assigner";
			$sql.= " Left join t_student e On e.id=r.assigner";
			$sql.= " Where r.protype='attend' And s.school={$this->school}";
			$sql.= " And r.`index`>='{$period['start']}'";
			$sql.= " And r.`index`<='{$period['end']}'";
			$keyword && $sql .= " And e.`name` like '%{$keyword}%'";
			$sql.= " Order by e.name";
			$records = db()->fetchAll($sql);
			$result = Array();		
			foreach($records as $item)
			{
				if(!isset($data[$item['id']]))
				{
					$_tmp = array(
						'no' => $item['no'],
						'name' => $item['name'],
						'attend' => 0,
						'absence'=> 0,
						'leave' => 0,
						'total' => 0
					);
				}else{
					$_tmp = $data[$item['id']];					
				}

				if(!isset($data[$item['id']]['series'][$item['sid']]))
				{					
					$_series = array(
						'title' => $item['title'],
						'course' => $item['course'],
						'attend' => 0,
						'absence'=> 0,
						'leave' => 0,
						'total' => 0
					);
				}else{
					$_series = $data[$item['id']]['series'][$item['sid']];
				}

				$_tmp['total']++;
				$_series['total']++;
				switch($item['value'])
				{
					case 1:
						$_tmp['absence']++;
						$_series['absence']++;
						break;
					case 1:
						$_tmp['leave']++;
						$_series['leave']++;
						break;
					default:
						$_tmp['attend']++;
						$_series['attend']++;
				}
				$data[$item['id']] = $_tmp;
				$data[$item['id']]['series'][$item['sid']] = $_series;
			}
			$result = $data;
			if($data)
			{
				cache()->set($cache_key, $result);
			}
		}

		if($export && !empty($result['data']))
		{
			$this->_export_student("统计-学生-{$year}-{$month}", $result);
		}				

		$paginator = paginator($page, count($result), $perpage, 10);
		$this->assign('paginator', $paginator);
		$result = array_slice($result, $offset, $perpage);
		$this->assign('result', $result);
		$this->display('school/stat/student');
	}

	// 课程统计
	public function Teacher_Action()
	{
		$refresh = Http::get('refresh', 'int', 0);
		$year = Http::get('year', 'int', date('Y'));
		$month = Http::get('month', 'int', date('n'));
		$page = Http::get('page', 'int', 1);
		$perpage = 20;
		$offset = ($page-1) * $perpage;
		$export = Http::get('export', 'int', 0);
		$period = $this->period($year, $month, false);
		$cache_key = "stat_teacher_{$this->school}_{$year}_$month";
		$result = cache()->get($cache_key);
		if($result === false || $refresh)
		{
			$sql.= "select u.id,u.firstname,u.lastname,s.id as sid,s.title,s.course,r.`index`,r.`value`,i.class_times from t_schedule_record r";			
			$sql.= " Left join t_assign a On r.sid=a.sid";
			$sql.= " Left join t_schedule i On i.sid=r.sid And i.`index`=r.`index`";
			$sql.= " Left join t_series s On s.id=r.sid";
			$sql.= " Left join t_user u On a.assigner=u.id";
			$sql.= " Where r.protype='attend' And s.school={$this->school} And u.id>0 And a.`type`=1";
			$sql.= " And r.`index`>='{$period['start']}'";
			$sql.= " And r.`index`<='{$period['end']}'";
			$keyword && $sql .= " And u.`firstname` like '%{$keyword}%'";
			$sql.= " Order by u.firstname_en";
			$records = db()->fetchAll($sql);
			$result = $data = Array();
			$class_stat = Array();
			foreach($records as $item)
			{
				if(!isset($data[$item['id']]))
				{
					$_tmp = array(						
						'name' => $item['firstname'] . $item['lastname'],
						'class_times' => 0,
						'attend'=> 0						
					);
				}else{
					$_tmp = $data[$item['id']];					
				}

				if(!isset($data[$item['id']]['series'][$item['sid']]))
				{					
					$_series = array(
						'title' => $item['title'],
						'course' => $item['course'],
						'attend' => 0,
						'class_times'=> 0
					);
				}else{
					$_series = $data[$item['id']]['series'][$item['sid']];
				}

				$stat_key = $item['id'] . $item['sid'] . $item['index'];
				if(!isset($class_stat[$stat_key]))
				{
					$_tmp['class_times'] += $item['class_times'];					
					$_series['class_times'] += $item['class_times'];
					$class_stat[$stat_key] = true;
				}			

				if($item['value'] == 0 )
				{
					$_tmp['attend']++;
					$_series['attend']++;
				}
				$data[$item['id']] = $_tmp;
				$data[$item['id']]['series'][$item['sid']] = $_series;				
			}
			$result = $data;
			if($data)
			{
				cache()->set($cache_key, $result);
			}
		}

		if($export && !empty($result))
		{
			$this->_export_teacher("统计-老师-{$year}-{$month}", $result);			
		}

		$paginator = paginator($page, count($result), $perpage, 10);
		$this->assign('paginator', $paginator);
		$result = array_slice($result, $offset, $perpage);
		$this->assign('result', $result);
		$this->display('school/stat/teacher');
	}

	/*
	* 取时间周期
	*/
	private function period($year, $month, $current = true)
	{		
		if(!$year || !$month) return false;
		$start = mktime(0,0,0, $month, 1, $year);		
		if($current && $year == date('Y') && $month >= date('n')) 
			return false;
		if($year == date('Y') && $month == date('n')) // 当前月
		{			
			// $end = mktime(0,0,0, $month, date('j')-1, $year);
			$end = mktime(0,0,0, $month, date('j'), $year);
		}else{
			$end = mktime(23, 59, 59, $month+1, 1, $year);
		}
		$this->assign('year', (int)$year);
		$years = range(2013, date('Y'));
		$this->assign('years', array_combine($years, $years));
		if($year == date('Y'))
		{
			$months = range(1, date('n'));
		}else{
			$months = range(1, 12);
		}
		$this->assign('month', (int)$month);
		$this->assign('months', array_combine($months, $months));
		return  compact('start', 'end');
	}

	/*
	 * 取此时间内周排布
	*/
	private function week_days($start, $end)
	{		
		$result = Array();
		while($start <= $end)
		{
			$w = date('w', $start);
			$result[$w][] = $start;
			$start += 86400;
		}
		return $result;
	}	

	private $_column_event = array(
		'title' => '课程名称'	,
		'course' => '科目',
		'attend' => '出勤人次',
		'expend' => '应消耗课时数',
		'real' => '实际消耗课时数',
		'rate' => '课时消耗比',
	);
	
	private $_column_student = array(
		'no' => '学号',
		'name' => '学生名',
		'attend' => '课程名',
		'attend' => '出勤',
		'absence' => '缺勤',
		'leave' => '请假',
		'rate' => '出勤率',
	);

	private $_column_teacher = array(
		'name' => '老师名',
		'course' => '课程',
		'class_times' => '总课时',
		'attend' => '出勤人次'
	);

	private function _export_event($title, Array $data)
	{
		$result = array();
		$columns = $this->_column_event;
		foreach($data as $item)
		{
			$_tmp = Array();
			foreach($columns as $key => $v){
				$key == 'rate' && $item[$key] .= "%";
				$_tmp[$key] = $item[$key];
			}
			$result[] = $_tmp;
		}
		excelExport($title, array_values($columns), $result);
		Header('Location:' . Http::curl());
	}

	private function _export_teacher($title, Array $data)
	{
		$result = array();
		$columns = $this->_column_teacher;		
		foreach($data as $item)
		{			
			$_tmp = Array();
			foreach($columns as $key => $v){
				if($_key != 'title')
				{
					$_tmp[$key] = $item[$key];
				}
			}
			$result[] = $_tmp;
			if(isset($item['series']) && is_array($item['series']))
			{
				foreach($item['series'] as $val)
				{			
					$_series_tmp = Array();
					foreach($columns as $_key => $val){
						if($_key != 'name')
						{
							$_series_tmp[$_key] = $val[$_key];
						}
					}
					$result[] = $_series_tmp;
				}
			}
			
		}
		excelExport($title, array_values($columns), $result);
		Header('Location:' . Http::curl());		
	}

	private function _export_student($title, Array $data)
	{
		$result = array();
		$columns = $this->_column_student;
		foreach($data as $item)
		{			
			$_tmp = Array();
			foreach($columns as $key => $v){
				if($key == 'rate')
				{
					$v[$key] = round(($v['attend']/$v['total']) * 100, 2);
				}
				if($_key != 'course')
				{
					$_tmp[$key] = $item[$key];
				}
			}
			$result[] = $_tmp;
			if(isset($item['series']) && is_array($item['series']))
			{
				foreach($item['series'] as $val)
				{			
					$_series_tmp = Array();
					foreach($columns as $_key => $val){	
						if($key == 'rate')
						{
							$val[$_key] = round(($val['attend']/$val['total']) * 100, 2);
						}
						if($_key != 'no' && $_key != 'name')
						{
							$_series_tmp[$_key] = $val[$_key];
						}
					}
					$result[] = $_series_tmp;
				}
			}			
		}
		excelExport($title, array_values($columns), $result);
		Header('Location:' . Http::curl());		
	}
}

/*
require('../library/http.class.php');
$period = Stat_Module::period();

$weeks = Stat_Module::week_days($period['start'], $period['end']); 
foreach($weeks as &$week)
{
	foreach($week as &$item)
	{
		$item = date('Y-m-d', $item);
	}
}
print_r($weeks);
*/