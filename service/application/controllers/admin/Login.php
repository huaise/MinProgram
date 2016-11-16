<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 登录控制器，用于登录操作
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2015/12/3
 * Time: 16:22
 */

class Login extends MY_Admin_Controller {

	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * 显示登录表单/进行登录操作
	 *
	 * @return	null
	 */
	public function index()
	{
		// 如果不是post请求，则显示页面
		if ($this->input->method() != 'post') {
			$this->_show_login_view();
		}

		// 判断form_token
		if ( !password_verify($this->input->session('form_token'), $this->input->post('form_token'))
			|| time() > $this->input->session('form_token_time') + 600) {
			$this->showmessage('页面已过期，请重新登录！', config_item('base_url').ADMINDIR."login", TRUE);
		}

		// 表单验证
		$this->load->library('form_validation');
		$this->form_validation->set_error_delimiters('<div class="text-danger">', '</div>');
		$this->form_validation->set_rules('username', '用户名', 'trim|required');
		$this->form_validation->set_rules('password', '密码', 'required');

		// 表单验证没有通过，则显示页面，由于已经调用了表单验证，因此登录页面会含有用户的输入内容和错误提醒
		if ($this->form_validation->run() == FALSE)
		{
			$this->_show_login_view();
		}

		// 判断用户名密码是否合法
		$result = $this->superadmin_model->check_superadmin_login($this->input->post('username'), $this->input->post('password'));

		// 如果不合法，返回错误
		if (!$result['state']) {
			$this->showmessage($result['error'], '', TRUE);
		}

		// 如果合法，则写入session
		unset($_SESSION['form_token']);
		unset($_SESSION['form_token_time']);
		$this->session->sess_regenerate(TRUE);
		$_SESSION['suid'] = $result['superadmin']['uid'];
		$_SESSION['stoken'] = password_hash($this->superadmin_model->get_superadmin_token_seed($result['superadmin']['uid'], $result['superadmin']['password']), PASSWORD_DEFAULT);

		$this->showmessage('欢迎回来！', config_item('base_url').ADMINDIR."main", TRUE);
	}


	/**
	 * 显示添加/编辑页面
	 *
	 * @param	array		$data		要赋值给view的数据
	 * @return	null
	 */
	private function _show_login_view($data = array())
	{
		//往session里面写入一个form_token
		$this->load->helper('string');
		$form_token = random_string('alnum', 32);
		$_SESSION['form_token'] = $form_token;
		$_SESSION['form_token_time'] = time();

		$data['form_token'] = password_hash($form_token, PASSWORD_DEFAULT);

		$data['form_action_url'] = config_item('base_url').ADMINDIR.$this->router->class."/".$this->router->method;
		$footer_data['js'][] = config_item('static_url')."js/".ADMINDIR.$this->router->class."/".$this->router->method.".js";

		echo $this->load->view(ADMINDIR.'common/header', NULL, TRUE, TRUE);
		echo $this->load->view(ADMINDIR.$this->router->class.'/'.$this->router->method, $data, TRUE, TRUE);
		echo $this->load->view(ADMINDIR.'common/footer', $footer_data, TRUE, TRUE);
		exit;
	}
}