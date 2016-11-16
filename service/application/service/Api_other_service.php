<?php
/**
 * 其他功能接口服务，如偷窥功能等
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/7
 * Time: 21:19
 */

class Api_other_service extends Api_Service {

	/**
	 *
	 * 接口名称：get_grade_list
	 * 接口功能：用户获取偷窥打分列表
	 * 接口编号：0401
	 * 接口加密名称：tsUQeRlnaiu2kE0D
	 *
	 * @param int		$uid			用户id，如果是未登录状态传0
	 * @param string	$token			用户token，如果是未登录状态传空字符串
	 * @param int		$gender			用户性别，如果是未登录状态才需要
	 * @param int		$start_uid		上次获取的用户中最小的uid，如果是未登录状态才需要，第一次请传0
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['user_list']: 当调用成功时返回，为一个数组，每个元素为一个用户，包含uid、头像、是否是会员、key等信息
	 *
	 */
	public function get_grade_list($uid, $token, $gender, $start_uid)
	{
		$api_code = '0401';

		// 查询内容
		$current_date = date('Y-m-d');
		$data = "uid, username, birthday, avatar, education, height, salary, vip_date>'".$current_date."' as vip_flag";

		// 如果是已登录用户
		if (is_id($uid)) {
			// 用户鉴权
			$result = $this->user_model->check_authority($uid, $token, TRUE);
			if ($result['state'] == FALSE) {
				return $result;
			}
			$user = $result['user'];

			// 判断该用户的性别
			if ($user['gender'] == 1) {
				$target_gender = 2;
			}
			else if ($user['gender'] == 2) {
				$target_gender = 1;
			}
			else {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "未填写性别";
				$output['ecode'] = $api_code."001";
				return $output;
			}

			// 判断该用户的省份城市是否合法
			if (!is_id($user['province']) || !is_id($user['city'])) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "未填写居住地";
				$output['ecode'] = $api_code."002";
				return $output;
			}
			// 获取该用户打过分的用户
			$this->load->model('look_grade_model');
			$graded_uid_array = array_keys($this->look_grade_model->select(array('from_uid'=>$uid), FALSE, 'to_uid', '', '', '', 'to_uid'));
			$graded_uid_array[] = $uid;

			// 下面开始构建查询条件
			$this->user_model->set_table_name('p_userdetail');
			$this->user_model->db->reset_query();

			// 性别异性，省份城市需要相同，必须信息完善
			$this->user_model->db->where('gender', $target_gender);
			$this->user_model->db->where('province', $user['province']);
			$this->user_model->db->where('city', $user['city']);
			$this->user_model->db->where('complete_flag', 1);

			$this->user_model->db->where_not_in('uid', $graded_uid_array);

			// 查找数据库，获取列表
			$output['user_list'] = $this->user_model->select(array(), FALSE, $data, MAX_GRADE_PER_GET, 'look_grade_times ASC, uid ASC');
		}
		// 如果是未登录用户
		else {
			// 判断该用户的性别
			if ($gender == 1) {
				$target_gender = 2;
			}
			else if ($gender == 2) {
				$target_gender = 1;
			}
			else {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "未填写性别";
				$output['ecode'] = $api_code."001";
				return $output;
			}

			// 下面开始构建查询条件
			$this->user_model->set_table_name('p_userdetail');
			$this->user_model->db->reset_query();

			// 性别异性，必须信息完善
			$this->user_model->db->where('gender', $target_gender);
			$this->user_model->db->where('complete_flag', 1);

			// 要求必须在3分以上
			$this->user_model->db->where('`look_grade_total` / `look_grade_times` > ', 3, FALSE);

			// 如果是现网则过滤加州用户，因为加州有审查员以及一系列账号.
			if (!DEBUG) {
				$this->user_model->db
					->group_start()
						->where('province !=', 101)
						->or_where('city !=', 5)
					->group_end();
			}

			// 使用uid进行分页
			if (is_id($start_uid)) {
				$this->user_model->db->where('uid <', $start_uid);
			}

			// 查找数据库，获取列表
			$output['user_list'] = $this->user_model->select(array(), FALSE, $data, MAX_GRADE_PER_GET, 'uid DESC');
		}

		// 数据处理
		$this->load->helper('url_helper');
		foreach ($output['user_list'] as &$row) {
			$row['avatar'] = get_attachment_url($row['avatar'], 'avatar');
			$row['key'] = $this->user_model->get_grade_key($uid, $row['uid']);
		}
		unset($row);


		$output['state'] = TRUE;
		return $output;
	}



	/**
	 *
	 * 接口名称：grade_user
	 * 接口功能：用户偷窥打分
	 * 接口编号：0402
	 * 接口加密名称：wwvcMPFiJnFtYUXC
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$to_uid			被打分者uid
	 * @param string	$key			打分密钥，每位用户均不同
	 * @param int		$grade			分数（1至5）
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 *
	 */
	public function grade_user($uid, $token, $to_uid, $key, $grade)
	{
		$api_code = '0402';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}
//		$user = $result['user'];

		// 检测to_uid是否合法
		if (!is_id($to_uid)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "被打分者id不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 检测密钥是否合法
		if ($this->user_model->get_grade_key($uid, $to_uid) != $key) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "密钥不合法";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 检测分数是否合法
		$grade = (int)$grade;
		if ($grade < 1 || $grade > 5) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "分数不合法";
			$output['ecode'] = $api_code."003";
			return $output;
		}

		// 首先插入一条打分记录
		$this->load->model('look_grade_model');

		// 如果插入失败，则证明已经打过分了
		if (!$this->look_grade_model->set_look_grade($uid, $to_uid, $grade)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "您已经打过分了";
			$output['ecode'] = $api_code."004";
			return $output;
		}

		// 更新被打分者信息
		$this->user_model->update_userdetail(array('look_grade_total'=>'+='.$grade, 'look_grade_times'=>'+=1'), $to_uid);

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：suggestion_commit
	 * 接口功能：用户提交反馈，支持上传图片
	 * 接口编号：0403
	 * 接口加密名称：qfrbwvdU3MFKDc4L
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$type			反馈类型，1为软件bug，2为意见建议
	 * @param string	$content		反馈内容
	 * 注：上传的文件的key请设置为upfile
	 *
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 *
	 */
	public function suggestion_commit($uid, $token, $type, $content)
	{
		$api_code = '0403';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}

		// 判断类型是否合法
		$type = (int)$type;
		if ($type != 1 && $type != 2) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "反馈类型不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 判断内容是否为空
		$content = trim($content);
		if ($content == '') {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "反馈内容不能为空";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 构建插入内容
		$suggestion = array('uid'=>$uid, 'type'=>$type, 'suggestion'=>$content, 'time'=>time());

		// 图片上传
		if (isset($_FILES['upfile']['name']) && $_FILES['upfile']['name']!='') {
			$this->load->library('upload');
			$this->load->helper('string');

			$config = array();
			$config['upload_path'] = './uploads/suggestion';
			$config['allowed_types'] = 'jpg|jpeg|png';
			$config['file_name'] = time().strtolower(random_string('alnum',10));
			$config['overwrite'] = FALSE;

			$this->upload->initialize($config);

			// 如果上传失败
			if ( !$this->upload->do_upload('upfile')) {
				//输出错误
				$output['state'] = FALSE;
				$output['error'] = "图片上传失败：".$this->upload->display_errors('','');
				$output['ecode'] = $api_code."003";
				return $output;
			}

			$upload_result = $this->upload->data();

			// 上传七牛
			$this->upload->upload_to_qiniu(array(array('path'=>$config['upload_path'].'/'.$upload_result['file_name'],'key'=>'suggestion/'.$upload_result['file_name'])));

			// 更新插入内容
			$suggestion['image'] = $upload_result['file_name'];
		}


		// 插入数据库
		$this->load->model('suggestion_model');

		if (!$this->suggestion_model->insert($suggestion)) {
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
	 * 接口名称：get_pay_result
	 * 接口功能：用户查询付款结果，用于第三方支付完成后
	 * 接口编号：0404
	 * 接口加密名称：gQvmpptd19QOKy5M
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param double	$order_no		订单号
	 *
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['success_flag']: 表示支付是否成功，为1表示成功，为0表示失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['user']: 支付成功且订单为购买会员时返回，包含用户的基本信息，如昵称、头像等
	 * $output['discount_pay']: 支付失败时，是否弹出优惠购买1为是， 0为不是
	 * $output['discount']: 支付失败时，优惠购买给予的折扣
	 *
	 */
	public function get_pay_result($uid, $token, $order_no)
	{
		$api_code = '0404';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}

		// 首先获取该订单
		$this->load->model('pingpp_order_model');
//		$order_no = number_format($order_no, 0, '', '');
		$pingpp_order = $this->pingpp_order_model->get_row_by_id($order_no, "uid,pingpp_id,id_2,type,state");

		// 如果找不到
		if (empty($pingpp_order)) {
			//输出错误
			$output['state'] = FALSE;
			$output['error'] = "找不到该订单";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 判断该订单是否属于该用户
		if ($pingpp_order['uid'] != $uid) {
			//输出错误
			$output['state'] = FALSE;
			$output['error'] = "您没有权限";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 如果该订单是没有请求成功的订单，返回错误
		if ($pingpp_order['state'] == ORDER_STATE_WAIT) {
			//输出错误
			$output['state'] = FALSE;
			$output['error'] = "该订单无效";
			$output['ecode'] = $api_code."003";
			return $output;
		}


		// 如果请求成功，但是还没有完成，去Ping++查询是否完成
		if ($pingpp_order['state'] == ORDER_STATE_CALL) {
			$this->load->service('pay_service');
			$result = $this->pay_service->get_pingpp_order_state($order_no, $pingpp_order['pingpp_id']);
			// 如果检测订单状态时发生错误
			if ($result['state'] == FALSE) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = '支付失败';
				$output['ecode'] = $api_code."004";
				return $output;
			}
			$output['success_flag'] = $result['success_flag'] ? 1 : 0;
			//失败时判断是否弹出优惠
			if (in_array($pingpp_order['type'], array(ORDER_TYPE_MENU_FOR_SEND, ORDER_TYPE_MENU_FOR_RECIEVE, ORDER_TYPE_FREE_COUPON)) && $pingpp_order['id_2'] <= 1 && $output['success_flag'] == 0){
				$output['discount_pay'] = 0;
				if ($this->pingpp_order_model->get_is_first($uid)){
					$output['discount_pay'] = 1;
					$output['discount'] = DISCOUNT_PAY;
				}
			}
		}
		// 如果已经完成了，直接返回结果即可
		else if ($pingpp_order['state'] == ORDER_STATE_SUCCESS) {
			$output['success_flag'] = 1;
		}
		// 如果是非法状态，输出错误
		else {
			//输出错误
			$output['state'] = FALSE;
			$output['error'] = "该订单无效";
			$output['ecode'] = $api_code."003";
			return $output;
		}

		// 如果成功，则返回补充信息
		if ($output['success_flag']) {
			// 购买会员需要返回用户的个人信息
			if ($pingpp_order['type'] == ORDER_TYPE_VIP) {
				$user = $this->user_model->get_row_by_id($uid, 'p_userdetail');
				$output['user'] = $this->user_model->send_userdetail($user, $uid);
			}
		}

		//返回结果
		$output['state'] = TRUE;

		return $output;
	}

	/**
	 *
	 * 接口名称：informant_commit
	 * 接口功能：用户提交举报
	 * 接口编号：0405
	 * 接口加密名称：lpp8HhPJOOd5Plag
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$to_uid			被举报者的uid
	 * @param int		$type			举报类型，具体见对应表
	 *
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 *
	 */
	public function informant_commit($uid, $token, $to_uid, $type)
	{
		$api_code = '0405';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}

		// 判断类型是否合法
		$type = (int)$type;
		if ($type < 1 || $type > 6) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "举报类型不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 判断to_uid是否为合法
		if (!is_id($to_uid)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "被举报者id不合法";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 构建插入内容
		$informant = array('from_uid'=>$uid, 'to_uid'=>$to_uid, 'type'=>$type, 'time'=>time());


		// 插入数据库
		$this->load->model('informant_model');

		if (!$this->informant_model->insert($informant)) {
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
	 * 接口名称：blacklist_commit
	 * 接口功能：用户拉黑另一个用户
	 * 接口编号：0406
	 * 接口加密名称：p24QKadOv7IeRnNx
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$to_uid			被拉黑的uid
	 *
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 *
	 */
	public function blacklist_commit($uid, $token, $to_uid)
	{
		$api_code = '0406';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token, TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		$user = $result['user'];

		// 判断to_uid是否为合法
		if (!is_id($to_uid)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "被拉黑者id不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 根据性别构建查询条件
		$where = array();
		if ($user['gender'] == 1) {
			$where['uid_1'] = $uid;
			$where['uid_2'] = $to_uid;
		}
		else if ($user['gender'] == 2) {
			$where['uid_1'] = $to_uid;
			$where['uid_2'] = $uid;
		}
		else {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "用户性别不合法";
			$output['ecode'] = $api_code."002";
			return $output;
		}


		// 删除这两个用户之间的所有匹配
		$this->load->model('match_model');
		$this->match_model->delete($where);

		// 删除这两个用户之间的所有邀请
		$this->load->model('invite_model');
		$this->invite_model->delete($where);


		//返回结果
		$output['state'] = TRUE;

		return $output;
	}

	/**
	 *
	 * 接口名称：get_news_update
	 * 接口功能：用户获取新闻（的更新）
	 * 接口编号：0407
	 * 接口加密名称：MvLlVzDvAIEwEnc4
	 *
	 * @param int		$uid				用户id
	 * @param string	$token				用户token
	 * @param int		$update_time		上次获取时间，首次获取时，可以设为0
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['update_time']: 调用成功时返回，表示本次调用时间，客户端应使用该时间覆盖本地保存的上次调用该接口时间
	 * $output['news_list']: 调用成功时返回，每一个元素均为一条新闻，包含nid, title, images, url等基本信息，如quit为1表示该消息无效
	 *
	 */
	public function get_news_update($uid, $token, $update_time)
	{
//		$api_code = '0407';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}

		$user = $result['user'];

		$post_time = time();
		$update_time = (int)$update_time;

		$only_active = FALSE;			// 这个标志位表示，是否只返回有效的新闻

		// 如果是首次获取，则使用该用户上次获取聊天内容的时间
		if ($update_time == 0) {
			// 如果该用户上次获取聊天内容的时间也为0，证明是刚刚注册，那么不返回任何新闻
			if ($user['last_chat_time'] == 0) {
				$output['state'] = TRUE;
				$output['news_list'] = array();
				$output['update_time'] = $post_time;
				return $output;
			}

			$update_time = $user['last_chat_time'];
			$only_active = TRUE;
		}

		// 获取所有（更新）的新闻
		$output['news_list'] = array();
		$this->load->model('news_model');
		// 如果是只返回有效的新闻，则证明肯定是首次获取，那么将显示时间在更新时间之后的新闻返回给客户端
		if ($only_active) {
			$news_list = $this->news_model->select(array('display_time >='=>$update_time, 'active_flag'=>1), FALSE, FIELD_NEWS_CLIENT);
		}
		// 如果是获取增量更新，则将更新时间之后有更新的所有新闻返回给客户端
		else {
			$news_list = $this->news_model->select(array('update_time >='=>$update_time), FALSE, FIELD_NEWS_CLIENT);
		}

		if (!empty($news_list)) {
			foreach ($news_list as $row) {
				$output['news_list'][] = $this->news_model->send($row);
			}
		}

		$output['state'] = TRUE;
		$output['update_time'] = $post_time;
		return $output;
	}


	/**
	 *
	 * 接口名称：get_discover_list
	 * 接口功能：用户获取发现更新
	 * 接口编号：0408
	 * 接口加密名称：Mwn2uK40eKcttiMD
	 *
	 * @param int		$uid				用户id
	 * @param string	$token				用户token
	 * @param int		$start_time			上次获取时间，首次获取时，可以设为0
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['ad_list']: 调用成功且$start_time=0时返回，每一个元素均为一条广告，包含ad_id, title, image, url, share_url,share_title,share_desc等基本信息
	 * $output['underway_activity']: 调用成功且$start_time=0时返回，每一个元素均为一个正在进行的活动，包含aid, name, image, start_time, end_time, url, share_url,share_title,share_desc,sign_flag等基本信息
	 * $output['finish_activity']: 调用成功时返回，每一个元素均为一个已结束的活动，包含aid, name, image, start_time, end_time, url, share_url,share_title,share_desc,sign_flag等基本信息
	 *
	 */
	public function get_discover_list($uid, $token, $start_time)
	{
//		$api_code = '0408';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}

		//加载需要的model
		$this->load->model('activity_model');
		//当前时间
		$current_time = time();

		//获取该用户所有报名的活动
		$this->load->model('activity_sign_model');
		$sign_list = $this->activity_sign_model->select(array("uid"=>$uid, "pay_state"=>2),FALSE, 'aid', '', '', '', 'aid');

		if ($start_time==0) {
			//获取所有有效的广告
			$this->load->model('ad_model');
			$ad_list = $this->ad_model->get_valid_ad_list();

			//广告返回给客户端的处理
			$output['ad_list'] = array();
			if (!empty($ad_list)) {
				foreach ($ad_list as $row) {
					$output['ad_list'][] = $this->ad_model->send($row, $uid);
				}
			}

			//首次返回所有进行中的活动加几个已结束的活动
			//首先获取正在进行的活动
			$output['underway_activity'] = array();
			$underway_activity = $this->activity_model->select(array("active_flag"=>1, "end_time>="=>$current_time), FALSE, FIELD_ACTIVITY_CLIENT, '', 'sign_end_time ASC');
			//正在进行的活动返回给客户端处理
			if (!empty($underway_activity)) {
				foreach ($underway_activity as &$row) {
					if (isset($sign_list[$row['aid']])) {
						$row['sign_flag'] = TRUE;
					}
					$output['underway_activity'][] = $this->activity_model->send($row, $uid);
				}
			}

			//然后取得已结束的活动，根据start_time 从大到小排序
			$finish_activity = $this->activity_model->select(array("active_flag"=>1, "end_time<"=>$current_time), FALSE, FIELD_ACTIVITY_CLIENT, MAX_ACTIVITY_PER_GET, 'start_time DESC');
		} else {
			//获取活动开始时间小于start_time的已结束的活动，
			$finish_activity = $this->activity_model->select(array("active_flag"=>1, "end_time<"=>$current_time, "start_time <="=>$start_time), FALSE, FIELD_ACTIVITY_CLIENT, MAX_ACTIVITY_PER_GET, 'start_time DESC');
		}

		//已结束的活动返回给客户端的处理
		$output['finish_activity'] = array();
		if (!empty($finish_activity)) {
			foreach ($finish_activity as &$row) {
				if (isset($sign_list[$row['aid']])) {
					$row['sign_flag'] = TRUE;
				}
				$output['finish_activity'][] = $this->activity_model->send($row, $uid);
			}
		}

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：get_activity_sign_list
	 * 接口功能：用户获取我的活动列表
	 * 接口编号：0409
	 * 接口加密名称：MlrW3B0Had67yumc
	 *
	 * @param int		$uid				用户id
	 * @param string	$token				用户token
	 * @param int		$update_time			上次获取时间，首次获取时，可以设为0
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['activity_sign_list']: 调用成功时返回，每一个元素均为一条已报名活动，包含aid,name,start_time,update_time,image,url等基本信息
	 *
	 */
	public function get_activity_sign_list($uid, $token, $update_time)
	{
		//$api_code = '0409';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}

		//获取该用户所有已报名的活动列表,根据报名时间排序
		$this->load->model('activity_sign_model');

		if ($update_time == 0) {
			$activity_sign_list = $this->activity_sign_model->select(array("uid"=>$uid, "pay_state"=>array(ORDER_STATE_SUCCESS,ORDER_STATE_REFUND_SUCCES)), FALSE, 'aid, update_time', MAX_ACTIVITY_SIGN_GET, 'update_time DESC');
		} else {
			$activity_sign_list = $this->activity_sign_model->select(array("uid"=>$uid, "pay_state"=>array(ORDER_STATE_SUCCESS,ORDER_STATE_REFUND_SUCCES) , "update_time <=" => $update_time), FALSE, 'aid, update_time', MAX_ACTIVITY_SIGN_GET, 'update_time DESC');
		}


		foreach ($activity_sign_list as &$row) {
			$output['activity_sign_list'][] = $this->activity_sign_model->send($row, $uid);
		}

		$output['state'] = TRUE;

		return $output;
	}






}