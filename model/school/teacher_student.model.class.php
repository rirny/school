<?php
/*
 * 机构老师
*/

class Teacher_student_Model Extends Model
{
	protected $_table = 't_teacher_student';

	protected $_key = 'id';	
	protected $_cache_key = 'teacher_student';
	protected $_timelife = '3600';

	public function __construct(){
		parent::__construct();
	}
	

	public function createRelation($teacher, $student, $school=0)
	{
		static $_tmp = array();
		if(isset($_tmp[$teacher]) && in_array($student, $_tmp[$teacher])) 
		{
			return ;
		}else{
			$_tmp[$teacher][] = $student;
		}
		$data = Array(
			'ext' => $school,
			'type'=> 1,
			'teacher' => $teacher,
			'student' => $student,
			'create_time' => TM,
			'study_date' => DAY						
		);
		return $this->insert($data);
	}

	public function createMultiRelation($teachers, $students, $school=0)
	{
		if(empty($students) || empty($teachers)) return false;
		foreach($teachers as $teacher)
		{
			foreach($students as $student)
			{
				$res = $this->getRow(array(
					'student' => $student,
					'teacher' => $teacher,
					'ext' => $school
				));
				if($res) continue;
				$res = $this->createRelation($teacher, $student, $school);
				if(!$res) return false;
			}
		}
		return true;
	}
}