<?php
/**
 * 匹配邀请接口服务，如获取匹配邀请列表、发出/接受邀请、查看他人个人信息等
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/7
 * Time: 21:19
 */

class Api_match_service extends Api_Service {

	/**
	 *
	 * 接口名称：get_other_user_detail
	 * 接口功能：用户获取其他用户个人信息
	 * 接口编号：0501
	 * 接口加密名称：F7s3HrJ9Hu1h1Q5e
	 *
	 * @param int		$uid			用户id，如果是未登录状态传0
	 * @param string	$token			用户token，如果是未登录状态传空字符串
	 * @param int		$to_uid			要查看的用户的uid
	 * @param string	$key			本次秘钥，长度必须为16位，由大小写字母和随机数生成
	 * @param string	$key_pw			密码，由to_uid和key生成，全部小写
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['user']: 调用成功时返回，包含了要查看的用户的基本信息
	 * $output['auth_identity_info']: 调用成功且身份已认证时返回
	 * $output['auth_education_info']: 调用成功且学历已认证时返回
	 * $output['invite_flag']: 调用成功时返回，表示是否允许邀请该用户，1为允许邀请
	 * $output['album']: 调用成功时返回，表示他人相册列表（最新的一页）
	 * $output['invite']: 调用成功且和要查看的用户的之间已经有邀请时返回，则表示邀请的信息
	 * $output['look_grade_state']: 调用成功时返回，为1时表示已经打过分了，为字符时表示未打分，返回的是打分提交时的key
	 * $output['red_packet_flag']: 调用成功时返回，为1时表示需要红包弹框，为0表示没有红包弹框
	 * $output['cancel_invite_flag']: 调用成功且存在邀请并且该用户为男生时返回，为1时表示可以取消邀请，为0表示无法取消邀请
	 * $output['cancel_invite_content']: 调用成功且cancel_invite_flag为0时返回，表示提示用户取消邀请失败的文案
	 *
	 */
	public function get_other_user_detail($uid, $token, $to_uid, $key, $key_pw)
	{
		$api_code = '0501';

		// 用户鉴权，未登录用户也可以调用此接口
		$user = array();
		if (is_id($uid)) {
			$result = $this->user_model->check_authority($uid, $token, TRUE);
			if ($result['state'] == FALSE) {
				return $result;
			}

			$user = $result['user'];
		}

		// 检查to_uid是否合法
		if (!is_id($to_uid)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "用户id不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}


		// 检查秘钥和密码是否合法
		$result = $this->check_key_and_pw($key, $key_pw, array($to_uid, $key), array('DTiStWPELOevMO44','JsR3vy5XLAJcDp5p','RXkm81mtPMi7Rv5k'));
		if ($result['state'] == FALSE) {
			return $result;
		}

		// 获取要查看的用户的信息
		$to_user = $this->user_model->get_row_by_id($to_uid, 'p_userdetail');

		if (empty($to_user)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "找不到该用户";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 返回相册信息
		$this->load->model('user_album_model');
		$output['album'] = $this->user_album_model->get_list($to_user, 0, MAX_ALBUM_PER_GET);

		// 如果是未登录用户，这里就可以返回了
		if (!is_id($uid)) {
			// 返回用户信息
			$output['user'] = $this->user_model->send_userdetail($to_user, $uid, FALSE);
			$output['invite_flag'] = 0;

			$output['state'] = TRUE;
			return $output;
		}

		// 默认逻辑变量的赋值
		$invite_flag = FALSE;
		$invite = NULL;

		// 被推荐的人随意邀请
		if ($to_user['recommend'] == 1){
			$invite_flag = true;
		}

		// 判断两人是否为异性
		if ( $this->user_model->is_opposite_sex($user['gender'], $to_user['gender']) ) {
			if ($user['gender']==1) {
				$uid_1 = $uid;
				$uid_2 = $to_uid;
			}
			else {
				$uid_1 = $to_uid;
				$uid_2 = $uid;
			}

			// 如果是会员，则可以直接邀请
			if ($this->user_model->is_vip($user)) {
				$invite_flag = TRUE;
			}
			// 否则需要去查找是否存在匹配
			else {
				/*$this->load->model('match_model');
				$match = $this->match_model->get_row_by_uid($uid_1, $uid_2, 'iid');

				// 如果存在匹配，则可以邀请
				if (!empty($match)) {
					$invite_flag = TRUE;
				}*/
				//不对存在匹配进行限制
				$invite_flag = TRUE;
			}

			// 无论是否可以邀请，都要去查找是否已经邀请
			// 因为要考虑到这种情形：会员用户A通过偷窥邀请了非会员用户B，非会员用户B调用此接口
			$this->load->model('invite_model');
			$invite = $this->invite_model->get_row_by_uid($uid_1, $uid_2, '*');

			// 如果存在邀请
			if (!empty($invite)) {
				// 将invite_flag改为true
				$invite_flag = TRUE;

				//如果是男性发出邀请，则女性查看时修改状态 或者 女性发出邀请，男性查看
				if (($user['gender']==2 && $invite['state']==1) || ($user['gender']==1 && $invite['state']==3)) {
					//将是否已读状态改为已读
					if ($invite['read_flag']==0) {
						$this->invite_model->update(array("read_flag"=>1), array("iid"=>$invite['iid']));
					}
				}

				// 如果有套餐
				if (is_id($invite['menu_id'])) {
					$this->load->model('menu_model');
					$menu = $this->menu_model->get_row_by_id($invite['menu_id'], 'menu_id, topic');

					// 如果能获取到套餐
					if (!empty($menu)) {
						// 处理体验券的名称和主题
						if ($menu['menu_id'] == FREE_COUPON_MENU_ID) {
							// 男生发出的，一定显示体验券
							if ($invite['state'] < 3) {
								$this->menu_model->format_free_menu($menu, 1);
							}
							// 女生发出的，待接受，一定显示心动券
							else if ($invite['state'] == 3) {
								$this->menu_model->format_free_menu($menu, 2);
							}
							// 女生发出的，已经接受，因为不确定买单者，需要根据优惠券id再去查一次
							else {
								$this->load->model('coupon_model');
								$coupon = $this->coupon_model->get_row_by_id($invite['coupon_id'], 'uid');
								$uid_1 = $user['gender']==1 ? $uid : $to_uid;
								if (isset($coupon['uid']) && $coupon['uid'] == $uid_1) {
									$this->menu_model->format_free_menu($menu, 1);
								}
								else {
									$this->menu_model->format_free_menu($menu, 2);
								}
							}
						}

						$invite['topic'] = $menu['topic'];
					}
				}
			}
		}

		// 判断邀请是否成功
		$invite_success_flag = FALSE;
		if ( isset($invite['state']) && ($invite['state']==2 || $invite['state']==4) ) {
			$invite_success_flag = TRUE;
		}

		// 返回用户信息
		$output['user'] = $this->user_model->send_userdetail($to_user, $uid, $invite_success_flag);

		//认证详情
		$this->load->model('auth_model');
		if ($output['user']['auth_identity'] == 1){
			$where_flag = array(
				'uid' => $to_uid,
				'type' => 1,
				'done_flag' => 1,
			);
			//为空也不返回
			$auth_identity_info = $this->auth_model->get_one($where_flag, FALSE, 'id,content', 'review_time DESC');
			if (!empty($auth_identity_info))
				$output['auth_identity_info'] = $auth_identity_info;
		}
		if ($output['user']['auth_education'] >= 1){
			$where_flag = array(
				'uid' => $to_uid,
				'type' => 2,
				'done_flag' => 1,
			);
			$auth_education_info = $this->auth_model->get_one($where_flag, FALSE, 'id,content', 'review_time DESC');
			if (!empty($auth_education_info))
				$output['auth_education_info'] = $auth_education_info;
		}

		// 返回邀请相关信息
		$output['invite_flag'] = $invite_flag ? 1 : 0;

		if (!empty($invite)) {
			$this->load->helper('array');
			$output['invite'] = array_merge(
				elements_not_null(array('state', 'expire_time', 'iid', 'menu_id', 'heartbeat_type', 'hcid'), $invite, 0),
				elements_not_null(array('topic', 'heartbeat_remark'), $invite, '')
			);

			//将心动卡类型进行转换
			$this->load->service('vip_service');
			$heartbeat_config = $this->vip_service->get_heartbeat_config();
			if ($output['invite']['heartbeat_type'] != 0) {
				$output['invite']['heartbeat_price'] = $heartbeat_config[$output['invite']['heartbeat_type']-1]['price'];
			}

		}

		//获取是否对该用户进行头像打分过
		$this->load->model('look_grade_model');
		$look_grade = $this->look_grade_model->select(array("from_uid"=>$uid, "to_uid"=>$to_uid));
		if (empty($look_grade)) {
			$output['look_grade_state'] = $this->user_model->get_grade_key($uid, $to_uid);
		} else {
			$output['look_grade_state'] = 1;
		}


		//是否有红包的弹框
		if (isset($invite) && $user['gender']==2 && $invite['state']==1 && $invite['menu_id']==FREE_COUPON_MENU_ID) {
			$output['red_packet_flag'] = 1;
		} else {
			$output['red_packet_flag'] = 0;
		}

		//取消邀请(只有男生才能取消邀请)
		if (isset($invite) && $user['gender'] == 1) {
			//判断是否能取消邀请
			if ($this->user_model->is_vip($user)) {
				//判断邀请时间是否满足或者该邀请是否已读
				if (($invite['create_time']-time())<86400 && !$invite['read_flag']) {
					$output['cancel_invite_flag'] = 0;
					$output['cancel_invite_content'] = "发出的邀请已读或超出24小时后才能取消，等等再来吧~";
				} else {
					$output['cancel_invite_flag'] = 1;
				}
			} else {
				//判断邀请时间是否满足
				if (($invite['create_time']-time())<86400) {
					$output['cancel_invite_flag'] = 0;
					$output['cancel_invite_content'] = "发出的邀请超出24小时后才能取消，等等再来吧~";
				}  else {
					$output['cancel_invite_flag'] = 1;
				}
			}
		}

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：get_match_and_invite_list
	 * 接口功能：用户获取匹配和邀请记录
	 * 接口编号：0502
	 * 接口加密名称：xePGhpPGWg1mTllu
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$type			要获取的数据类型，0为全部获取，1为获取发出的邀请，2为获取收到的邀请，3为获取成功的邀请，4为获取匹配记录
	 * @param int		$start_time		仅当$type不为0时才支持此参数，表示开始时间，用于加载更多，请传列表中最后一条记录的create_time，初次获取请传0
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['match_list']: 调用成功时返回，包含了用户的一定条数的匹配记录，每个匹配包含匹配id、创建时间、邀请状态、对方uid、昵称、头像、是否是会员、身高、薪水、生日等信息
	 * $output['invite_list_send']: 调用成功时返回，包含了用户的一定条数的发出的邀请，每个邀请包含邀请id、创建时间、过期时间、对方uid、头像、是否是会员、套餐主题、心动卡类型、心动卡附言等信息
	 * $output['invite_list_receive']: 调用成功时返回，包含了用户的一定条数的收到的邀请，每个邀请包含邀请id、创建时间、过期时间、对方uid、头像、是否是会员、套餐主题、心动卡类型、心动卡附言等信息
	 * $output['invite_list_success']: 调用成功时返回，包含了用户的一定条数的成功的邀请，每个邀请包含邀请id、创建时间、过期时间、对方uid、头像、是否是会员、套餐主题、心动卡类型、心动卡附言等信息
	 * $output['ad_index']: 返回N条主页广告,包含ad_id, title, image, url, share_url,share_title,share_desc,type(7为主页验证广告， 8为主页外链广告)
	 *
	 */
	public function get_match_and_invite_list($uid, $token, $type, $start_time)
	{
		$api_code = '0502';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token, TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		$user = $result['user'];

		// 判断类型是否合法
		$type = (int)$type;
		if ($type<0 || $type>4) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "类型不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 如果是获取全部，强行将开始时间置为0
		if ($type == 0) {
			$start_time = 0;
		}

		// 返回邀请记录
		$this->load->model('invite_model');

		// 发出的邀请
		if ($type==0 || $type==1) {
			$output['invite_list_send'] = array();
			$result = $this->invite_model->get_history_by_uid($uid, $user['gender'], 1, $start_time);
			foreach ($result as $row) {
				$output['invite_list_send'][] = $this->invite_model->send($row);
			}
		}


		// 收到的邀请
		if ($type==0 || $type==2) {
			$output['invite_list_receive'] = array();
			$result = $this->invite_model->get_history_by_uid($uid, $user['gender'], 2, $start_time);
			foreach ($result as $row) {
				$output['invite_list_receive'][] = $this->invite_model->send($row);
			}
		}


		// 成功的邀请
		if ($type==0 || $type==3) {
			$output['invite_list_success'] = array();
			$result = $this->invite_model->get_history_by_uid($uid, $user['gender'], 3, $start_time);
			foreach ($result as $row) {
				$output['invite_list_success'][] = $this->invite_model->send($row);
			}
		}

		// 匹配记录
		if ($type==0 || $type==4) {
			$output['match_list'] = array();
			$this->load->model('match_model');
			$result = $this->match_model->get_history_by_uid($uid, $user['gender'], $start_time);
			foreach ($result as $row) {
				$output['match_list'][] = $this->match_model->send($row);
			}
		}

		//主页广告 首次获取全部时返回
		if ($type==0 && $start_time==0){
			$this->load->model('ad_model');
			$ad_index = $this->ad_model->get_valid_ad_list(3);
			if ($ad_index) {
				$output['ad_index'] = array();
				foreach($ad_index as $row){
					$output['ad_index'][] = $this->ad_model->send($row, $uid);
				}
			}
		}

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：boy_invite_girl_get_menu
	 * 接口功能：男生邀请女生第一步，获取套餐
	 * 接口编号：0503
	 * 接口加密名称：PAuJgEmUlglusqwf
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$to_uid			要查看的用户的uid
	 * @param string	$key			本次秘钥，长度必须为16位，由大小写字母和随机数生成
	 * @param string	$key_pw			密码，由to_uid和key生成，全部小写
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['menu_list']: 调用成功时返回，表示可以购买的套餐列表
	 * $output['coupon_list']: 调用成功时返回，表示已经购买，可以直接使用的消费券列表
	 * $output['allow_free_coupon']: 当用户有可用体验券时，表示是否允许该用户使用体验券，当用户没有可用体验券时，表示是否允许该用户购买体验券，1为允许，0为不允许
	 * $output['love_entrust_price']：恋爱委托的价格，单位为分
	 * $output['heartbeat_config']: 调用成功时返回，表示心动卡的配置信息
	 * $output['heartbeat_list']: 调用成功时返回，表示可以直接使用的心动卡列表
	 *
	 */
	public function boy_invite_girl_get_menu($uid, $token, $to_uid, $key, $key_pw)
	{
		$api_code = '0503';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token, TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		$user = $result['user'];

		// 检查to_uid是否合法
		if (!is_id($to_uid)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "用户id不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 检查秘钥和密码是否合法
		$result = $this->check_key_and_pw($key, $key_pw, array($to_uid, $key), array('vW4VynuZDQgicmfe','YH3uCpnzear3gZte','qC7w5wCBGmFQ8hch'));
		if ($result['state'] == FALSE) {
			return $result;
		}

		// 判断用户性别是否合法
		if ($user['gender'] != 1) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "用户性别不合法";
			$output['ecode'] = $api_code."002";
			return $output;
		}


		// 获取要邀请的用户的信息
		$to_user = $this->user_model->get_row_by_id($to_uid, 'p_userdetail', 'uid,gender,province,city,recommend');

		// 检测是否满足邀请条件
		$this->load->service('match_service');
		$result = $this->match_service->can_invite($user, $to_user);
		if ($result['state'] == FALSE) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = $result['error'];
			$output['ecode'] = $api_code."003";
			return $output;
		}

		// 返回可用套餐
		$this->load->model('menu_model');
		$output['menu_list'] = $this->menu_model->get_available_list($uid, $user['gender'], $user['province'], $user['city'], FIELD_MENU_CLIENT_FOR_LIST);
		if ($output['menu_list'] == NULL) {
			$output['menu_list'] = array();
		}

		// 返回可用消费券
		$this->load->model('coupon_model');
		$output['coupon_list'] = $this->coupon_model->get_available_list($uid, $user['gender'], $user['province'], $user['city'], FIELD_COUPON_CLIENT_FOR_LIST);
		if ($output['coupon_list'] == NULL) {
			$output['coupon_list'] = array();
		}

		// 如果既没有可用套餐，也没有可用消费券
		if (empty($output['menu_list']) && empty($output['coupon_list'])) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "暂无可用套餐";
			$output['ecode'] = $api_code."004";
			return $output;
		}

		// 如果用户是会员，那么肯定允许用户购买/使用体验券
		if ($this->user_model->is_vip($user)) {
			$output['allow_free_coupon'] = 1;
		}
		// 如果不是会员，则需要进行进一步判断
		else {
			// 如果用户有可用体验券，则返回是否允许该用户使用体验券
			if (isset($output['coupon_list'][0]) && $output['coupon_list'][0]['menu_id']==FREE_COUPON_MENU_ID) {
				$output['allow_free_coupon'] = (int) $this->coupon_model->allow_free_coupon($uid, TRUE);
			}
			// 否则返回是否允许该用户购买体验券
			else {
				$output['allow_free_coupon'] = (int) $this->coupon_model->allow_free_coupon($uid, FALSE);
			}
		}

		//获取心动卡配置
		$this->load->service('vip_service');
		$heartbeat_config = $this->vip_service->get_heartbeat_config();
		$output['heartbeat_config'] = $heartbeat_config;

		//获取该用户可用的心动卡列表
		$this->load->model('heartbeat_card_model');
		$output['heartbeat_list'] = $this->heartbeat_card_model->get_available_list($uid, 'hcid, type');

		$output['love_entrust_price'] = LOVE_ENTRUST_PRICE;
		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：boy_invite_girl_choose_menu
	 * 接口功能：男生邀请女生第二步，选择套餐并付款
	 * 接口编号：0504
	 * 接口加密名称：Wa4T59CPIheq2JNA
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$to_uid			要查看的用户的uid
	 * @param int		$menu_id		要选择的套餐的id
	 * @param int		$channel		渠道代码，具体见对应表，如果传0则表示用已有的消费券
	 * @param string	$key			本次秘钥，长度必须为16位，由大小写字母和随机数生成
	 * @param string	$key_pw			密码，由to_uid、menu_id和key生成，全部小写
	 * @param int		$is_discount	是否优惠购买,1是0不是
	 * @param int		$heartbeat_id	心动卡的类型id
	 * @param string	$heartbeat_remark 心动卡附言
	 * @param int 		$heartbeat_channel	如果购买心动卡，则购买心动卡的渠道代码，具体见对应表。如果传0则表示用已有的心动卡，如果套餐跟心动卡都需要购买，则需要保持两个参数一样
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['order_no']: 当使用第三方支付时，成功生成订单后会返回该值，为19位的订单号
	 * $output['json']: 当使用第三方支付时，成功生成订单后会返回该值，为Ping++返回给服务器的信息，供客户端使用
	 *
	 */
	public function boy_invite_girl_choose_menu($uid, $token, $to_uid, $menu_id, $channel, $key, $key_pw, $is_discount = 0, $heartbeat_id = 0, $heartbeat_remark = '', $heartbeat_channel = 0)
	{
		$api_code = '0504';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token, TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		$user = $result['user'];

		// 检查to_uid是否合法
		if (!is_id($to_uid)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "用户id不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 检查menu_uid是否合法
		if (!is_id($menu_id)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "套餐id不合法";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		//如果不买，则将心动卡渠道置为0
		if (!is_id($heartbeat_id)) {
			$heartbeat_id = 0;
			$heartbeat_channel = 0;
			$heartbeat_remark = '';
		}
		else {
			$heartbeat_id = (int)$heartbeat_id;
		}

		// 如果是体验券
		if ($menu_id == FREE_COUPON_MENU_ID) {
			// 如果是购买体验券
			if (is_id($channel)) {
				// 会员可以随便购买，非会员只能购买一次
				$this->load->model('coupon_model');
				if (!$this->user_model->is_vip($user) && !$this->coupon_model->allow_free_coupon($uid, FALSE)) {
					// 输出错误
					$output['state'] = FALSE;
					$output['error'] = "您没有会员权限";
					$output['ecode'] = "0000011";
					return $output;
				}
			}
			// 如果是使用体验券
			else {
				// 会员可以随便使用，非会员只能用一次
				$this->load->model('coupon_model');
				if (!$this->user_model->is_vip($user) && !$this->coupon_model->allow_free_coupon($uid, TRUE)) {
					// 输出错误
					$output['state'] = FALSE;
					$output['error'] = "您没有会员权限";
					$output['ecode'] = "0000011";
					return $output;
				}

				// 将channel置为0
				$channel = 0;
			}
		}


		// 检查秘钥和密码是否合法
		$result = $this->check_key_and_pw($key, $key_pw, array($to_uid, $menu_id, $key), array('Yg07pV49fIverqNk','Pdl2XAPEIpbiZC40','DD9MCG7XSfEtfvwq', 'AeehkBr91vqJcZhx'));
		if ($result['state'] == FALSE) {
			return $result;
		}

		// 判断用户性别是否合法
		if ($user['gender'] != 1) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "用户性别不合法";
			$output['ecode'] = $api_code."003";
			return $output;
		}

		// 如果是请求支付，且不是体验券，需要检测套餐是否可用
		$this->load->model('menu_model');
		if (is_id($channel) && $menu_id != FREE_COUPON_MENU_ID) {
			$result = $this->menu_model->is_available($menu_id, $user['province'], $user['city']);
			// 如果套餐不可用
			if ($result['state'] == FALSE) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = $result['error'];
				$output['ecode'] = $api_code."004";
				return $output;
			}
		}

		// 获取要查看的用户的信息
		$to_user = $this->user_model->get_row_by_id($to_uid, 'p_userdetail', 'uid,gender,province,city,recommend,username');

		// 检测是否满足邀请条件
		$this->load->service('match_service');
		$result = $this->match_service->can_invite($user, $to_user);
		if ($result['state'] == FALSE) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = $result['error'];
			$output['ecode'] = $api_code."005";
			return $output;
		}

		//如果需要购买套餐及心动卡
		if (is_id($channel) && is_id($heartbeat_channel) && $channel!=$heartbeat_channel) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "套餐的渠道应该和心动卡渠道相同";
			$output['ecode'] = $api_code."008";
			return $output;
		}


		// 如果满足邀请条件，则开始进入邀请逻辑
		$this->load->service('pay_service');

		if ($heartbeat_remark=="" && $heartbeat_id != 0) {
			//获取被邀请人的昵称
			$heartbeat_remark = $to_user['username']."你好，我是一个阳光型大男孩，请给我一个认识你的机会，或许你会发现，我还不错呢~";
		}

		//保留券的信息
		$remark_arr['menu'] = $this->menu_model->get_row_by_id($menu_id);
		//判断套餐是否需要购买
		$remark_arr['menu']['channel'] = $channel;
		$remark_arr['heartbeat']['heartbeat_type'] = $heartbeat_id;
		$remark_arr['heartbeat']['heartbeat_remark'] = $heartbeat_remark;
		$remark_arr['heartbeat']['heartbeat_channel'] = $heartbeat_channel;
		$remark = serialize($remark_arr);

		//如果不发起支付
		if (!is_id($channel) && !is_id($heartbeat_channel)) {
			$result = $this->pay_service->finish_pay($uid, ORDER_TYPE_MENU_FOR_SEND, $to_uid, $menu_id, 0, $channel, $remark);
			// 如果使用消费券时发生错误
			if ($result['state'] == FALSE) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = $result['error'];
				$output['ecode'] = $api_code."006";
				return $output;
			}
		}
		//否则发起支付请求
		else {
			$result = $this->pay_service->add_pingpp_order($uid, ORDER_TYPE_MENU_FOR_SEND, $to_uid, $menu_id, 0, $channel, $remark, array('is_discount' => $is_discount));

			// 如果请求支付时发生错误
			if ($result['state'] == FALSE) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = $result['error'];
				$output['ecode'] = $api_code . "007";
				return $output;
			}

			// 如果成功了，构建返回值
			$output['order_no'] = $result['pingpp_order']['order_no'];
			$output['json'] = $result['json'];
		}

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：girl_invite_boy_get_menu
	 * 接口功能：女生邀请男生第一步，获取套餐
	 * 接口编号：0505
	 * 接口加密名称：EaG0DaL8DhHg3m2U
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$to_uid			要查看的用户的uid
	 * @param string	$key			本次秘钥，长度必须为16位，由大小写字母和随机数生成
	 * @param string	$key_pw			密码，由to_uid和key生成，全部小写
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['menu_list']: 调用成功时返回，表示可以购买的套餐列表
	 * $output['coupon_list']: 调用成功时返回，表示已经购买，可以直接使用的消费券列表
	 *
	 */
	public function girl_invite_boy_get_menu($uid, $token, $to_uid, $key, $key_pw)
	{
		$api_code = '0505';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token, TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		$user = $result['user'];

		// 检查to_uid是否合法
		if (!is_id($to_uid)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "用户id不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 检查秘钥和密码是否合法
		$result = $this->check_key_and_pw($key, $key_pw, array($to_uid, $key), array('JXyHfXdpuAdlNZcL','FGfPBiDwW7dXwVxA','iLh7kBF6w2oy4yMh'));
		if ($result['state'] == FALSE) {
			return $result;
		}

		// 判断用户性别是否合法
		if ($user['gender'] != 2) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "用户性别不合法";
			$output['ecode'] = $api_code."002";
			return $output;
		}


		// 获取要邀请的用户的信息
		$to_user = $this->user_model->get_row_by_id($to_uid, 'p_userdetail', 'uid,gender,province,city,recommend');

		// 检测是否满足邀请条件
		$this->load->service('match_service');
		$result = $this->match_service->can_invite($user, $to_user);
		if ($result['state'] == FALSE) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = $result['error'];
			$output['ecode'] = $api_code."003";
			return $output;
		}

		// 返回可用套餐
		$this->load->model('menu_model');
		$output['menu_list'] = $this->menu_model->get_available_list($uid, $user['gender'], $user['province'], $user['city'], FIELD_MENU_CLIENT_FOR_LIST);
		if ($output['menu_list'] == NULL) {
			$output['menu_list'] = array();
		}

		// 返回可用消费券
		$this->load->model('coupon_model');
		$output['coupon_list'] = $this->coupon_model->get_available_list($uid, $user['gender'], $user['province'], $user['city'], FIELD_COUPON_CLIENT_FOR_LIST);
		if ($output['coupon_list'] == NULL) {
			$output['coupon_list'] = array();
		}

		// 如果既没有可用套餐，也没有可用消费券
		if (empty($output['menu_list']) && empty($output['coupon_list'])) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "暂无可用套餐";
			$output['ecode'] = $api_code."004";
			return $output;
		}

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：girl_invite_boy_choose_menu
	 * 接口功能：女生邀请男生第二步，选择套餐
	 * 接口编号：0506
	 * 接口加密名称：Y9Jt7xKOa9WD88J8
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$to_uid			要查看的用户的uid
	 * @param int		$menu_id		要选择的套餐的id，如果不选择套餐请设为0
	 * @param string	$key			本次秘钥，长度必须为16位，由大小写字母和随机数生成
	 * @param string	$key_pw			密码，由to_uid、menu_id和key生成，全部小写
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 *
	 */
	public function girl_invite_boy_choose_menu($uid, $token, $to_uid, $menu_id, $key, $key_pw)
	{
		$api_code = '0506';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token, TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		$user = $result['user'];

		// 检查to_uid是否合法
		if (!is_id($to_uid)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "用户id不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 检查秘钥和密码是否合法
		$result = $this->check_key_and_pw($key, $key_pw, array($to_uid, $menu_id, $key), array('rp44nLi8rJAbNCLY','4dQm4T9ewvcRG3tb','Xn9hB40YcYzG21vT', 'OWDFoghcg9snyuPL'));
		if ($result['state'] == FALSE) {
			return $result;
		}

		// 判断用户性别是否合法
		if ($user['gender'] != 2) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "用户性别不合法";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 获取要查看的用户的信息
		$to_user = $this->user_model->get_row_by_id($to_uid, 'p_userdetail', 'uid,gender,province,city,recommend');

		// 检测是否满足邀请条件
		$this->load->service('match_service');
		$result = $this->match_service->can_invite($user, $to_user);
		if ($result['state'] == FALSE) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = $result['error'];
			$output['ecode'] = $api_code."004";
			return $output;
		}

		// 如果满足邀请条件，则开始进入邀请逻辑
		$coupon_id = 0;

		// 开始事务
		$this->user_model->db->trans_start();

		// 如果是用了体验券
		if ($menu_id == FREE_COUPON_MENU_ID) {
			$this->load->model('coupon_model');

			// 首先锁定消费券
			$coupon_id = $this->coupon_model->lock_by_invite($uid, $menu_id);

			// 如果锁定失败
			if (!is_id($coupon_id)) {
				// 回滚事务
				$this->user_model->db->trans_rollback();

				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "使用消费券时发生错误";
				$output['ecode'] = $api_code."006";
				return $output;
			}
		}
		// 如果不是体验券，但是选择了套餐，检测套餐是否可用
		else if (is_id($menu_id)) {
			$this->load->model('menu_model');
			$result = $this->menu_model->is_available($menu_id, $user['province'], $user['city']);
			// 如果套餐不可用
			if ($result['state'] == FALSE) {
				// 回滚事务
				$this->user_model->db->trans_rollback();

				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = $result['error'];
				$output['ecode'] = $api_code."003";
				return $output;
			}
		}

		// 添加一个邀请
		$invite['uid_1'] = $to_uid;
		$invite['uid_2'] = $uid;
		$invite['menu_id'] = is_id($menu_id) ? $menu_id : 0;
		$invite['coupon_id'] = $coupon_id;
		$invite['state'] = 3;
		$this->load->model('invite_model');
		$iid = $this->invite_model->insert_invite($invite, FALSE, TRUE);

		// 如果添加失败
		if (!is_id($iid)) {
			// 回滚事务
			$this->user_model->db->trans_rollback();

			$output['state'] = FALSE;
			$output['error'] = '添加邀请失败';
			$output['ecode'] = $api_code."005";
			return $output;
		}

		//添加统计数据
		$this->load->service('total_service');
		$this->total_service->set_match_invite($uid, $to_uid, $iid);

		// 结束事务
		$this->user_model->db->trans_complete();

		// 如果事务失败，返回错误
		if ($this->user_model->db->trans_status() === FALSE) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "数据库错误";
			$output['ecode'] = "0000003";
			return $output;
		}

		// 推送消息
		$this->load->service('push_service');
		$message['type'] = PUSH_INVTIE_RECEIVE;
		$message['uid'] = $to_uid;
//		$message['iid'] = $iid;
		$this->push_service->push_message($message);

		$output['state'] = TRUE;
		return $output;
	}

	/**
	 *
	 * 接口名称：boy_response_girl_get_menu
	 * 接口功能：男生接受女生邀请第一步，获取套餐
	 * 接口编号：0507
	 * 接口加密名称：aEgzYVXckpsEqZUg
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$iid			邀请的id
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['menu_list']: 调用成功时返回，表示可以购买的套餐列表
	 * $output['coupon_list']: 调用成功时返回，表示已经购买，可以直接使用的消费券列表
	 * $output['allow_free_coupon']: 当用户有可用体验券时，表示是否允许该用户使用体验券，当用户没有可用体验券时，表示是否允许该用户购买体验券，1为允许，0为不允许
	 * $output['love_entrust_price']：恋爱委托的价格，单位为分
	 *
	 */
	public function boy_response_girl_get_menu($uid, $token, $iid)
	{
		$api_code = '0507';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token, TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		$user = $result['user'];

		// 检查iid是否合法
		if (!is_id($iid)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "邀请id不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 判断用户性别是否合法
		if ($user['gender'] != 1) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "用户性别不合法";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 检测邀请状态是否正确
		$this->load->service('match_service');
		$result = $this->match_service->can_response($uid, 1, $iid);
		if ($result['state'] == FALSE) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = $result['error'];
			$output['ecode'] = $api_code."003";
			return $output;
		}

		// 返回可用套餐
		$this->load->model('menu_model');
		$output['menu_list'] = $this->menu_model->get_available_list($uid, $user['gender'], $user['province'], $user['city'], FIELD_MENU_CLIENT_FOR_LIST);
		if ($output['menu_list'] == NULL) {
			$output['menu_list'] = array();
		}

		// 返回可用消费券
		$this->load->model('coupon_model');
		$output['coupon_list'] = $this->coupon_model->get_available_list($uid, $user['gender'], $user['province'], $user['city'], FIELD_COUPON_CLIENT_FOR_LIST);
		if ($output['coupon_list'] == NULL) {
			$output['coupon_list'] = array();
		}

		// 如果既没有可用套餐，也没有可用消费券
		if (empty($output['menu_list']) && empty($output['coupon_list'])) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "暂无可用套餐";
			$output['ecode'] = $api_code."004";
			return $output;
		}

		// 如果用户是会员，那么肯定允许用户购买/使用体验券
		if ($this->user_model->is_vip($user)) {
			$output['allow_free_coupon'] = 1;
		}
		// 如果不是会员，则需要进行进一步判断
		else {
			// 如果用户有可用体验券，则返回是否允许该用户使用体验券
			if (isset($output['coupon_list'][0]) && $output['coupon_list'][0]['menu_id']==FREE_COUPON_MENU_ID) {
				$output['allow_free_coupon'] = (int) $this->coupon_model->allow_free_coupon($uid, TRUE);
			}
			// 否则返回是否允许该用户购买体验券
			else {
				$output['allow_free_coupon'] = (int) $this->coupon_model->allow_free_coupon($uid, FALSE);
			}
		}

		$output['love_entrust_price'] = LOVE_ENTRUST_PRICE;

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：boy_response_girl_choose_menu
	 * 接口功能：男生接受女生邀请第二步，选择套餐并付款
	 * 接口编号：0508
	 * 接口加密名称：xKggGOA9wkSaBIHu
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$iid			邀请id
	 * @param int		$menu_id		要选择的套餐的id（0表示接收女生使用体验券发出的邀请）
	 * @param int		$channel		渠道代码，具体见对应表，如果传0则表示用已有的消费券
	 * @param int		$is_discount	是否优惠购买,1是0不是
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['order_no']: 当使用第三方支付时，成功生成订单后会返回该值，为19位的订单号
	 * $output['json']: 当使用第三方支付时，成功生成订单后会返回该值，为Ping++返回给服务器的信息，供客户端使用
	 *
	 */
	public function boy_response_girl_choose_menu($uid, $token, $iid, $menu_id, $channel, $is_discount = 0)
	{
		$api_code = '0508';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token, TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		$user = $result['user'];

		// 检查iid是否合法
		if (!is_id($iid)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "邀请id不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 判断用户性别是否合法
		if ($user['gender'] != 1) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "用户性别不合法";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 检测邀请状态是否正确
		$this->load->service('match_service');
		$result_invite = $this->match_service->can_response($uid, 1, $iid);
		if ($result_invite['state'] == FALSE) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = $result_invite['error'];
			$output['ecode'] = $api_code."003";
			return $output;
		}
		$invite = $result_invite['invite'];

		// 如果是使用体验券接受邀请
		if ($menu_id == FREE_COUPON_MENU_ID) {
			// 如果是购买体验券
			if (is_id($channel)) {
				// 会员可以随便购买，非会员只能购买一次
				$this->load->model('coupon_model');
				if (!$this->user_model->is_vip($user) && !$this->coupon_model->allow_free_coupon($uid, FALSE)) {
					// 输出错误
					$output['state'] = FALSE;
					$output['error'] = "您没有会员权限";
					$output['ecode'] = "0000011";
					return $output;
				}
			}
			// 如果是使用体验券
			else {
				// 会员可以随便使用，非会员只能用一次
				$this->load->model('coupon_model');
				if (!$this->user_model->is_vip($user) && !$this->coupon_model->allow_free_coupon($uid, TRUE)) {
					// 输出错误
					$output['state'] = FALSE;
					$output['error'] = "您没有会员权限";
					$output['ecode'] = "0000011";
					return $output;
				}

				// 将channel置为0
				$channel = 0;
			}

		}
		// 如果是直接接受女生使用体验券发出的邀请
		else if ($menu_id == 0) {
			// 将channel置为0
			$channel = 0;

			// 如果这个邀请不是使用体验券发出的
			if (!$result_invite['use_free_coupon']) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = '请选择套餐';
				$output['ecode'] = $api_code."007";
				return $output;
			}
		}

		$this->load->model('menu_model');
		// 如果是请求支付，且不是体验券，需要检测套餐是否可用
		if (is_id($channel) && $menu_id != FREE_COUPON_MENU_ID) {
			$result = $this->menu_model->is_available($menu_id, $user['province'], $user['city']);
			// 如果套餐不可用
			if ($result['state'] == FALSE) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = $result['error'];
				$output['ecode'] = $api_code."004";
				return $output;
			}
		}

		// 如果满足邀请条件，则开始进入邀请逻辑

		//保留券的信息
		$menu_info['menu'] = $this->menu_model->get_row_by_id($menu_id);
		$remark = serialize($menu_info);

		// 如果是直接接受女生使用体验券发出的邀请
		if ($menu_id == 0) {
			// 更新邀请
			$data['state'] = 4;
			$this->load->model('invite_model');
			$this->invite_model->update($data, array('iid'=>$iid));

			//添加统计数据
			$this->load->service('total_service');
			$this->total_service->set_match_response($uid, $invite['uid_2']);

			// 如果修改失败
			if ($this->invite_model->db->affected_rows() <= 0) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "数据库错误";
				$output['ecode'] = "0000003";
				return $output;
			}

			// 下面开始创建聊天
			$this->load->model('chat_model');

			// 首先检测是否已经存在聊天了
			$result_chat = $this->chat_model->check_chat_exist($uid, $invite['uid_2']);

			// 如果不存在则添加
			if ($result_chat['state'] == FALSE) {
				// 聊天的类型
				$chat['type'] = 1;

				// 男生的信息
				$chatuser[0]['uid'] = $uid;
				$chatuser[0]['name'] = $user['username'];
				$chatuser[0]['avatar'] = $user['avatar'];

				// 女生的信息，不包含name和avatar，insert函数会自动查找
				$chatuser[1]['uid'] = $invite['uid_2'];

				// 插入数据库
				$chatid = $this->chat_model->insert_chat($chat, $chatuser, TRUE);

				// 插入成功则发送一条消息
				if (is_id($chatid)) {
					$this->load->service('api_chat_service');
					$this->api_chat_service->chat_send($uid, $token, $chatid, CHATCONTENT_TYPE_SYSTEM, '你好，我们已经成为好友，可以开始聊天啦', time());
				}
			}

			// 推送消息
			$this->load->service('push_service');
			$message['type'] = PUSH_INVTIE_ACCEPT;
			$message['uid'] = $invite['uid_2'];
//			$message['iid'] = $iid;
			$this->push_service->push_message($message);
		}
		// 如果不是女生使用体验券发出的邀请
		else {
			$this->load->service('pay_service');

			// 如果是使用现有消费券
			if (!is_id($channel)) {
				$result = $this->pay_service->finish_pay($uid, ORDER_TYPE_MENU_FOR_RECIEVE, $iid, $menu_id, 0, $channel, $remark);
				// 如果使用消费券时发生错误
				if ($result['state'] == FALSE) {
					// 输出错误
					$output['state'] = FALSE;
					$output['error'] = $result['error'];
					$output['ecode'] = $api_code."005";
					return $output;
				}
			}
			// 如果是请求支付
			else {
				// 请求Ping++，生成订单
				$result = $this->pay_service->add_pingpp_order($uid, ORDER_TYPE_MENU_FOR_RECIEVE, $iid, $menu_id, 0, $channel, $remark, array('is_discount' => $is_discount));
				// 如果请求支付时发生错误
				if ($result['state'] == FALSE) {
					// 输出错误
					$output['state'] = FALSE;
					$output['error'] = $result['error'];
					$output['ecode'] = $api_code."006";
					return $output;
				}

				// 如果成功了，构建返回值
				$output['order_no'] = $result['pingpp_order']['order_no'];
				$output['json'] = $result['json'];
			}
		}

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：girl_response_boy
	 * 接口功能：女生接受男生邀请
	 * 接口编号：0509
	 * 接口加密名称：Dtn4TLqgQ3gPo2GB
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$iid			邀请的id
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 *
	 */
	public function girl_response_boy($uid, $token, $iid)
	{
		$api_code = '0509';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token, TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		$user = $result['user'];

		// 检查iid是否合法
		if (!is_id($iid)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "邀请id不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 判断用户性别是否合法
		if ($user['gender'] != 2) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "用户性别不合法";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 检测邀请状态是否正确
		$this->load->service('match_service');
		$result = $this->match_service->can_response($uid, 2, $iid);
		if ($result['state'] == FALSE) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = $result['error'];
			$output['ecode'] = $api_code."003";
			return $output;
		}
		$invite = $result['invite'];

		// 更新邀请
		$data['state'] = 2;
		$this->load->model('invite_model');
		$this->invite_model->update($data, array('iid'=>$iid));



		// 如果修改失败
		if ($this->invite_model->db->affected_rows() <= 0) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = '该邀请已被接受';
			$output['ecode'] = $api_code."004";
			return $output;
		}

		//添加统计数据
		$this->load->service('total_service');
		$this->total_service->set_match_response($uid, $invite['uid_1']);

		// 下面开始创建聊天
		$this->load->model('chat_model');

		// 首先检测是否已经存在聊天了
		$result_chat = $this->chat_model->check_chat_exist($uid, $invite['uid_1']);

		// 如果不存在则添加
		if ($result_chat['state'] == FALSE) {
			// 聊天的类型
			$chat['type'] = 1;

			// 女生的信息
			$chatuser[0]['uid'] = $uid;
			$chatuser[0]['name'] = $user['username'];
			$chatuser[0]['avatar'] = $user['avatar'];

			// 男生的信息，不包含name和avatar，insert函数会自动查找
			$chatuser[1]['uid'] = $invite['uid_1'];

			// 插入数据库
			$chatid = $this->chat_model->insert_chat($chat, $chatuser, TRUE);

			// 插入成功则发送一条消息
			if (is_id($chatid)) {
				$this->load->service('api_chat_service');
				$this->api_chat_service->chat_send($uid, $token, $chatid, CHATCONTENT_TYPE_SYSTEM, '你好，我们已经成为好友，可以开始聊天啦', time());
			}
		}


		// 推送消息
		$this->load->service('push_service');
		$message['type'] = PUSH_INVTIE_ACCEPT;
		$message['uid'] = $invite['uid_1'];
//		$message['iid'] = $iid;
		$this->push_service->push_message($message);


		//接受邀请之后，判断是否给与女生发送红包
		$this->load->model('invite_model');
		$invite = $this->invite_model->get_row_by_id($iid);

		//获取用户的openid
		$userweixin = $this->user_model->get_row_by_id($uid, 'p_userweixin', 'openid');

		//首先判断是否有心动卡
		if (!empty($invite) && $invite['heartbeat_type'] != 0) {
			//如果能获取到，则直接发送红包
			if (!empty($userweixin) && $userweixin['openid']!='') {
				//获取心动卡红包的区间
				$this->load->service('vip_service');
				$heartbeat_config = $this->vip_service->get_heartbeat_config();
				$red_envelope_min = $heartbeat_config[$invite['heartbeat_type'] - 1]['red_envelope_min'];
				$red_envelope_max = $heartbeat_config[$invite['heartbeat_type'] - 1]['red_envelope_max'];
				$amount = rand($red_envelope_min, $red_envelope_max);

				//生成订单
				$this->load->service('pay_service');
				$pingpp_result = $this->pay_service->add_pingpp_order($uid, ORDER_TYPE_WEIXIN_CASH, $invite['iid'], 0, $amount, 12, '', array("openid" => $userweixin['openid']));

				// 如果是测试网，并且使用的是测试key，那么不会有回调，为了测试，需要直接写入账单
				if (DEBUG) {
					if (strpos(config_item("pingpp_secretkey"), "sk_test_") !== FALSE) {
						$this->pay_service->finish_pingpp_order($pingpp_result['pingpp_order']['order_no'], $pingpp_result['pingpp_order']['pingpp_id']);
					}
				}
			}
			//如果不能，则标记该邀请需要用户绑定openid之后发送红包
			else {
				$this->invite_model->update(array("send_red_flag"=>1), array("iid"=>$iid));
			}

		}
		//然后判断是否有体验券的红包
		else if (!empty($invite) && $invite['menu_id']==FREE_COUPON_MENU_ID) {
			//如果能获取到，则直接发送红包
			if (!empty($userweixin) && $userweixin['openid']!='') {
				$rand = rand(0, 100);
				if ($rand <= 85) {
					$amount = 100;
				} else if ($rand <= 95) {
					$amount = rand(100, 200);
				} else if ($rand <= 99) {
					$amount = rand(200, 500);
				} else {
					$amount = rand(500, 1000);
				}

				//生成订单
				$this->load->service('pay_service');
				$pingpp_result = $this->pay_service->add_pingpp_order($uid, ORDER_TYPE_WEIXIN_CASH, $invite['iid'], 0, $amount, 12, '', array("openid" => $userweixin['openid']));

				// 如果是测试网，并且使用的是测试key，那么不会有回调，为了测试，需要直接写入账单
				if (DEBUG) {
					if (strpos(config_item("pingpp_secretkey"), "sk_test_") !== FALSE) {
						$this->pay_service->finish_pingpp_order($pingpp_result['pingpp_order']['order_no'], $pingpp_result['pingpp_order']['pingpp_id']);
					}
				}
			}
			//如果不能，则标记该邀请需要用户绑定openid之后发送红包
			else {
				$this->invite_model->update(array("send_red_flag"=>1), array("iid"=>$iid));
			}

		}

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：boy_cancel_invite
	 * 接口功能：取消邀请
	 * 接口编号：0510
	 * 接口加密名称：BqptNbXY0vz2CoZQ
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$iid			邀请的id
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 *
	 */
	public function boy_cancel_invite ($uid, $token, $iid)
	{
		$api_code = '0510';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token, TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		$user = $result['user'];

		//获取邀请
		$this->load->model('invite_model');
		$invite = $this->invite_model->get_row_by_id($iid);

		//判断该邀请是否该男生发出
		if ($invite['uid_1'] != $uid) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = '你没有权限取消该邀请';
			$output['ecode'] = $api_code."003";
			return $output;
		}

		//判断用户是否vip
		$this->load->model('user_model');
		if ($this->user_model->is_vip($user)) {
			//判断邀请时间是否满足或者该邀请是否已读
			if (($invite['create_time']-time())<86400 && !$invite['read_flag']) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = '发出的邀请已读或超出24小时后才能取消，等等再来吧~';
				$output['ecode'] = $api_code."001";
				return $output;
			}
		} else {
			//判断邀请时间是否满足
			if (($invite['create_time']-time())<86400) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = '发出的邀请超出24小时后才能取消，等等再来吧~';
				$output['ecode'] = $api_code."002";
				return $output;
			}
		}

		//删除这个邀请
		$this->invite_model->delete(array("iid"=>$iid));

		$output['state'] = TRUE;
		return $output;
	}



}