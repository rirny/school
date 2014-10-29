<?php
class Lead_model Extends Model
{
	protected $_table = 't_student_resource';
	protected $_key = 'id';
	
	protected $_cache_key = 'lead';
	protected $_timelife = '3600';

	public function __construct(){
		parent::__construct();
	}
	
	
}