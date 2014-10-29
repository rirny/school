<?php
set_time_limit(0);
class import_Api extends Api
{
    
    public function __construct() {
        
    }
    
	private $userImportNum = 0;
	private $studentImportNum = 0;
	private $tm = 0;

    public function student()
    {
		$school = http::post("school", "int", 0);
		if(!$school) throw new exception("机构不能为空！");		
		$source = array();
		if(!isset($_FILES['source'])) throw new exception("请上传Excel文件");
		$CSV = $_FILES['source']['tmp_name'];
		$this->tm = time();
		db()->begin();
		try{
			$source = $this->loadExcel($CSV);			
			// if(!isset($source['cols']) || ($source['cols'] != 2)) throw new exception("文件格式不正确！");
			
			$success = array();
			$error = array();
			if(!isset($source['data'][0])) throw new exception("无数据！");
			$title = $source['data'][0];			
			if($title[0] != '学生姓名' || $title[1] == '' || $title[2] == '' || $title[3] == '' ) throw new exception("文件格式不正确！");	
			$sourceData = $source['data'];			
			$studentArr = array();
			$sourceData = $source['data'];

			for($i=1;$i< count($sourceData); $i++)
			{		
				$name = "";					
				if(empty($sourceData[$i][0]))
				{
					$error[$i] = "学生姓名不能为空！"; 
					continue;
				}else{
					$name = $sourceData[$i][0];
				}

				$parents = array(); // 家长
				(isset($sourceData[$i][1]) && strlen($sourceData[$i][1]) == 11) && $parents[3] = $this->getUser($sourceData[$i][1]);
				(isset($sourceData[$i][2]) && strlen($sourceData[$i][2]) == 11) && $parents[2] = $this->getUser($sourceData[$i][2]);	
				(isset($sourceData[$i][3]) && strlen($sourceData[$i][3]) == 11) && $parents[4] = $this->getUser($sourceData[$i][3]);
				$birthday = '0000-00-00';
				
				if(empty($parents))
				{
					$error[$i] = "联系方式不能为空"; continue;
				}

				$student = $this->hasStudent($name, array_values($parents)); // 是否已经有此学生档案		
				
				if(!$student)
				{
					$student = $this->createStudent(compact('name', 'birthday')); // 没有则创建档案	
					$newParent = array_values($parents);
					load_model('student')->update(array('creator' => $newParent[0]), $student);
				}
				
				if($student)
				{					
					foreach($parents as $key => $val)
					{
						if(!in_array($key, array(1,2,3,4))) $key == 4;
						$this->createRelation($val, $student, $key); // 创建家长关系
					}

					// 学校relation
					$this->schoolRelation($school, $student); // 创建机构学生关系
					$success[] = array($name, $sourceData);
				}else{
					$error[$i] = "学生导入败！"; continue;
				}				
			}			
			
			/*
			if($source['cols'] == 2) // 旧数据
			{
				$studentArr = array();
				for($i=1;$i< count($source); $i++)
				{					
					if(strlen($source[$i][0]) < 11)
					{
						$error[$i] = "手机格式不正确"; continue;
					}
					if(empty($source[$i][1]))
					{
						$error[$i] = "学生姓名不能为空！"; continue;
					}					
					$user = $this->getUser($source[$i][0]);
					$student = $this->hasStudent($source[$i][1], array($user)); // 是否已经有此学生档案
					if(!$student)
					{
						$student = $this->createStudent($source[$i][1]); // 没有则创建档案				
						load_model('student')->update(array('creator' => $user), $student);
					}else{
						$this->studentImportNum++;
					}
					$this->createRelation($val, $student, 4);
					$this->schoolRelation($school, $student);
					$success[$i] = $source[$i];				
				}
			}else if($source['cols'] == 5){ // 致郎 1=姓名 2、生日 3 妈妈 4爸爸

				$studentArr = array();
				$sourceData = $source['data'];

				for($i=1;$i< count($sourceData); $i++)
				{		
					$name = "";					
					if(empty($sourceData[$i][0]))
					{
						$error[$i] = "学生姓名不能为空！"; 
						continue;
					}else{
						$name = $sourceData[$i][0];
					}
					
					$birthday = $sourceData[$i][1];					
					
					$parents = array(); // 家长
					(isset($sourceData[$i][2]) && strlen($sourceData[$i][2]) == 11) && $parents[1] = $this->getUser($sourceData[$i][2]);
					(isset($sourceData[$i][3]) && strlen($sourceData[$i][3]) == 11) && $parents[3] = $this->getUser($sourceData[$i][3]);	
					
					if(empty($parents))
					{
						$error[$i] = "联系方式不能为空"; continue;
					}				

					$student = $this->hasStudent($name, array_values($parents)); // 是否已经有此学生档案
					
					if(!$student)
					{
						$student = $this->createStudent(compact('name', 'birthday')); // 没有则创建档案				
						load_model('student')->update(array('creator' => $parents[0]), $student);
					}

					if($student)
					{
						foreach($parents as $key => $val)
						{
							if(!in_array($key, array(1,2,3,4))) $key == 4;
							$this->createRelation($val, $student, $key); // 创建家长关系
						}

						// 学校relation
						$this->schoolRelation($school, $student); // 创建机构学生关系
						$success[] = array($name, $sourceData);
					}else{
						$error[$i] = "学生导入败！"; continue;
					}
				}			
				
			}else{ // 关系数据
				$studentArr = array();
				$sourceData = $source['data'];
				
				for($i=1;$i< count($sourceData); $i++)
				{		
					$studentName = "";					
					if(empty($sourceData[$i][0]))
					{
						$error[$i] = "学生姓名不能为空！"; continue;
					}else{
						$studentName = $sourceData[$i][0];
					}					
					if(
						(isset($source[$i][1]) && strlen($sourceData[$i][1]) < 11) &&
						(isset($source[$i][2]) && strlen($sourceData[$i][2]) < 11) &&
						(isset($source[$i][3]) && strlen($sourceData[$i][3]) < 11)
					)						
					{
						$error[$i] = "手机格式不正确"; continue;
					}
					isset($studentArr[$studentName]) || $studentArr[$studentName] = array();					 
					(isset($sourceData[$i][1]) && strlen($sourceData[$i][1]) == 11) && $studentArr[$studentName][] = $sourceData[$i][1];
					$studentArr[$studentName] = array_unique($studentArr[$studentName]);
				}
				
				foreach($studentArr as $key => $item)
				{
					$users = array();
					foreach($item as $account)
					{
						$uid = $this->getUser($account);
						$uid && $users[] = $uid;
					}
					$student = $this->hasStudent($key, $users); // 是否已经有此学生档案
					if(!$student)
					{
						$student = $this->createStudent($key); // 没有则创建档案				
						load_model('student')->update(array('creator' => $users[0]), $student);
					}
					if(!$student)
					{
						$error[] = $student;
						continue;
					}
					
					foreach($users as $val)
					{
						$this->createRelation($val, $student, 4); // 创建家长关系
					}
					$this->schoolRelation($school, $student); // 创建机构学生关系
					$success[] = array($key, $item);
				}
				
			}
			*/
			echo date('Y-m-d H:i:s',  $this->tm) . "[{$this->tm}]成功导入" . count($success) . "条记录，成功创建" . $this->userImportNum ."个用户，学生档案" . $this->studentImportNum . "\n";
			
			if($error)
			{
				foreach($error as $key => $item)
				{
					echo "第" . $key . "行," . $item . "\n";
				}
			}
			// throw new exception("stop");
			db()->commit();
		}catch(exception $e)
		{
			echo "导入失败！" . $e->getMessage();
			db()->rollback();
		}		
	}
	
	// 旧版CSV
	// return array('student', array('phone1', 'phone2'))
	private function loadExcel($file)
	{
		$result = array('rows' => 0, 'cols' => 0, 'data' => array());	
		require_once LIB . '/PHPExcel.php';
		$objReader = new PHPExcel_Reader_Excel2007(); //use excel2007
		try
		{
			$objPHPExcel = $objReader->load($file); //指定的文件
			$sheetCount = $objPHPExcel->getSheetCount();
			$sheetNames = $objReader->listWorksheetNames($file);
			
			$sheet = $objPHPExcel->getSheet(0);
			$highestRow = $sheet->getHighestRow(); // 取得总行数
			$highestColumn = $sheet->getHighestColumn(); // 取得总列数
			$col = PHPExcel_Cell::columnIndexFromString($sheet->getHighestColumn());
				
			for($j = 1;$j <= $highestRow; $j++) {
				$item = array();
				for($k='A';$k<=$highestColumn;$k++)
				{
					$data = $objPHPExcel->getActiveSheet()->getCell("$k$j")->getValue();
					$item[] = $data;
				}
				empty($item[0]) || $result['data'][] = $item;
			}
			$result['cols'] = isset($result['data'][0]) ? count($result['data'][0]) : 0;
		}catch(Exception $e)
		{
			return $result;
		}
		return $result;
	}

	private function old($file)
	{
		
	}
	// 多关系
	private function multiRelation($source)
	{
		
	}

	private function getUser($account)
	{
		if($account == '' && strlen($account) != 11) return 0; // 非手机号
		$user =  load_model('user')->getRow(array('account' => $account));
		if(!$user)
		{
			$data = array(
				'account' => $account,
				'password'=> md5(md5('000000') . 12345),
				'login_salt' => 12345,
				'create_time'=> $this->tm
			);			
			$data['setting'] = json_encode(array(
				"hulaid" => 0,
                "friend_verify" => 1,
                "notice" => array(
                    "method" => 0,
                    "types" => "1,2,3,4,5"
				)
			));						
			$id = load_model('user')->insert($data);
			$this->userImportNum ++;
			load_model('user')->update(array('hulaid' => 'h_' . sprintf("%u", crc32($id))), $id);
		}else{
			$id = $user['id'];
		}
		return $id;
	}

	public function createStudent($data = array())
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
		$this->studentImportNum ++;
		
		$data = array_merge($data, array(			
			'name_en' => $name_en,
			'create_time' => $this->tm
		));
		
		if(!empty($data['birthday']))
		{
			$data['birthday'] = date('Y-m-d', strtotime(str_replace(array('年', '月', '日'), array("-", "-", ""), $data['birthday'])));
		}else{
			$data['birthday'] = "0000-00-00";
		}

		return load_model('student')->insert($data);		
	}
	
	private function createRelation($user, $student, $relation=2)
	{
		$res = load_model('user_student')->getRow(array('user' => $user, 'student' => $student, 'relation' => $relation));
		if(!$res)
		{			
			load_model('user_student')->insert(array('user' => $user, 'student' => $student, 'relation' => $relation, 'create_time' => $this->tm));
		}
	}
	
	private function schoolRelation($school, $student)
	{
		$res = load_model('school_student')->getRow(array('school' => $school, 'student' => $student));
		if(!$res)
		{
			load_model('school_student')->insert(array('school' => $school, 'student' => $student, 'create_time'=> $this->tm));
		}
	}

	private function hasStudent($student, $users)
	{
		if(!$student) return false;
		$existStudent = load_model('student')->getRow(array('name' => $student, 'creator,in' => $users));
		if($existStudent)
		{
			return $existStudent['id'];
		}
		return false;
	}
}

