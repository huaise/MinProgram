<?php
/**
 * 个人信息接口服务，如修改个人信息、上传头像、修改配置等等
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/7
 * Time: 21:19
 */

class Api_personal_service extends Api_Service {

	/**
	 *
	 * 接口名称：get_user_detail
	 * 接口功能：用户获取个人信息和系统配置
	 * 接口编号：0201
	 * 接口加密名称：D3ZgFGyuseP52HlW
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$platform		平台代码
	 * @param int		$version		版本号
	 * @param string	$canal			渠道标识符
	 * @param string	$device			设备型号，如iPhone5s、Sumsung S4等
	 * @param string	$system_version	系统版本，如iOS7.1、Android 4.1等
	 * @param string	$imei			IMEI码
	 * @param string	$cid			推送id
	 * @param string	$gc_id_ios		IOS端的个推ID
	 * @param string	$xc_id			信鸽的推送id
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['user']: 调用成功时返回，包含用户的基本信息，如昵称、头像等
	 * $output['album']: 调用成功时返回，包含用户相册信息（最新的一页）
	 * $output['user_config']: 调用成功时返回，包含用户的配置，如是否接收匹配通知，是否接受私聊通知等
	 * $output['area_state']: 调用成功时返回，最多包括以下三个字段
	 * 		open_state（0表示未开通，1表示开通但是人数不够，2表示开通且人数足够）
	 * 		current（open_state为1时才返回，表示当前人数）
	 * 		required（open_state为1时才返回，表示需要的人数）
	 * $output['task']: 调用成功时返回，表示任务列表
	 * $output['coupon_num']: 调用成功时返回，表示用户的可用消费券数量
	 * $output['order_num']: 调用成功时返回，表示用户的支付成功订单数量
	 * $output['activity_num']: 调用成功时返回，表示用户的已报名活动数量
	 * $output['ad']: 返回一条弹窗广告,包含ad_id, title, image, url, share_url,share_title,share_desc,type(4为会员广告（内链到会员服务）， 5为活动广告（带验证的活动链接）， 6为验证广告（待验证的链接）)
	 * $output['system_config']: 调用成功时返回，包含系统配置，如匹配时间，服务器时间等
	 * $output['lover_flag']: 为1表示正常模式，为0表示审查员模式
	 *
	 */
	public function get_user_detail($uid, $token, $platform, $version, $canal, $device, $system_version, $imei, $cid, $gc_id_ios, $xc_id)
	{
//		$api_code = '0201';

		$time = time();

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token, TRUE, TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		$user = $result['user'];

		// 如果不是微信登录，则进行一些更新操作（微信登录的相关操作已经在第一次打开页面的时候进行了）
		if (!defined('IN_WX')){
			$this->user_model->update_by_login($user, $platform, $version, $canal, $device, $system_version, $imei, $cid, $gc_id_ios, $xc_id);
		}

		// 返回用户信息
		$output['user'] = $this->user_model->send_userdetail($user, $uid);
		$output['user_config'] = $this->user_model->send_useredition($user);

		// 返回相册信息
		$this->load->model('user_album_model');
		$output['album'] = $this->user_album_model->get_list($uid, 0, MAX_ALBUM_PER_GET);

		// 城市状态
		$this->load->model('area_model');
		$output['area_state'] = $this->area_model->get_area_state($user['province'], $user['city']);

		// 任务列表
		$this->load->model('task_model');
		$output['task'] = $this->task_model->get_list();
		// 用户已完成的任务列表
		$this->load->model('task_log_model');
		$user_task_log = $this->task_log_model->get_list($uid);
		foreach ($output['task'] as $key=>$val){
			if (isset($user_task_log[$val['t_id']])){
				$output['task'][$key]['state'] = 0;
			} else {
				$output['task'][$key]['state'] = 1;
			}

			$output['task'][$key]['vip'] = $val['remark'];
		}
		$this->load->library('send');
		$output['task'] = $this->send->send($output['task'], 'task');

		// 消费券数量
		$current_date = date('Y-m-d');
		$this->load->model('coupon_model');
		$output['coupon_num'] = $this->coupon_model->count(array('uid'=>$uid, 'invite_flag'=>0, 'consume_flag'=>0, 'end_date >='=>$current_date));

		// 支付成功订单数量
		$this->load->model('pingpp_order_model');
		$output['order_num'] = $this->pingpp_order_model->count(array('uid'=>$uid, 'state'=>2,  'type <>' => ORDER_TYPE_WEIXIN_CASH));

		//已报名的活动数量
		$this->load->model('activity_sign_model');
		$output['activity_num'] = $this->activity_sign_model->count(array('uid'=>$uid, 'pay_state'=>2));

		// 弹出广告
		$this->load->model('ad_model');
		$ad = $this->ad_model->get_valid_ad_list(2);
		if ($ad) {
			$output['ad'] = $this->ad_model->send($ad, $uid);
		}

		// 系统配置
		$this->load->model('config_model');
		$output['system_config'] = $this->config_model->get_rows_by_name_array('match_time');
		$output['system_config']['server_time'] = $time;

		// 优惠活动配置
		$output['system_config']['vip_activity_time'] = strtotime(VIP_ACTIVITY_TIME);
		$output['system_config']['vip_activity_register'] = VIP_ACTIVITY_REGISTER;

		$output['state'] = TRUE;
		$output['lover_flag'] = $this->get_lover_flag($platform, $version);

		return $output;
	}


	/**
	 *
	 * 接口名称：edit_info
	 * 接口功能：用户修改自己的个人信息
	 * 接口编号：0202
	 * 接口加密名称：A7wqyrZSKVTI1MQx
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param array		$info_list		用户修改的个人信息，数组形式
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['user']: 调用成功时返回，包含用户的基本信息，如昵称、头像等
	 * $output['area_state']: 仅当修改了居住地且调用成功时返回，最多包括以下三个字段
	 * 		open_state（0表示未开通，1表示开通但是人数不够，2表示开通且人数足够）
	 * 		current（open_state为1时才返回，表示当前人数）
	 * 		required（open_state为1时才返回，表示需要的人数）
	 *
	 */
	public function edit_info($uid, $token, $info_list)
	{
		$api_code = '0202';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token, TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		$user = $result['user'];
		$data = array();		// 要更新的信息

		// 性别
		if (isset($info_list['gender'])) {
			// 如果性别合法且没有设置过性别
			if ($info_list['gender']==1 || $info_list['gender']==2) {
				if (!is_id($user['gender'])) {
					$user['gender'] = $info_list['gender'];
					$data['gender'] = $info_list['gender'];
				}
			}
			else{
				//输出错误
				$output['state'] = FALSE;
				$output['error'] = "性别不合法";
				$output['ecode'] = $api_code."001";
				return $output;
			}
		}

		// 不能为空的字段
		$keys = array('username');
		foreach ($keys as $key) {
			// 如果为空则去除
			if (isset($info_list[$key]) && $info_list[$key]=='') {
				unset($info_list[$key]);
			}
		}

		// 其他通用字段
		$keys = array('height', 'weight', 'salary', 'education', 'industry',
			'position_1', 'position_2', 'position_3', 'blood_type', 'only_child',
			'province', 'city', 'district', 'province_n', 'city_n', 'province_r', 'city_r',
			'target_age_min', 'target_age_max', 'target_education', 'target_height_min', 'target_height_max',
			'target_salary', 'target_look', 'salary_vs_look', 'birthday', 'match_flag', 'invite_expire_remind_flag');

		foreach ($keys as $key) {
			if (isset($info_list[$key]) && $info_list[$key] != $user[$key]) {
				$user[$key] = $info_list[$key];
				$data[$key] = $info_list[$key];
			}
		}

		// 需要进行敏感词检测的字段
		$keys = array('username', 'hobby', 'sign', 'weixin_id', 'introduction', 'expect');

		foreach ($keys as $key) {
			if (isset($info_list[$key]) && $info_list[$key] != $user[$key]) {
				$this->load->helper('minganci');
				$info_list[$key] = filter_minganci($info_list[$key]);
				$user[$key] = $info_list[$key];
				$data[$key] = $info_list[$key];
			}
		}

		// 更新用户信息
		if (!empty($data)) {
			if (!$this->user_model->update_userdetail($data, $uid)) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "数据库错误";
				$output['ecode'] = "0000003";
				return $output;
			}
		}

		// 如果设置了省份城市等信息，则需要返回城市状态
		if (isset($info_list['province']) || isset($info_list['city'])) {
			$this->load->model('area_model');
			$output['area_state'] = $this->area_model->get_area_state($user['province'], $user['city']);
		}

		// 返回用户信息
		$output['user'] = $this->user_model->send_userdetail($user, $uid);

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：config_settings
	 * 接口功能：用户进行系统配置
	 * 接口编号：0203
	 * 接口加密名称：tFooy6Dx5zyOezIW
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$info_list		用户修改的系统配置，数组形式
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['user_config']: 调用成功时返回，该用户目前的系统配置
	 *
	 */
	public function config_settings($uid, $token, $info_list)
	{
//		$api_code = '0203';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token, FALSE, TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		$user = $result['user'];
		$data = array();		// 要更新的信息

		$keys = array('match_inform_flag', 'chat_inform_flag');

		foreach ($keys as $key) {
			if ($info_list[$key] == '') {
				$info_list[$key] = 0;
			}
			if ($user[$key] != $info_list[$key]) {
				$user[$key] = $info_list[$key];
				$data[$key] = $info_list[$key];
			}
		}

		// 更新用户配置
		if (!empty($data)) {
			if (!$this->user_model->update_useredition($data, array('uid'=>$uid))) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "数据库错误";
				$output['ecode'] = "0000003";
				return $output;
			}
		}

		// 返回用户信息
		$output['user_config'] = $this->user_model->send_useredition($user);

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：upload_avatar
	 * 接口功能：用户上传头像
	 * 接口编号：0204
	 * 接口加密名称：kQP1sfdHVOlWI4sr
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * 注：上传的文件的key请设置为upfile
	 *
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['user']: 调用成功时返回，包含用户的基本信息，如昵称、头像等
	 *
	 */
	public function upload_avatar($uid, $token)
	{
		$api_code = '0204';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token, TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}
		$user = $result['user'];

		// 对于非审查员账号，如果不是会员，则每月只有一次修改机会
		if (DEBUG || $uid != '6437579990300098560') {
			if (!$this->user_model->is_vip($user) && $user['avatar']!='') {
				$temp_array = explode('-', $user['last_avatar_date']);
				if (date('Y')==$temp_array[0] && date('m')==$temp_array[1]) {
					// 输出错误
					$output['state'] = FALSE;
					$output['error'] = "本月修改头像机会已经用完";
					$output['ecode'] = $api_code."004";
					return $output;
				}
			}
		}

		// 图片上传
		$this->load->library('upload');
		$this->load->helper('string');

		// 头像的单图上传
		if (!isset($_FILES['upfile']['name']) || $_FILES['upfile']['name']=='') {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "找不到上传的文件";
			$output['ecode'] = $api_code."001";
			return $output;
		}


		$config = array();
		$config['upload_path'] = './uploads/avatar';
		$config['allowed_types'] = 'jpg|jpeg|png';
		$config['file_name'] = time().strtolower(random_string('alnum',10));
		$config['overwrite'] = FALSE;

		$this->upload->initialize($config);

		// 如果上传失败
		if (!$this->upload->do_upload('upfile')) {
			//输出错误
			$output['state'] = FALSE;
			$output['error'] = "头像上传失败：".$this->upload->display_errors('','');
			$output['ecode'] = $api_code."002";
			return $output;
		}
		$upload_result = $this->upload->data();

		$this->load->service('common_service');

/*		//调用face++接口识别人脸
		$face = $this->common_service->get_face($upload_result);
		$avatar_state = $face['avatar_state'];
		$gender = $face['gender'];
		//判断人脸性别是否符合
		if ($gender!=$user['gender']) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "人脸性别与用户性别不符合";
			$output['ecode'] = $api_code."003";
			return $output;
		}*/

		//调用youtu识别人脸
		$face = $this->common_service->get_youtu($upload_result);
		$avatar_state = $face['avatar_state'];
		if ($avatar_state == 1) {
			$output['state'] = FALSE;
			$output['error'] = '检测到您上传的照片不符合规范，请上传清晰的真人头像';
			$output['ecode'] = $api_code."003";
			return $output;
		}

		// 上传七牛
		$this->upload->upload_to_qiniu(array(array('path'=>$config['upload_path'].'/'.$upload_result['file_name'],'key'=>'avatar/'.$upload_result['file_name'])));

		// 更新用户信息
		$userdetail['avatar'] = $upload_result['file_name'];
		$userdetail['avatar_state'] = $avatar_state;

		$first_upload = ($user['avatar']=='');

		//如果是第一次上传，自动给用户打分
		if ($first_upload) {
			$face_score = $this->common_service->get_youtu_score($upload_result);
			$userdetail['look_grade_total'] = (int)($face_score['score']/2);
			$userdetail['look_grade_times'] = 10;
		}


		// 如果不是第一次上传，则需要记录时间
		if (!$first_upload) {
			$userdetail['last_avatar_date'] = date('Y-m-d');
		}

		// 如果用户的外貌打分次数多于十次，则改为10次，且保持平均分不变
		if ($user['look_grade_times'] > 10) {
			$userdetail['look_grade_times'] = 10;
			$userdetail['look_grade_total'] = round( ($user['look_grade_total'] / $user['look_grade_times']) * 10 );

			// 删除该用户的被打分记录
			$this->load->model('look_grade_model');
			$this->look_grade_model->delete(array('to_uid'=>$uid));
		}

		if (!$this->user_model->update_userdetail($userdetail, $uid)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "数据库错误";
			$output['ecode'] = "0000003";
			return $output;
		}

		//添加统计数据
		$this->load->model('user_total_model');
		$user_total = array(
			'uid' => $uid,
			'type' => 30,
			'content' => $upload_result['file_name'],
			'create_time' => time(),
			'update_time' => time(),
		);
		$this->user_total_model->insert($user_total);

		//如果是第一次上传头像，且通过Face++审核，则走完成邀请逻辑
		if ($first_upload && $avatar_state==11) {
			// 完成邀请成功，则需要重新获取一下被邀请人的详情（因为会员时间会改变）
			$this->load->model('share_invite_model');
			if ($this->share_invite_model->finish_invite($uid, $user['phone']) ) {
				$userdetail = $this->user_model->get_row_by_id($uid, 'p_userdetail');
			}
		}

		// 添加BI
		$bi = array('uid'=>$uid, 'type'=>5, 'value'=>($user['avatar']=='' ? 1 : 2));
		$this->load->model('bi_model');
		$this->bi_model->insert_bi($bi);

		//返回结果
		$user = array_merge($user, $userdetail);
		$output['user'] = $this->user_model->send_userdetail($user, $uid);

		$output['state'] = TRUE;

		// 如果是第一次上传头像，则需要给该用户推荐两个用户
		if ($first_upload) {
			$this->load->service('match_service');
			$this->match_service->start_match_single($user);
		}

		return $output;
	}


	/**
	 *
	 * 接口名称：auth_commit
	 * 接口功能：用户提交认证
	 * 接口编号：0205
	 * 接口加密名称：zLqQh90gwXeYv9PM
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$type			认证类型，具体见对应表
	 * @param int		$id				相关id，其含义和认证类型有关，具体见对应表
	 * @param string	$content		认证内容，其含义和认证类型有关，具体见对应表
	 * 注：本接口为多附件上传，上传的文件的key请设置为upfile[]
	 *
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 *
	 */
	public function auth_commit($uid, $token, $type, $id, $content)
	{
		$api_code = '0205';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}
//		$user = $result['user'];

		// 判断类型是否合法
		$type = (int)$type;
		if ($type<0 || $type>4) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "类型不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 根据类型，判断必填字段
		$id = (int)$id;
		$content = trim($content);
		switch ($type) {
			// 身份认证content必填
			case AUTH_TYPE_IDENTITY:
				if ($content == '') {
					// 输出错误
					$output['state'] = FALSE;
					$output['error'] = "内容不能为空";
					$output['ecode'] = $api_code."003";
					return $output;
				}
				break;
			// 学历认证id和content都必填
			case AUTH_TYPE_EDUCATION:
				if ($id<0 || $id>6) {
					// 输出错误
					$output['state'] = FALSE;
					$output['error'] = "id不合法";
					$output['ecode'] = $api_code."002";
					return $output;
				}
				if ($content == '') {
					// 输出错误
					$output['state'] = FALSE;
					$output['error'] = "内容不能为空";
					$output['ecode'] = $api_code."003";
					return $output;
				}
				break;
			// 房产认证content必填
			case AUTH_TYPE_HOUSE:
				if ($content == '') {
					// 输出错误
					$output['state'] = FALSE;
					$output['error'] = "内容不能为空";
					$output['ecode'] = $api_code."003";
					return $output;
				}
				break;
			// 车产认证id必填
			case AUTH_TYPE_CAR:
				if (!is_id($id)) {
					// 输出错误
					$output['state'] = FALSE;
					$output['error'] = "id不合法";
					$output['ecode'] = $api_code."002";
					return $output;
				}
				break;
			default:
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "类型不合法";
				$output['ecode'] = $api_code."001";
				return $output;
				break;
		}

		// 图片上传
		$this->load->library('upload');
		$this->load->helper('string');

		// 多图上传
		$this->upload->multifile_array('upfile');		// 这行代码必须，是用来做多图格式兼容的
		$index = 0;
		$image_array = array();

		while (true) {
			$current_key = 'upfile__'.$index;
			if (!isset($_FILES[$current_key])) {
				break;
			}

			$config = array();
			$config['upload_path'] = './uploads/auth';
			$config['file_name'] = time().strtolower(random_string('alnum',10));
			$config['allowed_types'] = 'jpg|jpeg|png';
			$config['overwrite'] = FALSE;

			$this->upload->initialize($config);

			// 如果上传失败
			if ( !$this->upload->do_upload($current_key)) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "图片上传失败：".$this->upload->display_errors('','');
				$output['ecode'] = $api_code."004";
				return $output;
			}
			else {
				$upload_result = $this->upload->data();
				$image_array[] = $upload_result['file_name'];
				$file_array[] = array('path'=>$config['upload_path'].'/'.$upload_result['file_name'],'key'=>'auth/'.$upload_result['file_name']);
			}

			$index++;

		}

		// 必须要有图片
		if (empty($image_array)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "请上传图片";
			$output['ecode'] = $api_code."005";
			return $output;
		}


		// 上传七牛
		if (!empty($file_array)) {
			$this->upload->upload_to_qiniu($file_array);
		}

		// 插入数据库
		$data['uid'] = $uid;
		$data['type'] = $type;
		$data['id'] = $id;
		$data['content'] = $content;
		$data['image'] = implode(",", $image_array);
		$data['done_flag'] = 0;
		$this->load->model('auth_model');
		if (!$this->auth_model->insert_auth($data, FALSE)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "数据库错误";
			$output['ecode'] = "0000003";
			return $output;
		}

		//返回结果
		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：get_auth_list
	 * 接口功能：用户获取认证列表
	 * 接口编号：0206
	 * 接口加密名称：uTCK4kzYapki2fgf
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 *
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['auth_identity']: 调用成功时返回，表示身份认证列表
	 * $output['auth_education']: 调用成功时返回，表示学历认证列表
	 * $output['auth_house']: 调用成功时返回，表示房产认证列表
	 * $output['auth_car']: 调用成功时返回，表示车产认证列表
	 * 以上四个返回值均为数组，每一条记录为一个认证，包含auth_id、id、content、done_flag（0为未处理，1为已通过）等字段
	 *
	 */
	public function get_auth_list($uid, $token)
	{
//		$api_code = '0206';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}
//		$user = $result['user'];

		$output['auth_identity'] = array();
		$output['auth_education'] = array();
		$output['auth_house'] = array();
		$output['auth_car'] = array();

		// 获取该用户的所有的已通过和未处理的认证
		$this->load->model('auth_model');
		$result = $this->auth_model->select(array('uid'=>$uid, 'done_flag'=>array(0,1)), FALSE, 'auth_id,type,id,content,done_flag', '', 'create_time DESC');
		if (!empty($result)) {
			foreach ($result as $row) {
				switch ($row['type']) {
					// 身份认证取最近提交的一个
					case AUTH_TYPE_IDENTITY:
						if (empty($output['auth_identity'])) {
							$output['auth_identity'][] = $row;
						}
						break;
					// 学历认证取最近提交的一个
					case AUTH_TYPE_EDUCATION:
						if (empty($output['auth_education'])) {
							$output['auth_education'][] = $row;
						}
						break;
					// 房产认证全部返回
					case AUTH_TYPE_HOUSE:
						$output['auth_house'][] = $row;
						break;
					// 车产认证全部返回
					case AUTH_TYPE_CAR:
						$output['auth_car'][] = $row;
						break;
					default:
						break;
				}
			}
		}

		//返回结果
		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：auth_delete
	 * 接口功能：用户删除认证
	 * 接口编号：0207
	 * 接口加密名称：WmJERar841MagygR
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$auth_id		认证的id
	 *
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 *
	 */
	public function auth_delete($uid, $token, $auth_id)
	{
		$api_code = '0207';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}
//		$user = $result['user'];

		// 首先获取该认证
		$this->load->model('auth_model');
		$auth = $this->auth_model->get_row_by_id($auth_id);

		// 如果找不到
		if (empty($auth)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "找不到该认证";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 判断是否有权限
		if ($auth['uid'] != $uid) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "您没有权限";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 判断类型是否合法，目前仅支持删除房产和车产
		$auth['type'] = (int)$auth['type'];
		if ($auth['type'] != AUTH_TYPE_HOUSE && $auth['type'] != AUTH_TYPE_CAR) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "类型不合法";
			$output['ecode'] = $api_code."003";
			return $output;
		}

		// 判断是否已通过
		$auth['done_flag'] = (int)$auth['done_flag'];
		if ($auth['done_flag'] != 1) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "认证状态不合法";
			$output['ecode'] = $api_code."004";
			return $output;
		}

		// 删除该记录
		if (!$this->auth_model->delete_by_id($auth_id)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "数据库错误";
			$output['ecode'] = "0000003";
			return $output;
		}

		//返回结果
		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：upload_album
	 * 接口功能：用户上传个人相册
	 * 接口编号：0208
	 * 接口加密名称：kQP1sfdCZYlWI4sr
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param string	$content		图片描述
	 * 注：上传的文件的key请设置为upfile，一次最多九张
	 *
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['album']: 成功时返回本次提交的相册的相关信息
	 *
	 */
	public function upload_album($uid, $token, $content)
	{
		$api_code = '0208';
		$upload_num = 9;	//一次能上传的文件数量

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}

		//一次最多九张
		if (count($_FILES['upfile']['name']) > $upload_num) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "一次最多能上传" . $upload_num . '张图片';
			$output['ecode'] = $api_code."003";
			return $output;
		}

		// 图片上传
		$this->load->library('upload');
		$this->load->service('common_service');
		$this->load->helper('string');
		$this->load->model('user_album_model');

		//转化上传文件的结构， 方便调用上传方法
		$this->upload->multifile_array('upfile');

		//设置接口编号
		$this->common_service->_api_code = $api_code;

		//插入数据初始化
		$insert_data = array();
		$insert_data['image'] = '';
		$insert_data['uid'] = $uid;
		$insert_data['content'] = $content;

		//批量上传
		foreach ($_FILES as $key=>$val) {
			//只对转换过的做上传
			if ($key != 'upfile') {
				$upload_result = $this->common_service->upload_file_to_qiniu($key, 'jpg|jpeg|png', 'album');
				if (isset($upload_result['state']) && $upload_result['state'] == FALSE) {
					return $upload_result;	//上传异常处理
				} else {
					//保存数据
					$insert_data['image'] .= $upload_result['file_name'] . ',';
				}
			}
		}

		//去掉尾部的,号
		$insert_data['image'] = substr($insert_data['image'], 0, -1);

		//插入数据
		$album_id = $this->user_album_model->insert_album($insert_data, TRUE);
		if (!$album_id){
			$output['state'] = FALSE;
			$output['error'] = "数据库错误";
			$output['ecode'] = "0000003";
			return $output;
		}

		//添加统计数据
		$this->load->service('total_service');
		$this->total_service->set_user_album($uid, $insert_data['image']);

		// 添加BI
		$bi = array('uid'=>$uid, 'type'=>9, 'value'=> 1);
		$this->load->model('bi_model');
		$this->bi_model->insert_bi($bi);

		//返回结果
		$output['state'] = TRUE;
		$output['album'] = $this->user_album_model->send($insert_data);

		return $output;
	}


	/**
	 *
	 * 接口名称：get_album_list
	 * 接口功能：获取个人相册
	 * 接口编号：0209
	 * 接口加密名称：kwP10hdCZYlWI4sr
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$create_time	上次取数据最后一个图片的创建时间
	 * @param int		$to_uid			要查看的用户的uid
	 * @param string	$key			本次秘钥，长度必须为16位，由大小写字母和随机数生成
	 * @param string	$key_pw			密码，由to_uid和key生成，全部小写
	 *
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['album']: 相册列表, $output['album']['image']里面的图片以逗号分隔
	 *
	 */
	public function get_album_list($uid, $token, $create_time, $to_uid, $key, $key_pw)
	{
		$api_code = '0209';

		$this->load->model('user_album_model');

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}

		//查看他人的时候需要验证
		if ($uid != $to_uid){
			// 检查to_uid是否合法
			if (!is_id($to_uid)) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "用户id不合法";
				$output['ecode'] = $api_code."001";
				return $output;
			}

			// 检查秘钥和密码是否合法
			$result = $this->check_key_and_pw($key, $key_pw, array($to_uid, $key), array('DTiCZYPELOevMO44','JsRCZY5XLAJcDp5p','RXkCZYmtPMi7Rv5k'));
			if ($result['state'] == FALSE) {
				return $result;
			}

			//认证通过，使用要查看的用户的uid
			$uid = $to_uid;
		}

		//获取列表
		$list = $this->user_album_model->get_list($uid, $create_time, MAX_ALBUM_PER_GET);

		//返回结果
		$output['state'] = TRUE;
		$output['album'] = $list;

		return $output;
	}


	/**
	 *
	 * 接口名称：del_album
	 * 接口功能：删除个人相册图片
	 * 接口编号：0210
	 * 接口加密名称：kwP10hdCZYlFv4sq
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param string	$album_id		个人相册id
	 * @param string	$image			要删除的图片
	 *
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 *
	 */
	public function del_album($uid, $token, $album_id, $image)
	{
		$api_code = '0210';

		$this->load->model('user_album_model');

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}

		//删除图片
		if (!$this->user_album_model->delete_one($uid, $album_id, $image)){
			$output['state'] = FALSE;
			$output['error'] = "删除失败";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 添加BI
		$bi = array('uid'=>$uid, 'type'=>9, 'value'=> 2);
		$this->load->model('bi_model');
		$this->bi_model->insert_bi($bi);

		//返回结果
		$output['state'] = TRUE;

		return $output;
	}


	/**
	 * 接口名称：get_recommend_list
	 * 接口功能：获取推荐列表
	 * 接口编号：0211
	 * 接口加密名称：xUNGbiLJdAD0w1W0
	 *
	 * @param array		$data			请求参数
	 * param	int		$uid			用户id
	 * param	string	$token			用户token
	 * param	int		$last_time	默认0，type为1时传上次取数据最后一个的last_time，为2时传上次取数据第一个的last_time，格式为14位数字，纳秒级，取前十位就是秒了
	 * param	int		$type		默认1，1为历史数据，2为新增数据
	 *
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['recommend']: 推荐列表
	 *
	 */
	public function get_recommend_list($data)
	{
//		$api_code = '0211';

		$last_time = isset($data['last_time']) ? $data['last_time'] : 0;
		$type = isset($data['type']) ? $data['type'] : 1;

		if(!isset($data['uid']) || !isset($data['token'])){
			$output['state'] = FALSE;
			$output['error'] = "缺少必要参数";
			$output['ecode'] = "0000012";
			return $output;
		}

		// 用户鉴权
		$result = $this->user_model->check_authority($data['uid'], $data['token'], TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		//获取用户列表
//		$output['recommend_list'] = $this->user_model->get_recommend_list($result['user']['gender'], $result['user']['province'], $result['user']['city'], $last_time, $type);
		// 这几天把城市限制去掉，就是全国的推荐显示在一起，因为这几天投上海，用户太少，上来没事做
		$output['recommend_list'] = $this->user_model->get_recommend_list($result['user']['gender'], 0, 0, $last_time, $type);

		//返回结果
		$output['state'] = TRUE;

		return $output;
	}


	/**
	 *
	 * 接口名称：apply_recommend
	 * 接口功能：申请推荐/取消推荐
	 * 接口编号：0212
	 * 接口加密名称：YOccvOIn8k6RAnRL
	 *
	 * @param array		$data			请求参数
	 * param	 int	$uid			用户id
	 * param	 string	$token			用户token
	 * param	 string	$type			默认0,0为申请推荐，1为取消申请
	 * param	 string	$introduction	个人介绍,type为0时必须
	 * param	 string	$expect	对TA的期望,type为0时必须
	 *
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 *
	 */
	public function apply_recommend($data)
	{
		$api_code = '0212';

		$this->load->model('user_model');
		$this->load->model('user_album_model');

		$type = isset($data['type']) ? $data['type'] : 0;
		$introduction = isset($data['introduction']) ? $data['introduction'] : '';
		$expect = isset($data['expect']) ? $data['expect'] : '';

		if(!isset($data['uid']) || !isset($data['token'])){
			$output['state'] = FALSE;
			$output['error'] = "缺少必要参数";
			$output['ecode'] = "0000012";
			return $output;
		}

		if($type==0 && (!isset($introduction) || $introduction == '')){
			$output['state'] = FALSE;
			$output['error'] = "请填写个人介绍";
			$output['ecode'] = $api_code . "001";
			return $output;
		}
		if($type==0 && (mb_strlen($introduction) < 20 || mb_strlen($introduction) > 200)){
			$output['state'] = FALSE;
			$output['error'] = "个人介绍须为20-200个字符";
			$output['ecode'] = $api_code . "002";
			return $output;
		}
		if($type==0 && (!isset($expect) || $expect == '')){
			$output['state'] = FALSE;
			$output['error'] = "请填写对TA的期望";
			$output['ecode'] = $api_code . "001";
			return $output;
		}
		if($type==0 && (mb_strlen($expect) < 20 || mb_strlen($expect) > 200)){
			$output['state'] = FALSE;
			$output['error'] = "对TA的期望须为20-200个字符";
			$output['ecode'] = $api_code . "002";
			return $output;
		}

		// 用户鉴权
		$result = $this->user_model->check_authority($data['uid'], $data['token'], TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		//取消和申请各自验证的
		if($type == 1){
			if($result['user']['recommend'] != 1){
				$output['state'] = FALSE;
				$output['error'] = "您尚未被推荐，无需取消申请";
				$output['ecode'] = $api_code . "005";
				return $output;
			}
		} else {
			if ($result['user']['recommend'] == 1){
				$output['state'] = FALSE;
				$output['error'] = "您已被推荐，无需再申请";
				$output['ecode'] = $api_code . "006";
				return $output;
			}
			if ($result['user']['recommend'] == 2){
				$output['state'] = FALSE;
				$output['error'] = "您的申请正在审核中，无需再申请";
				$output['ecode'] = $api_code . "007";
				return $output;
			}
			if (!in_array($result['user']['avatar_state'], array(2,11,12))){
				$output['state'] = FALSE;
				$output['error'] = "您的头像还在审核，请等待审核后申请";
				$output['ecode'] = $api_code . "003";
				return $output;
			}
			$this->load->service('user_service');
			$this->user_service->_user = $result['user'];
			if ($this->user_service->get_info_complete() != 100){
				$output['state'] = FALSE;
				$output['error'] = "您的资料未填完整，请检查后申请";
				$output['ecode'] = $api_code . "008";
				return $output;
			}

			if (!$this->user_service->get_album(3)){
				$output['state'] = FALSE;
				$output['error'] = "您的相册照片不足3张，请先上传照片";
				$output['ecode'] = $api_code . "004";
				return $output;
			}

		}

		//添加申请记录
		$this->user_model->apply_recommend($data['uid'], $data['type'], $introduction, $expect);

		//添加统计数据
		$this->load->model('user_total_model');
		$user_total_info = $this->user_total_model->get_one(array('uid' => $data['uid'], 'type' => 33));
		//只记录一次申请推荐
		if (empty($user_total_info)){
			$user_total = array(
				'uid' => $data['uid'],
				'type' => 33,
				'content' => '',
				'create_time' => time(),
				'update_time' => time(),
			);
			$this->user_total_model->insert($user_total);
		}

		//返回结果
		$output['state'] = TRUE;

		return $output;
	}


	/**
	 *
	 * 接口名称：mating_analysis
	 * 接口功能：择偶分析
	 * 接口编号：0213
	 * 接口加密名称：eJ0SnHsDUwdXLBYg
	 *
	 * @param array		$data			请求参数
	 * param	 int	$uid			用户id
	 * param	 string	$token			用户token
	 *
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['my_score']:自身的分数。包括总分score,salary_score,education_score,age_score,height_score
	 * $output['other_score']:要求的分数，包括总分score,salary_score,education_score,age_score,height_score
	 * $output['analysis']:分析建议
	 *
	 */
	public function mating_analysis($data) {
//		$api_code = '0213';

		$this->load->model('user_model');
		$this->load->service('common_service');


		// 用户鉴权
		$result = $this->user_model->check_authority($data['uid'], $data['token']);
		if ($result['state'] == FALSE) {
			return $result;
		}

		//获取用户信息
		$user = $this->user_model->get_row_by_id($data['uid'], 'p_userdetail');

		//然后获取用户自身的分数
		$my_score = $this->common_service->get_my_score($user);
		$other_score = $this->common_service->get_other_score($user);

		if ($my_score['score'] >= $other_score['target_score']) {
			if (($my_score['score'] - $other_score['target_score'])>1) {
				$analysis = "您的择偶要求有些偏低，略微提高交友条件，可匹配到更优质的异性";
			} else {
				$analysis = "您的交友条件比较合理,继续提升自己的综合实力,将更容易捕获爱情";
			}
		} else {
			if (($other_score['target_score'] - $my_score['score'])>1) {
				$analysis = "您的择偶要求有些偏高，与自身条件更接近的要求会利于您的匹配";
			} else {
				$analysis = "您的交友条件比较合理,继续提升自己的综合实力,将更容易捕获爱情";
			}
		}
		$output['analysis'] = $analysis;
		$output['my_score'] = $my_score;
		$output['other_score'] = $other_score;

		//返回结果
		$output['state'] = TRUE;

		return $output;
	}

	/**
	 * 接口名称：get_cloud_base
	 * 接口功能：获取恋爱云数据 - 基础数据
	 * 接口编号：0214
	 * 接口加密名称：aODJblgAfxBitxWM
	 *
	 * @param 	array	$data			请求参数
	 * param	int		$uid			用户id
	 * param	string	$token			用户token
	 *
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['cloud_base']: 基础数据，对应的key说明：match_num为参与匹配次数、match_suc_num匹配成功次数、
	 * 		response_num收到邀请次数、response_suc_num接受邀请次数、invite_num发起邀请次数、invite_suc_num邀请成功次数
	 *
	 */
	public function get_cloud_base($data)
	{
//		$api_code = '0214';

		$this->load->model('user_total_model');

		if(!isset($data['uid']) || !isset($data['token'])){
			$output['state'] = FALSE;
			$output['error'] = "缺少必要参数";
			$output['ecode'] = "0000012";
			return $output;
		}

		// 用户鉴权
		$result = $this->user_model->check_authority($data['uid'], $data['token'], TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		//初始化基本数据 避免为空
		$output['cloud_base'] = array(
			'match_num' => 0,
			'match_suc_num' => 0,
			'response_num' => 0,
			'response_suc_num' => 0,
			'invite_num' => 0,
			'invite_suc_num' => 0,
		);
		$cloud_base = $this->user_total_model->get_cloud_base($data['uid']);
		foreach ($cloud_base as $key=>$val) {
			switch ($val['type']) {
				case 1:
					$output['cloud_base']['match_num'] = $val['content'];
					break;
				case 22:
					$output['cloud_base']['invite_num'] = $val['content'];
					break;
				case 23:
					$output['cloud_base']['response_num'] = $val['content'];
					break;
				case 24:
					$output['cloud_base']['invite_suc_num'] = $val['content'];
					break;
				case 25:
					$output['cloud_base']['response_suc_num'] = $val['content'];
					break;
				case 66:
					$output['cloud_base']['match_suc_num'] = $val['content'];
					break;
				default:
					break;
			}
		}

		//返回结果
		$output['state'] = TRUE;

		return $output;
	}


	/**
	 * 接口名称：get_cloud_footprint
	 * 接口功能：获取恋爱云数据 - 恋爱足迹
	 * 接口编号：0215
	 * 接口加密名称：pkq8vpIl4V2Z5IGA
	 *
	 * @param 	array	$data			请求参数
	 * param	int		$uid			用户id
	 * param	string	$token			用户token
	 * param	int		$create_time	默认0，type为1时传上次取数据最后一个的create_time，为2时传上次取数据第一个的create_time，格式为10位时间戳
	 * param	int		$type			默认1，1为历史数据，2为新增数据
	 *
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['cloud_footprint']: 恋爱足迹,对应的key说明：id数据标识,content描述直接输出（例如：我在简爱开启了第一次匹配），create_time为时间戳，image为图片多个以,分隔,image_type：activity为活动图片，album为相册图片，avatar为头像
	 *
	 */
	public function get_cloud_footprint($data)
	{
//		$api_code = '0215';

		$this->load->model('user_total_model');
		$create_time = isset($data['create_time']) ? $data['create_time'] : 0;
		$type = isset($data['type']) ? $data['type'] : 1;

		if(!isset($data['uid']) || !isset($data['token'])){
			$output['state'] = FALSE;
			$output['error'] = "缺少必要参数";
			$output['ecode'] = "0000012";
			return $output;
		}

		// 用户鉴权
		$result = $this->user_model->check_authority($data['uid'], $data['token'], TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		$list = $this->user_total_model->get_list($data['uid'], $create_time, $type);
		foreach ($list as $key=>$val){
			$cloud_footprint = array(
				'create_time' => $val['create_time'],
				'id' => $val['id']
			);
			switch ($val['type']) {
				case 2:
					$cloud_footprint['content'] = '我在简爱开启了第一次匹配';
					break;
				case 4:
					$cloud_footprint['content'] = '天呐，我已在简爱匹配了1000个异性';
					break;
				case 5:
					$cloud_footprint['content'] = '匹配人数达到100，距离ta的位置应该不远了';
					break;
				case 6:
					$cloud_footprint['content'] = '我已匹配了10个异性，在此留下足迹，我还会再接再厉的';
					break;
				case 7:
					$cloud_footprint['content'] = '转眼过去了一周，我已匹配了' . $val['content'] . '个异性，我相信真爱快要出现了';
					break;
				case 8:
					$match_num = 0;
					$invite_num = 0;
					$response_num = 0;
					$month_total_list = explode(',', trim($val['content'], ','));
					foreach ($month_total_list as $v){
						$flag_arr = explode(':', $v);
						$flag_str = $flag_arr[0];
						if (isset($flag_arr[1])) {
							$$flag_str = $flag_arr[1];
						}
					}
					$cloud_footprint['content'] = '1个月了，我已匹配了' . $match_num . '个异性，发出了' . $invite_num . '次邀请，收到了' . $response_num . '次邀请，爱情之门已悄悄为我打开';
					break;
				case 9:
					$cloud_footprint['content'] = '今天是我加入简爱的日子，我将在这里踏上我的爱情之旅';
					break;
				case 10:
					$cloud_footprint['content'] = '今天是我加入简爱的第100天，祝我自己早日脱单';
					break;
				case 11:
					$cloud_footprint['content'] = '今天是我加入简爱的第365天，原来我还关注着简爱';
					break;
				case 12:
					$user_info = $this->user_model->get_row_by_id($val['content'], 'p_userdetail');

					$cloud_footprint['content'] = '我接受了“' . $user_info['username'] . '”的邀请，这会是我命中的“TA”吗';
					break;
				case 13:
					$user_info = $this->user_model->get_row_by_id($val['content'], 'p_userdetail');

					$cloud_footprint['content'] = '真开心，“' . $user_info['username'] . '”接受了我邀请';
					break;
				case 14:
					break;
				case 15:
					break;
				case 16:
					$cloud_footprint['content'] = '今天我发出了第10个邀请，“TA”一定会接受吧';
					break;
				case 17:
					$cloud_footprint['content'] = '我已经发出了20个邀请，爱情应该更近一步了';
					break;
				case 18:
					$cloud_footprint['content'] = '发起第100个邀请，原来我也可以为爱痴狂';
					break;
				case 19:
					$cloud_footprint['content'] = '收到第10个邀请，原来我这么受欢迎~';
					break;
				case 20:
					$cloud_footprint['content'] = '我已经收到了20个邀请，真是人见人爱花见花开~';
					break;
				case 21:
					$cloud_footprint['content'] = '收到第100个邀请，请叫我万人迷~';
					break;
				case 26:
					$cloud_footprint['content'] = '我通过了身份认证，从此做一个“合法公民”';
					break;
				case 27:
					$cloud_footprint['content'] = '我通过了学历认证，从此做一个有文化的人';
					break;
				case 28:
					$cloud_footprint['content'] = '我通过了房产认证，幸福之门为你开启';
					break;
				case 29:
					$cloud_footprint['content'] = '我通过了车产认证，谁会和我一起去兜风呢';
					break;
				case 30:
					$this->load->helper('url');
					$cloud_footprint['content'] = '上传了一张头像';
					$cloud_footprint['image'] = get_attachment_url($val['content'], 'avatar');
					$cloud_footprint['image_type'] = 'avatar';
					break;
				case 31:
					$this->load->helper('url');
					// 处理图片链接
					$image = '';
					if ($val['content'] != '') {
						$temp_array = explode(',', $val['content']);
						foreach ($temp_array as &$row) {
							$row = get_attachment_url($row, 'album');
						}
						$image = implode(',', $temp_array);
					}
					$cloud_footprint['content'] = '上传了相册';
					$cloud_footprint['image'] = $image;
					$cloud_footprint['image_type'] = 'album';
					break;
				case 32:
					$cloud_footprint['content'] = '资料完整度达到100%，开始认真找对象';
					break;
				case 33:
					$cloud_footprint['content'] = '申请上推荐，让更多人发现自己';
					break;
				case 34:
					$this->load->helper('url');
					$this->load->model('activity_model');
					$activity_info = $this->activity_model->get_row_by_id($val['content']);

					$cloud_footprint['content'] = '参加“' . $activity_info['name'] . '”活动';
					$cloud_footprint['image_type'] = 'activity';
					$cloud_footprint['image'] = get_attachment_url($activity_info['image'], 'activity');
					break;
				case 35:
					$cloud_footprint['content'] = '我在简爱发起了第一个邀请，会成功吗';
					break;
				case 36:
					$cloud_footprint['content'] = '哇，收到了人生中第一条邀请，好激动';
					break;
				default:
					break;
			}
			$output['cloud_footprint'][] = $cloud_footprint;
		}

		//返回结果
		$output['state'] = TRUE;

		return $output;
	}

}