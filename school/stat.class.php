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
	
	// 统计
	public function index_Action()
	{		
		$this->display('school/stat/index')
	}
	public function main_Action()
	{
		extract($this->int_search());		
		$method = Http::get('method', 'trim', 'student');		
		$this->assign('method', array('student' => '学生', 'teacher' => '老师', 'course' => '课程'));
		$dateStart = $this->monthStart();
		$dateEnd = DAY;
		$this->assign('dateStart', $dateStart); // 机构科目
		$this->assign('dateEnd', $dateEnd); // 机构科目
		$where['start'] = isset($where['start']) ? $where['start'] : $dateStart;
		$where['end'] = isset($where['end']) ? $where['end'] : $dateEnd;
		$this->assign('start', $where['start'] ); // 开始时间
		$this->assign('end', $where['end'] ); // 结束时间			
		$this->$method($where, $order, $page);		
	}

	private function student($where, $order, $page)
	{
		$_School_student = load_model('school_student');		
		$total = $_School_student->getSumCount($this->school, $where);	
		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);
		$limit = $_School_student->getLimit($this->_perpage, $page);		
		if(Http::get('export', 'int', 0) == 1)
		{
			$source = $_School_student->getSumResult($this->school, $where, $order);
			$this->_export(array(
				'no' => '学号',
				'name' => '学生名',
				'course' => '总课次',
				'unattend' => '未考勤数',
				'attend' => '出勤数',
				'absence' => '缺勤数',
				'leave' => '请假数',
				'classtime' => '总课时数'
			), $source);
		}
		$result = $_School_student->getSumResult($this->school, $where, $order, $limit);		
		$this->assign('result', $result);
		$this->display('school/stat/student');
	}
	
	private function _export($header = array(), $source=array())
	{			
		$title = array_keys($header);
		array_walk($source, function(&$val, $key) use($title){
			foreach($val as $k => $v)
			{
				if(!in_array($k, $title)) unset($val[$k]);
			}
		});		
		excelExport('数据导出', array_values($header), $source);
		Header('Location:' . Http::curl());
	}

	// 课程统计
	public function course($where, $order, $page)
	{		
		$_Event = load_model('event');		
		$total = $_Event->getSumCount($this->school, $where, array(), '', 'e.text');
		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);		
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);
		$limit = $_Event->getLimit($this->_perpage, $page);		
		
		if(Http::get('export', 'int', 0) == 1)
		{
			$source = $_Event->getSumResult($this->school, $where, $order, '', 'e.text');
			array_walk($source, function(&$v){
				$v['teachers'] = json_decode($v['teachers'], true);
				array_walk($v['teachers'], create_Function('&$s', '$s=$s[\'name\'];'));
				$v['teachers'] = join(" ", array_slice(array_values($v['teachers']), 0, 2));
				$v['rate'] = $v['course'] > 0 ? round(($v['attend']/$v['course']) * 100 ,1) . "%": '0%';
			});		
			$this->_export(array(
				'text' => '课程',
				'teachers' => '上课老师',
				'student' => '学生人次',
				'course' => '课次总数',
				'rate' => '出勤率',
			), $source);
		}
		$result = $_Event->getSumResult($this->school, $where, $order, $limit, 'e.text');
		array_walk($result, function(&$v){
			$v['teachers'] = json_decode($v['teachers'], true);
			array_walk($v['teachers'], create_Function('&$s', '$s=$s[\'name\'];'));
			$v['teachers'] = join(" ", array_slice(array_values($v['teachers']), 0, 2));
			$v['rate'] = $v['course'] > 0 ? round(($v['attend']/$v['course']) * 100 ,1) . "%": '0%';
		});		
		$this->assign('result', $result);
		$this->display('school/stat/event');
	}
	// 老师统计
	public function teacher($where, $order, $page)
	{		
		$_School_teacher = load_model('school_teacher');
		$teachers = $this->getTeachers();
		$this->assign('teachers', $teachers); // 机构老师	
		$total = $_School_teacher->getSumCount($this->school, $where, array(), '', 'r.teacher');		
		$paginator = paginator($page, $total, $this->_perpage, $this->_pageRange);		
        $this->assign('paginator', $paginator);
		$this->assign('record', $total);
		if(Http::get('export', 'int', 0) == 1)
		{
			$source = $_School_teacher->getSumResult($this->school, $where, $order, '', 'r.teacher');
			$this->_export(array(
				'name' => '老师',
				'course' => '总课次数',
				'classtime' => '总课时数',
				'student' => '出勤学生人次',
				'comment' => '点评数',
			), $source);
		}
		$limit = $_School_teacher->getLimit($this->_perpage, $page);		
		$result = $_School_teacher->getSumResult($this->school, $where, $order, $limit, 'r.teacher');		
		$this->assign('result', $result);
		$this->display('school/stat/teacher');
	}
}