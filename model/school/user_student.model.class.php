<?php
class User_student_Model Extends Model
{
	protected $_table = 't_user_student';

	protected $_key = 'id';	
	protected $_cache_key = 'user_student';
	protected $_timelife = '3600';

	public function __construct(){
		parent::__construct();
	}
	
	public function createRelation($user, $student, $relation=4)
	{
		if(!$user) return false;
		$res = $this->getRow(array('user' => $user, 'student' => $student, 'relation' => $relation));
		if(!$res)
		{			
			$this->insert(array('user' => $user, 'student' => $student, 'relation' => $relation, 'create_time' => TM));
		}
		return true;
	}
}