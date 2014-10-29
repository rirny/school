<?php
class message_Module extends School
{
	
	private $message = '';
	private $handle = array();
	private $code = 0;
	private $time = 0;
	private $tpl = 'message';
    private $target = 0;

	public function __construact(){
		
	}

	public function index_Action()
	{		
		$this->assign('result', $o = $this->_Format());		
		Http::delete_session('message');
		$this->display("message/" . $this->tpl);
	}

	private function toArray()
	{
		return array(
			'message' => $this->message,
			'handle' => $this->handle,
			'code' => $this->code,
			'time' => $this->time,
            'target' => $this->target,
		);
	}

	private function _Format()
	{
		extract(Http::get_session('message'));
		isset($message) && $this->message = $message;
		isset($handle) && $this->handle = $handle;
		isset($code) && $this->code = $code;
		isset($time) && $this->time = $time;		
		return $this->toArray();
	}
}