<?php
/**
 * 拓展的数字辅助函数
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/8
 * Time: 16:06
 */

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 产生一个$length长度的id，要求第一位不为0
 *
 * @param	int		$length	要产生的长度
 * @return	int		产生的id
 */
function generate_id($length) {
	$offset = pow(10, $length-1);
	$id_prefix = mt_rand(1, 9);

	$id_suffix = 0;
	for ($i = 0; $i < $length-1; $i++) {
		$id_suffix = $id_suffix * 10 + mt_rand(1, 9);
	}

	$id = $id_prefix*$offset + $id_suffix;
	return $id;
}


/**
 * 产生一个由年月日时分秒和长度为length的随机数构成的id，其中年份取后两位，如151211152054*******，*为随机数
 *
 * @param	int		$length	随机数部分的长度
 * @return	string	产生的id
 */
function generate_timestamp_id($length) {
	$prefix = (double) substr(date("YmdHis"), 2);		// 前面的年月日时分秒部分

	// 如果随机数长度为0，直接返回年月日时分秒部分
	if ($length <= 0) {
		return $prefix;
	}

	$offset = (double) pow(10, $length);
	$suffix = (double) mt_rand(0, $offset-1);

	return number_format($prefix * $offset + $suffix, 0, '', '');
}


/**
 * 将只含有"零〇一二三四五六七八九十"的汉字转换为相应的数字，适用于年月日
 *
 * @param	string	$str	要转换的汉字
 * @return	int		相应的数字
 */
function hanzi_to_number($str)
{
	$str_length = mb_strlen($str);
	$num = 0;
	$num_array = array();
	$num_array_length = 0;

	for($i=0; $i<$str_length; $i++) {
		$str_temp = mb_substr($str, $i, 1);
		switch ($str_temp) {
			case "零" :
			case "〇" :
				$num_array[$num_array_length] = 0;
				$num_array_length++;
				break;
			case "一" :
				$num_array[$num_array_length] = 1;
				$num_array_length++;
				break;
			case "二" :
				$num_array[$num_array_length] = 2;
				$num_array_length++;
				break;
			case "三" :
				$num_array[$num_array_length] = 3;
				$num_array_length++;
				break;
			case "四" :
				$num_array[$num_array_length] = 4;
				$num_array_length++;
				break;
			case "五" :
				$num_array[$num_array_length] = 5;
				$num_array_length++;
				break;
			case "六" :
				$num_array[$num_array_length] = 6;
				$num_array_length++;
				break;
			case "七" :
				$num_array[$num_array_length] = 7;
				$num_array_length++;
				break;
			case "八" :
				$num_array[$num_array_length] = 8;
				$num_array_length++;
				break;
			case "九" :
				$num_array[$num_array_length] = 9;
				$num_array_length++;
				break;
			case "十" :
				$num_array[$num_array_length] = 10;
				$num_array_length++;
				break;
		}
	}

	//对每一个十进行处理
	//如果只有一个十，不需要进行处理
	if ($num_array_length>1) {
		for($i=0; $i<$num_array_length; $i++) {
			if ($num_array[$i] == 10) {
				//如果十前面还有别的数字，但是后面没有别的数字，则需要将10变为0
				if ($i>0 && $i==$num_array_length-1) {
					$num_array[$i] = 0;
				}
				//如果十前面没有别的数字，但是后面有别的数字，则需要将10变为1
				elseif ($i==0 && $i<$num_array_length-1) {
					$num_array[$i] = 1;
				}
				//如果十前面后面都有别的数字，则需要将10变为-1
				elseif ($i>0 && $i<$num_array_length-1) {
					$num_array[$i] = -1;
				}
			}

		}
	}

	//利用$num_array数组，从后至前生成数字
	$digit = 1;
	for($i=$num_array_length-1; $i>=0; $i--) {
		if ($num_array[$i]!=-1) {
			$num += $num_array[$i]*$digit;
			$digit *= 10;
		}

	}

	return $num;
}


/**
 * 在0至total-1个连续整数中随机选取target个不重复的数字,target要小于等于total
 *
 * @param	int		$total	候选整数最大值
 * @param	int		$target	选取个数
 * @return	array	随机选取的整数，保存在数组中
 */
function rand_select($total, $target) {
	$result = array();
	$temp = 0;

	// 如果要选取的数字比全部可选还要多，那么直接全部返回
	if ($total <= $target) {
		for ($i=0; $i<$total; $i++) {
			$result[] = $i;
		}
		return $result;
	}

	for ($i=0; $i<$target; $i++) {
		while(TRUE) {
			$temp = mt_rand(0,$total-1);
			if (!in_array($temp, $result)) {
				break;
			}
		}
		$result[] = $temp;
	}
	return $result;
}