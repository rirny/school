<?php
/*
 * 预设课程
 * 
*/
class Series_Module extends School
{
	private $_perpage = 30;

	public function __construact(){
		parent::__construct();
	}
	
	// 预设课程
	public function Index_Action()
	{
		$param = $this->int_search();
		$param['where']['school'] = $this->school;
		$result = load_model('series', Null, true)->get_series($param, true);
		$course = load_model('course_type', NULL, true)
			->field('name,id')
			->result();
		$this->assign('paginator', $result['paginator']);
		$rules = array();
		$color = array();
			
		foreach($result['data'] as &$item)
		{
			$item['rule'] = implode('；', $item['rule']);
			foreach($course as $key => $value)
			{
				if($value['id'] == $item['course'])
					$item['course'] = $value['name'];
			}
			$color = $this->getColors();
			$item['vcolor'] = $color[$item['color']];
		}
		$this->assign('result', isset($result['data']) ? $result['data'] : Array());
		$this->display('school/schedule/series');
	}

	public function Add_Action(){
		$this->assign('colors', $this->getColors());
		$this->assign('curTimeStamp', time()); 
		$this->display('school/schedule/add');
	}

	public function Save_Action()
	{
		import('schedule');
		$rule = array();
		$title = Http::post('title', 'string', '');
		$course = Http::post('course', 'int', 0);
		$color = Http::post('color', 'int', 0);
		$rules = Http::post('rule', 'trim', '');
		$create_time =  time();
		$school = $this ->school;
		$creator = $this -> uid;
		$week = $rules['week'];
		for($i=0; $i< count($rules['start']); $i++)
		{
			$rule[$i]['start'] = $rules['start'][$i];
			$rule[$i]['end'] = $rules['end'][$i];
			$rule[$i]['week'] = $rules['week'][$i];
			$rule[$i]['times'] = $rules['times'][$i];
		}
		$sortAry = Schedule::getSort($rule);
		$sort = $sortAry[0]['sort'];
		$rule = json_encode($rule);
		$week = json_encode($week);
		$id = load_model('series', Null, true)->insert(compact('title', 'course','create_time','creator','school','sort','color', 'rule', 'week'));
		if(!$id)	 throw new HLPException('操作失败!');
		if($id)
		{
			echo "<script>window.top.art.dialog({'content' : '添加成功', 'lock' : true, 'icon' : 'succeed', 'ok' : function(){window.top.right.location.href='/series';}});</script>";
		}else{
			$this->show_error('MLGB');
		}
	}
	
	// 0正常 1、删除 2、已排课
	public function Delete_Action()
	{
		if(!Http::is_post()) throw new HLPException('非法操作!');
		$id = http::post('id', 'int', 0);		
		$_Series = load_model('series', Null, true);		
		$res = $_Series->where('id', $id)->where('status', 0)->Delete();
		if($res) Out(1, '删除成功!');
		throw new HLPException('删除失败!');
	}
	
	// 结算
	public function Close_Action()
	{
		$sid = Http::post('sid', 'int', 0);
		$date = Http::post('date', 'trim');
		$min = mktime(0,0,0, date('n'), date('j'), date('Y'));
		db()->begin();
		try{
			$time = strtotime($date);
			if(!$sid) throw new HLPException('参数错误！');
			if($time < $min) throw new HLPException('结课日期不能早于今天！');
			$time = strtotime('+1 day',strtotime($date));
			$date = date('Y-m-d',$time);
			$assign = load_model('assign', Null, true)
				->where('sid', $sid)
				->Result();
			if(!$assign) throw new HLPException('无权限或参数不匹配！');
			if($assign['end_date'] != '0000-00-00' ) 
			{	
				//var_dump($assign['end_date']);
				 if(strtotime($assign['end_date']) < $time)  
					 throw new HLPException('课程已结束不能结课！');
			}
			$_Series = load_model('series', Null, true) ->where('id',$sid)
				->update(array( 'status' => 2));
			foreach($assign as $item)
			{
				$character = $item['type']?'teacher':'student';
				if(strtotime($item['end_date'])) 
				{
					 if(strtotime($item['end_date']) < $time)  
						continue;
				}

				// 所有未发生的记录删除
				load_model('schedule_record', Null, true)
					->where('sid', $sid)->where('type', 0)
					->where('assigner', $item['assigner'])
					->where('index,>', $time)
					->delete(true);
				// 所有结束时间 > time的结束时间
				load_model('assign', Null, true)
					->where('sid', $sid)
					->where('assigner', $item['assigner'])
					->where('end_date,>', $date)
					->update(array('end_date' => $date, 'status' => 2));
				// 相关评论
				load_model('comment', Null, true)
					->where('sid', $sid)
					->where($character, $item['assigner'])
					->where('index,>', $time)
					->where('pid', 0)
					->delete(true);
			}
			db()->commit();
		}catch(HLPException $e){			
			db()->rollback();
			Out(0, $e->getMessage());
		}	
		Out(1, '结课成功');		
	}

	public function Update_Action()
	{
		if(!Http::is_post()) throw new HLPException('非法操作!');
		$id = http::post('id', 'int', 0);
		$field = http::post('field', 'string', '');
		$value = http::post('value');
		$fileds = Array('title', 'color', 'course');
		if(in_array($field, $fields)) Out(0, '修改失败');
		$res = load_model('series', Null, true)->where('id', $id)->update(array($field => $value));
		Out(1, '修改成功！');
	}

	public function Ajax_Action(){	
		if(!Http::is_post()) throw new HLPException('非法操作!');	
		extract(Http::post());
		if($action=='getCourse') //获得课程
		{
			$result = load_model('course_type',NULL,true)
				->field('id,name')
				->where('pid',$type)
				->result();
			if(!$result) throw new Exception("获取数据失败!");
			Out(1, 'success', $result);
		}
		else
			Out(0,'错误的请求！');
	}	
}