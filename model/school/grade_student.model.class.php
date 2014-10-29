<?php
/*
 * 机构老师
*/
class Grade_student_Model Extends Model
{
	protected $_table = 't_grade_student';

	protected $_key = 'id';	
	protected $_cache_key = 'grade_student';
	protected $_timelife = '3600';

	public function __construct(){
		parent::__construct();
	}
	
	public function getStudent($where=array(), $order=array(), $limit="", $fields="*", $cache=false)
	{
		// $this->getAll($where, '', '', );
	}
	

}