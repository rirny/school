<?php
/*
 * 课程
*/
class Schedule_sh_Model Extends Model
{
	protected $_table = 't_schedule';

	protected $_key = 'id';	
	protected $_cache_key = 't_schedule';
	protected $_timelife = '3600';

	public function __construct(){
		parent::__construct();
	}
}