<?php
/**
 * 拓展的公共辅助类，会自动加载
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/6
 * Time: 20:56
 */

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 检测id是否合法，要求满足id为正整数
 *
 * @param	string	$id	要检测的id
 * @return	bool	TRUE(成功)/FALSE(失败)
 */
function is_id($id) {
	//检测id是否是合法数字
	$id = (int)$id;
	if (is_int($id) && $id>0) {
		return TRUE;
	}
	else{
		return FALSE;
	}
}