<?php
class Event_model Extends Model
{
	protected $_table = 't_event';
	protected $_key = 'id';
	
	protected $_cache_key = 'event';
	protected $_timelife = '3600';

	public function __construct(){
		parent::__construct();
	}

	public function create($data)
	{		
		$event = $data;		
		isset($data['students']) && $data['students'] = json_encode($data['students']);
		isset($data['teachers']) && $data['teachers'] = json_encode($data['teachers']);		
		$event['id'] = parent::insert($data);		
		$res = $this->createRelation($event,  $event['students'], 'student');
		if(!$res) return false;
		$res = $this->createRelation($event,  $event['teachers'], 'teacher');
		if(!$res) return false;
		/*
		$res = load_model('teacher_student')->createMultiRelation($event['students'], $event['teacher'], $data['school']);
		if(!$res) return false;
		*/
		return $event['id'];
	}

	public function createRelation($event=array(), $users=array(), $character='teacher')
	{		
		if(!$users) return false;		
		$_Relation = load_model('course_' . $character);		
		foreach($users as $user)
		{
			$relations = array(
				'event' => $event['id'],			
				$character => $user['id'],				
				'remark' => $event['text'],
				'is_loop'=> $event['is_loop'],
				'color' => $event['color']
			);
			if(!$_Relation->getRow(array('event' => $event['id'], $character => $user['id']))) // 已建立关系的不再建立
			{
				$character == 'teacher' && $relations['priv'] = $user['priv'];	
				$res = $_Relation->insert($relations);
				if(!$res) return false;
			}
		}		
		return true;
	}

	public function compare($character = 'teacher', $user=array(), $old=array())
	{	
		$new = $lost = $keep = array();		
		if(empty($user))
		{
			$lost = $old;
		}else if(empty($old))
		{
			$new = $user;
		}else{		
			$new = array_diff_key($user, $old);
			$lost = array_diff_key($old, $user);		
			$keep = array_diff_key($old, $lost);
		}
		return compact('new', 'lost', 'keep');
	}

	public function cut_repeat($rec_type = '')
	{
		$result = array(
			'repeat' => 0,
			'times' => 1,
			'week' => array()
		);
		if($rec_type == '') return $result;
		list($str, $times) = explode("#", $rec_type);
		list($type, $step, $s, $t, $week) = explode("_", $str);
		$week = $week ? explode(",") : array();
		if($type == 'day')
		{
			$repeat = 1;
		}else{
			if($step == 2)
			{
				$repeat = 3;
			}else{
				$repeat = 2;
			}
		}
		return array_merge($result, compact('repeat', 'times', 'week'));
	}

	public function xml($item)
	{		  
        $str ="<event id='".$item['id']."' >\n";
		$str.="<start_date><![CDATA[".$item['start_date']."]]></start_date>\n";
		$str.="<end_date><![CDATA[".$item['end_date']."]]></end_date>\n";
        $str.="<text><![CDATA[".$item['text']."]]></text>\n";
        $str.="<color><![CDATA[".$item['color']."]]></color>\n";
        $str.="<readonly><![CDATA[".($item['readonly'] ? 1 : 0)."]]></readonly>\n";
        $str.="<commented><![CDATA[".($item['commented'] ? 1 : 0)."]]></commented>\n";
		$str.="<title><![CDATA[".$item['title']."]]></title>\n";  
		//$str.="<content><![CDATA[".$content."]]></content>\n";       
		return $str."</event>\n";
	}

	// 统计 >>	
	public function getSumResult($school, $where=array(), $order=array(), $limit='', $group)
	{	
		$cache_key = 'school_course_stat_result_' . $this->get_cache_key($school, $where, $order, $limit);
		$result = cache()->get($cache_key);		
		if($result === false)
		{
			$sql = $this->getSumSql($school, $where, $order, $limit, $group);
			$result = db()->fetchAll($sql);
			cache()->set($cache_key, $result, 600);
		}
		return $result;
	}
	// 统计
	public function getSumCount($school, $where=array(), $order=array(), $limit='', $group='')
	{	
		$cache_key = 'school_course_stat_count_' . $this->get_cache_key($school, $where, $group);	
		$result = cache()->get($cache_key);
		if($result === false)
		{
			$sql = $this->getSumSql($school, $where, $order, $limit, $group);
			$result = db()->fetchAll($sql);
			$result = count($result);
			cache()->set($cache_key, $result, 600);
		}
		return $result;
	}

	private function getSumSql($school, $where=array(), $order=array(), $limit='', $group='')
	{
		if(!$school) return false;		
		$whereStr = $this->getSumWhere($where);		
		$result = "select e.text,e.teachers,count(e.id) course, count(r.student) student,sum(r.attend) attend";
		$result.= " From t_course_student r Left join t_event e ON e.id=r.`event` where e.school={$school}" . ( $whereStr ? " And " . $whereStr : '');
		$order = $this->getSumOrder($order);		
		$group && $result.= " Group by ". $group;
		$order && $result.= " Order by ". $order;
		$limit && $result.= " Limit " . $limit;
		return $result;
	}

	private function getSumOrder($order){
		$result = Array();	
		if(empty($order)) $order['text'] = 0;
		foreach($order as $key => $item)
		{
			$field = '';
			switch($key)
			{
				case 'course':
				case 'student' :	
				case 'text':
					$field = $key;
					break;
			}
			$field && $result[] = $field . " " . ($item ? 'Desc' : 'Asc');
		}		
		return join(",", $result);
	}

	private function getSumWhere($param=array()){
		$result = Array();		
		if(!empty($param['keyword']))
		{
			$result['keyword'] = "e.`text` like '%{$param['keyword']}%'";
		}		
		if(!empty($param['start']) && !empty($param['end']))
		{
			$result[] = sprintf("e.start_date>'%s' And e.end_date<'%s'", $param['start'] . " 00:00:00", $param['end'] . " 23:59:59");
		}else if(!empty($param['start'])){
			$result[] = sprintf("e.start_date>'%s'", $param['start'] . " 00:00:00");
		}else if(!empty($param['end'])){
			$result[] = sprintf("e.end_date<'%s'", $param['end'] . " 23:59:59");
		}			
		return join(" And ", $result);
	}
	

	// 批量修改
	public function AjaxUpdate($events, $param)
	{
		if(!$events) return false;
		$updateData = Array();
		foreach($param as $key=>$value)
		{
			switch($key)
			{
				case 'color':
				case 'text' : 
					$updateData[$key] = $value;
					break;				
			}
		}		
		if($updateData)
		{
			$this->update($updateData, array('id,in' => $events));
			return true;
		}
		return false;
	}

	public function updateTime($events, $start, $end)
	{		
		if(empty($events)) return false;		
		foreach($events as $event)
		{
			$date = date('Y-m-d', strtotime($event['start_date']));
			$update = array(
				'start_date' => $date ." " . $start,
				'end_date' => $date ." " . $end
			);			
			if(strtotime($event['start_date']) != strtotime($update['start_date']) || strtotime($event['end_date']) != strtotime($update['end_date']))
			{
				$res = $this->update($update, $event['id']);
				if(!$res) return false;

				$teachers = json_decode($event['teachers'], true);
				$students = json_decode($event['students'], true);			
				$_event = array_merge($event, $update);

				if($teachers)
				{
					$res = $this->logs($_event, 'update', 'teacher', array_keys($teachers), $event, 1);
					if(!$res) return false;
				}
				if($students)
				{
					$res = $this->logs($_event, 'update', 'student', array_keys($students), $event, 1);
					if(!$res) return false;
				}
			}
		}
		return true;
	}
	
	public function updateDate($events, $date)
	{	
		if(empty($events) || strLen($date) != 10) return false;		
		foreach($events as $event)
		{
			$start = date('H:i:s', strtotime($event['start_date']));
			$end = date('H:i:s', strtotime($event['end_date']));

			$update = array(
				'start_date' => $date ." " . $start,
				'end_date' => $date ." " . $end
			);			
			if(strtotime($event['start_date']) != strtotime($update['start_date']) || strtotime($event['end_date']) != strtotime($update['end_date']))
			{
				$res = $this->update($update, $event['id']);
				if(!$res) return false;

				$teachers = json_decode($event['teachers'], true);
				$students = json_decode($event['students'], true);			
				$_event = array_merge($event, $update);

				if($teachers)
				{
					$res = $this->logs($_event, 'update', 'teacher', array_keys($teachers), $event, 1);
					if(!$res) return false;
				}
				if($students)
				{
					$res = $this->logs($_event, 'update', 'student', array_keys($students), $event, 1);
					if(!$res) return false;
				}
			}
		}
		return true;
	}
	

	public function addTeacher($events, $param)
	{
		if(empty($events) || empty($param)) return false;
		foreach($events as $event)
		{
			$teachers = json_decode($event['teachers'], true);			
			// 新增已存在的老师权限问题 ？
			if(!empty($teachers))
			{
				$param = array_filter($param, function($item) use ($teachers){				
					if(!array_key_exists($item['id'], $teachers)) return $item;
				});
			}			
			if(!$param) continue;
			$res = $this->createRelation($event,  $param, 'teacher');			
			if(!$res) return false;

			$new = $param;
			array_walk($new, function(&$v){
				$v = $v['id'];
			});			
			$res = $this->logs($event, 'add', 'teacher', $new, Array(), 1);

			// << 2014/5/6
			// 新增加的老师建立学生与老师关系
			$event_students = load_model('course_student')->getColumn(array('event' => $event['id'], 'status' => 0), 'student');
			if($new && $event_students)
			{
				$res =  load_model('teacher_student')->createMultiRelation($new, $event_students, $event['school']);
				if(!$res) return false;
			}
			// >>  2014/5/6

			load_model('delete_logs')->delete(array('app' => 'event', 'ext' => $event['id'], 'to,in' => $new), true); // 新logs清除
			if(!$res) return false;
			// $param = array_combine($new, $param);	
			foreach($param as $pm)
			{
				$teachers[$pm['id']] = $pm;
			}
			$teachers = json_encode($teachers);			
			$res = $this->update(compact('teachers'), $event['id']);
			if(!$res) return false;
		}		
		return true;
	}

	public function removeTeacher($events, $param)
	{
		if(empty($events) || empty($param)) return false;
		foreach($events as $event)
		{
			$teachers = json_decode($event['teachers'], true);			
			$param = array_filter($param, function($item) use ($teachers){				
				if(!empty($teachers) && array_key_exists($item['id'], $teachers)) return $item;
			});					
			if(!$param) continue;
			$lost = $param;
			array_walk($lost, function(&$v){
				$v = $v['id'];
			});
			$res = $this->logs($event, 'delete', 'teacher', $lost, $event, 2);
			if(!$res) return false;
			foreach($param as $pm)
			{
				$res = load_model('course_teacher')->delete(array('event' => $event['id'], 'teacher' => $pm['id']), true);
				if(!$res) return false;				
				unset($teachers[$pm['id']]);
			}			
			$teachers = json_encode($teachers);			
			$res = $this->update(compact('teachers'), $event['id']);
			if(!$res) return false;
		}		
		return true;
	}
	public function addStudent($events, $param)
	{
		if(empty($events) || empty($param)) return false;
		foreach($events as $event)
		{
			$students = json_decode($event['students'], true);			
			if(!empty($students))
			{
				$param = array_filter($param, function($item) use ($students){				
					if(!array_key_exists($item['id'], $students)) return $item;
				});
			}			
			if(!$param) continue;
			$res = $this->createRelation($event,  $param, 'student');
			if(!$res) return false;
			
			$new = $param;
			array_walk($new, function(&$v){
				$v = $v['id'];
			});			


			// << 2014/5/6
			// 新增加的老师建立学生与老师关系
			$event_teachers = load_model('course_teacher')->getColumn(array('event' => $event['id'], 'status' => 0), 'teacher');
			if($new && $event_teachers)
			{
				$res = load_model('teacher_student')->createMultiRelation($event_teachers, $new, $event['school']);
				if(!$res) return false;
			}
			// >>  2014/5/6

			$res = $this->logs($event, 'add', 'student', $new, Array(), 1);
			load_model('delete_logs')->delete(array('app' => 'event', 'ext' => $event['id'], 'ext,in' => $new), true); // 新logs清除
			if(!$res) return false;
			foreach($param as $pm)
			{
				$students[$pm['id']] = $pm;
			}			
			$students = json_encode($students);			
			$res = $this->update(compact('students'), $event['id']);
			if(!$res) return false;
		}		
		return true;
	}
	public function removeStudent($events, $param)
	{
		if(empty($events) || empty($param)) return false;        
		foreach($events as $event)
		{
			$students = json_decode($event['students'], true);
            if(is_array($param))
            {
                $param = array_filter($param, function($item) use ($students){				
                    if(!empty($students) && array_key_exists($item['id'], $students)) return $item;
                });
                if(!$param) continue;
                $lost = $param;
                array_walk($lost, function(&$v){
                    $v = $v['id'];
                });
            }else{      // 单个删除          
                $_tmp = array_filter($students, function($item) use ($param){				
                    if(!empty($param) && $item['id'] == $param) return $item;
                });
                if(!$_tmp) continue;
                $lost = array($param); 
            }
			$res = $this->logs($event, 'delete', 'student', $lost, $event, 1);            
			if(!$res) return false;
			foreach($lost as $pm)
			{                
				load_model('course_student')->delete(array('event' => $event['id'], 'student' => $pm), true); 						
				unset($students[$pm]);
			}			
			$students = json_encode($students);	
            
			$this->update(compact('students'), $event['id']);
			//if(!$res) return false;
			$this->updateStat($event['id']);
		}		
		return true;
	}
	
	public function updateStat($event)
	{
		$rs = load_model('course_student')->getAll(array('event' => $event, 'attended' => 1), '','',false, false, 'attend,`leave`,absence,attended');
		$attend = $absence = $leave = 0;
		foreach($rs as $item)
		{
			if($item['attend']) 
			{
				$attend ++;
			}else if($item['absence']){
				$absence ++;
			}else{
				$leave++;
			}
		}
		$this->update(compact('attend', 'absence', 'leave'), $event);
		return true;
	}
	public function logs($event, $action='add', $character='student', $target=Array(), $old=Array(), $type = 2)
	{
		$logs = array( 
			'app' => 'event', 
			'act' => $action, 
			'character'=> $character,
			'creator' => $event['school'], 
			'target' => $target, 
			'ext' => array(),
			'source' => array(),
			'data' => array()
		);
		$logs['source'] = array(
			'event' => $event['id'],
			'is_loop' => 0,
			'whole' => 0,
			'school'=> $event['school']						
		);		
		if($action == 'update' || $action == 'delete')
		{
			$logs['source']['old']['text'] = $old['text'];
			$logs['source']['old']['is_loop'] = $old['is_loop'];
			$logs['source']['old']['rec_type'] = $old['rec_type'];
			$logs['source']['old']['start_date'] = $old['start_date'];
			$logs['source']['old']['end_date'] = $old['end_date'];
			$logs['source']['old']['school'] = $old['school'];	
		}		
		$logs['type'] = 0;//$type;
		return logs('db')->add('event', 'EventAction', $logs);	
	}
}