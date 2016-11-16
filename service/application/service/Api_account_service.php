<?php
/**
 * 账号系统接口服务，如登录、注册、找回密码等等
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/7
 * Time: 21:19
 */

class Api_account_service extends Api_Service {

	/**
	 *
	 * 接口名称：upload_image/upload
	 * 接口功能：用户上传照片
	 * 接口编号：0102
	 * 接口加密名称：D3ZgFGyusewfetEtk
	 * @param $username  string 用户名
	 * @return true/false
	 * $output['state']: 调用成功/失败，成功还会返回$output['user']，失败还会返回$output['error']和$output['ecode']
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['user']: 用户的基本信息
	 */
	public function upload_image($username){

		$api_code = '10080';

		if(isset($_FILES['webfile']['name']) && $_FILES['webfile']['name'] != ''){

			$this->load->library('upload');
			$this->load->helper('string');

			$config = array();
			$config['upload_path'] = './uploads/webimage';
			$config['allowed_types'] = '*';
			$config['file_name'] = time().strtolower(random_string('alnum',10));
			$config['overwrite'] = false;

			$this->upload->initialize($config);

			//如果上传失败
			if(!$this->upload->do_upload('webfile')){
				//输出错误
				$output['state'] = FALSE;
				$output['error'] = $username."图片上传失败：".$this->upload->display_errors('','');
				$output['ecode'] = $api_code;
				return $output;
			}

			//获取mime
			$image_mime = getimagesize($config['upload_path'].'/'.$config['file_name'])['mime'];

			//从新拼接名字
			$mime_filename = $config['file_name'].'.'.substr(strrchr($image_mime,'/'),1);

			//重新更改文件名
			rename($config['upload_path'].'/'.$config['file_name'],$config['upload_path'].'/'.$mime_filename);

			//下面代码是上传到七牛或者写到数据库，数据库未进行,所以不再写

		}

		//直接输出

		$output['state'] = true;
		$output['ecode'] = $api_code."200";
		return $output;
	}

	/**
	 *
	 * 接口名称：request_register_code
	 * 接口功能：使用手机号注册时，请求验证码
	 * 接口编号：0101
	 * 接口加密名称：m65CncV3dzgnowe6
	 *
	 * @param string	$phone			手机号，必须为以1开头的11位数字
	 * @param string	$key			本次秘钥，长度必须为16位，由大小写字母和随机数生成
	 * @param string	$key_pw			密码，由phone和key生成，全部小写
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 */
	public function request_register_code($phone, $key, $key_pw)
	{
		$api_code = '0101';

		// 检查手机号是否合法
		$this->load->helper('string');

		if (!is_phone($phone)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "手机号不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 检查秘钥和密码是否合法
		$result = $this->check_key_and_pw($key, $key_pw, array($phone, $key), array('K147bGitvhL0eb2e','O0aWpfLnJopu6p77','SicE85EwVindEX9N'));
		if ($result['state'] == FALSE) {
			return $result;
		}

		// 检测发送时间间隔
		$this->load->model('phone_code_model');
		if (!$this->phone_code_model->check_send_time($phone, SMS_TYPE_REGISTER)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "请求过于频繁，请稍后重试";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 检测该手机号是否注册
		if (!$this->user_model->check_phone_available($phone)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "该手机号已被注册";
			$output['ecode'] = $api_code."003";
			return $output;
		}

		// 生成验证码
		$phone_code['phone'] = $phone;
		$phone_code['code'] = random_string('numeric', 6);
		$phone_code['type'] = SMS_TYPE_REGISTER;

		// 写入数据库
		if (!$this->phone_code_model->insert($phone_code, FALSE)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "数据库错误";
			$output['ecode'] = "0000003";
			return $output;
		}

		// 发送验证码
		$this->load->library('sms');
		$result = $this->sms->send_valid_message($phone, $phone_code['code']);
		if ($result['state'] == FALSE) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "短信发送时出错";
			$output['ecode'] = $api_code."004";
			return $output;
		}

		$output['state'] = TRUE;
		return $output;
	}

	/**
	 *
	 * 接口名称：register_by_phone
	 * 接口功能：用户使用手机号注册
	 * 接口编号：0102
	 * 接口加密名称：1CJa2uNRFysJmzoe
	 *
	 * @param string	$phone			手机号，必须为以1开头的11位数字
	 * @param string	$code			验证码
	 * @param string	$pw				密码（一级加密）
	 * @param string	$key			本次秘钥，长度必须为16位，由大小写字母和随机数生成
	 * @param string	$key_pw			密码，由phone、code和key生成，全部小写
	 * @param int		$platform		对应平台
	 * @param int		$version		版本号，格式：AABBCCC
	 * @return mixed
	 * $output['state']: 调用成功/失败，成功还会返回$output['user']
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['user']: 用户的个人信息，包含uid、token、refresh_token、expire_in
	 * $output['lover_flag']: 为1表示正常模式，为0表示审查员模式
	 */
	public function register_by_phone($phone, $code, $pw, $key, $key_pw, $platform, $version)
	{
		$api_code = '0102';

		$this->load->helper('date');

		// 检查手机号是否合法
		$this->load->helper('string');
		if (!is_phone($phone)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "手机号不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 检查秘钥和密码是否合法
		$result = $this->check_key_and_pw($key, $key_pw, array($phone, $code, $key), array('gug169UhskwCsUeW','UiRmzvhDnPbEgI6b','5xzlqs2xnVXW4PX3','Y74pDAs19O413SH6'));
		if ($result['state'] == FALSE) {
			return $result;
		}

		// 检测用户密码是否合法
		if (strlen($pw)!=32) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "用户密码不合法";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 检测验证码是否正确
		$this->load->model('phone_code_model');
		$result = $this->phone_code_model->check_code($phone, $code, SMS_TYPE_REGISTER);
		// 如果不正确，则根据错误码返回对应的错误
		if ($result['state'] == FALSE) {
			if ($result['ecode'] == 2) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "验证码错误";
				$output['ecode'] = $api_code."003";
				return $output;
			}
			else {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "验证码失效，请重新获取";
				$output['ecode'] = $api_code."004";
				return $output;
			}
		}

		// 检测该手机号是否注册
		if (!$this->user_model->check_phone_available($phone)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "该手机号已被注册";
			$output['ecode'] = $api_code."005";
			return $output;
		}

		// 添加用户
		$data['phone'] = $phone;
		$data['register_time'] = time();
		$data['last_time'] = get_microtime();
		$data['password'] = $this->user_model->get_pw_seed_from_first_pw($pw);
		if ($data['password'] == NULL) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "用户密码不合法";
			$output['ecode'] = $api_code."002";
			return $output;
		}
		$data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);

		$uid = $this->user_model->insert_user($data);

		// 如果添加失败
		if (!is_id($uid)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "数据库错误";
			$output['ecode'] = "0000003";
			return $output;
		}

		//添加统计数据
		$this->load->service('total_service');
		$this->total_service->reg_init($uid);

		// 如果添加成功，清空验证码
		$this->phone_code_model->delete(array('phone'=>$phone));

		// 重设token
		$result = $this->user_model->reset_token($uid);
		if ($result['state'] == FALSE) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "数据库错误";
			$output['ecode'] = "0000003";
			return $output;
		}

		// 添加BI
		$bi_list = array();
		$bi_list[] = array('uid'=>$uid, 'type'=>1);
		$bi_list[] = array('uid'=>$uid, 'type'=>2, 'value'=>1);
		$this->load->model('bi_model');
		$this->bi_model->insert_bi_batch($bi_list);

		// 构建返回值
		$output['user'] = $result['user'];
		$output['lover_flag'] = $this->get_lover_flag($platform, $version);
		$output['state'] = TRUE;
		return $output;
	}

	/**
	 *
	 * 接口名称：login_by_phone_and_pw
	 * 接口功能：用户使用手机号和密码登录
	 * 接口编号：0103
	 * 接口加密名称：A6mUb39TRd6ekFtT
	 *
	 * @param string	$phone			手机号，必须为以1开头的11位数字
	 * @param string	$pw				密码（二级加密）
	 * @param string	$key			本次秘钥，长度必须为16位，由大小写字母和随机数生成
	 * @param string	$key_pw			密码，由phone、pw和key生成，全部小写
	 * @param int		$platform		对应平台
	 * @param int		$version		版本号，格式：AABBCCC
	 * @return mixed
	 * $output['state']: 调用成功/失败，成功还会返回$output['user']
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['user']: 用户的个人信息，包含uid、token、refresh_token、expire_in（公众号只包含uid）
	 * $output['lover_flag']: 为1表示正常模式，为0表示审查员模式
	 */
	public function login_by_phone_and_pw($phone, $pw, $key, $key_pw, $platform, $version)
	{
		$api_code = '0103';

		// 检查手机号是否合法
		$this->load->helper('string');
		if (!is_phone($phone)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "手机号不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 检查秘钥和密码是否合法
		$result = $this->check_key_and_pw($key, $key_pw, array($phone, $pw, $key), array('MAQQef93r4QghBAx','7OFm2nLUmb9bs9wE','1VVv8InPL9wFl0Zs','9cUDJKlP24gFLhAB'));
		if ($result['state'] == FALSE) {
			return $result;
		}

		// 检测用户密码是否合法
		if (strlen($pw)!=32) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "用户密码不合法";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 检测用户名密码是否正确
		$this->user_model->set_table_name('p_userdetail');
		$userdetail = $this->user_model->get_one(array('phone'=>$phone), FALSE, 'uid,password');
		if (empty($userdetail)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "该手机号尚未注册";
			$output['ecode'] = $api_code."003";
			return $output;
		}

		if (!password_verify($this->user_model->get_pw_seed_from_second_pw($pw), $userdetail['password'])) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "密码错误";
			$output['ecode'] = $api_code."004";
			return $output;
		}

		$uid = $userdetail['uid'];

		// 如果不是公众号，则重设token
		if ($platform != PLATFORM_WEIXIN) {
			$result = $this->user_model->reset_token($uid);
			if ($result['state'] == FALSE) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "数据库错误";
				$output['ecode'] = "0000003";
				return $output;
			}

			$output['user'] = $result['user'];
		}
		else {
			$output['user'] = array('uid'=>$uid);
		}


		// 添加BI  新增微信登录类型
		if (defined('IN_WX')){
			$bi = array('uid'=>$uid, 'type'=>2, 'value'=>4);
		} else {
			$bi = array('uid'=>$uid, 'type'=>2, 'value'=>2);
		}
		$this->load->model('bi_model');
		$this->bi_model->insert_bi($bi);

		// 构建返回值
		$output['lover_flag'] = $this->get_lover_flag($platform, $version);
		$output['state'] = TRUE;
		return $output;
	}

	/**
	 *
	 * 接口名称：request_login_code
	 * 接口功能：使用手机号登录时，请求验证码
	 * 接口编号：0104
	 * 接口加密名称：W3OqUPah0Id3vqqM
	 *
	 * @param string	$phone			手机号，必须为以1开头的11位数字
	 * @param string	$key			本次秘钥，长度必须为16位，由大小写字母和随机数生成
	 * @param string	$key_pw			密码，由phone和key生成，全部小写
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 */
	public function request_login_code($phone, $key, $key_pw)
	{
		$api_code = '0104';

		// 检查手机号是否合法
		$this->load->helper('string');
		if (!is_phone($phone)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "手机号不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 检查秘钥和密码是否合法
		$result = $this->check_key_and_pw($key, $key_pw, array($phone, $key), array('lZ07LGhthFVmgla6','SVaEf5pJdrUYs3A9','JL2v4BdZNK4nulnq'));
		if ($result['state'] == FALSE) {
			return $result;
		}


		// 检测发送时间间隔
		$this->load->model('phone_code_model');
		if (!$this->phone_code_model->check_send_time($phone, SMS_TYPE_LOGIN)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "请求过于频繁，请稍后重试";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 检测该手机号是否注册
		if ($this->user_model->check_phone_available($phone)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "该手机号尚未注册";
			$output['ecode'] = $api_code."003";
			return $output;
		}

		// 生成验证码
		$phone_code['phone'] = $phone;
		$phone_code['code'] = random_string('numeric', 6);
		$phone_code['type'] = SMS_TYPE_LOGIN;

		// 写入数据库
		if (!$this->phone_code_model->insert($phone_code, FALSE)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "数据库错误";
			$output['ecode'] = "0000003";
			return $output;
		}

		// 发送验证码
		$this->load->library('sms');
		$result = $this->sms->send_valid_message($phone, $phone_code['code']);
		if ($result['state'] == FALSE) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "短信发送时出错";
			$output['ecode'] = $api_code."004";
			return $output;
		}

		$output['state'] = TRUE;
		return $output;
	}

	/**
	 *
	 * 接口名称：login_by_phone_and_code
	 * 接口功能：用户使用手机号和验证码登录
	 * 接口编号：0105
	 * 接口加密名称：dkQPfEoMolfhNSd8
	 *
	 * @param string	$phone			手机号，必须为以1开头的11位数字
	 * @param string	$code			验证码
	 * @param string	$key			本次秘钥，长度必须为16位，由大小写字母和随机数生成
	 * @param string	$key_pw			密码，由phone、code和key生成，全部小写
	 * @param int		$platform		对应平台
	 * @param int		$version		版本号，格式：AABBCCC
	 * @return mixed
	 * $output['state']: 调用成功/失败，成功还会返回$output['user']
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['user']: 用户的个人信息，包含uid、token、refresh_token、expire_in（公众号只包含uid）
	 * $output['lover_flag']: 为1表示正常模式，为0表示审查员模式
	 */
	public function login_by_phone_and_code($phone, $code, $key, $key_pw, $platform, $version)
	{
		$api_code = '0105';

		// 检查手机号是否合法
		$this->load->helper('string');
		if (!is_phone($phone)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "手机号不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 检查秘钥和密码是否合法
		$result = $this->check_key_and_pw($key, $key_pw, array($phone, $code, $key), array('i3pCQGCsYrxatKeH','8VVi5MPzZ38s19h0','2CHiSgg6Dka4aLuS','WeBqPq2k0WFKqDpK'));
		if ($result['state'] == FALSE) {
			return $result;
		}

		// 检测验证码是否正确
		$this->load->model('phone_code_model');
		$result = $this->phone_code_model->check_code($phone, $code, SMS_TYPE_LOGIN);
		// 如果不正确，则根据错误码返回对应的错误
		if ($result['state'] == FALSE) {
			if ($result['ecode'] == 2) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "验证码错误";
				$output['ecode'] = $api_code."002";
				return $output;
			}
			else {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "验证码失效，请重新获取";
				$output['ecode'] = $api_code."003";
				return $output;
			}
		}

		// 根据手机号获取用户id
		$this->user_model->set_table_name('p_userdetail');
		$userdetail = $this->user_model->get_one(array('phone'=>$phone), FALSE, 'uid');
		if (empty($userdetail)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "该手机号尚未注册";
			$output['ecode'] = $api_code."004";
			return $output;
		}

		$uid = $userdetail['uid'];

		// 如果不是公众号，则重设token
		if ($platform != PLATFORM_WEIXIN) {
			$result = $this->user_model->reset_token($uid);
			if ($result['state'] == FALSE) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "数据库错误";
				$output['ecode'] = "0000003";
				return $output;
			}
			$output['user'] = $result['user'];
		}
		else {
			$output['user'] = array('uid'=>$uid);
		}

		// 如果登录成功，则清空验证码
		$this->phone_code_model->delete(array('phone'=>$phone));

		// 添加BI  新增微信登录类型
		if (defined('IN_WX')){
			$bi = array('uid'=>$uid, 'type'=>2, 'value'=>4);
		} else {
			$bi = array('uid'=>$uid, 'type'=>2, 'value'=>2);
		}
		$this->load->model('bi_model');
		$this->bi_model->insert_bi($bi);

		// 构建返回值
		$output['lover_flag'] = $this->get_lover_flag($platform, $version);
		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：logout
	 * 接口功能：用户注销登录
	 * 接口编号：0106
	 * 接口加密名称：bRAU87svyTz2ZbOM
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			验证码
	 * @param int		$platform		平台代码
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 */
	public function logout($uid, $token, $platform)
	{
		$api_code = '0106';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}

		// 删除推送id
		$useredition = array();
		if ($platform==PLATFORM_ANDROID) {
			$useredition['gc_id'] = '';
			$useredition['xc_id'] = '';
		}
		elseif ($platform==PLATFORM_IOS_INHOUSE || $platform==PLATFORM_IOS_APPSTORE) {
			$useredition['ac_id'] = '';
			$useredition['gc_id_ios'] = '';
		}
		else{
			$output['state'] = FALSE;
			$output['error'] = "平台信息错误";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		if (!$this->user_model->update_useredition($useredition, array('uid'=>$uid))) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "数据库错误";
			$output['ecode'] = "0000003";
			return $output;
		}

		// 清空token
		$this->load->helper('string');
		$userbase['token'] = '';
		$userbase['refresh_token'] = '';
		$this->user_model->update_userbase($userbase, array('uid'=>$uid));

		// 添加BI
		$bi = array('uid'=>$uid, 'type'=>3);
		$this->load->model('bi_model');
		$this->bi_model->insert_bi($bi);

		$output['state'] = TRUE;
		return $output;
	}

	/**
	 *
	 * 接口名称：change_pw
	 * 接口功能：用户修改密码
	 * 接口编号：0107
	 * 接口加密名称：Tny3h95HCmO0Vkk0
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param string	$old_pw			旧密码（二级加密）
	 * @param string	$new_pw			新密码（一级加密）
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 */
	public function change_pw($uid, $token, $old_pw, $new_pw)
	{
		$api_code = '0107';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token, TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		$user = $result['user'];

		// 检测用户密码是否合法
		if (strlen($old_pw)!=32) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "旧密码不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 检测用户密码是否合法
		if (strlen($new_pw)!=32) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "新密码不合法";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 检测旧密码是否正确
		if (!password_verify($this->user_model->get_pw_seed_from_second_pw($old_pw), $user['password'])) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "旧密码错误";
			$output['ecode'] = $api_code."003";
			return $output;
		}

		// 检测新旧密码是否相同
		if ($old_pw == $this->user_model->get_second_pw_from_first_pw($new_pw)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "新旧密码不得相同";
			$output['ecode'] = $api_code."004";
			return $output;
		}

		// 生成新密码
		$data['password'] = $this->user_model->get_pw_seed_from_first_pw($new_pw);
		if ($data['password'] == NULL) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "新密码不合法";
			$output['ecode'] = $api_code."002";
			return $output;
		}
		$data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);

		// 更新数据库
		if (!$this->user_model->update_userdetail($data, $uid)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "数据库错误";
			$output['ecode'] = "0000003";
			return $output;
		}

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：refresh_token
	 * 接口功能：用户刷新token
	 * 接口编号：0108
	 * 接口加密名称：sPsl49YM1bWakiBX
	 *
	 * @param int		$uid				用户id
	 * @param string	$refresh_token		刷新凭证
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['user']: 用户的个人信息，包含uid、token、refresh_token、expire_in
	 */
	public function refresh_token($uid, $refresh_token)
	{
		$api_code = '0108';

		// 检测凭据是否合法
		if (strlen($refresh_token) != 32) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "凭据不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 获取用户信息
		$user = $this->user_model->get_row_by_id($uid, 'p_userbase', 'refresh_token');

		// 如果凭据错误
		if (!isset($user['refresh_token']) || $user['refresh_token']!==$refresh_token) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "凭据错误";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 重设token
		$result = $this->user_model->reset_token($uid);
		if ($result['state'] == FALSE) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "数据库错误";
			$output['ecode'] = "0000003";
			return $output;
		}

		// 构建返回值
		$output['user'] = $result['user'];
		$output['state'] = TRUE;
		return $output;
	}

}