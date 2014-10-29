<?php
class Cms_model Extends Model
{
	protected $_table = '';
	protected $_key = '';	

	
	public function __construct(){
		parent::__construct();

	}
	
	
	public function getList()
	{
		$sql = "select v.*,c.content from phpcms.v9_hulapai v left join phpcms.v9_hulapai_data c on v.id=c.id where catid=17";
		$result = db()->fetchAll($sql);
		return $result;
	}

}