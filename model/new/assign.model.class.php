<?php
/*
 * 课程模型
*/
class Assign_Model_New Extends Model_New
{
	
	protected $_db = NULL;
	protected $_key = 'id';
	protected $_table = 'assign';

	public function __Construct()
	{
		parent::__construct();
	}

	/*
	 * 取学生
	*/
	public function getStudent($id, $start, $end)
	{
		if(!$id || !$start || !$end) return Array();
		return load_model('assign', Null, true)
				->where('type', 0, true)
				->where('sid', $id)
				->where('start_date,<=', $end)
				->where('end_date,>=', $start)
				->field('assigner')
				->Column();
	}

	/*
	 * 取老师
	*/
	public function getTeacher($id, $start, $end)
	{
		if(!$id || !$start || !$end) return Array();
		//"(`status`=0 or `end_date`<='{$start}')")
		return load_model('assign', Null, true)
				->where('type', 1, true)
				->where('sid', $id)
				->where('start_date,<=', $end)
				->or_where(array('status' =>0, 'end_date,>=' => $start))
				->field('assigner')
				->Column();
	}
		
	/*
	 * 交叉
	*/
	public function is_cross($sid, $assigner, $start, $end='', $type=0)
	{
		if(!$sid || !$assigner || !$start) return false;
		$_Assign = load_model('assign', Null, true)->where('sid', $sid, true)->where('assigner', $assigner);
		if($type == 0)
		{
			if(!$end) return false;
			$_Assign->where('start_date,<=', $end)->where('end_date,>=', $start);
		}else{
			$_Assign->where("(`status`=0 or `end_date`>='{$start}')");
		}
		if($_Assign->Row()) return true;
		return false;
	}
}