<?php
/**
 * 循环课程
*/
class Repeat
{
	const DAY_TIME = 86400;
	static $classes = 0;

	public static function resolve($start, $end, $rec_type, $length)
	{
		$result = array();		
		if(!$start || !$end || !$rec_type || !$length) return $result;		
		list($rec_str, $rec_rate) = explode('#', $rec_type, 2);
		list($type, $step, $_t1, $_t2, $week) = explode("_", $rec_str, 5);
		$rec_rate = intval($rec_rate);
		$start = strtotime($start);
		$end = strtotime($end);

		switch($type){
			case 'day':	// 每几天				
				while($start < $end)
				{
					if($rec_rate > 0 && count($result) == $rec_rate) break; // 设置了循环次数
					$_end = $start + $length;
					$result[] = array(						
						'start_date' => datetime('Y-m-d H:i:s', $start),
						'end_date' => datetime('Y-m-d H:i:s', $_end),
						'length' => $start
					);
					$start += $step * self::DAY_TIME;					
				}
				break;
			case 'week':				
				$week_day = explode(",", $week);
				$minw = count($week_day) > 1 ? min(array_filter($week_day)) : min($week_day);
				$startw = date('w',$start);
				$minw == 0 && $startw != $minw && $minw = 7;
				$oldstart = $start;
				$start -= ($startw-$minw) * self::DAY_TIME;
				while($end > $start)
				{	
					if($rec_rate > 0 && count($result) == $rec_rate) break; // 设置了循环次数					
					foreach($week_day as $w)
					{			
						$ev_start = self::month_week($start, $w, 1);				
						$_end = $ev_start + $length;
						if($ev_start < $oldstart) continue;
						if($ev_start > $end) continue;
						$result[] = array(
							'start_date' => datetime('Y-m-d H:i:s', $ev_start),
							'end_date' => datetime('Y-m-d H:i:s', $_end),
							'length' => $ev_start
						);						
					}
					$start += ($step * 7) * self::DAY_TIME;					
				}
				break;
			case 'month':
				while($start < $end)
				{
					if($rec_rate > 0 && count($result) == $rec_rate) break; // 设置了循环次数
					$_end = $start + $length;
					$result[] = array(
						'start_date' => datetime('Y-m-d H:i:s', $start),
						'end_date' => datetime('Y-m-d H:i:s', $_end),
						'length' => $start
					);
					$start += mktime(0,0,0, date('m', $start) + $step, date("d", $start), date("Y", $start));			
				}				
				break;
		}
		if(!$result) return $result;
		sort($result);
		if($rec_rate > 0 && count($result) > $rec_rate){
			$result = array_chunk($result,$rec_rate);
			$result = $result[0];
		}
		return $result;
	}
	
	/*
	 * start 起始时间 $week 周几 step 第几周
	 * 取start的周几/当前月几周周几
	*/
	public static function month_week($start, $week, $setp=1)
	{
		$w = date("w", $start);		
		if($week > $w)
		{
			$date = $start + ($week - $w + ($setp -1) * 7) * self::DAY_TIME;
		}else if($week < $w){
			$date = $start +  (7 - $w + $week + ($setp -1) * 7 ) * self::DAY_TIME;
		}else
		{
			$date = $start + ($setp -1) * 7;
		}
		return $date;
	}
	
	
	public static function start_end_date($start,$num,$rec_type, $length)
	{
		$result = array();		
		if(!$start || !$num || !$rec_type || !$length) return $result;		
		list($rec_str, $rec_rate) = explode('#', $rec_type, 2);
		list($type, $step, $_t1, $_t2, $week) = explode("_", $rec_str, 5);

		$rec_rate = intval($rec_rate);
		$start = strtotime($start);
		$i = 0;
		switch($type){
			case 'day':	// 每几天				
				while($i < $num)
				{
					if($rec_rate > 0 && count($result) == $rec_rate) break; // 设置了循环次数
					$_end = $start + $length;
					$result[] = array(						
						'start_date' => datetime('Y-m-d H:i:s', $start),
						'end_date' => datetime('Y-m-d H:i:s', $_end),
						'length' => $start
					);
					$start += $step * self::DAY_TIME;
					$i++;					
				}
				break;
			case 'week':
				$week_day = explode(",", $week);
				$minw = count($week_day) > 1 ? min(array_filter($week_day)) : min($week_day);
				$startw = date('w',$start);
				$minw == 0 && $startw != $minw && $minw = 7;
				$oldstart = $start;
				$start -= ($startw-$minw) * self::DAY_TIME;
				while($i < $num)
				{	
					if($rec_rate > 0 && count($result) == $rec_rate) break; // 设置了循环次数
					foreach($week_day as $w)
					{			
						$ev_start = self::month_week($start, $w, 1);						
						$_end = $ev_start + $length;
						if($ev_start < $oldstart) continue;
						$result[] = array(
							'start_date' => datetime('Y-m-d H:i:s', $ev_start),
							'end_date' => datetime('Y-m-d H:i:s', $_end),
							'length' => $ev_start
						);
						$i++;							
					}
					$start += ($step * 7) * self::DAY_TIME;					
				}
				break;
			case 'month':
				break;
		}
		if(!$result) return $result;
		sort($result);
		if($rec_rate > 0 && count($result) > $rec_rate){
			$result = array_chunk($result,$rec_rate);
			$result = $result[0];
		}
		$start = array_shift($result);
		$end = $num > 1 ? array_pop($result) : $start;
		return array($start['start_date'],$end['end_date']);
	}
	

	public static function day($start, $times, $length)
	{		
		$result = Array();
		if($times < 2 || $length < 0) return $result;
		$start_time = strtotime($start . ":00");
		if(empty($start_time)) return $result;
		while($times > 0)
		{			
			$start_date = date('Y-m-d H:i:s', $start_time);
			$end_date = date('Y-m-d H:i:s', $start_time + $length);
			$result[] = array(
				'start_date' => $start_date,
				'end_date' => $end_date,
				'length' => $start_time
			);
			$start_time += self::DAY_TIME;
			$times--;
		}		
		return $result;
	}	

	public static function week($start, $times, $week = array(), $length)
	{		
		$result = Array();		
		if(empty($week) || $times < 2 || $length < 0) return $result;
		$start_time = strtotime($start);
		if(empty($start_time)) return $result;
		$w = date('w', $start_time);		
		while($times)
		{
			if(in_array($w, $week))
			{
				$end_date = date('Y-m-d H:i:s', $start_time + $length);
				$result[] = array(
					'start_date' => $start,
					'end_date' => $end_date,
					'length' => $start_time
				);
				$times--;
			}
			$start_time += self::DAY_TIME;
			$start = date('Y-m-d H:i:s', $start_time);
			$w = date('w', $start_time);
		}
		return $result;
	}
	
	// 每两周
	public static function week2($start, $times, $week = array(), $length)
	{
		$result = Array();		
		$start_time = strtotime($start);	
		$w = date('w', $start_time);
		while($times)
		{
			$curWeekTimes = 0; // 当周课次数
			for($i=$w; $i<7; $i++)
			{	
				$cur_week = date('w', $start_time);
				if(in_array($cur_week, $week))
				{				
					$result[] = array(
						'start_date' => date('Y-m-d H:i:s', $start_time),
						'end_date' => date('Y-m-d H:i:s', $start_time + $length),
						'length' => $start_time,
						'week' => date('w', $start_time)
					);
					$curWeekTimes++;
				}
				$start_time += self::DAY_TIME;
				$start = date('Y-m-d H:i:s', $start_time);			
			}
			if($curWeekTimes)
			{
				$start_time += 7 * self::DAY_TIME;
				$times-= $curWeekTimes;
			}
			$start_time += 86400;
			$start = date('Y-m-d H:i:s', $start_time);		
		}
		return $result;
	}

}
