<?php
/**
 * 拓展的日期辅助函数
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/8
 * Time: 16:06
 */

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 检测生日是否在指定范围内，如开始日期是2012-12-1，结束日期是2012-12-3，则12.2和12.3为符合要求的生日
 *
 * @param	string|int 	$year		现在的年份
 * @param	string|int	$month		现在的月份
 * @param	string|int	$day		现在的日子
 * @param	int			$start_time	开始日期，UNIX时间戳
 * @param	int			$end_time	结束日期，UNIX时间戳
 * @return	bool		TRUE(在指定范围内)/FASLE(不在指定范围内)
 */
function check_birthday_coming($year, $month, $day, $start_time, $end_time) {
	$birthday_str = $year."-".$month."-".$day;
	$timestamp = strtotime($birthday_str);
	//如果不存在跨年问题
	if ($start_time < $end_time) {
		if ($timestamp>$start_time && $timestamp<=$end_time) {
			return TRUE;
		}
		else{
			return FALSE;
		}
	}
	else{
		if ($timestamp>$start_time || $timestamp<=$end_time) {
			return TRUE;
		}
		else{
			return FALSE;
		}
	}
}

/**
 * 由生日获取年龄
 *
 * @param	string 		$birthday				生日，为YYYY-MM-DD格式
 * @param	bool		$compare_only_year		TRUE为只比较年份，FALSE为还要比较月、日
 * @param	string		$now					目标日期，为YYYY-MM-DD格式，计算该天时的年龄
 * @return	int			年龄
 */
function get_age($birthday, $compare_only_year = TRUE, $now = '') {
	if ($now == '') {
		$now = time();
	}
	else {
		$now = strtotime($now);
	}

	// 如果只比较年份
	if ($compare_only_year) {
		$y1 = date("Y",strtotime($birthday));
		$y2 = date("Y",$now);
		$age = $y2 - $y1;
	}
	// 如果还要比较月、日
	else {
		list($y1, $m1, $d1) = explode("-", date("Y-m-d",strtotime($birthday)));
		list($y2, $m2, $d2) = explode("-",date("Y-m-d",$now));
		$age = $y2 - $y1;
		if ((int)($m2.$d2) < (int)($m1.$d1)) {
			$age -= 1;
		}
	}

	return $age;
}

/**
 * 由生日获取星座信息
 *
 * @param	string 		$birthday		生日，为YYYY-MM-DD格式
 * @param	array		$format			星座格式，数组形式，默认为空
 * @param	bool		$num_flag		TRUE代表返回星座编码，否则返回字符串
 * @return	string|int	星座信息
 */
function get_constellation($birthday, $format=NULL, $num_flag=FALSE) {
	$pattern = '/^\d{4}-\d{1,2}-\d{1,2}$/';
	if (!preg_match($pattern, $birthday, $matchs)) {
		if ($num_flag) {
			return 0;
		}
		else {
			return "";
		}
	}
	$date = explode('-', $birthday);
//	$year = $date[0];
	$month = intval($date[1]);
	$day   = intval($date[2]);
	if ($month <1 || $month>12 || $day < 1 || $day >31) {
		if ($num_flag) {
			return 0;
		}
		else {
			return "";
		}
	}
	//设定星座数组
	if ($num_flag) {
		$constellations = array(10, 11, 12, 1, 2, 3, 4, 5, 6, 7, 8, 9,);
	}
	else {
		$constellations = array('摩羯座', '水瓶座', '双鱼座', '白羊座', '金牛座', '双子座',
			'巨蟹座','狮子座', '处女座', '天秤座', '天蝎座', '射手座',);
	}


	//设定星座结束日期的数组，用于判断
	$enddays = array(19, 18, 20, 19, 20, 21, 22, 22, 22, 23, 22, 21,);
	//如果参数format被设置，则返回值采用format提供的数组，否则使用默认的数组
	if ($format != null) {
		$values = $format;
	}
	else{
		$values = $constellations;
	}
	//根据月份和日期判断星座
	if ($day <= $enddays[$month-1]) {
		return $values[$month-1];
	} else if ($month == 12) {
		return $values[0];
	} else {
		return $values[$month];
	}

}

/**
 * 由时间戳得到可以用来显示的年月日时分秒
 * 由于CodeIgniter的unix_to_human、mdate函数都没有对0进行处理，因此如果时间戳可能为0，建议使用该函数
 *
 * @param	int		$time	时间戳
 * @return	string	年月日时分秒
 */
function get_str_from_time($time) {
	return ($time==0 ? "无" : date("Y-m-d H:i:s",$time));
}

/**
 * 由时间戳得到可以用来显示的年月日
 * 由于CodeIgniter的unix_to_human、mdate函数都没有对0进行处理，因此如果时间戳可能为0，建议使用该函数
 *
 * @param	int		$time	时间戳
 * @return	string	年月日时分秒
 */
function get_str_from_time_day($time) {
	return ($time==0 ? "无" : date("Y-m-d",$time));
}


/**
 * 由时间戳转换为与当前时间的差值,如果差值小于一天，则用时分表示，如果差值大于一天，则用天时表示
 * @param	int		$time 	要计算的时间
 * @return  string  	计算好的时间差值
 * */
function change_difference_from_time($time) {
	$time_show = $time - time();
	if($time_show < 0){
		$output = "0小时0分";
	} else if ($time_show<=86400) {
		$show_h = intval($time_show % 86400 / 3600);
		$show_m = intval(($time_show % 3600)/60);
		$output = $show_h."小时".$show_m."分";
	} else {
		$show_day = intval($time_show / 86400);
		$show_h = intval($time_show % 86400 / 3600);
		$output = $show_day."天".$show_h."小时";
	}
	return $output;
}

//获取时间戳微秒数
function get_microtime(){
	return intval(microtime(TRUE) * 10000);
}