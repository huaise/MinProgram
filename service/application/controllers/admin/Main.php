<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 主页控制器，用于显示主页、导航等
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2015/12/3
 * Time: 16:22
 */

class Main extends MY_Admin_Controller {

	/**
	 * 显示主页，包括导航栏和右侧iframe
	 *
	 * @return	null
	 */
	public function index()
	{
		// 根据用户权限，生成左侧菜单
		$admin_nav = array();
		$admin_authority = config_item('admin_authority');

		foreach (config_item('admin_nav') as $parent_name => $child_array) {
			// 该数组包括了在这个父目录下面的子目录
			$current_array = array();
			foreach ($child_array as $child_name => $path) {
				if (isset($admin_authority[$path[0]]) && isset($admin_authority[$path[0]][$path[1]])) {
					if (in_array(-1, $admin_authority[$path[0]][$path[1]])
						|| in_array($this->_superadmin['authority'], $admin_authority[$path[0]][$path[1]])) {
						$current_array[] = array('name'=>$child_name, 'url'=>config_item('base_url').ADMINDIR.$path[0]."/".$path[1]);
					}
				}
			}

			if (!empty($current_array)) {
				$admin_nav[] = array('name'=>$parent_name, 'child'=>$current_array);
			}
		}


		$data['admin_nav'] = $admin_nav;
		$footer_data['js'][] = config_item('static_url')."js/".ADMINDIR.$this->router->class."/".$this->router->method.".js";

		$this->load->view(ADMINDIR.'common/header');
		$this->load->view(ADMINDIR.'main/index', $data);
		$this->load->view(ADMINDIR.'common/footer', $footer_data);
	}

	/**
	 * 欢迎页面
	 *
	 * @return	null
	 */
	public function welcome()
	{
		$this->load->view(ADMINDIR.'common/header');
		$this->load->view(ADMINDIR.'main/welcome');
		$this->load->view(ADMINDIR.'common/footer');
	}
}