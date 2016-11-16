<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 默认的欢迎页面，仅仅用于跳转
 * User: LvPeng
 * Date: 2015/12/3
 * Time: 16:22
 */

class Welcome extends MY_Admin_Controller {

	/**
	 * 显示登录表单
	 *
	 * @return	null
	 */
	public function index()
	{
		// 如果没有登录，那么父类就会跳转到登录页面，不会走到这里
		// 如果走到这里，说明已经登录了，那么显示主页即可
		$this->showmessage('您已登录，将自动跳转到首页', config_item('base_url').ADMINDIR."main");
	}
}