<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 管理系统的控制器，用于管理员修改密码、登出，超级管理员查看、添加、编辑、删除其他管理员
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2015/12/3
 * Time: 16:22
 */

class Manage extends MY_Admin_Controller {

	/**
	 * 管理员列表
	 *
	 * @return	null
	 */
	public function lists()
	{
		// 搜索条件
		$where = array();
		if ($this->input->get('action') == 'search') {
			$search['username'] = $this->input->get('username');
			$search['authority'] = $this->input->get('authority');
			$data['search'] = $search;

			if ($search['username'] != '') {
				$where[] = "username LIKE '%".$this->db->escape_like_str($search['username'])."%'";
			}
			if ($search['authority'] !== '' && $search['authority'] !== NULL) {
				$where[] = "authority=".$this->db->escape($search['authority']);
			}
		}

		$where = implode(" and ", $where);

		// 分页
		$total = $this->superadmin_model->count($where);
		$this->load->library('pagination');
		$config = $this->get_pagination_config($total);
		$this->pagination->initialize($config);
		$pagination = $this->pagination->create_links();

		// 根据当前页来查找数据
		$current_page = max($this->input->get('p'), 1);
		$superadmin_list = $this->superadmin_model->select($where, FALSE, 'uid,username,authority', ($current_page-1)*$config['per_page'].",".$config['per_page'], 'uid ASC');

		// 对于每一个条目，进行一些用于显示的处理
		foreach ($superadmin_list as &$row) {
			$row['authority_display'] = $this->superadmin_model->get_authority_str($row['authority']);
		}
		unset($row);

		$data['superadmin_list'] = $superadmin_list;
		$data['authority_lookup_table'] = $this->superadmin_model->get_authority_lookup_table();
		$data['pagination'] = $pagination;
		$data['edit_url'] = config_item('base_url').ADMINDIR.$this->router->class."/edit";
		$data['delete_url']= config_item('base_url').ADMINDIR.$this->router->class."/delete";

		$data['search_url'] = config_item('base_url').ADMINDIR.$this->router->class."/".$this->router->method;
		$footer_data['js'][] = config_item('static_url')."js/".ADMINDIR.$this->router->class."/".$this->router->method.".js";

		$this->load->view(ADMINDIR.'common/header');
		$this->load->view(ADMINDIR.$this->router->class.'/'.$this->router->method, $data);
		$this->load->view(ADMINDIR.'common/footer', $footer_data);
	}

	/**
	 * 删除管理员
	 *
	 * @return	null
	 */
	public function delete()
	{
		// 如果是删除操作
		if ($this->input->post('action') == 'delete') {
			$uid = $this->input->post('uid');
			if (is_id($uid)) {
				if ($this->superadmin_model->delete(array('uid'=>$uid))) {
					echo "删除成功！";
					return;
				}
				else {
					echo "数据库错误！";
					return;
				}
			}
		}

		echo "无效的请求！";
	}
	/**
	 * 添加/编辑管理员
	 *
	 * @return	null
	 */
	public function edit()
	{
		// 如果不是post请求，则直接走显示逻辑
		if ($this->input->method() != 'post') {
			// 如果没有uid参数，则为添加逻辑
			if ( ($uid = $this->input->get('uid')) === NULL) {
				$this->_show_edit_view();
			}
			// 如果含有uid参数，则为编辑逻辑
			else {
				// 去数据库中获取要编辑的数据
				$superadmin = $this->superadmin_model->get_row_by_id($uid);

				// 如果获取不到，显示错误
				if (empty($superadmin)) {
					$this->showmessage('找不到该管理员');
				}

				// 如果可以找到，则显示页面
				$data['superadmin'] = $superadmin;
				$this->_show_edit_view($data, TRUE);
			}

		}

		// 如果是post，则需要处理提交的数据
		// 如果是添加数据
		if ( $this->input->post('action') == 'add' ) {
			// 从post的数据中取出需要的部分
			$superadmin = $this->input->post(array('username', 'password', 'password_confirm', 'authority'));

			// 验证数据合法性，并格式化数据
			// 如果不合法，则显示添加页面，且保留表单数据
			if ($this->_validate_and_format($superadmin) == FALSE) {
				$this->_show_edit_view();
			}

			// 表单验证通过之后，开始插入数据
			if ($this->superadmin_model->insert($superadmin)) {
				$this->showmessage('添加成功！');
			}
			else {
				$this->showmessage('数据库错误！');
			}
		}
		// 如果是编辑数据
		else if ( $this->input->post('action') == 'edit' ) {
			// 去数据库中获取要编辑的数据
			$uid = $this->input->get('uid');
			$original_superadmin = $this->superadmin_model->get_row_by_id($uid);

			// 如果获取不到，显示错误
			if (empty($original_superadmin)) {
				$this->showmessage('找不到该管理员');
			}

			// 从post的数据中取出需要的部分
			$superadmin = $this->input->post(array('username', 'password', 'password_confirm', 'authority'));

			// 验证数据合法性，并格式化数据
			// 如果不合法，则显示编辑页面，且保留表单数据
			if ($this->_validate_and_format($superadmin, TRUE, $original_superadmin) == FALSE) {
				$data['superadmin'] = $original_superadmin;
				$this->_show_edit_view($data, TRUE);
			}

			// 表单验证通过之后，开始更新数据
			if ($this->superadmin_model->update($superadmin, array('uid =' => $uid))) {
				$this->showmessage('编辑成功！');
			}
			else {
				$this->showmessage('数据库错误！');
			}
		}

		// 如果action不合法，则显示错误
		$this->showmessage('请求不合法！');
	}

	/**
	 * 显示添加/编辑页面
	 *
	 * @param	array		$data		要赋值给view的数据
	 * @param	bool		$edit_mode	是否为编辑模式
	 * @return	null
	 */
	private function _show_edit_view($data = array(), $edit_mode = FALSE)
	{
		$data['edit_mode'] = $edit_mode;
		$data['form_action_url'] = config_item('base_url').ADMINDIR.$this->router->class."/".$this->router->method;
		if ($edit_mode) {
			$data['form_action_url'] .= "?uid=".$data['superadmin']['uid'];
		}

		$data['authority_lookup_table'] = $this->superadmin_model->get_authority_lookup_table();

		$footer_data['js'][] = config_item('static_url')."js/".ADMINDIR."util/form_cancel.js";
		$footer_data['js'][] = config_item('static_url')."js/".ADMINDIR.$this->router->class."/".$this->router->method.".js";


		echo $this->load->view(ADMINDIR.'common/header', NULL, TRUE, TRUE);
		echo $this->load->view(ADMINDIR.$this->router->class.'/'.$this->router->method, $data, TRUE, TRUE);
		echo $this->load->view(ADMINDIR.'common/footer', $footer_data, TRUE, TRUE);
		exit;
	}


	/**
	 * 验证添加/编辑的数据是否合法，并且进行一些预处理和格式化
	 *
	 * @param	array		$data			要验证的数据，指针形式
	 * @param	bool		$edit_mode		是否为编辑模式
	 * @param	array		$original_data	原先的数据，如果是编辑模式，则需要传入，便于进行逻辑判断
	 * @return	bool		TRUE(合法)/FALSE(不合法)
	 */
	private function _validate_and_format(&$data = array(), $edit_mode = FALSE, $original_data = array())
	{
		// 数据的预处理和格式化
		$data['username'] = trim($data['username']);

		// 表单验证
		$this->load->library('form_validation');
		$this->form_validation->set_error_delimiters('<div class="text-danger">', '</div>');
		$this->form_validation->set_data($data);

		// 设置验证规则
		// 如果是编辑模式
		if ($edit_mode) {
			// 如果修改了用户名，则要求用户名唯一
			if ($data['username'] !== $original_data['username']) {
				$this->form_validation->set_rules('username', '用户名', 'required|is_unique[p_superadmin.username]');
			}
			// 如果没有修改，不需要做唯一性验证
			else {
				$this->form_validation->set_rules('username', '用户名', 'required');
			}
		}
		else {
			$this->form_validation->set_rules('username', '用户名', 'required|is_unique[p_superadmin.username]');
		}

		$this->form_validation->set_rules('password', '密码', 'required|min_length[8]|matches[password_confirm]|differs[username]');
		$this->form_validation->set_rules('password_confirm', '确认密码', 'required|min_length[8]|matches[password]|differs[username]');
		$this->form_validation->set_rules('authority', '权限', 'required');
		if ($this->form_validation->run() == FALSE) {
			return FALSE;
		}

		// 数据的后处理
		$data['password'] = password_hash($this->superadmin_model->get_superadmin_password_seed($data['password']), PASSWORD_DEFAULT);

		// 去除不需要进入数据库的字段
		unset($data['password_confirm']);

		return TRUE;
	}

	/**
	 * 修改密码
	 *
	 * @return	null
	 */
	public function password()
	{
		// 如果不是post请求，则直接走显示逻辑
		if ($this->input->method() != 'post') {
			$this->_show_password_view();
		}

		// 如果是post，则需要处理提交的数据
		// 如果是修改密码
		if ( $this->input->post('action') == 'edit' ) {
			// 表单验证
			$this->load->library('form_validation');
			$this->form_validation->set_error_delimiters('<div class="text-danger">', '</div>');
			$this->form_validation->set_rules('password', '密码', 'required|min_length[8]|matches[password_confirm]|callback_password_username_check');
			$this->form_validation->set_rules('password_confirm', '确认密码', 'required|min_length[8]|matches[password]');
			// 如果不合法，则显示页面，且保留表单数据
			if ($this->form_validation->run() == FALSE) {
				$this->_show_password_view();
			}

			// 表单验证通过之后，开始修改数据
			$superadmin = $this->input->post(array('password'));
			$superadmin['password'] = password_hash($this->superadmin_model->get_superadmin_password_seed($superadmin['password']), PASSWORD_DEFAULT);

			// 如果修改成功
			if ($this->superadmin_model->update($superadmin, array('uid =' => $this->_superadmin['uid']))) {
				// 需要重设session
				$_SESSION['stoken'] = password_hash($this->superadmin_model->get_superadmin_token_seed($this->_superadmin['uid'], $superadmin['password']), PASSWORD_DEFAULT);

				$this->showmessage('修改密码成功！');
			}
			else {
				$this->showmessage('数据库错误！');
			}
		}

		// 如果action不合法，则显示错误
		$this->showmessage('请求不合法！');
	}


	/**
	 * 显示修改密码页面
	 *
	 * @param	array		$data		要赋值给view的数据
	 * @return	null
	 */
	private function _show_password_view($data = array())
	{
		$data['form_action_url'] = config_item('base_url').ADMINDIR.$this->router->class."/".$this->router->method;

		$footer_data['js'][] = config_item('static_url')."js/".ADMINDIR."util/form_cancel.js";
		$footer_data['js'][] = config_item('static_url')."js/".ADMINDIR.$this->router->class."/".$this->router->method.".js";


		echo $this->load->view(ADMINDIR.'common/header', NULL, TRUE, TRUE);
		echo $this->load->view(ADMINDIR.$this->router->class.'/'.$this->router->method, $data, TRUE, TRUE);
		echo $this->load->view(ADMINDIR.'common/footer', $footer_data, TRUE, TRUE);
		exit;
	}

	/**
	 * 系统配置
	 *
	 * @return	null
	 */
	public function config()
	{
		$this->load->model('config_model');

		// 如果不是post请求，则直接走显示逻辑
		if ($this->input->method() != 'post') {
			$this->_show_config_view();
		}

		// 如果是post，则需要处理提交的数据
		// 如果是修改配置
		if ( $this->input->post('action') == 'edit' ) {
			// 表单验证
			$this->load->library('form_validation');
			$this->form_validation->set_error_delimiters('<div class="text-danger">', '</div>');
			$this->form_validation->set_rules('project_name', '项目名称', 'required');
			// 如果不合法，则显示页面，且保留表单数据
			if ($this->form_validation->run() == FALSE) {
				$this->_show_config_view();
			}

			// 表单验证通过之后，开始修改数据
			$config = $this->input->post(array('project_name'));

			// 如果修改成功
			if ($this->config_model->update_config($config) > 0) {
				$this->showmessage('修改成功！');
			}
			else {
				$this->showmessage('数据库错误！');
			}
		}

		// 如果action不合法，则显示错误
		$this->showmessage('请求不合法！');
	}

	/**
	 * 显示系统配置页面
	 *
	 * @return	null
	 */
	private function _show_config_view()
	{
		// 首先获取所有的配置
		$config_list = $this->config_model->select(NULL, FALSE, '*', '', '', '', 'name');

		// 区分可编辑和不可编辑条目
		$this->load->helper('array');
		$data['config_editable_list'] = elements(array('project_name'), $config_list);
		$data['config_static_list'] = elements(array('access_token', 'jsapi_ticket', 'ticket_expire_time', 'token_expire_time', 'wx_lock_flag'), $config_list);

		// 如果有些条目需要特殊处理，可以单独拿出来，放到这里
		$data['config_special_list'] = NULL;

		// 进行显示的处理
		$this->load->helper('date');
		$data['config_static_list']['ticket_expire_time']['values'] = get_str_from_time($data['config_static_list']['ticket_expire_time']['values']);
		$data['config_static_list']['token_expire_time']['values'] = get_str_from_time($data['config_static_list']['token_expire_time']['values']);
		$data['config_static_list']['wx_lock_flag']['values'] = ($data['config_static_list']['wx_lock_flag']['values']==1 ? "是" : "否");

		$data['form_action_url'] = config_item('base_url').ADMINDIR.$this->router->class."/".$this->router->method;

		$footer_data['js'][] = config_item('static_url')."js/".ADMINDIR."util/form_cancel.js";
		$footer_data['js'][] = config_item('static_url')."js/".ADMINDIR.$this->router->class."/".$this->router->method.".js";

		echo $this->load->view(ADMINDIR.'common/header', NULL, TRUE, TRUE);
		echo $this->load->view(ADMINDIR.$this->router->class.'/'.$this->router->method, $data, TRUE, TRUE);
		echo $this->load->view(ADMINDIR.'common/footer', $footer_data, TRUE, TRUE);
		exit;
	}


	/**
	 * 表单验证的自定义验证函数，用来检测密码是否和用户名相同
	 *
	 * @param	string		$str		用户输入的密码
	 * @return	null
	 */
	public function password_username_check($str)
	{
		// 检测密码是否和用户名相同
		if ($str == $this->_superadmin['username']) {
			$this->form_validation->set_message('password_username_check', '{field}不能和用户名相同');
			return FALSE;
		}
		else {
			return TRUE;
		}
	}

	/**
	 * 退出后台
	 *
	 * @return	null
	 */
	public function logout()
	{
		$this->session->sess_regenerate(TRUE);
		session_destroy();
		$this->showmessage("退出成功！", config_item('base_url').ADMINDIR."login", TRUE);
	}

}