<?php
class Notify_model Extends Model
{
	protected $_table = 't_notify';
	protected $_key = 'id';
	
	protected $_cache_key = 'notify';
	protected $_timelife = '3600';

	public function __construct(){
		parent::__construct();
	}
}