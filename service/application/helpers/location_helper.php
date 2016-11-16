<?php
/**
 * 地点辅助函数
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/8
 * Time: 16:06
 */

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 通过两个点的百度坐标，计算两点距离
 *
 * @param	int	$lng_a	点A的经度
 * @param	int	$lat_a	点A的纬度
 * @param	int	$lng_b	点B的经度
 * @param	int	$lat_b	点B的纬度
 * @return	double		两点距离
 */
function get_distance_by_points($lng_a, $lat_a, $lng_b, $lat_b) {
	$pk = 180000000 / 3.14169;
	$a1 = $lat_a / $pk;
	$a2 = $lng_a / $pk;
	$b1 = $lat_b / $pk;
	$b2 = $lng_b / $pk;
	$t1 = cos($a1) * cos($a2) * cos($b1) * cos($b2);
	$t2 = cos($a1) * sin($a2) * cos($b1) * sin($b2);
	$t3 = sin($a1) * sin($b1);
	$tt = acos($t1 + $t2 + $t3);
	return 6366000 * $tt;
}

/**
 * 计算百度地图点到直线之间的距离（a点到bc两点组成的直线之间距离）
 *
 * @param	int	$lng_a	点A的经度
 * @param	int	$lat_a	点A的纬度
 * @param	int	$lng_b	点B的经度
 * @param	int	$lat_b	点B的纬度
 * @param	int	$lat_c	点C的经度
 * @param	int	$lng_c	点C的纬度
 * @return	double		a点到bc两点组成的直线之间距离，单位为米
 */
function get_dot_line_distance($lat_a,$lng_a,$lat_b,$lng_b,$lat_c,$lng_c) {
	//计算三个点之间的距离
	$distance_a = get_distance_by_points($lng_a, $lat_a, $lng_b, $lat_b);
	$distance_b = get_distance_by_points($lng_a, $lat_a, $lng_c, $lat_c);
	$distance_c = get_distance_by_points($lng_c, $lat_c, $lng_b, $lat_b);

	//求角余弦
	$a = ($distance_a*$distance_a + $distance_c*$distance_c - $distance_b*$distance_b)/(2*$distance_a*$distance_c);
	$d = -$a*$distance_a;

	//根据勾股定理求距离
	$a1 = $distance_a*$distance_a - $d*$d;
	if ($a <= 0) {
		$output = 0;
	}else{
		$output = sqrt($a1);
	}
	return $output;
}

/**
 * 将百度坐标由整数(例如118769789)转为字符串(例如118.769789)
 *
 * @param	int	$value	要转换的数字
 * @return	string		相应的字符串
*/
function location_int_to_str($value) {
	$str = $value/1000000;
	$str = "$str";
	return $str;
}


/**
 * 将百度坐标由字符串(例如118.769789)转为整数(例如118769789)
 *
 * @param	string	$str	要转换的字符串
 * @return	int				相应的数字
 */
function location_str_to_int($str) {
	if ($str == '4.9E-324') {
		return  0;
	}
	return (double)$str * 1000000;
}

