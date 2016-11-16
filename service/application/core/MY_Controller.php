<?php
/**
 * 拓展的核心控制器类
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/6
 * Time: 20:56
 */

defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller {
	// 多个不同的类的共有功能，放在这里
//	public function __construct()
//	{
//		parent::__construct();
//	}
}

class MY_Admin_Controller extends MY_Controller {
	protected $_superadmin;
	protected $_diplayed_error = '';		// 用于显示后端表单验证类库不能显示的错误

	public function __construct()
	{
		define("IN_ADMIN", TRUE);
		parent::__construct();
		$this->config->load('config_admin');

		// 一些常用的类库、模型和辅助函数直接在这里加载，这样就不需要每次都加载了
		// 因为超级后台流量小，这样造成的资源浪费几乎可以忽略
		$this->load->library('session');
		$this->load->model('superadmin_model');
		$this->load->helper(array('form','security'));

		// 后台背景图逻辑
		if (!isset($_SESSION['bg'])) {
			$_SESSION['bg'] = mt_rand(1,12);
		}

		// 写日志
		$log_array = array(
			"ip"=>get_ip(),
			"from"=>$this->input->server('HTTP_REFERER'),
			"UA"=>$this->input->server('HTTP_USER_AGENT'),
			"QUERY"=>$this->input->server('QUERY_STRING'),
			"session"=>$_SESSION,
			'post'=>$_POST,
		);
		$this->log->write_log_json($log_array, 'admin');

		// IP白名单
//		if (!DEBUG) {
//			$this->load->helper('security');
//
//			$admin_ip_white_list = array(array( '115.236.68.58' , '115.236.68.59'),
//				array( '121.41.86.1' , '121.41.86.1'),
//				array( '115.236.59.0' , '115.236.59.255'));
//
//			if ($this->input->server('REMOTE_ADDR') == NULL) {
//				exit();
//			}
//
//			if (!is_ip_in_range($this->input->server('REMOTE_ADDR'), $admin_ip_white_list)
//				&& $this->input->server('REMOTE_ADDR') != $this->input->server('SERVER_ADDR')) {
//				exit();
//			}
//		}

		// 鉴权
		$result = $this->check_authority();

		// 如果失败，则走错误逻辑
		if (!$result['state']) {
			if ($result['ecode'] == 1) {
				$this->showmessage('请先登录', config_item('base_url').ADMINDIR."login", TRUE);
			} else if ($result['ecode'] == 2) {
				$this->showmessage('您没有权限', 'blank');
			} else {
				$this->showmessage('发生未知错误，请再登录', config_item('base_url').ADMINDIR."login");
			}
		}
	}


	/**
	 * 用户鉴权
	 *
	 * @return	mixed
	 * 返回结果: 1. $output['state']: 成功(TRUE)/失败(FALSE)，失败还会返回$output['ecode']
	 * 2.$output['ecode']：失败原因，1为未登录，2为权限不够
	 *
	 */
	final protected function check_authority()
	{
		$class_name = $this->router->class;
		$method_name = $this->router->method;

		// 如果是访问login的控制器，则不需要任何鉴权
		if ($class_name == 'login') {
			$output['state'] = TRUE;
			return $output;
		}

		// 否则，先看SESSION中有没有数据
		if (!is_id($this->input->session('suid')) || $this->input->session('stoken')=='') {
			$output['state'] = FALSE;
			$output['ecode'] = 1;
			return $output;
		}

		// 如果有数据，则根据uid去找该用户
		$superadmin = $this->superadmin_model->get_row_by_id($this->input->session('suid'));

		// 如果找不到，或者token错误
		if (empty($superadmin) ||
			!password_verify($this->superadmin_model->get_superadmin_token_seed($superadmin['uid'], $superadmin['password']), $this->input->session('stoken'))) {
			$output['state'] = FALSE;
			$output['ecode'] = 1;
			return $output;
		}

		// 如果用户正确，则判断权限
		$admin_authority = config_item('admin_authority');
		if (isset($admin_authority[$class_name]) && isset($admin_authority[$class_name][$method_name])) {
			if (in_array(-1, $admin_authority[$class_name][$method_name])
				|| in_array($superadmin['authority'], $admin_authority[$class_name][$method_name])) {

				// 如果权限正确，则返回正确
				$this->_superadmin = $superadmin;
				$output['state'] = TRUE;
				return $output;
			}
		}

		// 否则返回权限错误
		$output['state'] = FALSE;
		$output['ecode'] = 2;
		return $output;
	}


	/**
	 * 展示提醒页面
	 *
	 * @param	string	$msg			提醒信息
	 * @param	string	$url_forward	要跳转到的url，如果为空字符串，则为返回来路url，如果为'blank'，则不跳转，如果为'goback'，则返回到上一页，如果为'close'，则点击关闭页面
	 * @param	bool	$show_bg		是否需要显示背景，一般来讲只有不在后台的iframe中才需要显示背景
	 * @param	int		$ms				延迟多少毫秒进行自动跳转
	 * @return	null
	 *
	 */
	protected function showmessage($msg, $url_forward = '', $show_bg = FALSE, $ms = 1000) {

		if ($url_forward == '') {
			$url_forward = $this->input->server('HTTP_REFERER');
		}

		$data = array("msg"=>$msg,"url_forward"=>$url_forward,"show_bg"=>$show_bg,"ms"=>$ms);

		echo $this->load->view(ADMINDIR.'common/header', NULL, TRUE, TRUE);
		echo $this->load->view(ADMINDIR.'common/message', $data, TRUE, TRUE);
		echo $this->load->view(ADMINDIR.'common/footer', NULL, TRUE, TRUE);
		exit;
	}

	/**
	 * 获取默认的分页配置项（可以再返回值基础上面再做修改）
	 *
	 * @param	int		$total			总条数
	 * @return	array					分页配置
	 *
	 */
	protected function get_pagination_config($total) {

		$config['base_url'] = config_item('base_url').ADMINDIR.$this->router->class."/".$this->router->method;
		$config['total_rows'] = $total;
		$config['per_page'] = ADMIN_PER_PAGE;
		$config['page_query_string'] = TRUE;
		$config['use_page_numbers'] = TRUE;
		$config['query_string_segment'] = 'p';
		$config['reuse_query_string'] = TRUE;
		$config['full_tag_open'] = '<ul class="pagination">';
		$config['full_tag_close'] = '</ul>';
		$config['first_tag_open'] = '<li>';
		$config['first_tag_close'] = '</li>';
		$config['last_tag_open'] = '<li>';
		$config['last_tag_close'] = '</li>';
		$config['next_tag_open'] = '<li>';
		$config['next_tag_close'] = '</li>';
		$config['prev_tag_open'] = '<li>';
		$config['prev_tag_close'] = '</li>';
		$config['cur_tag_open'] = '<li class="active"><a href="#">';
		$config['cur_tag_close'] = '</li></a>';
		$config['num_tag_open'] = '<li>';
		$config['num_tag_close'] = '</li>';

		return $config;
	}


	/**
	 * 向显示错误字符串拼接内容
	 *
	 * @param	string	$msg			要拼接的信息
	 * @return	null
	 *
	 */
	protected function apppen_diplayed_error($msg) {
		if ($msg == '') {
			return NULL;
		}

		if ($this->_diplayed_error != '') {
			$this->_diplayed_error .= "<br>";
		}

		$this->_diplayed_error .= $msg;
		return NULL;
	}
}


class MY_Visitor_Controller extends  MY_Controller {
	protected $_visitoradmin;
	protected $_diplayed_error = '';		// 用于显示后端表单验证类库不能显示的错误

	public function __construct()
	{
		define("IN_VISITOR", TRUE);
		parent::__construct();
		$this->config->load('config_visitor');

		// 一些常用的类库、模型和辅助函数直接在这里加载，这样就不需要每次都加载了
		// 因为超级后台流量小，这样造成的资源浪费几乎可以忽略
		$this->load->library('session');
		$this->load->model('visitoradmin_model');
		$this->load->helper(array('form', 'security'));

		// 后台背景图逻辑
		if (!isset($_SESSION['bg'])) {
			$_SESSION['bg'] = mt_rand(1,12);
		}

		// 写日志
		$log_array = array(
			"ip"=>get_ip(),
			"from"=>$this->input->server('HTTP_REFERER'),
			"UA"=>$this->input->server('HTTP_USER_AGENT'),
			"QUERY"=>$this->input->server('QUERY_STRING'),
			"session"=>$_SESSION,
			'post'=>$_POST,
		);
		$this->log->write_log_json($log_array, 'visitor');

		// IP白名单
//		if (!DEBUG) {
//			$this->load->helper('security');
//
//			$admin_ip_white_list = array(array( '115.236.68.58' , '115.236.68.59'),
//				array( '121.41.86.1' , '121.41.86.1'),
//				array( '115.236.59.0' , '115.236.59.255'));
//
//			if ($this->input->server('REMOTE_ADDR') == NULL) {
//				exit();
//			}
//
//			if (!is_ip_in_range($this->input->server('REMOTE_ADDR'), $admin_ip_white_list)
//				&& $this->input->server('REMOTE_ADDR') != $this->input->server('SERVER_ADDR')) {
//				exit();
//			}
//		}

		// 鉴权
		$result = $this->check_authority();

		// 如果失败，则走错误逻辑
		if (!$result['state']) {
			if ($result['ecode'] == 1) {
				$this->showmessage('请先登录', config_item('base_url').VISITORDIR."login", TRUE);
			} else if ($result['ecode'] == 2) {
				$this->showmessage('您没有权限', 'blank');
			} else {
				$this->showmessage('发生未知错误，请再登录', config_item('base_url').VISITORDIR."login");
			}
		}
	}


	/**
	 * 用户鉴权
	 *
	 * @return	mixed
	 * 返回结果: 1. $output['state']: 成功(TRUE)/失败(FALSE)，失败还会返回$output['ecode']
	 * 2.$output['ecode']：失败原因，1为未登录，2为权限不够
	 *
	 */
	final protected function check_authority()
	{
		$class_name = $this->router->class;
		$method_name = $this->router->method;

		// 如果是访问login的控制器，则不需要任何鉴权
		if ($class_name == 'login') {
			$output['state'] = TRUE;
			return $output;
		}

		// 否则，先看SESSION中有没有数据
		if (!is_id($this->input->session('suid')) || $this->input->session('stoken')=='') {
			$output['state'] = FALSE;
			$output['ecode'] = 1;
			return $output;
		}

		// 如果有数据，则根据uid去找该用户
		$visitoradmin = $this->visitoradmin_model->get_row_by_id($this->input->session('suid'));

		// 如果找不到，或者token错误
		if (empty($visitoradmin) ||
			!password_verify($this->visitoradmin_model->get_visitoradmin_token_seed($visitoradmin['uid'], $visitoradmin['password']), $this->input->session('stoken'))) {
			$output['state'] = FALSE;
			$output['ecode'] = 1;
			return $output;
		}

		// 如果用户正确，则判断权限
		$admin_authority = config_item('admin_authority');
		if (isset($admin_authority[$class_name]) && isset($admin_authority[$class_name][$method_name])) {
			if (in_array(-1, $admin_authority[$class_name][$method_name])
				|| in_array($visitoradmin['authority'], $admin_authority[$class_name][$method_name])) {

				// 如果权限正确，则返回正确
				$this->_visitoradmin = $visitoradmin;
				$output['state'] = TRUE;
				return $output;
			}
		}

		// 否则返回权限错误
		$output['state'] = FALSE;
		$output['ecode'] = 2;
		return $output;
	}


	/**
	 * 展示提醒页面
	 *
	 * @param	string	$msg			提醒信息
	 * @param	string	$url_forward	要跳转到的url，如果为空字符串，则为返回来路url，如果为'blank'，则不跳转，如果为'goback'，则返回到上一页，如果为'close'，则点击关闭页面
	 * @param	bool	$show_bg		是否需要显示背景，一般来讲只有不在后台的iframe中才需要显示背景
	 * @param	int		$ms				延迟多少毫秒进行自动跳转
	 * @return	null
	 *
	 */
	protected function showmessage($msg, $url_forward = '', $show_bg = FALSE, $ms = 1000) {

		if ($url_forward == '') {
			$url_forward = $this->input->server('HTTP_REFERER');
		}

		$data = array("msg"=>$msg,"url_forward"=>$url_forward,"show_bg"=>$show_bg,"ms"=>$ms);

		echo $this->load->view(VISITORDIR.'common/header', NULL, TRUE, TRUE);
		echo $this->load->view(VISITORDIR.'common/message', $data, TRUE, TRUE);
		echo $this->load->view(VISITORDIR.'common/footer', NULL, TRUE, TRUE);
		exit;
	}

	/**
	 * 获取默认的分页配置项（可以再返回值基础上面再做修改）
	 *
	 * @param	int		$total			总条数
	 * @return	array					分页配置
	 *
	 */
	protected function get_pagination_config($total) {

		$config['base_url'] = config_item('base_url').VISITORDIR.$this->router->class."/".$this->router->method;
		$config['total_rows'] = $total;
		$config['per_page'] = ADMIN_PER_PAGE;
		$config['page_query_string'] = TRUE;
		$config['use_page_numbers'] = TRUE;
		$config['query_string_segment'] = 'p';
		$config['reuse_query_string'] = TRUE;
		$config['full_tag_open'] = '<ul class="pagination">';
		$config['full_tag_close'] = '</ul>';
		$config['first_tag_open'] = '<li>';
		$config['first_tag_close'] = '</li>';
		$config['last_tag_open'] = '<li>';
		$config['last_tag_close'] = '</li>';
		$config['next_tag_open'] = '<li>';
		$config['next_tag_close'] = '</li>';
		$config['prev_tag_open'] = '<li>';
		$config['prev_tag_close'] = '</li>';
		$config['cur_tag_open'] = '<li class="active"><a href="#">';
		$config['cur_tag_close'] = '</li></a>';
		$config['num_tag_open'] = '<li>';
		$config['num_tag_close'] = '</li>';

		return $config;
	}


	/**
	 * 向显示错误字符串拼接内容
	 *
	 * @param	string	$msg			要拼接的信息
	 * @return	null
	 *
	 */
	protected function apppen_diplayed_error($msg) {
		if ($msg == '') {
			return NULL;
		}

		if ($this->_diplayed_error != '') {
			$this->_diplayed_error .= "<br>";
		}

		$this->_diplayed_error .= $msg;
		return NULL;
	}
}


class MY_Home_Controller extends MY_Controller {
	protected $_uid = 0;			// 用户id
	protected $_openid = '';		// 用户openid

	public function __construct()
	{
		define("IN_HOME", TRUE);
		parent::__construct();
		$this->config->load('config_home');
		$this->load->library('session');
	}

	/**
	 *
	 * @param	bool	$request_login_flag		是否必须处在登录状态
	 * @param	bool	$guest_enable_flag		是否允许游客身份
	 * @param	int		$type					类型 0表示无类型 1为活动类型,2为服务类型，3为清风，4为约会,5为高端服务
	 * @return	null
	 *
	 */
	protected function authority($request_login_flag=TRUE, $guest_enable_flag=FALSE, $type=0){
		//如果不需要授权，那么肯定允许游客登陆
		if ($type==0) {
			$request_login_flag = FALSE;
		}

		// 如果需要登陆状态，那么判断是否有用户信息
		if ($request_login_flag) {
			//如果为活动授权
			if ($type==1) {
				$aid = $this->input->get('aid');
				//如果活动验证没有该活动，则跳转到官网
				if (!isset($aid)) {
					$this->jump_to_index();
				}
				$this->_check_authority($type, $guest_enable_flag);
			}
			//如果为恋爱委托,清风,约会，高端服务
			else if ($type==2 || $type==3 || $type==4 || $type==5) {
				$this->_check_authority($type, $guest_enable_flag);
			}
			else {
				$this->jump_to_index();
			}
		}
		//如果允许游客登陆
		else if ($guest_enable_flag) {
			//如果是活动验证，需要aid
			if ($type==1) {
				$aid = $this->input->get('aid');
				//如果没有该活动，则跳转到官网
				if (!isset($aid)) {
					$this->jump_to_index();
				}
			}
			//如果是服务类型,暂时不需要传入参数
			else if ($type==2) {

			} else {
				$this->jump_to_index();
			}
		}

	}

	private function jump_to_index(){
		//跳转到官网
		$this->load->helper('url');
		redirect(config_item('base_url') . HOMEDIR . 'index');
	}

	private function _check_authority($type, $guest_enable_flag) {
		//首先去url参数中获取用户信息
		$uid = $this->input->get('uid');
		$token = $this->input->get('token');
		$aid = $this->input->get('aid');

		//是否重新创建一个session
		$reset_session = TRUE;

		if (is_id($uid) && $token!='') {
			$valid_uid = $uid;
			$valid_token = $token;
		} else if (isset($_SESSION['uid']) && isset($_SESSION['token'])) {
			$reset_session = FALSE;
			$valid_uid = $_SESSION['uid'];
			$valid_token = $_SESSION['token'];
		} else {
			$valid_uid = 0;
			$valid_token = 'none';
		}

		//验证的check_token
		$check_token = '';

		if ($type==1) {
			$this->load->model("activity_model");
			$check_token = $this->activity_model->encrypt($aid, $valid_uid);
		}
		else if ($type==2 || $type==3 || $type==4 || $type==5) {
			$this->load->model("ad_model");
			$check_token = $this->ad_model->encrypt($valid_uid);
		}

		if ($valid_token === $check_token) {
			if ($reset_session) {
				//验证通过，新建一个sessionid并更新session内的uid
				unset($_SESSION['uid']);
				unset($_SESSION['token']);
				$this->session->sess_regenerate(TRUE);
			}
			$_SESSION['uid'] = $valid_uid;
			$_SESSION['token'] = $valid_token;
			$this->_uid = $valid_uid;
		} else if (!$guest_enable_flag) {
			$this->jump_to_index();
		}

	}

}

class MY_Wx_Controller extends MY_Controller {
	protected $_uid = 0;			// 用户id
	protected $_user = NULL;		// 用户详细信息，如果不允许游客登录，则肯定有p_userweixin中的信息，如果鉴权时设置为需要用户详情，则肯定有p_userdetail中的信息

	//用于兼容接口
	protected $_api_code = 1000; //接口代码
	protected $_token = 'weixin';			//客户端token 微信不需要 随便传个- -
	protected $_key = 'weixin';				//客户端秘钥 微信不需要 随便传个- -
	protected $_key_pw = 'weixin';			//客户端密码 微信不需要 随便传个- -
	protected $_platform = PLATFORM_WEIXIN;	//平台代码
	protected $_version = 100000;			//版本号
	protected $_canal = 'weixin';			//渠道标识符
	protected $_device = 'weixin';			//设备型号，如iPhone5s、Sumsung S4等
	protected $_system_version = 'weixin';	//系统版本，如iOS7.1、Android 4.1等
	protected $_imei = 'weixin';			//IMEI码
	protected $_cid = 'weixin';				//推送id
	protected $_gc_id_ios = 'weixin';		//IOS端的个推ID
	protected $_xc_id = 'weixin';			//信鸽的推送id

	protected $_iv = '';		//iv既是用于生成key的种子，也是密钥偏移量

	public function __construct()
	{
		define("IN_WX", TRUE);
		parent::__construct();
		$this->config->load('config_wx');
		$this->load->service('Wx_service');
		$this->load->service('common_service');

		$this->_iv = base64_decode('ujmhygtfrdbfghjytghfrd==');
	}

	/**
	 *
	 * @param	bool	$request_login_flag		是否必须处在登录状态
	 * @param	bool	$guest_enable_flag		是否允许游客身份
	 * @param	bool	$need_userdetail		是否需要用户详情
	 * @return	null
	 *
	 */
	protected function authority($request_login_flag=TRUE, $guest_enable_flag=FALSE, $need_userdetail=FALSE){
//		// IP白名单
//		if (!DEBUG) {
//			$this->load->helper('security');
//
//			$admin_ip_white_list = array(array( '125.118.59.10' , '125.118.59.10'));
//
//			if ($this->input->server('REMOTE_ADDR') == NULL) {
//				exit();
//			}
//
//			if (!is_ip_in_range($this->input->server('REMOTE_ADDR'), $admin_ip_white_list)
//				&& $this->input->server('REMOTE_ADDR') != $this->input->server('SERVER_ADDR')) {
//				$this->load->helper('url');
//				redirect(config_item('base_url').'assets/html/sys_upgrade_notice.html');
//				exit();
//			}
//		}

		if ($request_login_flag) {
			$guest_enable_flag = FALSE;
		}

		//用户授权及获取用户的个人信息
		$wechatObj = new Wx_service(DEBUG);

		if (ENVIRONMENT === 'development') {
//			 本地开发环境下自己绑定一个uid
			$this->_user = $this->user_model->get_row_by_openid('oQQGTuGWFQJ9K00Tc2TyzZz7C1X0');
		}
		else {
			$this->_user = $wechatObj->authority($guest_enable_flag);
		}
		$this->_uid = $this->_user['uid'];

		// 判断用户是否已经登录注册完成
		if ($request_login_flag) {
			if ($this->_uid==0) {
				//如果未完成,跳转到问卷页面
				$this->load->helper('url');
				redirect(config_item('base_url').'wx/login');
				exit();
			}
		}

		// 如果用户已经登录，并且需要用户详情信息
		if (is_id($this->_uid) && $need_userdetail) {
			$this->_user = array_merge($this->_user, $this->user_model->get_row_by_id($this->_uid, 'p_userdetail'));
		}

		// 如果是注册用户从公众号自定义菜单打开公众号，视作一次登录，进行一些更新操作
		if (is_id($this->_uid) && $this->input->server('HTTP_REFERER')=='') {
			$useredition = $this->user_model->get_row_by_id($this->_uid, 'p_useredition');
			$this->user_model->update_by_login($useredition, $this->_platform,
				$this->_version, $this->_canal, $this->_device, $this->_system_version, $this->_user['openid'],
				$this->_cid, $this->_gc_id_ios, $this->_xc_id);
		}
	}
}