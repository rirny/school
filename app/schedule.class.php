<?php
/*
 * 课程管理
 * 
*/
class Schedule_Module extends School
{
	private $_perpage = 30;

	public function __construact(){
		parent::__construct();
	}

	/*
	 * 列表	
	 * @
	*/
	public function Index_Action()
	{
		$param = $this->int_search();
		extract($param);
		empty($where['unit']) && $where['unit'] = 'week';
		$this->assign('unit', $where['unit']);
		empty($where['start']) && $where['start'] = date("Y-m-d",mktime(0,0,0,date("m"),date("d")-(date("w")?(date("w")):7)+1,date("Y")));
		empty($where['end']) && $where['end'] = date("Y-m-d",mktime(23,59,59,date("m"),date("d")-(date("w")?(date("w")):7)+7,date("Y")));

		$this -> assign('curTime',date('H:i:s',time()));
		$extent['start'] =  $where['start'];
		$extent['end'] =  $where['end'];
		$this->assign('extent', $extent);
		$start = $where['start'].' 00:00:00';
		$end = $where['end'].' 00:00:00';
			
		// 时间
		$where = array_merge($where, $extent);
		$_Series = load_model('series', Null, true);
		$where['school'] = $this->school;

		if($teacher)
		{
			$series = $_Series->get_teacher_series($teacher, $where);
		}else{
			$_Series->where('status,>', 0)->where('school', $this->school); // 已排课
			isset($where['keyword']) && $_Series->where('title,like', $where['keyword']);
			isset($where['week']) && $_Series->where('week,like', $where['week']);
			//isset($where['course']) && $_Series->where('course', $where['course']); // 科目子集
			$series = $_Series->Result();			
		}
		

		$course = load_model('course_type', NULL, true)
			->field('name,id')
			->where('pid,>',0)
			->result();		
		$result = Array();
		$_Schedule =  load_model('schedule', NULL, true);
		$_Record =  load_model('schedule_record', NULL, true);
		$record = $_Record -> field('assigner,value,sid') ->where('protype','change') ->where('value,>=',strtotime($start)) ->where('value,<=',strtotime($end))->where('school',$this->school)->where('type',0)->result();
		$delay = $_Record -> field('assigner,value,sid') ->where('protype','delay',true) ->where('value,>=',strtotime($start)) ->where('value,<=',strtotime($end))->where('school',$this->school)->where('type',0)->result();
		$record = array_merge($record,$delay);
		$sche = $_Schedule -> field('index,sid,start,end,class_times,attended')  ->where('status',2) ->where('index,>=',strtotime($start)) ->where('index,<=',strtotime($end)) ->result();

		$scheduleChange = $changeData = $tmpAry = Array();
		if($record && $sche)
		{
			foreach($record as $item)
			{
				$scheduleChange[$item['sid']][$item['value']][] = $item['assigner'];
			}

			foreach($series as $items)
			{
				foreach($scheduleChange as $key => &$item)
				{
					if($items['id'] == $key)
					{
						foreach($sche as $value)
						{
							if($value['sid'] == $key  && $value['index'] == key($item))
							{
								$changeData ['start'] =  $value['index'];
								$changeData['end'] = $value['index']; + 60*60*$value['class_times'];
								$changeData['week'] =  date('w',$value['index']);
								$changeData['times'] = $value['class_times'];
								$cweek = $this->getWeek($changeData['week']);
								$changeData['rule'] = $cweek.' '.$value['start'].'~'.$value['end'];
								$changeData['sid'] = $key;
								$changeData['title'] = $items['title'];
								$changeData['course'] =  $items['course'];							
								$changeData['date'] =  date('Y-m-d',$value['index']);
								$changeData['sort'] = $items['sort'];
								$changeData['attended'] = $value['attended'];
								$changeData['classes'] = 'change';
								$tmpAry[0] = $changeData;
								$result = array_merge($result, $tmpAry);
							}else{
								continue;
							}
						}
					}
				}
			}
		}
		// 分解
		import('schedule');		
		// 先取系列课
		foreach($series as $key => $item)
		{
			$rules = json_decode($item['rule'], true);			
			foreach($rules as &$rule)
			{
				$rule['sid'] = $item['id'];
				$rule['title'] = $item['title'];
				$rule['rule'] = $item['rule'];
				$rule['sort'] = $item['sort'];
				$rule['course'] = $item['course'];
			}			
			$end_date = $item['close'] && $item['end_date'] < $where['end'] ? $item['end_date'] : $where['end'];
			$_tmp = Schedule::resolve($rules, $where['start'], $end_date);	
			$result = array_merge($result, $_tmp);
		}
		// 排序
		isset($order) || $order['time'] = 0;		
		switch(key($order))
		{
			case 'time':
				$order_key = 'start';
				break;
			default:
				$order_key = key($order);
		}
		$order_value = current($order) ? SORT_DESC : SORT_ASC;
		$index = Array();
		// 格式化
		foreach($result as $rskey => & $v)
		{
			foreach($course as $key => $value)
			{
				if($value['id'] == $v['course'])
					$v['course'] = $value['name'];
			}

			$recChange = $_Schedule -> field('id') -> where('index',$v['start'],true) -> where('sid',$v['sid']) -> where('status',1) ->result();
			
			if($recChange)
			{
				unset($result[$rskey]);
			}
			
			if(!isset($v['attended']))
            {
				//$v['def'] = date('Ymd',$v['start']);
				$event = $_Schedule -> field('id,attended')->where('sid', $v['sid'], true)->where('index', $v['start'])->Row();
				$v['attended'] = $event && $event['attended'] ? 1 : 0; // 考勤
			}

			if(isset($where['attended']))
			{
				if($where['attended'] == 0) // 未考勤
				{
					if($v['attended'] == 1) {
						unset($result[$rskey]);
						continue;
					}
				}else{
					if($v['attended'] == 0) 
					{
						unset($result[$rskey]);
						continue;
					}
				}
			}
			$date = date('Y-m-d', $v['start']);
			$students = load_model('schedule', Null, true)->getStudent($v['sid'], $v['start']);
			$v['students'] = count($students);
			if(!$students) 
			{
				unset($result[$rskey]);
				continue;
			}
			$teachers = load_model('schedule', Null, true)->getTeacher($v['sid'], $v['start']);
			if(!$teachers) 
			{
				unset($result[$rskey]);
				continue;
			}
			for($i=0; $i < count($teachers); $i++)
			{
				 $teacher = load_model('user', Null, true)->where('id', $teachers[$i],true)->field('id,firstname,lastname')->Row();
				 if($i == 0)
				{
					 $v['teachers']['id'] .= $teacher['id'];
					 $v['teachers']['name'] .= $teacher['firstname'].$teacher['lastname'];
				}else{
					 $v['teachers']['id'] .= ','.$teacher['id'];
					 $v['teachers']['name'] .= ';'.$teacher['firstname'].$teacher['lastname'];
				}
			}
			
			if(!isset($v['classes']))
			{
				$rules = json_decode($v['rule'], true);
				foreach($rules as $rkey => &$rule)
				{
					if($rule['week'] !=  date('w',$v['start']))
					{
						unset($rules[$rkey]);
						continue;
					}
					$rule = Schedule::ruleToString($rule);					
				}
				$v['rule'] = join(" ", $rules);		
			}

			isset($order_key) && $index[$order_key][] = $v[$order_key];
		}
		// 分页
		$total = count($result);
		$perpage = 20;
		$start = ($page - 1) * $perpage;
		if($start <= $total)
		{
			array_multisort($index[$order_key], $order_value, $result);
			$this->assign('result', array_slice($result, $start, $perpage));
		}else{
			$this->assign('result', Array());
		}		

		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);
		$this->assign('paginator', $paginator);
		$this->assign('record', $total);		
		$this->display('school/schedule/index');
	}
	
	private function _extent($model, $start)
	{
		if($model == 'week')
		{
			return week_day($start);
		}else{ // 月
			return month_day($start);
		}
	}	

	/*
	 * 代课	
	 * @
	*/
	public function Replace_Action()
	{
		extract(http::post());
		$character = http::get('character', 'int', 0); // 0/1
		$_Record = load_model('schedule_record',NULL ,true);
		$_Assign = load_model('assign',NULL ,true);
		$res = $_Record ->field('id,assigner')-> where('sid',$sid)-> where('school',$this->school)-> where('index',$index)-> where('protype','replace')->where('type',1)
			->Row();
		$assign	=	$_Assign->field('assigner')-> where('sid',$sid)-> where('school',$this->school)-> where('type',1) ->where('status',0)
			->result();
		db()->begin();
		try{
			$ids = array();
			foreach($teachers as $item)
			{
				$ids[]=$item['id'];
			}

			if($res)
			{
				$_Record->where('id',$res['id'],true) ->Delete();
			}
			$ids = json_encode($ids);
			$_Record->insert(array('assigner' => 0, 'sid' => $sid, 'school'=> $this->school, 'index' => $index, 'value' => $ids, 'protype' => 'replace' ,'create_time'=>time(),'type'=>1));
			db()->commit();
		}catch(HLPException $e){			
			db()->rollback();
			Out(0, $e->getMessage());
		}	
		Out(1,"代课成功");
	}
	
	/*
	 * 考勤
	 * 
	*/
	public function Attend_Action()
	{
		if(!http::is_post())
		{
			$sid = http::get('sid', 'int', 0);
			$index = http::get('index', 'int', 0);		
			$page = http::get('page', 'int', 0);
			$end = http::get('end', 'int', 0);
			$times = http::get('times', 'string', 0);

			$this -> assign('index',$index);
			$this -> assign('sid',$sid);
			$this -> assign('end',$end);
			$this -> assign('times',$times);
			if(!$sid || !$index) throw new HLPException('数据错误！');
			$date = date('Y-m-d', $index);
			$_Assign = load_model('assign', Null, true);
			$students = $_Assign
				->where('sid', $sid)
				->where('type', 0)
				->where('start_date,<=', $date)
				->where('end_date,>=', $date)
				->field('assigner')
				->Column();
			$_Record = load_model('schedule_record', Null, true);
			$delay = $_Record ->where('sid', $sid)
				->where('index', $index)
				->where('type', 0)
				->where('protype', 'delay') // 延期的学生不显示
				->field('assigner')
				->Column();

			$change = $_Record ->where('sid', $sid)
				->where('index', $index)
				->where('type', 0)
				->where('protype', 'change') // 调课的学生不显示
				->field('assigner')
				->Column();

			$total = count($students);
			$result = Array(); 
			$_Student = load_model('student', Null, true);

			foreach($students as $item)
			{
				if($delay)
				{
					if(in_array($item,$delay))
						continue;
				}
				if($change)
				{
					if(in_array($item,$change))
						continue;
				}
				$stu  = $_Student ->where('id', $item,true)->field('id,name')->Row();
				$res = $_Record ->where('sid', $sid,true)->where('index', $index)->where('type', 0)->where('protype', 'attend')->where('assigner', $item)->field('value')->Row();
				$res ? $stu['attend'] = $res['value'] : $stu['attend'] = 0 ;
				$result[] = $stu;
			}
			$this->assign('attendes', array(0 => '出勤', 1 => '缺勤', 2=> '请假'));
			$paginator = paginator($page, $total, 10);
			$this->assign('paginator', $paginator);
			$this->assign('record', $total);
			$this->assign('result', $result);
			$this -> display('school/schedule/attend');	
		}else{
			extract(http::post());
			$_Record = load_model('schedule_record',Null,true);
			$_Assign = load_model('assign', Null, true);
			$_Schedule = load_model('schedule',Null,true);
			$field = ['0' =>'attend','1'=>'absence','2'=>'leave'];
			$end = date('H:i',$end);
			$start = date('H:i',$index);
			db()->begin();
			try{
				$schedule = $_Schedule -> field('id')->where('sid',$sid) ->where('index',$index) -> Row();
				if($schedule&&$schedule['status'] == 0)
					$_Schedule -> where('id',$schedule['id']) ->where('index',$index) -> update(array('attended'=>1,));
				else if(!$schedule)
					$_Schedule -> insert(array('attended'=>1,'sid'=>$sid,'absence'=> 0,'commented'=>0,'class_room'=>0,'attend'=>0,'leave'=>0,'status'=>0,'index'=>$index,'class_times'=>$times,'start'=>$start,'end'=>$end));
				foreach($ids as $item)
				{	
					$result = $_Record->field('id,value')
						->where('type', $character,true)->where('school', $this->school)->where('assigner',$item)->where('protype','attend')->where('sid',$sid)->where('index',$index)
						->Row();
					$record = array();
					if($result) 
					{
						$record = $_Record ->where('id', $result['id'],true) ->update(array('value' => $attend[$item]));

						$_Assign ->where('assigner',$item,true) ->where('sid',$sid)->where('type',0)-> increment($field[$attend[$item]]);
						$_Assign ->where('assigner',$item,true) ->where('sid',$sid)->where('type',0)-> decrement($field[$result['value']]);
						$_Schedule -> where('index',$index,true) ->where('sid',$sid)->increment($field[$attend[$item]]);
						$_Schedule -> where('index',$index,true) ->where('sid',$sid)->decrement($field[$result['value']]);
					}else{
						$_Record ->insert(array('value' => $attend[$item],'assigner'=>$item,'protype'=>'attend','sid'=>$sid,'school'=> $this->school,'create_time'=>time(),'type'=>0,'index'=>$index));

						$_Assign ->where('assigner',$item,true) ->where('sid',$sid)->where('type',0)-> increment($field[$attend[$item]]);
						$_Schedule ->where('index',$index,true) ->where('sid',$sid)->increment($field[$attend[$item]]);
						$_Assign ->where('assigner',$item,true) ->where('sid',$sid)->where('type',0)-> decrement('remain');
					}
				}
				db()->commit();
				echo "<script>window.top.art.dialog({'content' : '考勤成功', 'lock' : true, 'icon' : 'succeed', 'ok' : function(){window.top.right.location.href='/schedule';}});</script>";
			}catch(HLPException $e){			
				db()->rollback();
				Out(0, $e->getMessage());
			}
		}
	}

	/*
	 * 顺延
	 * @charachter 角色
	 * @
	*/
	public function Delay_Action()
	{
		extract(http::post());
		$date = date('Y-m-d',$index);
		$_Record = load_model('schedule_record',NULL ,true);
		$_Assign = load_model('assign',NULL ,true);
		$_Series = load_model('series', Null, true);
		$_Schedule = load_model('schedule', Null, true);
		$series = $_Series->field('rule')->where('id',$sid)->Row();
		$rules = json_decode($series['rule'],true);

		$students = load_model('assign', Null, true)
			->where('sid', $sid)
			->where('type', 0)
			->where('start_date,<=', $date)
			->where('end_date,>=', $date)
			->field('assigner')
			->Column();	

		$delay = $_Record ->where('sid', $sid)
			->where('index', $index)
			->where('type', 0)
			->where('protype', 'delay') // 延期的学生不显示
			->field('assigner')
			->Column();

		$change = $_Record ->where('sid', $sid,true)
			->where('index', $index)
			->where('type', 0)
			->where('protype', 'change') // 调课的学生不显示
			->field('assigner')
			->Column();	
		
		import('schedule');	
		$fstart = date('H:i',$index);
		$fend = date('H:i',$index+60*60*$times);
		db()->begin();
		try{
			foreach($students as $item)
			{
				if($change)
				{
					if(in_array($item,$change))
						continue;
				}
				if($delay)
				{
					if(in_array($item,$delay))
						continue;
				}
				$last = $_Assign->field('end_date') -> where('type', 0,true)->where('sid', $sid)->where('assigner',$item)-> Row();
				$end = date('Y-m-d',strtotime('+1 day',strtotime($last['end_date'])));
				$sches = Schedule::resolve($rules, $end, "",1);

				$end_date = $sches[0]['date'];
				$value = $sches[0]['start'];
				$result = $_Record ->field('id')
					->where('type', 0,true)->where('assigner',$item)->where('protype','delay')->where('sid',$sid)->where('index',$index)
					->Row();
				$_Assign->where('type', 0,true)->where('sid', $sid)->where('assigner',$item)->update(array('end_date'=>$end_date));
				$record = Array();
				if($result) 
				{
					$record = $_Record ->where('id', $result['id'],true)->update(array('value' => $value));
				}else{
					$record = $_Record->insert(array('value' => $value,'assigner'=>$item,'protype'=>'delay','sid'=>$sid,'school'=> $this->school,'create_time'=>time(),'type'=>0,'index'=>$index));
				}	
			}
			$schedule = $_Schedule-> field('id') -> where('sid',$sid,true) -> where('index',$index) -> where('status',0) -> Row(); 
			if($schedule)
				$_Schedule -> where('id',$schedule['id'],true) -> update(array('status'=>1));
			else 
				$_Schedule -> insert(array('attended'=>0,'sid'=>$sid,'absence'=> 0,'commented'=>0,'class_room'=>0,'attend'=>0,'leave'=>0,'status'=>1,'index'=>$index,'class_times'=>$times,'start'=>$fstart,'end'=>$fend));


			db()->commit();
		}catch(HLPException $e){			
			db()->rollback();
			Out(0, $e->getMessage());
		}	
		Out(1,"顺延成功");
	}

	/*
	 * 调课
	 * @charachter 角色
	 * @
	*/
	public function Change_Action()
	{
		extract(http::post());
		$character = http::post('character', 'int', 0); // 0/1
		$date = date('Y-m-d',$index);
		
		$value = strtotime($changeDate);
		$_Record = load_model('schedule_record',NULL ,true);
		$_Schedule = load_model('schedule', Null, true);

		$students = load_model('assign', Null, true)
			->where('sid', $sid)
			->where('type', 0)
			->where('start_date,<=', $date)
			->where('end_date,>=', $date)
			->field('assigner')
			->Column();

		$change = $_Record ->where('sid', $sid,true)
			->where('index', $index)
			->where('type', 0)
			->where('protype', 'change') // 调课的学生不显示
			->field('assigner')
			->Column();

		$delay = $_Record ->where('sid', $sid)
			->where('index', $index)
			->where('type', 0)
			->where('protype', 'delay') // 延期的学生不显示
			->field('assigner')
			->Column();
			
		db()->begin();
		$fstart = date('H:i',$index);
		$fend = date('H:i',$index+60*60*$times);
		$cstart = date('H:i',$value);
		$cend = date('H:i',$value+60*60*$times);
		try{
			foreach($students as $item)
			{
				if($change)
				{
					if(in_array($item,$change))
						continue;
				}
				if($delay)
				{
					if(in_array($item,$delay))
						continue;
				}

				$result = $_Record ->field('id')
					->where('type', $character,true)->where('school', $this->school)->where('assigner',$item)->where('protype','change')->where('sid',$sid)->where('index',$index)
					->Row();

				$record = array();
				if($result) 
				{
					$record = $_Record ->where('id', $result['id'],true)->update(array('value' => $value));
				}else{
					$record = $_Record->insert(array('value' => $value,'assigner'=>$item,'protype'=>'change','sid'=>$sid,'school'=> $this->school,'create_time'=>time(),'type'=>0,'index'=>$index));
				}	
			}
			$schedule = $_Schedule ->field('id') -> where('sid',$sid,true) -> where('index',$index) -> where('status',0) -> Row(); 
			if($schedule)
				$_Schedule -> where('id',$schedule['id'],true) -> update(array('status'=>1));
			else 
				$_Schedule -> insert(array('attended'=>0,'sid'=>$sid,'absence'=> 0,'commented'=>0,'class_room'=>0,'attend'=>0,'leave'=>0,'status'=>1,'index'=>$index,'class_times'=>$times,'start'=>$fstart,'end'=>$fend));

			$_Schedule -> insert(array('attended'=>0,'sid'=>$sid,'absence'=> 0,'commented'=>0,'class_room'=>0,'attend'=>0,'leave'=>0,'status'=>2,'index'=>$value,'class_times'=>$times,'start'=>$cstart,'end'=>$cend));
			db()->commit();
		}catch(HLPException $e){			
			db()->rollback();
			Out(0, $e->getMessage());
		}	
		Out(1, '调课成功');
	}

	// 课程下的学生
	public function List_Action()
	{		
		$sid = http::get('sid', 'int', 0);
		$index = http::get('index', 'int', 0);		
		$page = http::get('page', 'int', 0);		
		//if(!$id || !$index) throw new HLPException('数据错误！');
		$date = date('Y-m-d', $index);

		$students = load_model('assign', Null, true)
			->where('sid', $sid)
			->where('type', 0)
			->where('start_date,<=', $date)
			->where('end_date,>=', $date)
			->field('assigner')
			->Column();

		$change = load_model('schedule_record', Null, true)
			->where('sid', $sid)
			->where('index', $index)
			->where('type', 0)
			->where('protype', 'change') // 延期的学生不显示
			->field('assigner')
			->Column();

		$delay = $_Record ->where('sid', $sid)
			->where('index', $index)
			->where('type', 0)
			->where('protype', 'delay') // 延期的学生不显示
			->field('assigner')
			->Column();		
		$students = array_intersect($students, $change);
		foreach($students as $key => $item)
		{
			if($change)
			{
				if(in_array($item,$change))
				{
					unset($students[$key]);
					continue;
				}
			}
			if($delay)
			{
				if(in_array($item,$delay))
				{
					unset($students[$key]);
					continue;
				}
			}
		}
		$total = count($students);
		$result = load_model('student', Null, true)->where('id,in', $students)->field('id,name,avatar')->Result();
		$paginator = paginator($page, $total, 10);
		$this->assign('paginator', $paginator);
		$this->assign('record', $total);
		$this->assign('result', $result);
		$this->display('school/student/ajax.list');
	}
}