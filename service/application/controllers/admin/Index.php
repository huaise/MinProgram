<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 发现如果控制器名称和方法名称相同，会执行两遍方法，因此默认控制器不能叫做index
 * 这个控制器先放在这里供测试用，默认会进入welcome控制器
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2015/12/3
 * Time: 16:22
 */

class Index extends MY_Admin_Controller {

	/**
	 * 显示登录表单
	 *
	 * @return	null
	 */
	public function index()
	{
		echo "登录成功123123";
	}
}