<?php
class Info_Module extends School
{
	public function __construact(){
		
	}
	
	public function index_Action()
	{		
		if(Http::is_post())
		{
			try{
				$name = Http::post('name', 'trim', '');
				if(!$name) throw new HLPException('机构名称不能为空！');
				$type = Http::post('type', 'int', 0);
				// if(!$type) throw new HLPException('机构类型为必选！');
				$contact = Http::post('contact', 'trim', '');
				if(!$contact) throw new HLPException('联系人为必填');
				$province = Http::post('province', 'int', 0);
				$city = Http::post('city', 'int', 0);
				$area = Http::post('area', 'int', 0);
				if(!$area) throw new HLPException('所在地区为必填');
				$phone = Http::post('phone', 'trim', '');
				$phone2 = Http::post('phone2', 'trim', '');
				if(!$phone && !$phone2) throw new HLPException('联系方式至少填写一个');
				$address = Http::post('address', 'trim', '');
				$description = Http::post('description', 'trim', '');
                $web = Http::post('web', 'trim', '');
                $code = Http::post('code', 'trim', '');
				$operator = $this->uid;
                $school = load_model('school')->getRow($this->school);
				
				$data = compact('name', 'type', 'contact', 'province', 'city', 'area', 'web', 'province', 'phone', 'phone2', 'address', 'description', 'operator');
				
				$lng = $lat = 0.00000000;
				$province || $province = $school['province'];
				$address || $address = $school['address'];
				if($province && $address)
				{
					import('map');					
					$province = load_model('area')->getRow(array('id' => $province));
					if(preg_match("/[\x7f-\xff]/", $address)) {  //判断字符串中是否有中文					
						 list($lng, $lat) = Map::getCoordsFromAddress($province['title'], $address);
					}
				}
				if(intVal($lng))
				{
					$data = array_merge($data ,compact('lng', 'lat'));
				}
				
                if($school['code_set'] == 0 && $school['code'] != $code) // 未设置过
                {                    
                    $data['code'] = $code;
                    $data['code_set'] = 1;
                }
				load_model('school')->update($data, $this->school);
				$this->show_message('修改成功！', 'succeed', array(
					'back' => array('title' => '返回查看', 'url' => '/info', 'default' => 1)					
				), 'open');
			}catch(HLPException $e){
				$this->show_message('修改成功！', 'succeed', array(
					'back' => array('title' => '返回查看', 'url' => '/info', 'default' => 1)					
				), 'open');
			}
		}else{
			$result = load_model('school')->getRow($this->school);
			$result['logo'] = imageUrl($this->school, 3, 200);
			$this->assign('timestamp', time());
			$this->assign('result', $result);
			$this->assign('types', $this->getSchoolTypes());
			$province_source = load_model('area')->getAll(array('pid' => 0), '', '`sort` asc', true, false, 'id,title');
			$province_ids = array_map(create_function('$item', 'return $item[\'id\'];'), $province_source);
			$province_vals = array_map(create_function('$item', 'return $item[\'title\'];'), $province_source);
			$provinces = array_combine($province_ids, $province_vals);			
			$this->assign('session_name', session_name());
			$this->assign('provinces', $provinces);
			$this->display('school/info');
		}
	}	
}