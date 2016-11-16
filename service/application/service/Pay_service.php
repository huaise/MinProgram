<?php
/**
 * 支付服务
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/2/17
 * Time: 11:55
 */

class Pay_service extends MY_Service {

	public function __construct()
	{
		parent::__construct();
		$this->_log_tag = 'pay';
	}


	/**
	 * 增加一个无需支付的订单， 使用抵价券等情况
	 * @param int			$uid 		支付者的用户id
	 * @param int			$type		交易类型，具体见对应表
	 * @param int			$id_1		相关id1，与type有关，具体见对应表
	 * @param int			$id_2		相关id2，与type有关，具体见对应表
	 * @param int			$amount		金额，以分为单位
	 * @param int			$channel	渠道代码，具体见对应表，如果传0或者-1则表示用已有财富支付
	 * @param string		$remark		备注，附言等
	 * $output['state']: TRUE(成功)/FALSE(失败)
	 * $output['error']: 失败原因
	 */
	public function add_pingpp_order_finish($uid, $type, $id_1, $id_2, $amount, $channel, $remark='') {

		// 需要有合法的用户id,活动支付uid可以为0
		if (!is_id($uid) && $type!=ORDER_TYPE_ACTIVITY ) {
			$output['state'] = FALSE;
			$output['error'] = '用户id不合法';
			return $output;
		}


		// 检测类型是否合法
		if (!is_id($type)) {
			$output['state'] = FALSE;
			$output['error'] = "类型不合法";
			return $output;
		}


		// 根据类型来设置文案、价格等

		// 如果是购买套餐
		if ($type == ORDER_TYPE_MENU_FOR_SEND || $type == ORDER_TYPE_MENU_FOR_RECIEVE) {
		}
		// 如果是购买会员
		else if ($type == ORDER_TYPE_VIP) {
		}
		// 如果是购买体验券
		else if ($type == ORDER_TYPE_FREE_COUPON) {
		}
		// 如果是活动支付
		else if ($type == ORDER_TYPE_ACTIVITY) {
			// 需要有合法的活动id
			if (!is_id($id_1)) {
				$output['state'] = FALSE;
				$output['error'] = '活动id不合法';
				return $output;
			}
		}
		//如果是服务支付
		else if ($type == ORDER_TYPE_SERVICE) {
		}
		else {
			$output['state'] = FALSE;
			$output['error'] = "类型不合法";
			return $output;
		}

		// 检测渠道是否合法
		if (!is_id($channel)) {
			$output['state'] = FALSE;
			$output['error'] = "渠道不合法";
			return $output;
		}

		// 将渠道转换为字符串
		$channel_str = $this->get_channel_str($channel);

		if ($channel_str == '') {
			$output['state'] = FALSE;
			$output['error'] = "渠道不合法";
			return $output;
		}

		// 检测金额是否合法
		if (!is_id($amount)) {
			$output['state'] = FALSE;
			$output['error'] = "金额不合法";
			return $output;
		}


		// 首先往数据库里写一个订单
		$pingpp_order['uid'] = $uid;
		$pingpp_order['id_1'] = $id_1;
		$pingpp_order['id_2'] = $id_2;
		$pingpp_order['type'] = $type;
		$pingpp_order['channel'] = $channel;
		$pingpp_order['amount'] = $amount;
		$pingpp_order['remark'] = $remark;
		$pingpp_order['state'] = 2;
		$pingpp_order['update_time'] = time();

		$this->load->model('pingpp_order_model');
		$pingpp_order['order_no'] = $this->pingpp_order_model->insert_pingpp_order($pingpp_order, TRUE);
		if ($pingpp_order['order_no'] <= 0) {
			$output['state'] = FALSE;
			$output['error'] = "生成订单时出错";
			return $output;
		}

		//订单完成逻辑
		// 如果是购买消费券发出邀请（仅支持男生购买消费券发出邀请，女生使用体验券不要调用此函数）
		if ($type == ORDER_TYPE_MENU_FOR_SEND) {
		}
		// 如果是购买消费券接受邀请
		else if ($type == ORDER_TYPE_MENU_FOR_RECIEVE) {
		}
		// 如果是购买会员
		else if ($type == ORDER_TYPE_VIP) {
		}
		// 如果是购买体验券
		else if ($type == ORDER_TYPE_FREE_COUPON) {
		}
		//如果是活动支付
		else if ($type == ORDER_TYPE_ACTIVITY) {
			$this->load->model('user_model');
			$this->load->model('activity_model');
			$this->load->model('activity_sign_model');

			//将该用户支付状态修改为支付成功
			if ($uid==0) {
				$this->activity_sign_model->update(array("pay_state"=>2), array("uid"=>$uid, "aid"=>$id_1, "phone"=>$id_2));
			} else {
				$this->activity_sign_model->update(array("pay_state"=>2), array("uid"=>$uid, "aid"=>$id_1));
			}

			if ($this->activity_sign_model->db->affected_rows() > 0) {
				//给用户推送，如果是没注册的用户，则发送短信
				//首先获取活动名称
				$activity = $this->activity_model->get_row_by_id($id_1, "aid, name, start_time, address");
				$this->load->helper('date');
				$activity['start_time'] = get_str_from_time($activity['start_time']);
				//然后获取用户报名信息
				$user = $this->activity_sign_model->get_one(array("uid" => $uid, "aid" => $id_1), FALSE, "custom_input");
				$user = (array)json_decode($user['custom_input']);
				if ($uid == 0) {
					//然后发送短信
					$this->load->library('sms');
					$this->sms->send_activity_success_message($user['手机'], $activity['name']);
				} else {
					//然后进行消息推送
					// 构建消息内容
					$chatid = '-' . $uid;
					$content = "您已报名“" . $activity['name'] . "”活动，别忘记按时参加哦";
					$weixin_param['type'] = WEIXIN_PUSH_ACTIVITY;
					$weixin_param['activity'] = $activity;
					$this->load->service('api_chat_service');
					$this->api_chat_service->chat_send(0, SERVICE_TOKEN, $chatid, 1, $content, time(), $weixin_param);

				}
			}

			if ($uid!=0){
				//添加统计数据
				$this->load->model('user_total_model');
				$user_total = array(
					'uid' => $uid,
					'type' => 34,
					'content' => $id_1,
					'create_time' => time(),
					'update_time' => time(),
				);
				$this->user_total_model->insert($user_total);
			}
		}
		//如果是服务支付
		else if ($type == ORDER_TYPE_SERVICE) {
			// 需要有合法的服务id
			if (!is_id($id_1)) {
				$output['state'] = FALSE;
				$output['error'] = '服务id不合法';
				return $output;
			}
			//如果是恋爱委托
			if ($id_1==1) {
				//插入数据库该用户进行恋爱委托
				$this->load->model('service_sign_model');
				$data['sid'] = $id_1;
				$data['uid'] = $uid;
				$data['state'] = 1;
				$data['create_time'] = time();
				$data['update_time'] = time();

				//进行消息推送
				if ($this->service_sign_model->insert_with_unique_key($data)) {
					// 构建消息内容
					$chatid = '-' . $uid;
					$content = "您已购买“恋爱委托”服务，24小时内将有客服与您微信联系哦";
					$weixin_param['type'] = WEIXIN_PUSH_SERVICE_ENTRUST;
					$this->load->service('api_chat_service');
					$this->api_chat_service->chat_send(0, SERVICE_TOKEN, $chatid, 1, $content, time(), $weixin_param);

				}

			}

		}
		else {
			$output['state'] = FALSE;
			$output['error'] = '类型不合法';
			return $output;
		}

		//消耗抵价卷等情况 暂不做分离封装
		$remark = unserialize($remark);
		if (isset($remark['use_coupon_activity'])) {
			$this->load->model('coupon_activity_model');
			$update = array(
				'state' => 1,
				'amount_use' => $remark['use_coupon_activity']['amount_use'],
				'update_time' => time()
			);
			$where = array(
				'ca_id' => $remark['use_coupon_activity']['ca_id']
			);
			$this->coupon_activity_model->update($update, $where);
		}

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 * 当增加一个新的Ping++订单，并请求Ping++服务器
	 * @param int			$uid 		支付者的用户id
	 * @param int			$type		交易类型，具体见对应表
	 * @param int			$id_1		相关id1，与type有关，具体见对应表
	 * @param int			$id_2		相关id2，与type有关，具体见对应表
	 * @param int			$amount		金额，以分为单位
	 * @param int			$channel	渠道代码，具体见对应表，如果传0或者-1则表示用已有财富支付
	 * @param string		$remark		备注，附言等
	 * @param array			$extra_param 额外的一些支付参数
	 * $output['state']: TRUE(成功)/FALSE(失败)
	 * $output['error']: 失败原因
	 * $output['pingpp_order']: 增加的订单的信息
	 * $output['json']: Ping++接口返回的json串
	 */
	public function add_pingpp_order($uid, $type, $id_1, $id_2, $amount, $channel, $remark='', $extra_param=array()) {

		// 需要有合法的用户id,活动支付uid可以为0
		if (!is_id($uid) && $type!=ORDER_TYPE_ACTIVITY ) {
			$output['state'] = FALSE;
			$output['error'] = '用户id不合法';
			return $output;
		}


		// 检测类型是否合法
		if (!is_id($type)) {
			$output['state'] = FALSE;
			$output['error'] = "类型不合法";
			return $output;
		}


		// 根据类型来设置文案、价格等

		// 如果是购买套餐
		if ($type == ORDER_TYPE_MENU_FOR_SEND || $type == ORDER_TYPE_MENU_FOR_RECIEVE) {
			// 需要有合法的id_1
			if (!is_id($id_1)) {
				$output['state'] = FALSE;
				$output['error'] = 'id_1不合法';
				return $output;
			}

			// 需要有合法的套餐id
			if (!is_id($id_2)) {
				$output['state'] = FALSE;
				$output['error'] = '套餐id不合法';
				return $output;
			}

			$menu_id = $id_2;

			// 获取套餐
			// 如果是体验券
			if ($menu_id == FREE_COUPON_MENU_ID) {
				$this->load->service('vip_service');
				$this->load->model('user_model');
				$user = $this->user_model->get_row_by_id($uid, 'p_userdetail', 'gender');
				$result = $this->vip_service->get_free_coupon_config($uid, $user['gender'], 1);
				$menu['name'] = '体验券';
				$menu['price'] = $result['price'];
				//首单购买体验卷失败时，有一次优惠购买的机会
				if (isset($extra_param['is_discount']) && $extra_param['is_discount'] == 1){
					$this->load->model('pingpp_order_model');
					if ($this->pingpp_order_model->get_is_first($uid)){
						$menu['price'] = round($menu['price'] / 100 * DISCOUNT_PAY, 2);
					}
				}
			}
			// 如果不是体验券
			else {
				$this->load->model('menu_model');
				$menu = $this->menu_model->get_row_by_id($menu_id, 'name,price');
				if (empty($menu)) {
					$output['state'] = FALSE;
					$output['error'] = '套餐不存在';
					return $output;
				}
			}

			$pingpp_subject = $menu['name'];
			$pingpp_body = '购买套餐';
			$amount = $menu['price'];
		}
		// 如果是购买会员
		else if ($type == ORDER_TYPE_VIP) {
			// 需要有合法的天数
			if (!is_id($id_1)) {
				$output['state'] = FALSE;
				$output['error'] = '天数不合法';
				return $output;
			}

			$pingpp_subject = '购买会员';
			$pingpp_body = '购买会员';
		}
		// 如果是购买体验券
		else if ($type == ORDER_TYPE_FREE_COUPON) {
			// 需要有合法的体验券张数
			if (!is_id($id_1)) {
				$output['state'] = FALSE;
				$output['error'] = '体验券张数不合法';
				return $output;
			}

			$pingpp_subject = '购买体验券';
			$pingpp_body = '购买体验券';
		}
		// 如果是活动支付
		else if ($type == ORDER_TYPE_ACTIVITY) {
			// 需要有合法的活动id
			if (!is_id($id_1)) {
				$output['state'] = FALSE;
				$output['error'] = '活动id不合法';
				return $output;
			}
			$pingpp_subject = '活动支付';
			$pingpp_body = '活动支付';
		}
		//如果是服务支付
		else if ($type == ORDER_TYPE_SERVICE) {
			// 需要有合法的活动id
			if (!is_id($id_1)) {
				$output['state'] = FALSE;
				$output['error'] = '服务id不合法';
				return $output;
			}
			$pingpp_subject = '服务支付';
			$pingpp_body = '服务支付';
		}
		//如果是微信红包
		else if ($type == ORDER_TYPE_WEIXIN_CASH) {
			//需要有openid
			if (!isset($extra_param['openid']) || empty($extra_param['openid'])) {
				$output['state'] = FALSE;
				$output['error'] = '必须要有openid';
				return $output;
			}
			$pingpp_subject = isset($extra_param['subject'])?$extra_param['subject']:'微信红包';
			$pingpp_body = isset($extra_param['body'])?$extra_param['body']:'微信红包';
		}
		else {
			$output['state'] = FALSE;
			$output['error'] = "类型不合法";
			return $output;
		}

		$remark = unserialize($remark);
		//如果心动卡需要购买
		if ($type == ORDER_TYPE_MENU_FOR_SEND && isset($remark['heartbeat']) && is_id($remark['heartbeat']['heartbeat_type'])) {
			$heartbeat = $remark['heartbeat'];
			//获取心动卡配置,并获取需要支付的金额
			$this->load->service('vip_service');
			$heartbeat_config = $this->vip_service->get_heartbeat_config();

			//如果套餐不需要购买，但是心动卡需要购买
			if (!is_id($channel) && is_id($heartbeat['heartbeat_channel'])) {
				//构建订单参数,将之前的套餐支付作废
				//支付渠道应该为心动卡的支付渠道
				$channel = $heartbeat['heartbeat_channel'];

				$pingpp_subject = "心动卡";
				$pingpp_body = '购买心动卡';
				$amount = $heartbeat_config[$heartbeat['heartbeat_type']-1]['price'];

			}
			//如果套餐跟心动卡都需要购买
			else if (is_id($channel) && is_id($heartbeat['heartbeat_channel'])) {
				//判断渠道是否相同
				if ($channel != $heartbeat['heartbeat_channel']) {
					$output['state'] = FALSE;
					$output['error'] = "套餐支付渠道应该和心动卡支付渠道相同";
					return $output;
				}

				//重新构建订单参数，与之前的套餐支付相加
				$heartbeat_price = $heartbeat_config[$heartbeat['heartbeat_type']-1]['price'];

				$pingpp_subject .= "和心动卡";
				$pingpp_body .= "和心动卡";
				$amount = $amount + $heartbeat_price;
			}
			$remark['heartbeat']['price'] = $heartbeat_config[$heartbeat['heartbeat_type']-1]['price'];
		}

		// 检测渠道是否合法
		if (!is_id($channel)) {
			$output['state'] = FALSE;
			$output['error'] = "渠道不合法";
			return $output;
		}

		// 将渠道转换为字符串
		$channel_str = $this->get_channel_str($channel);

		if ($channel_str == '') {
			$output['state'] = FALSE;
			$output['error'] = "渠道不合法";
			return $output;
		}

		// 检测金额是否合法
		if (!is_id($amount)) {
			$output['state'] = FALSE;
			$output['error'] = "金额不合法";
			return $output;
		}

		//将remark序列化
		$remark = serialize($remark);

		// 首先往数据库里写一个订单
		$pingpp_order['uid'] = $uid;
		$pingpp_order['id_1'] = $id_1;
		$pingpp_order['id_2'] = $id_2;
		$pingpp_order['type'] = $type;
		$pingpp_order['channel'] = $channel;
		$pingpp_order['amount'] = $amount;
		$pingpp_order['remark'] = $remark;

		$this->load->model('pingpp_order_model');
		$pingpp_order['order_no'] = $this->pingpp_order_model->insert_pingpp_order($pingpp_order, TRUE);
		if ($pingpp_order['order_no'] <= 0) {
			$output['state'] = FALSE;
			$output['error'] = "生成订单时出错";
			return $output;
		}

		// 开始产生请求ping++接口需要的参数
		$extra = array();
		switch ($channel_str) {
			case 'alipay_wap':
				$extra = array(
					'success_url' => $extra_param['success_url'],
					'cancel_url' => $extra_param['cancel_url']
				);
				break;
			case 'wx_pub':
				//根据uid去查询该用户的openid
				//没传的情况 按原逻辑查一遍
				if (isset($extra_param['openid']) && $extra_param['openid']) {
					$extra = array(
						'open_id' => $extra_param['openid']
					);
				} //查看cookie内是否有openid，如果有，使用cookie内的openid
				else if (isset($_COOKIE['openid']) && $_COOKIE['openid']) {
					$extra = array(
						'open_id' => $_COOKIE['openid']
					);
				} //如果没有，则去数据库里面获取
				else {
					$this->load->model('user_model');
					$this->user_model->set_table_name("p_userweixin");
					$user = $this->user_model->get_one(array("uid" => $uid), FALSE, 'openid');
					$extra = array(
						'open_id' => $user['openid']
					);
				}

				break;
			case 'wx_pub_qr':
				$extra = array(
					'product_id' => 'Productid'
				);
				break;
			default :
				break;
		}

		// 开始请求Ping++接口
		require_once(dirname(__FILE__) . '/../third_party/pingpp/init.php');
		\Pingpp\Pingpp::setApiKey(config_item('pingpp_secretkey'));
		$this->load->helper('security');
		try {
			//发送微信红包
			if ($type == ORDER_TYPE_WEIXIN_CASH) {
				$ch = \Pingpp\RedEnvelope::create(
					array(
						'subject'     => $pingpp_subject,
						'body'     	  => $pingpp_body,
						'amount'      => $pingpp_order['amount'],
						'order_no'    => $pingpp_order['order_no'],
						'currency'    => 'cny',
						'app'         => array('id' => config_item("pingpp_appid")),
						'channel'     => $channel_str,
						'recipient'   => $extra['open_id'],
						'description' => $pingpp_subject,
						'extra'       => array("send_name"=>"简爱")
					)
				);
			}
			//如果是充值
			else {
				$ch = \Pingpp\Charge::create(
					array(
						'subject'   => $pingpp_subject,
						'body'      => $pingpp_body,
						'amount'    => $pingpp_order['amount'],
						'order_no'  => $pingpp_order['order_no'],
						'currency'  => 'cny',
						'extra'     => $extra,
						'channel'   => $channel_str,
						'client_ip' => get_ip()=='::1' ? '192.168.0.1' : get_ip(),
						'app'       => array('id' => config_item('pingpp_appid'))
					)
				);
			}
		} catch (\Pingpp\Error\Base $e) {

			// 删除数据库中的订单
			$this->pingpp_order_model->delete(array('order_no'=>$pingpp_order['order_no']));

			$error = json_decode($e->getHttpBody(), TRUE);

			// 写日志
			$this->log->write_log_json($pingpp_order, "pingpp_error");
			$this->log->write_log_json($error, "pingpp_error");

			$output['state'] = FALSE;
			$output['error'] = $error['error']['message'];
			return $output;
		}

		//消耗抵价卷等情况 暂不做分离封装
		//待支付在使用避免订单被删除
		$remark = unserialize($remark);
		if (isset($remark['use_coupon_activity'])) {
			$this->load->model('coupon_activity_model');
			$update = array(
				'state' => 1,
				'amount_use' => $remark['use_coupon_activity']['amount_use'],
				'update_time' => time()
			);
			$where = array(
				'ca_id' => $remark['use_coupon_activity']['ca_id']
			);
			$this->coupon_activity_model->update($update, $where);
		}

		// 将结果解码成数组
		$result = json_decode($ch, TRUE);
		$pingpp_order['pingpp_id'] = $result['id'];

		$remark = serialize($remark);
		// 更新数据库中的订单状态
		if (!$this->pingpp_order_model->update(array('pingpp_id'=>$pingpp_order['pingpp_id'], 'state'=>ORDER_STATE_CALL, 'remark'=>$remark), array('order_no'=>$pingpp_order['order_no']))) {
			$output['state'] = FALSE;
			$output['error'] = "数据库错误";
			return $output;
		}

		$output['state'] = TRUE;
		$output['pingpp_order'] = $pingpp_order;
		$output['json'] = $result;
		return $output;
	}


	/**
	 * 获取Ping++订单状态
	 * @param double			$order_no 		内部订单号
	 * @param string			$pingpp_id		Ping++的订单号
	 * $output['state']: TRUE(成功)/FALSE(失败)
	 * $output['error']: 失败原因
	 * $output['success_flag']: 表示支付是否成功，为1表示成功，为0表示失败
	 */
	public function get_pingpp_order_state($order_no, $pingpp_id) {
		$success_flag = FALSE;

		// 开始请求Ping++接口
		require_once(dirname(__FILE__) . '/../third_party/pingpp/init.php');
		\Pingpp\Pingpp::setApiKey(config_item('pingpp_secretkey'));
		try {
			$ch = \Pingpp\Charge::retrieve($pingpp_id);
		} catch (\Pingpp\Error\Base $e) {
			// 写日志
			$this->log->write_log_json($e->getHttpBody(), "pingpp_error");

			$output['state'] = FALSE;
			$output['error'] = "查询失败";
			return $output;
		}

		$result = json_decode($ch, TRUE);

//		$result['paid'] = 1;

		if ($result['paid'] == 1) {
			// 现网要求必须是livemode
			if (!DEBUG) {
				if ($result['livemode'] == FALSE) {
					$output['state'] = FALSE;
					$output['error'] = "订单不合法";
					return $output;
				}
			}

			// 判断订单号是否一致
			if ($order_no != $result['order_no']) {
				$output['state'] = FALSE;
				$output['error'] = "订单号不符";
				return $output;
			}

			// 修改内存中的变量，供最后的判断使用
			$success_flag = TRUE;

			// 调用订单完成的函数
			$this->finish_pingpp_order($order_no, $pingpp_id);
		}

		$output['state'] = TRUE;
		$output['success_flag'] = $success_flag;
		return $output;
	}

	/**
	 * 当Ping++订单完成的时候，调用此函数处理订单完成逻辑
	 * @param double			$order_no 		内部订单号
	 * @param string			$pingpp_id		Ping++的订单号
	 * $output['state']: TRUE(成功)/FALSE(失败)
	 * $output['error']: 失败原因
	 */
	public function finish_pingpp_order($order_no, $pingpp_id) {
//		$order_no = number_format($order_no, 0, '', '');

		// 将该订单改为支付成功
		$this->load->model('pingpp_order_model');
		// 更新数据库中的订单状态
		if (!$this->pingpp_order_model->update(array('state'=>ORDER_STATE_SUCCESS, 'update_time'=>time()), array('order_no'=>$order_no, 'pingpp_id'=>$pingpp_id, 'state !='=>ORDER_STATE_SUCCESS))) {
			$output['state'] = FALSE;
			$output['error'] = "数据库错误";
			return $output;
		}

		// 如果实际没有修改，说明订单状态已经变了
		if ($this->pingpp_order_model->db->affected_rows() <= 0) {
			$output['state'] = FALSE;
			$output['error'] = "订单已处理";
			return $output;
		}

		// 获取该订单
		$pingpp_order = $this->pingpp_order_model->get_row_by_id($order_no);

		// 然后调用支付完成函数
		$result = $this->finish_pay($pingpp_order['uid'], $pingpp_order['type'], $pingpp_order['id_1'], $pingpp_order['id_2'], $pingpp_order['amount'], $pingpp_order['channel'], $pingpp_order['remark'], $order_no);

		// 如果在处理支付完成的过程中出错，则修改订单状态
		if ($result['state'] == FALSE) {
			$this->pingpp_order_model->update(array('state'=>ORDER_STATE_FAILED), array('order_no'=>$order_no, 'pingpp_id'=>$pingpp_id));

			$output['state'] = FALSE;
			$output['error'] = $result['error'];
			return $output;
		}

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 * 当Ping++退款完成的时候，调用此函数处理订单完成逻辑
	 * @param double			$order_no 		内部订单号
	 * @param string			$pingpp_id		Ping++的订单号
	 * $output['state']: TRUE(成功)/FALSE(失败)
	 * $output['error']: 失败原因
	 */
	public function finish_pingpp_refund_order($order_no, $pingpp_id) {

		// 将该订单改为成功退款
		$this->load->model('pingpp_order_model');
		// 更新数据库中的订单状态
		if (!$this->pingpp_order_model->update(array('state'=>ORDER_STATE_REFUND_SUCCES, 'update_time'=>time()), array('order_no'=>$order_no, 'pingpp_id'=>$pingpp_id, 'state !='=>ORDER_STATE_REFUND_SUCCES))) {
			$output['state'] = FALSE;
			$output['error'] = "数据库错误";
			return $output;
		}

		// 如果实际没有修改，说明订单状态已经变了
		if ($this->pingpp_order_model->db->affected_rows() <= 0) {
			$output['state'] = FALSE;
			$output['error'] = "订单已处理";
			return $output;
		}

		// 然后调用支付完成函数
		$result = $this->finish_refund_pay($order_no);

		// 如果在处理支付完成的过程中出错，则修改订单状态
		if ($result['state'] == FALSE) {
			$this->pingpp_order_model->update(array('state'=>ORDER_STATE_REFUND_FAILED), array('order_no'=>$order_no, 'pingpp_id'=>$pingpp_id));

			$output['state'] = FALSE;
			$output['error'] = $result['error'];
			return $output;
		}

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 * 当退款完成的时候，调用此函数处理退款完成逻辑
	 * @param int			$order_no	订单
	 * $output['state']: TRUE(成功)/FALSE(失败)
	 * $output['error']: 失败原因
	 */
	public function finish_refund_pay($order_no) {

		//获取信息
		$this->load->model('pingpp_order_model');

		$order = $this->pingpp_order_model->get_row_by_id($order_no);

		if (empty($order)) {
			$output['state'] = false;
			$output['error'] = '查询不到该订单';
			return $output;
		}

		$type = $order['type'];

		// 如果是活动退款
		if ($type == ORDER_TYPE_ACTIVITY) {
			//加载model
			$this->load->model('activity_sign_model');

			//更新支付状态
			if (!$this->activity_sign_model->update(array('pay_state' => ORDER_STATE_REFUND_SUCCES, 'update_time' => time()), array('order_no' => $order_no))) {
				$output['state'] = false;
				$output['error'] = '活动退款数据库修改状态失败';
				return $output;
			}
		}
		//如果是恋爱委托
		else if($type == ORDER_TYPE_SERVICE){
			//加载model
			$this->load->model('service_sign_model');

			//更新支付状态
			if($this->service_sign_model->update(array('refund_time'=>time(), 'state'=>ORDER_STATE_REFUND_SUCCES),array('order_no' => $order_no))){
				$output['state'] = false;
				$output['error'] = '恋爱委托服务数据库修改错误';
				return $output;
			}
		}
		else{
			$output['state'] = false;
			$output['error'] = '类型错误';
			return $output;
		}


		$output['state'] = TRUE;
		return $output;

	}


	/**
	 * 当支付完成的时候，调用此函数处理支付完成逻辑
	 * @param int			$uid 		支付者的用户id
	 * @param int			$type		交易类型，具体见对应表
	 * @param int			$id_1		相关id1，与type有关，具体见对应表
	 * @param int			$id_2		相关id2，与type有关，具体见对应表
	 * @param int			$amount		金额，以分为单位
	 * @param int			$channel	渠道代码，具体见对应表，如果传0或者-1则表示用已有财富支付
	 * @param string		$remark		备注，附言等
	 * @param int			$order_no	订单号等
	 * $output['state']: TRUE(成功)/FALSE(失败)
	 * $output['error']: 失败原因
	 */
	public function finish_pay($uid, $type, $id_1, $id_2, $amount, $channel, $remark='', $order_no=0) {

		// 需要有合法的用户id
		if (!is_id($uid) && $type!=ORDER_TYPE_ACTIVITY) {
			$output['state'] = FALSE;
			$output['error'] = '用户id不合法';
			return $output;
		}

		// 如果是购买消费券发出邀请（仅支持男生购买消费券发出邀请，女生使用体验券不要调用此函数）
		if ($type == ORDER_TYPE_MENU_FOR_SEND) {
			// 需要有合法的被邀请者uid
			if (!is_id($id_1)) {
				$output['state'] = FALSE;
				$output['error'] = '被邀请者uid不合法';
				return $output;
			}

			// 需要有合法的套餐id
			if (!is_id($id_2)) {
				$output['state'] = FALSE;
				$output['error'] = '套餐id不合法';
				return $output;
			}

			$to_uid = $id_1;
			$menu_id = $id_2;
			$remark = unserialize($remark);

			// 开始事务
			$this->load->model('coupon_model');
			$this->load->model('heartbeat_card_model');
			$this->coupon_model->db->trans_start();

			// 如果是选择已有消费券(有一种情况为，选择了已有消费券，但是心动卡需要支付，那么$channel会变成心动卡的支付渠道)
			//为了版本兼容,判断remark中的menu是否有channel参数
			if ((isset($remark['menu']['channel']) && !is_id($remark['menu']['channel'])) || (!isset($remark['menu']['channel']) && !is_id($channel) )) {
				// 首先锁定消费券
				$coupon_id = $this->coupon_model->lock_by_invite($uid, $menu_id);

				// 如果锁定失败
				if (!is_id($coupon_id)) {
					// 回滚事务
					$this->coupon_model->db->trans_rollback();

					// 输出错误
					$output['state'] = FALSE;
					$output['error'] = "消费券已被使用";
					return $output;
				}
			}
			// 如果是通过支付购买消费券
			else {
				// 发放一张消费券
				$coupon_id = $this->coupon_model->grant_coupon($uid, $menu_id);

				// 如果发放消费券失败
				if (!is_id($coupon_id)) {
					// 写日志
					$this->log->write_log_json(array('uid'=>$uid, 'type'=>$type, 'id_1'=>$id_1, 'id_2'=>$id_2, 'amount'=>$amount, 'channel'=>$channel, 'remark'=>$remark), "coupon_error");

					// 回滚事务
					$this->coupon_model->db->trans_rollback();

					// 输出错误
					$output['state'] = FALSE;
					$output['error'] = "发放消费券失败";
					return $output;
				}
			}

			//心动卡逻辑（所有的男生选择套餐邀请女生都要携带这两个参数）
			$heartbeat_type = $remark['heartbeat']['heartbeat_type']!=NULL ? $remark['heartbeat']['heartbeat_type'] : 0;
			$heartbeat_remark = $remark['heartbeat']['heartbeat_remark']!=NULL ? $remark['heartbeat']['heartbeat_remark'] : '';
			$heartbeat_channel = $remark['heartbeat']['heartbeat_channel']!=NULL ? $remark['heartbeat']['heartbeat_channel'] : 0;

			//如果选择购买心动卡
			if (is_id($heartbeat_type)) {
				//如果是新购,则是通过支付购买心动卡
				if (is_id($heartbeat_channel)) {
					//发放一张心动卡
					$hcid = $this->heartbeat_card_model->grant_heartbeat_card($uid, $heartbeat_type);

					// 如果发放心动卡失败
					if (!is_id($hcid)) {
						// 写日志
						$this->log->write_log_json(array('uid'=>$uid, 'type'=>$heartbeat_type, 'id_1'=>$id_1, 'id_2'=>$id_2, 'amount'=>$amount, 'channel'=>$channel, 'remark'=>$remark), "heartbeat_card_error");

						// 回滚事务
						$this->coupon_model->db->trans_rollback();

						// 输出错误
						$output['state'] = FALSE;
						$output['error'] = "发放心动卡失败";
						return $output;
					}

				}
				//如果是使用已有的心动卡
				else {
					// 首先锁定心动卡
					$hcid = $this->heartbeat_card_model->lock_by_invite($uid, $heartbeat_type);

					// 如果锁定失败
					if (!is_id($hcid)) {
						// 回滚事务
						$this->coupon_model->db->trans_rollback();

						// 输出错误
						$output['state'] = FALSE;
						$output['error'] = "心动卡已被使用";
						return $output;
					}
				}
			} else {
				$hcid = 0;
			}


			// 添加一个邀请
			$invite['uid_1'] = $uid;
			$invite['uid_2'] = $to_uid;
			$invite['menu_id'] = $menu_id;
			$invite['coupon_id'] = $coupon_id;
			$invite['heartbeat_type'] = $heartbeat_type;
			$invite['heartbeat_remark'] = $heartbeat_remark;
			$invite['hcid'] = $hcid;
			$invite['state'] = 1;
			$this->load->model('invite_model');
			$iid = $this->invite_model->insert_invite($invite, TRUE, TRUE);

			// 如果添加失败
			if (!is_id($iid)) {
				// 回滚事务
				$this->coupon_model->db->trans_rollback();

				$output['state'] = FALSE;
				$output['error'] = '添加邀请失败';
				return $output;
			}

			//添加统计数据
			$this->load->service('total_service');
			$this->total_service->set_match_invite($uid, $to_uid, $iid);

			// 结束事务
			$this->coupon_model->db->trans_complete();

			// 如果事务失败，返回错误
			if ($this->coupon_model->db->trans_status() === FALSE) {
				$output['state'] = FALSE;
				$output['error'] = '数据库错误';
				return $output;
			}

			// 推送消息
			$this->load->service('push_service');
			$message['type'] = PUSH_INVTIE_RECEIVE;
			$message['uid'] = $to_uid;
//			$message['iid'] = $iid;
			$this->push_service->push_message($message);
		}
		// 如果是购买消费券接受邀请
		else if ($type == ORDER_TYPE_MENU_FOR_RECIEVE) {
			// 需要有合法的邀请id
			if (!is_id($id_1)) {
				$output['state'] = FALSE;
				$output['error'] = '邀请id不合法';
				return $output;
			}

			// 需要有合法的套餐id
			if (!is_id($id_2)) {
				$output['state'] = FALSE;
				$output['error'] = '套餐id不合法';
				return $output;
			}

			$iid = $id_1;
			$menu_id = $id_2;

			// 找到该邀请
			$this->load->model('invite_model');
			$invite = $this->invite_model->get_row_by_id($iid, 'uid_2');

			// 如果找不到邀请
			if (empty($invite)) {
				$output['state'] = FALSE;
				$output['error'] = '找不到邀请';
				return $output;
			}

			// 开始事务
			$this->load->model('coupon_model');
			$this->coupon_model->db->trans_start();

			// 如果是选择已有消费券
			if (!is_id($channel)) {
				// 首先锁定消费券
				$coupon_id = $this->coupon_model->lock_by_invite($uid, $menu_id);

				// 如果锁定失败
				if (!is_id($coupon_id)) {
					// 回滚事务
					$this->coupon_model->db->trans_rollback();

					// 输出错误
					$output['state'] = FALSE;
					$output['error'] = "消费券已被使用";
					return $output;
				}
			}
			// 如果是通过支付购买消费券
			else {
				// 发放一张消费券
				$coupon_id = $this->coupon_model->grant_coupon($uid, $menu_id);

				// 如果发放消费券失败
				if (!is_id($coupon_id)) {
					// 写日志
					$this->log->write_log_json(array('uid'=>$uid, 'type'=>$type, 'id_1'=>$id_1, 'id_2'=>$id_2, 'amount'=>$amount, 'channel'=>$channel, 'remark'=>$remark), "coupon_error");

					// 回滚事务
					$this->coupon_model->db->trans_rollback();

					// 输出错误
					$output['state'] = FALSE;
					$output['error'] = "发放消费券失败";
					return $output;
				}
			}

			// 更新邀请
			$data['menu_id'] = $menu_id;
			$data['coupon_id'] = $coupon_id;
			$data['state'] = 4;
			$this->invite_model->update($data, array('iid'=>$iid));

			// 如果修改失败
			if ($this->invite_model->db->affected_rows() <= 0) {
				// 回滚事务
				$this->coupon_model->db->trans_rollback();

				$output['state'] = FALSE;
				$output['error'] = '添加邀请失败';
				return $output;
			}

			//添加统计数据
			$this->load->service('total_service');
			$this->total_service->set_match_response($uid, $invite['uid_2']);

			// 结束事务
			$this->coupon_model->db->trans_complete();

			// 如果事务失败，返回错误
			if ($this->coupon_model->db->trans_status() === FALSE) {
				$output['state'] = FALSE;
				$output['error'] = '数据库错误';
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

				// 男生的信息，不包含name和avatar，insert函数会自动查找
				$chatuser[0]['uid'] = $uid;

				// 女生的信息，不包含name和avatar，insert函数会自动查找
				$chatuser[1]['uid'] = $invite['uid_2'];

				// 插入数据库
				$chatid = $this->chat_model->insert_chat($chat, $chatuser, TRUE);

				// 插入成功则发送一条消息
				if (is_id($chatid)) {
					$this->load->model('user_model');
					$user = $this->user_model->get_row_by_id($uid, 'p_userbase', 'token');
					$this->load->service('api_chat_service');
					$this->api_chat_service->chat_send($uid, $user['token'], $chatid, CHATCONTENT_TYPE_SYSTEM, '你好，我们已经成为好友，可以开始聊天啦', time());
				}
			}

			// 推送消息
			$this->load->service('push_service');
			$message['type'] = PUSH_INVTIE_ACCEPT;
			$message['uid'] = $invite['uid_2'];
//			$message['iid'] = $iid;
			$this->push_service->push_message($message);
		}
		// 如果是购买会员
		else if ($type == ORDER_TYPE_VIP) {
			// 需要有合法的天数
			if (!is_id($id_1)) {
				$output['state'] = FALSE;
				$output['error'] = '天数不合法';
				return $output;
			}

			// 开始事务
			$this->load->model('user_model');
			$this->user_model->db->trans_start();

			// 首先改变会员日期
			if (!$this->user_model->update_vip_date($id_1, array('uid'=>$uid), TRUE)) {
				// 回滚事务
				$this->user_model->db->trans_rollback();

				$output['state'] = FALSE;
				$output['error'] = '修改会员日期失败';
				return $output;
			}

			// 然后发放体验券
			$this->load->model('coupon_model');
			for ($i = 0; $i < $id_2; $i++) {
				$this->coupon_model->grant_coupon($uid, FREE_COUPON_MENU_ID, FALSE, FALSE);
			}

			//发放活动抵用卷
			$remark = unserialize($remark);
			$coupon_activity = $remark['config']['coupon_activity']['act'];
			$this->load->model('coupon_activity_model');
			$coupon_activity_data = array(
				'uid' => $uid,
				'amount' => $coupon_activity['amount'],
			);
			for ($i = 0; $i < $coupon_activity['num']; $i++) {
				$this->coupon_activity_model->grant($coupon_activity_data);
			}

			// 结束事务
			$this->user_model->db->trans_complete();

			// 如果事务失败，返回错误
			if ($this->user_model->db->trans_status() === FALSE) {
				$output['state'] = FALSE;
				$output['error'] = '数据库错误';
				return $output;
			}
		}
		// 如果是购买体验券
		else if ($type == ORDER_TYPE_FREE_COUPON) {
			// 需要有合法的体验券张数
			if (!is_id($id_1)) {
				$output['state'] = FALSE;
				$output['error'] = '体验券张数不合法';
				return $output;
			}

			// 开始事务
			$this->load->model('coupon_model');
			$this->coupon_model->db->trans_start();

			// 发放体验券
			for ($i = 0; $i < $id_1; $i++) {
				$this->coupon_model->grant_coupon($uid, FREE_COUPON_MENU_ID, FALSE, FALSE);
			}

			// 结束事务
			$this->coupon_model->db->trans_complete();

			// 如果事务失败，返回错误
			if ($this->coupon_model->db->trans_status() === FALSE) {
				$output['state'] = FALSE;
				$output['error'] = '数据库错误';
				return $output;
			}
		}
		//如果是活动支付
		else if ($type == ORDER_TYPE_ACTIVITY) {
			// 需要有合法的活动id
			if (!is_id($id_1)) {
				$output['state'] = FALSE;
				$output['error'] = '活动id不合法';
				return $output;
			}

			$this->load->model('user_model');
			$this->load->model('activity_model');
			$this->load->model('activity_sign_model');

			// 开始事务
			$this->activity_sign_model->db->trans_start();

			//将该用户支付状态修改为支付成功
			if ($uid==0) {
				$this->activity_sign_model->update(array("pay_state"=>2, "order_no"=>$order_no), array("uid"=>$uid, "aid"=>$id_1, "phone"=>$id_2));
			} else {
				$this->activity_sign_model->update(array("pay_state"=>2, "order_no"=>$order_no), array("uid"=>$uid, "aid"=>$id_1));
			}

			if ($this->activity_sign_model->db->affected_rows() > 0) {
				//给用户推送，如果是没注册的用户，则发送短信
				//首先获取活动名称
				$activity = $this->activity_model->get_row_by_id($id_1, "aid, name, start_time, address");
				$this->load->helper('date');
				$activity['start_time'] = get_str_from_time($activity['start_time']);
				//然后获取用户报名信息
				$user = $this->activity_sign_model->get_one(array("uid" => $uid, "aid" => $id_1, "phone"=>$id_2), FALSE, "custom_input");
				$user = (array)json_decode($user['custom_input']);
				if ($uid == 0) {
					//然后发送短信
					$this->load->library('sms');
					$this->sms->send_activity_success_message($user['手机'], $activity['name']);
				} else {
					//然后进行消息推送
					// 构建消息内容
					$chatid = '-' . $uid;
					$content = "您已报名“" . $activity['name'] . "”活动，别忘记按时参加哦";
					$weixin_param['type'] = WEIXIN_PUSH_ACTIVITY;
					$weixin_param['activity'] = $activity;
					$this->load->service('api_chat_service');
					$this->api_chat_service->chat_send(0, SERVICE_TOKEN, $chatid, 1, $content, time(), $weixin_param);

				}
			}

			// 结束事务
			$this->activity_sign_model->db->trans_complete();

			// 如果事务失败，返回错误
			if ($this->activity_sign_model->db->trans_status() === FALSE) {
				$output['state'] = FALSE;
				$output['error'] = '数据库错误';
				return $output;
			}

			if ($uid!=0){
				//添加统计数据
				$this->load->model('user_total_model');
				$user_total = array(
					'uid' => $uid,
					'type' => 34,
					'content' => $id_1,
					'create_time' => time(),
					'update_time' => time(),
				);
				$this->user_total_model->insert($user_total);
			}
		}
		//如果是服务支付
		else if ($type == ORDER_TYPE_SERVICE) {
			// 需要有合法的服务id
			if (!is_id($id_1)) {
				$output['state'] = FALSE;
				$output['error'] = '服务id不合法';
				return $output;
			}


			// 开始事务
			$this->load->model('service_sign_model');
			$this->service_sign_model->db->trans_start();

			//如果是恋爱委托
			if ($id_1== LOVE_SERVICE_TYPE_ENTRUST) {
				//插入数据库该用户进行恋爱委托
				$data['sid'] = $id_1;
				$data['uid'] = $uid;
				$data['state'] = 1;
				$data['create_time'] = time();
				$data['update_time'] = time();
				$data['amount'] = $id_2;
				$data['order_no'] = $order_no;

				//进行消息推送
				if ($this->service_sign_model->insert_with_unique_key($data)) {
					// 构建消息内容
					$chatid = '-' . $uid;
					$content = "您已购买“恋爱委托”服务，24小时内将有客服与您微信联系哦";
					$weixin_param['type'] = WEIXIN_PUSH_SERVICE_ENTRUST;
					$this->load->service('api_chat_service');
					$this->api_chat_service->chat_send(0, SERVICE_TOKEN, $chatid, 1, $content, time(), $weixin_param);

					// 如果是现网，发送短信给运营，测试网就不发了，容易混掉还打扰运营
					if (!DEBUG) {
						$phone  = array("13816094509", "13656719138");
						foreach ($phone as $row) {
							$this->load->library("sms");
							$this->sms->send_love_entrust_message($row, $uid);
						}
					}
				}
			}
			//如果是清风
			elseif($id_1 == LOVE_SERVICE_TYPE_QINGFENG){

				$this->load->model('pingpp_order_model');
				$state = $this->pingpp_order_model->get_one(array('uid'=>$uid,'id_1'=>LOVE_SERVICE_TYPE_QINGFENG,'id_2'=>0,'type'=>ORDER_TYPE_SERVICE,'state'=>2),false,'state');


				//如果支付成功则修改表的状态,并发送推送消息
				if($state['state'] == 2){
					if ($this->service_sign_model->update(array('state'=>2, "order_no"=>$order_no),array('sid'=>LOVE_SERVICE_TYPE_QINGFENG,'uid'=>$uid))) {
						// 构建消息内容
						$chatid = '-' . $uid;
						$content = "你已成功购买形象提升服务，12-48小时内将会有客服与您沟通确认";
						$weixin_param['type'] = WEIXIN_PUSH_SERVICE_QINGFENG;
						$this->load->service('api_chat_service');
						$this->api_chat_service->chat_send(0, SERVICE_TOKEN, $chatid, 1, $content, time(), $weixin_param);
					}
				}
			}//如果是模拟约会
			elseif($id_1 == LOVE_SERVICE_TYPE_DATING_SIMULATION){
				$this->load->model('pingpp_order_model');
				$state = $this->pingpp_order_model->get_one(array('uid'=>$uid,'id_1'=>LOVE_SERVICE_TYPE_DATING_SIMULATION,'id_2'=>0,'type'=>ORDER_TYPE_SERVICE,'state'=>2),false,'state');

				//如果支付成功则修改表的状态,并发送推送消息
				if($state['state'] == 2){
					if ($this->service_sign_model->update(array('state'=>2, "order_no"=>$order_no),array('sid'=>LOVE_SERVICE_TYPE_DATING_SIMULATION,'uid'=>$uid))) {
						// 构建消息内容
						$chatid = '-' . $uid;
						$content = "你已成功购买模拟约会服务，12-48小时内将会有客服与您沟通确认";
						$weixin_param['type'] = WEIXIN_PUSH_SERVICE_DATING;
						$this->load->service('api_chat_service');
						$this->api_chat_service->chat_send(0, SERVICE_TOKEN, $chatid, 1, $content, time(), $weixin_param);
					}
				}
			}
			//如果是高端推荐服务
			else if ($id_1 == LOVE_SERVICE_TYPE_TOP_SERVICE) {
				//插入数据库该用户进行高端服务
				$data['sid'] = $id_1;
				$data['uid'] = $uid;
				$data['state'] = 2;
				$data['create_time'] = time();
				$data['update_time'] = time();
				$data['amount'] = $id_2;
				$data['order_no'] = $order_no;

				//进行消息推送
				if ($this->service_sign_model->insert_with_unique_key($data)) {
					// 构建消息内容
					$chatid = '-' . $uid;
					$content = "您已购买“高端推荐”服务，24小时内将有客服与您微信联系哦";
					$weixin_param['type'] = WEIXIN_PUSH_SERVICE_TOP;
					$this->load->service('api_chat_service');
					$this->api_chat_service->chat_send(0, SERVICE_TOKEN, $chatid, 1, $content, time(), $weixin_param);
				}
			}

			// 结束事务
			$this->service_sign_model->db->trans_complete();

			// 如果事务失败，返回错误
			if ($this->service_sign_model->db->trans_status() === FALSE) {
				$output['state'] = FALSE;
				$output['error'] = '数据库错误';
				return $output;
			}
		}
		//如果是微信红包
		else if ($type == ORDER_TYPE_WEIXIN_CASH) {

		}
		else {
			$output['state'] = FALSE;
			$output['error'] = '类型不合法';
			return $output;
		}

		$output['state'] = TRUE;
		return $output;
	}

	//获取渠道对应的字符串
	public function get_channel_str($channel){
		// 将渠道转换为字符串
		switch ($channel) {
			// 支付宝手机支付
			case 1:
				$channel_str = 'alipay';
				break;
			// 支付宝手机网页支付
			case 2:
				$channel_str = 'alipay_wap';
				break;
			// 支付宝扫码支付
			case 3:
				$channel_str = 'alipay_qr';
				break;
			// 支付宝 PC 网页支付
			case 4:
				$channel_str = 'alipay_pc_direct';
				break;
			// 微信支付
			case 11:
				$channel_str = 'wx';
				break;
			// 微信公众账号支付
			case 12:
				$channel_str = 'wx_pub';
				break;
			// 微信公众账号扫码支付
			case 13:
				$channel_str = 'wx_pub_qr';
				break;
			// 抵价券支付
			case 14:
				$channel_str = 'coupon_activity';
				break;
			default :
				$channel_str = '';
				break;
		}

		return $channel_str;
	}

	/**
	 * 返还未支付订单使用的抵价券 - 只返还当前活动使用的低价卷
	 * @param int	$uid	用户id
	 * @param int	$aid	活动id
	 */
	public function coupon_activity_back($uid, $aid){
		$this->load->model('pingpp_order_model');
		$this->load->model('coupon_activity_model');
		//已存在订单直接返还抵价卷
		$pingpp_order = $this->pingpp_order_model->get_one(array('uid' => $uid, 'id_1' => $aid, 'type' => ORDER_TYPE_ACTIVITY, 'state' => ORDER_STATE_CALL));
		if ($pingpp_order) {
			$remark = $pingpp_order['remark'];
			$remark = unserialize($remark);
			if (isset($remark['use_coupon_activity']['ca_id'])) {
				$this->load->model('coupon_activity_model');
				$update = array(
					'state' => 0,
					'amount_use' => '-=' . $remark['use_coupon_activity']['amount_use'],
					'update_time' => time(),
				);
				$where = array(
					'ca_id' => $remark['use_coupon_activity']['ca_id']
				);
				$this->coupon_activity_model->update($update, $where);
			}

			// 取消订单
			$update = array('state' => ORDER_STATE_CLEAN);
			$upwhere = array('order_no' => $pingpp_order['order_no']);
			$this->pingpp_order_model->update($update, $upwhere);
		}
	}


	/**
	 * 对订单进行退款
	 * @param  array		$data		订单信息，包含order_no（订单号）、amount（金额，以分为单位）
	 * @return array					处理后的数据，用来返回
	 */

	public function refund($data){

		if(!is_array($data)){
			$output['state'] = false;
			$output['error'] = '数据格式错误';
			return $output;
		}

		if(empty($data)){

			$output['state'] = false;
			$output['error'] = '数据不能为空';
			return $output;
		}

		$this->load->model('pingpp_order_model');

		//查询ping++ 订单号
		$pingpp = $this->pingpp_order_model->get_one(array('order_no'=>$data['order_no'],'state'=>array(ORDER_STATE_SUCCESS, ORDER_STATE_FAILED, ORDER_STATE_REFUND_PROCESSING)),false,'state,pingpp_id,channel,update_time');

		//判断是否处在可退款时间范围内
		$time = time() - $pingpp['update_time'];
		$day = $time / 86400;

		//判断是否为空
		if(empty($pingpp)){
			$output['state'] = false;
			$output['error'] = '订单查询信息为空！';
			return $output;
		}
		//判断是否是支付宝支付
		elseif($pingpp['channel'] == 1 || $pingpp['channel'] == 2 || $pingpp['channel'] == 3 || $pingpp['channel'] == 4 )
		{
			//判断如果支付宝大于3个月则无法退款
			if($day >= 60){
				$output['state'] = false;
				$output['error'] = '支付宝支付大于3个月无法退款';
				return $output;
			}
		}
		//判断微信支付
		elseif($pingpp['channel'] == 11 || $pingpp['channel'] == 12 || $pingpp['channel'] == 13 ){
			//判断如果支付宝大于3个月则无法退款
			if($day >= 360){
				$output['state'] = false;
				$output['error'] = '微信支付超过一年无法退款';
				return $output;
			}
		}
		elseif($pingpp['channel'] == 14){

			$output['state'] = false;
			$output['error'] = '该订单用抵用券支付,无法退款';
			return $output;
		}
		else{
			//判断如果支付宝大于3个月则无法退款
			if($day >= 30){
				$output['state'] = false;
				$output['error'] = '支付时间超过一个月无法退款';
				return $output;
			}
		}


		// 开始请求Ping++接口
		require_once(dirname(__FILE__) . '/../third_party/pingpp/init.php');
		\Pingpp\Pingpp::setApiKey(config_item('pingpp_secretkey'));


		try {

			$ch = \Pingpp\Charge::retrieve($pingpp['pingpp_id']);
			$re = $ch->refunds->create(
				array(
					'amount' => $data['amount'],
					'description' => '【简爱】活动退款金额'
				)
			);


		} catch (\Pingpp\Error\Base $e) {


			$error = json_decode($e->getHttpBody(), TRUE);

			// 写日志
			$this->log->write_log_json($error, "pingpp_error");

			$output['state'] = FALSE;
			$output['error'] = $error['error']['message'];
			return $output;

		}



		$result = json_decode($re,true);

		// 修改订单状态为退款中
		$this->pingpp_order_model->update(array('state'=>ORDER_STATE_REFUND_PROCESSING,'refund_id'=>$result['id']), array('order_no'=>$data['order_no'],'state'=>$pingpp['state']));

		//绑定支付状态
		$result['state'] = true;

		return $result;


	}


	/**
	 * 退款订单信息状态查询
	 * @param  array		$order_no		订单信息，order_no（订单号）
	 * @return array					处理后的数据，用来返回
	 */

	public function querys_refund($order_no)
	{

		// 开始请求Ping++接口
		require_once(dirname(__FILE__) . '/../third_party/pingpp/init.php');
		\Pingpp\Pingpp::setApiKey(config_item('pingpp_secretkey'));


		$this->load->model('pingpp_order_model');

		//查询ping++ 订单号
		$pingpp = $this->pingpp_order_model->get_one(array('order_no' => $order_no, 'state' => array(ORDER_STATE_SUCCESS, ORDER_STATE_FAILED, ORDER_STATE_REFUND_PROCESSING)), false, 'pingpp_id,refund_id');

		if (empty($pingpp)) {

			$output['state'] = FALSE;
			$output['error'] = '订单信息为空';
			return $output;
		}

		try {

			$ch = \Pingpp\Charge::retrieve($pingpp['pingpp_id']);
			$refund = $ch->refunds->retrieve($pingpp['refund_id']);

		} catch (\Pingpp\Error\Base $e) {

			$error = json_decode($e->getHttpBody(), TRUE);

			// 写日志
			$this->log->write_log_json($error, "pingpp_error");

			$output['state'] = FALSE;
			$output['error'] = $error['error']['message'];

			return $output;
		}


		$result = json_decode($refund, TRUE);
		$output['error'] = '未知错误。';

		//如果订单成功
		if ($result['succeed'] == true && $refund['status'] == "succeeded") {
			//将订单改成退款成功
			if (!$this->pingpp_order_model->update(array('state' => ORDER_STATE_REFUND_SUCCES), array('order_no' => $order_no))) {
				$output['error'] = '退款成功,修改订单状态失败';
			}
			else {
				$output['error'] = '退款成功,修改订单状态成功';
			}
		} //如果订单不处于处理中，则为失败
		elseif ($result['succeed'] == false && $refund['status'] == "failed") {
			//如果退款失败将订单改为原始状态支付成功
			if (!$this->pingpp_order_model->update(array('state' => ORDER_STATE_SUCCESS), array('order_no' => $order_no))) {
				$output['error'] = '退款失败,且修改订单状态失败。' . $refund['failure_msg'];
			}
			else {
				$output['error'] = '退款失败,修改订单状态成功。' . $refund['failure_msg'];
			}
		} //如果订单处于处理中
		elseif ($result['succeed'] == false && $refund['status'] == "pending") {
			$output['error'] = '订单处理中。';
		}

		return $output;

	}
}