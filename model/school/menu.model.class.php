<?php
class Menu_model Extends Model
{
	protected $_table = 't_school_menu';
	protected $_key = 'id';
	
	protected $_cache_key = 'menu';
	protected $_timelife = '3600';

	public function __construct(){
		parent::__construct();
	}

}