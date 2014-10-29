<?php
class Area_model Extends Model
{
	protected $_table = 't_area';
	protected $_key = 'id';
	
	protected $_cache_key = 'area';
	protected $_timelife = '3600';

	public function __construct(){
		parent::__construct();
	}

	
}