<?php
/*
 * 课程管理
 * 
*/
class Assign_Module extends School
{
	private $_perpage = 30;

	public function __construact(){
		parent::__construct();
	}

	/*
	 * 排课
	 * @charachter 角色
	 * keyword,course
	 * @type 学生0老师1
	 * @multi 批量排课
	*/
	public function Index_Action()
	{		
		$param = $this->int_search();
		$param['where']['school'] = $this->school;
		extract($param);
		extract($where);		
		is_array($assigner) || $assigner = explode(',', $assigner);		
		if($type == 1)
		{
			$field = 'id,firstname,lastname';
			$table = 'user';			
		}else{
			$field = 'id,name';
			$table = 'student';
		}
		if(empty($assigner)) $this->show_error('排课对象不能为空！');

		$_Model = load_model($table, Null, true)->field($field);
		$assigners = $_Model->where('id,in', $assigner)->Result();
		$name = '';
		if($type == 1)
			$name = $this->get_teacher_name($assigners);
		else
			$name = $this->get_student_name($assigners);
		// 取课程		
		$series = load_model('series', Null, true)->get_series($param, true);
		$course = load_model('course_type', NULL, true)->field('name,id')->where('pid,>',0)->result();
		$data = Array();
		foreach($series['data'] as &$item)
		{
			$item['rule'] = implode('；', $item['rule']);
			foreach($course as $key => $value)
			{
				if($value['id'] == $item['course'])
					$item['course'] = $value['name'];
			}
			$tmp = json_decode($item['week']);
			in_array('1',$tmp) && $data['mon'][] = $item;
			in_array('2',$tmp) && $data['tue'][] = $item;
			in_array('3',$tmp) && $data['wed'][] = $item;
			in_array('4',$tmp) && $data['thu'][] = $item;
			in_array('5',$tmp) && $data['fri'][] = $item;
			in_array('6',$tmp) && $data['sat'][] = $item;
			in_array('0',$tmp) && $data['sun'][] = $item;
		}
		$record = ['mon' => count($data['mon']),'tue' => count($data['tue']),'wed' => count($data['wed']),'thu' => count($data['thu']),'fri' => count($data['fri']),'sat' => count($data['sat']),'sun' => count($data['sun'])];
		$this->assign('record',$record);
		$this->assign('result', $data);		
		$this->assign('curDate',date('Y-m-d',time()));
		$this->assign('assigner', $assigners);
		$this->assign('name', $name);
		$this->assign('series', $series['data']);
		$this->assign('paginator', $series['paginator']);
		$this->assign('mon', $data['mon']);
		$this->assign('tue', $data['tue']);
		$this->assign('wed', $data['wed']);
		$this->assign('thu', $data['thu']);
		$this->assign('fri', $data['fri']);
		$this->assign('sat', $data['sat']);
		$this->assign('sun', $data['sun']);
		$this->assign('type', $where['type']);
		$this->assign('multi', $multi);

		$this->display('school/schedule/assign');		
	}
	
	/*
	 * 排课
	 * @charachter 角色
	 * keyword,course
	 * @type 学生0老师1
	 * @multi 批量排课
	 * @学生带课次
	*/
	public function Save_Action()
	{
		$assigner = Http::post('assigner', 'trim');
		$type = Http::post('type', 'int', 0);
		$start_date = Http::post('start', 'trim', '');
		$times = Http::post('times', 'int', 0);
		$priv = Http::post('priv', 'int', 0);
		$sid = Http::post('schedule', 'int', 0);
		$multi = Http::post('multi', 'int', 0); // 是否为多人
		$create_time = time();	
		$school = $this->school;
		$remain = $times; 
		db()->begin();		
		try
		{
			if($times >100 || $times < 0) throw New Exception('课次只能输入1-100之间的整数!');
			if($type == 0 && $times == 0) throw New Exception('课次只能输入1-100之间的整数!');
			if(!$sid) throw New Exception('错误的参数！');
			$sche = load_model('series', Null, true)->where('id', $sid)->where('status,!=', 2)->Row();
			if(!$sche) throw New Exception('课程不存在！');
			if(empty($assigner)) throw New Exception('请确定要排课的对象！');
			if($type == 0 && $times < 1) throw New Exception('排课失败！');
			$end_date = '0000-00-00';

			$_Series = load_model('series', Null, true);
			$_Assign = load_model('assign', Null, true);
			$_Teacher_student = load_model('teacher_student', Null , true);
			if($type == 0) // 学生排课计算最后一节
			{
				import('schedule');
				$events = Schedule::resolve(json_decode($sche['rule'], true), $start_date, '', $times);
				$last = end($events);
				$end_date = date('Y-m-d', $last['start']);
			}
			$data = compact('sid', 'start_date', 'end_date', 'times', 'type','priv','create_time','school','remain');
			if($multi) // 多人 已经排过的不再排
			{
				is_array($assigner) || $assigner = explode(',', $assigner);
				foreach($assigner as $val)
				{
					$assigned = $_Assign->where('sid', $sid, true)
						->where('assigner', $val)
						->where('type', $type)->Row();
					if($assigned) continue; // 已经排过的不再排
					$data['assigner'] = $val;
					$res = $_Assign->insert($data);
					if(!$res) throw New Exception('排课失败！');
				}
				$_Series ->where('id',$sid)->update(array('assign'=>1));
			}else{
				$data['assigner'] = $assigner[0];
				if($type == 0)
				{
					if($_Assign->is_cross($sid,$data['assigner'],$start_date,$end_date)) throw New Exception('排课冲突！');
				}else{
					if($_Assign->is_cross($sid,$data['assigner'],$start_date,'',1)) throw New Exception('排课冲突！');
				}
				$res = $_Assign->insert($data);
				if(!$res) throw New Exception('排课失败！');
			}
			
			$ts = $_Teacher_student -> field('student,teacher') ->where('ext',$this->school) -> where('type',1)->Result();

			if($type)
				$studentAssign = $_Assign ->field('assigner') -> where('sid',$sid,true)->where('type',0) ->Result();
			else
				$teacherAssign = $_Assign ->field('assigner') -> where('sid',$sid,true)->where('type',1) ->Result();

			if($teacherAssign || $studentAssign)
			{
				if($type)
				{
					$has = 0;
					foreach($studentAssign as $item)
					{
						foreach($ts as $value)
						{
							if($value['student'] == $item['assigner'] && $value['teacher'] == $assigner[0])
								$has = 1;
						}
						if($has)
							continue;
						$_Teacher_student -> insert(array('student' => $item['assigner'],'teacher' => $assigner[0],'ext'=>$this->school, 'status' => 0,'type' => 1, 'create_time'=> time())); 
					}
				}
				else
				{
					$has = 0;
					foreach($teacherAssign as $item)
					{
						foreach($assigner as $value)
						{
							foreach($ts as $v)
							{
								if($v['student'] == $value && $v['teacher'] == $item['assigner'])
									$has = 1;
							}
							if($has)
								continue;
							$_Teacher_student -> insert(array('student' => $value,'teacher' => $item['assigner'],'ext'=>$this->school, 'status' => 0,'type' => 1, 'create_time'=> time()));
						}
					}
				}
			}
			$_Series ->where('id',$sid,true) -> update(array('status'=>1));
			db()->commit();
			Out(1, "排课成功！");
		}catch(Exception $e)
		{
			db()->rollback();
			Out(0, $e->getMessage());
		}
	}
	
	private function get_teacher_name($teachers = Array()){
		if(!is_array($teachers) || empty($teachers)) return;
		$teacherName = '';
		for($i =0; $i<count($teachers);$i++)
		{
			if($i == 0)
				$teacherName = $teachers[$i]['firstname'].$teachers[$i]['lastname'];
			else
				$teacherName.= '；'.$teachers[$i]['firstname'].$teachers[$i]['lastname'];
		}
		return $teacherName;
	}

	private function get_student_name($students = Array()){
		if(!is_array($students) || empty($students)) return;
		$studentName = '';
		for($i =0; $i<count($students);$i++)
		{
			if($i == 0)
				$studentName = $students[$i]['name'];
			else
				$studentName.= '；'.$students[$i]['name'];
		}
		return $studentName;		
	}
}