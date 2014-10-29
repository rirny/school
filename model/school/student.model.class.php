<?php
class Student_Model Extends Model
{
	protected $_table = 't_student';

	protected $_key = 'id';	
	protected $_cache_key = 'student';
	protected $_timelife = '3600';

	public function __construct(){
		parent::__construct();
	}

	public function create($data = array())
	{
		if(is_array($data))
		{
			extract($data);
		}else{
			$name = $data;
		}
		if($name == '') return false;

		load('ustring');
		$name_en = Ustring::topinyin($name);
		
		$data = array_merge($data, array(			
			'name_en' => $name_en,
			'create_time' => TM
		));
		
		if(!empty($data['birthday']))
		{
			$data['birthday'] = str_replace(array('年', '月', '日'), array("-", "-", ""), $data['birthday']);
			$data['birthday'] = str_replace("/", "-", $data['birthday'] );			
		}else{
			$data['birthday'] = "0000-00-00";
		}
		return $this->insert($data);		
	}

	public function get_user()
	{
	
	}
}