<?php
/**
 * 拓展的数组辅助函数
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/8
 * Time: 16:06
 */

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 多维数组排序
 *
 * @param	array	$arr	要排序的数组
 * @param	string	$keys	排序依据的键值
 * @param	string	$type	升序('asc')还是倒序('dsc')
 * @return	array
 * 返回结果: 排序后的数组
 */
function array_sort($arr, $keys, $type='asc') {
	$keysvalue = $new_array = array();
	foreach ($arr as $k=>$v) {
		$keysvalue[$k] = $v[$keys];
	}
	if ($type == 'asc') {
		asort($keysvalue);
	}else{
		arsort($keysvalue);
	}
	reset($keysvalue);
	foreach ($keysvalue as $k=>$v) {
		$new_array[$k] = $arr[$k];
	}
	return $new_array;
}


/**
 * Elements
 *
 * Returns only the array items specified. Will return a default value if
 * it is not set.
 * 与CI自带的element不同的是，当键对应的值存在但是为NULL时，也会返回默认值
 *
 * @param    array
 * @param    array
 * @param    mixed
 * @return    mixed    depends on what the array contains
 */
function elements_not_null($items, array $array, $default = NULL)
{
	$return = array();

	is_array($items) OR $items = array($items);

	foreach ($items as $item) {
		$return[$item] = (array_key_exists($item, $array) && $array[$item]!=NULL) ? $array[$item] : $default;
	}

	return $return;
}