<?php
/*
 * 课程
*/
class Schedule_Model Extends Model
{
	protected $_table = 't_schedule';

	protected $_key = 'id';	
	protected $_cache_key = 't_schedule';
	protected $_timelife = '3600';

	public function __construct(){
		parent::__construct();
	}
	
	public function getList($where='', $order=array(), $limit="", $fields="*", $cache=false)
	{
		$sql = "select {$fields} from " . $this->_table . " As s";
		$sql.= " LEFT JOIN t_course_type c ON  c.id = s.course  where s.status<2";		
		$where && $sql .= " AND " . $where;
		$order = $this->getOrder($order);
		$order && $sql.= " Order by " . $order;
		$limit = $this->getLimit($limit);
		$limit && $sql .= " Limit " . $limit;
		$res = db()->fetchAll($sql);
		return $res;
	}

	public function getCount(	$where='', $order=array(),$fields='count(*)')
	{
		$sql = "select {$fields} from " . $this->_table . " As s";
		$sql.= " LEFT JOIN t_course_type c ON  c.id = s.course  where s.status<2";		
		$where && $sql .= " AND " . $where;	
		return db()->fetchOne($sql);
	}

	public function getRule($where='',  $order=array() ,$fields="*", $cache=false)
	{
		$sql = "select {$fields} from t_schedule_rule As r";
		$sql.= " LEFT JOIN ".$this->_table." s ON  r.schedule=s.id LEFT JOIN t_course_type c ON s.course=c.id Where s.`status`=1 AND s.course=c.id";		
		$where && $sql .= " AND " . $where;
		$order = $this->getOrder($order);
		$order && $sql.= " Order by " . $order;
		$res = db()->fetchAll($sql);
		return $res;
	}

	public function getTeacher($school)
	{
		$sql = "select t.name,sa.assign,sa.schedule from t_schedule_assign sa INNER JOIN t_user t ON sa.assign=t.id  Where sa.`status`=0 AND sa.type=1 AND sa.school=".$school;	
		$res = db()->fetchAll($sql);
		return $res;
	}

	public function getStudent($school)
	{
		$sql = "select sa.assign,sa.schedule from t_schedule_assign sa INNER JOIN t_student s ON sa.assign=s.id  Where sa.`status`=0 AND sa.type=0 AND s.`status`=0 AND sa.school=".$school;
		$res = db()->fetchAll($sql);
		return $res;
	}
}