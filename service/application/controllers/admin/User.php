<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 用户系统的控制器，用于用户列表、添加、编辑、删除
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2015/12/3
 * Time: 16:22
 */

class User extends MY_Admin_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->load->model('user_model');
	}

	/**
	 * 用户列表
	 *
	 * @return	null
	 */
	public function lists()
	{
		// 搜索条件
		$where = array();
		if ($this->input->get('action') == 'search') {
			$search['phone'] = $this->input->get('phone');
			$search['gender'] = $this->input->get('gender');
			$data['search'] = $search;

			if ($search['phone'] != '') {
				$where[] = "phone LIKE '%".$this->db->escape_like_str($search['phone'])."%'";
			}
			if ($search['gender'] !== '' && $search['gender'] !== NULL) {
				$where[] = "gender=".$this->db->escape($search['gender']);
			}
		}

		$where = implode(" and ", $where);
		$this->user_model->set_table_name('p_userdetail');

		// 分页
		$total = $this->user_model->count($where);
		$this->load->library('pagination');
		$config = $this->get_pagination_config($total);
		$this->pagination->initialize($config);
		$pagination = $this->pagination->create_links();

		// 根据当前页来查找数据
		$current_page = max($this->input->get('p'), 1);
		$user_list = $this->user_model->select($where, FALSE, 'uid,phone,gender,province,city,district,register_time', ($current_page-1)*$config['per_page'].",".$config['per_page'], 'uid DESC');

		// 对于每一个条目，进行一些用于显示的处理
		$this->load->helper('string');
		$this->load->helper('date');
		$this->load->model('area_model');
		foreach ($user_list as &$row) {
			$row['gender_display'] = get_str_from_gender($row['gender']);
			$row['location'] = $this->area_model->get_string_by_id($row['province'], $row['city'], $row['district']);
			$row['register_time'] = get_str_from_time($row['register_time']);
		}
		unset($row);

		$data['user_list'] = $user_list;
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
	 * 删除用户
	 *
	 * @return	null
	 */
	public function delete()
	{
		// 如果是删除操作
		if ($this->input->post('action') == 'delete') {
			$uid = $this->input->post('uid');
			if (is_id($uid)) {
				if ($this->user_model->delete(array('uid'=>$uid))) {
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
	 * 添加/编辑用户
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
				$user = $this->user_model->get_row_by_id($uid);

				// 如果获取不到，显示错误
				if (empty($user)) {
					$this->showmessage('找不到该用户');
				}

				// 如果可以找到，则显示页面
				$data['user'] = $user;
				$this->_show_edit_view($data, TRUE);
			}

		}

		// 如果是post，则需要处理提交的数据
		// 如果是添加数据
		if ( $this->input->post('action') == 'add' ) {
			// 从post的数据中取出需要的部分
			$user = $this->input->post(array('phone', 'gender', 'birthday', 'province', 'city', 'district', 'sign'));

			// 验证数据合法性，并格式化数据
			// 如果不合法，则显示添加页面，且保留表单数据
			if ($this->_validate_and_format($user) == FALSE) {
				$this->_show_edit_view();
			}

			// 表单验证通过之后，开始插入数据
			if ($this->user_model->insert_user($user)) {
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
			$original_user = $this->user_model->get_row_by_id($uid);

			// 如果获取不到，显示错误
			if (empty($original_user)) {
				$this->showmessage('找不到该用户');
			}

			// 从post的数据中取出需要的部分
			$user = $this->input->post(array('phone', 'gender', 'birthday', 'province', 'city', 'district', 'sign'));

			// 验证数据合法性，并格式化数据
			// 如果不合法，则显示编辑页面，且保留表单数据
			if ($this->_validate_and_format($user, TRUE, $original_user) == FALSE) {
				$data['user'] = $original_user;
				$this->_show_edit_view($data, TRUE);
			}

			// 表单验证通过之后，开始更新数据
			if ($this->user_model->update($user, array('uid =' => $uid))) {
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
		$data['diplayed_error'] = $this->_diplayed_error;
		$data['form_action_url'] = config_item('base_url').ADMINDIR.$this->router->class."/".$this->router->method;
		if ($edit_mode) {
			$data['form_action_url'] .= "?uid=".$data['user']['uid'];

			// 进行显示的处理
			$this->load->helper('date');
			$data['user']['register_time'] = get_str_from_time($data['user']['register_time']);
			if ($data['user']['birthday'] == '0000-00-00') {
				$data['user']['birthday'] = '';
				$data['user']['constellation'] = '';
			}
			else {
				$data['user']['constellation'] = get_constellation($data['user']['birthday']);
			}

			$this->load->helper('url');
			$data['user']['avatar'] = get_attachment_url($data['user']['avatar'], 'avatar');

			if ($data['user']['album'] != '') {
				$temp_array = explode(',', $data['user']['album']);
				$data['user']['album'] = array();
				foreach ($temp_array as $value) {
					if ($value != '') {
						$data['user']['album'][] = get_attachment_url($value, 'album');
					}
				}
			}
		}

		$footer_data['js'][] = config_item('static_url')."js/".ADMINDIR."util/form_cancel.js";
		$footer_data['js'][] = config_item('static_url')."js/".ADMINDIR."lib/jquery.Jcrop.js";
		$footer_data['js'][] = config_item('static_url')."js/".ADMINDIR."util/bootstrap-datepicker.min.js";
		$footer_data['js'][] = config_item('static_url')."js/".ADMINDIR."util/bootstrap-datepicker.zh-CN.min.js";
		$footer_data['js'][] = config_item('static_url')."js/".ADMINDIR."util/area_data_min.js";
		$footer_data['js'][] = config_item('static_url')."js/".ADMINDIR."util/complex_select.js";
		$footer_data['js'][] = config_item('static_url')."js/".ADMINDIR."util/image-uploader-with-preview.js";
		$footer_data['js'][] = config_item('static_url')."js/".ADMINDIR.$this->router->class."/".$this->router->method.".js";

		$footer_data['css'][] = config_item('static_url')."css/".ADMINDIR."bootstrap-datepicker3.min.css";
		$footer_data['css'][] = config_item('static_url')."css/".ADMINDIR."jquery.Jcrop.min.css";

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
		$data['phone'] = trim($data['phone']);

		// 表单验证
		$this->load->library('form_validation');
		$this->form_validation->set_error_delimiters('<div class="text-danger">', '</div>');
		$this->form_validation->set_data($data);

		// 设置验证规则
		// 如果是编辑模式
		if ($edit_mode) {
			// 如果修改了手机号，则要求手机号唯一
			if ($data['phone'] !== $original_data['phone']) {
				$this->form_validation->set_rules('phone', '手机号', 'required|regex_match[/^1\d{10}$/]|is_unique[p_userdetail.phone]');
			}
			// 如果没有修改，不需要做唯一性验证
			else {
				$this->form_validation->set_rules('phone', '手机号', 'required|regex_match[/^1\d{10}$/]');
			}
		}
		else {
			$this->form_validation->set_rules('phone', '手机号', 'required|regex_match[/^1\d{10}$/]|is_unique[p_userdetail.phone]');
		}
		$this->form_validation->set_rules('birthday', '生日', 'regex_match[/^\d{4}-\d{1,2}-\d{1,2}$/]');


		if ($this->form_validation->run() == FALSE) {
			return FALSE;
		}

		// 图片上传部分
		$this->load->library('upload');
		$this->load->library('image_lib');
		$this->load->helper('string');
		$this->load->helper('file');

		// 头像的单图上传
		if (isset($_FILES['avatar']['name']) && $_FILES['avatar']['name']!='') {
			$config = array();
			$config['upload_path'] = './uploads/avatar';
			$config['allowed_types'] = 'jpg|jpeg|png';
			$config['file_name'] = time().strtolower(random_string('alnum',10)).'_full';
			$config['overwrite'] = FALSE;

			$this->upload->initialize($config);

			// 如果上传失败
			if ( !$this->upload->do_upload('avatar')) {
				$this->apppen_diplayed_error("上传头像时发生错误：".$this->upload->display_errors());
				return FALSE;
			}
			else {
				$upload_result = $this->upload->data();

				//对原图进行裁剪生成缩略图
				//首先获取x,y,w,h参数
				$x = $this->input->post('avatar_x');
				$y = $this->input->post('avatar_y');
				$w = $this->input->post('avatar_w');
				$h = $this->input->post('avatar_h');
				$xrb = $this->input->post('avatar_xbr');
				$yrb = $this->input->post('avatar_ybr');

				//获取原图的尺寸
				$origin_width = $upload_result['image_width'];
				$origin_height =$upload_result['image_height'];

				//目标图片的宽长
				$target_width = 100;
				$target_height = 100;

				//获取图片裁剪的一些参数
				$image_size = $this->image_lib->getResizeThumbSize($x, $y, $w, $h, $xrb, $yrb, $origin_width, $origin_height);

				$full_image_name = $upload_result['file_name'];//大图名称
				$thumb_image_name = get_thumb_from_full($full_image_name);//缩略图名称
				$thumb_image_url = $config['upload_path'].'/'.$thumb_image_name;//目标缩略图路径
				$image_url = $config['upload_path'].'/'.$full_image_name;//大图图片路径

				//裁剪生成缩略图
				$this->image_lib->resizeThumbnailImage($thumb_image_url, $image_url, $image_size['crop_width'], $image_size['crop_height'], $image_size['start_width'], $image_size['start_height'], $target_width, $target_height);
				$data['avatar'] = $thumb_image_name;
			}
		}

		// 相册的多图上传
		$this->upload->multifile_array('album');		// 这行代码必须，是用来做多图格式兼容的
		$index = 0;
		$album_array = array();
		while (true) {
			$current_key = 'album__'.$index;
			if (!isset($_FILES[$current_key])) {
				break;
			}

			$config = array();
			$config['upload_path'] = './uploads/album';
			$config['file_name'] = time().strtolower(random_string('alnum',10));
			$config['allowed_types'] = 'jpg|jpeg|png';
			$config['overwrite'] = FALSE;

			$this->upload->initialize($config);

			// 如果上传失败
			if ( !$this->upload->do_upload($current_key)) {
				$this->apppen_diplayed_error("上传相册时发生错误：".$this->upload->display_errors());
				return FALSE;
			}
			else {
				$upload_result = $this->upload->data();
				$album_array[] = $upload_result['file_name'];
			}

			$index++;

		}
		if (!empty($album_array)) {
			$data['album'] =implode(",", $album_array);
		}



		// 数据的后处理
		// 如果是添加数据
		if (!$edit_mode) {
			$data['register_time'] = time();
		}

		// 如果设置了生日，则生成星座
		if (isset($data['birthday'])) {
			$this->load->helper('date');
			$data['constellation'] = get_constellation($data['birthday'], NULL, TRUE);
		}

		// 去除不需要进入数据库的字段
//		unset($data['password_confirm']);

		return TRUE;
	}
}