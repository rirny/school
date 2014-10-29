<?php
class Download_Module extends School
{
	public function __construact(){
		exit('ee');
	}

	public function index_Action() {
		$version = Http::get('version','trim', '');
		$source = Http::get('source','int', 1);
		if($version){
			if(is_numeric($version)){				
				$info = load_model('version')->getRow(array('source'=>$source,'version'=>$version));
				if($info && $info['url']){
					$andChar = strpos($info['url'],'?') > 0 ?'&t='.time():"?t=".time(); 
					header("Location:".$info['url'].$andChar); 
					exit;
				}
			}else{
				$path = array(
					'.apk'=>'android',
					'.ipa'=>'ios',
				);
				$extend = mb_substr($version,-4);
				if(in_array($extend,array_keys($path))){
					if(in_array($version,array('latest.apk','hulapai.apk','latest.ipa','hulapai.ipa'))){
						$source = $version == 'latest.apk' || $version == 'hulapai.apk' ? 1 : 2;
						$info = load_model('version')->getRow(array('source'=>$source,'type'=>1), false, '*', 'ID desc');
						if($info && $info['url']){
							$andChar = strpos($info['url'],'?') > 0 ?'&t='.time():"?t=".time();
							header("Location:".$info['url'].$andChar);
							exit;
						}
					}else{
						$path = Config::get('upload', 'attach');
						$file = $path .'/package/'.$path[$extend].'/'.$version;
						if(file_exists($file)){
							$andChar = '?t='.time(); 
							header("Location:". $path. '/package/'.$path[$extend].'/'.$version.$andChar); 
							exit;
						}
					}
				}
			}
			$this->show_error('文件不存在', '/download');
		}else{
			$info = load_model('version')->getRow(array('source'=>$source,'type'=>1), false, '*','ID desc');
			$this->assign('info', $info);
			$tpl = "download" . ($this->is_mobile ? 'mobile' : '');
			$this->display($tpl);
		}
	}
}