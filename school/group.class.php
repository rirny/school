<?php
// 学生
class Group_Module extends School
{
	private $tpl = '';
	private $_perpage = 10;

	public function __construact(){
	
	}
	
	public function index_Action(){		
		extract($this->int_search());
		
	}

	public function do_Action()
	{
		try
		{
			if(!Http::is_post()) throw new HLPException('错误的操作！');		
			$name = Http::post('name', 'trim', '');
			if(!$name) throw new HLPException('组名不能为空！');
			$id = load_model('school_group')->insert(array(
				'name' => $name, 
				'school' => $this->school,
				'creator'=> $this->uid,
				'create_time' => TM
			));
			if(!$id) throw new HLPException('组添加失败！');
			Out(1, '添加成功！', array('id' => $id));// 返回此ID
		}catch(HLPException $e){
			Out(0, $e->getMessage());
		}
	}
}