<?php
/**
 * 会员相关接口服务，如查看套餐详情、获取消费券列表、查看消费券详情等
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/3/9
 * Time: 11:14
 */

class Api_vip_service extends Api_Service {

	/**
	 *
	 * 接口名称：get_vip_config
	 * 接口功能：用户获取会员相关的配置参数
	 * 接口编号：0701
	 * 接口加密名称：HBDgefGhLNLFg59n
	 *
	 * @param array		$data				请求参数
	 * param int		$uid				用户id
	 * param string		$token				用户token
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['config']: 调用成功时返回，表示会员的配置参数，为一个数组，每个数组元素包含id、days、price、coupon_num
	 * $output['coupon_num']: 调用成功时返回，表示该用户拥有的体验券数量
	 *
	 */
	public function get_vip_config($data)
	{
//		$api_code = '0701';

		if(!isset($data['uid']) || !isset($data['token'])){
			$output['state'] = FALSE;
			$output['error'] = "缺少必要参数";
			$output['ecode'] = "0000012";
			return $output;
		}
		$version = isset($data['version']) ? $data['version'] : 0;

		// 用户鉴权
		$result = $this->user_model->check_authority($data['uid'], $data['token'], TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		$user = $result['user'];

		// 获取会员的配置参数
		$this->load->service('vip_service');
		$output['config'] = $this->vip_service->get_vip_config($user, '', $version);

		// 获取该用户的体验券数量
		$this->load->model('coupon_model');
		$current_date = date('Y-m-d');
		$output['coupon_num'] = $this->coupon_model->count(array('uid'=>$data['uid'], 'menu_id'=>FREE_COUPON_MENU_ID, 'invite_flag'=>0, 'consume_flag'=>0, 'end_date >='=>$current_date));

		// 获取该用户的抵价券数量
		$this->load->model('coupon_activity_model');
		$output['coupon_activity_num'] = $this->coupon_activity_model->count(array('uid'=>$data['uid'], 'state'=>0));

		// 优惠活动配置
		$output['vip_activity_time'] = strtotime(VIP_ACTIVITY_TIME);
		$output['vip_activity_register'] = VIP_ACTIVITY_REGISTER;

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：request_buy_vip
	 * 接口功能：用户请求购买会员服务
	 * 接口编号：0702
	 * 接口加密名称：UqZpsZ7eF59Hv3fe
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$id				vip服务对应的id
	 * @param int		$channel		渠道代码，具体见对应表
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['order_no']: 当使用第三方支付时，成功生成订单后会返回该值，为19位的订单号
	 * $output['json']: 当使用第三方支付时，成功生成订单后会返回该值，为Ping++返回给服务器的信息，供客户端使用
	 *
	 */
	public function request_buy_vip($uid, $token, $id, $channel)
	{
		$api_code = '0702';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token, TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		$user = $result['user'];

		// 首先获取会员配置 未上线时取老版本配置
		$id = (int)$id;
		$this->load->service('vip_service');
		$config_parameters = $this->vip_service->get_vip_config($user, $id, DEFAULT_VERSION);

		// 如果获取失败
		if (!isset($config_parameters['price'])) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "id不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 判断渠道是否合法
		if (!is_id($channel)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "渠道不合法";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 请求Ping++，生成订单
		$this->load->service('pay_service');
		$remark['config'] = $config_parameters;
		$remark = serialize($remark);	//保存购物配置
		//不是新配置就走老配置..
		if (isset($config_parameters['coupon']['act']['num'])) {
			$result = $this->pay_service->add_pingpp_order($uid, ORDER_TYPE_VIP, $config_parameters['days'], $config_parameters['coupon']['act']['num'], $config_parameters['price'], $channel, $remark);
		} else {
			$result = $this->pay_service->add_pingpp_order($uid, ORDER_TYPE_VIP, $config_parameters['days'], $config_parameters['coupon_num'], $config_parameters['price'], $channel, $remark);
		}

		// 如果请求支付时发生错误
		if ($result['state'] == FALSE) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = $result['error'];
			$output['ecode'] = $api_code . "003";
			return $output;
		}

		// 如果成功了，构建返回值
		$output['order_no'] = $result['pingpp_order']['order_no'];
		$output['json'] = $result['json'];

		$output['state'] = TRUE;
		return $output;
	}

	/**
	 *
	 * 接口名称：get_free_coupon_config
	 * 接口功能：用户获取体验券相关的配置参数
	 * 接口编号：0703
	 * 接口加密名称：TMKwQs0fduXGKx4k
	 *
	 * @param int		$uid				用户id
	 * @param string	$token				用户token
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['config']: 调用成功时返回，表示会员的配置参数，为一个数组，每个数组元素包含id、price、coupon_num
	 * $output['coupon_num']: 调用成功时返回，表示该用户拥有的体验券数量
	 * $output['allow_free_coupon']: 表示是否允许该用户购买体验券，1为允许，0为不允许
	 *
	 */
	public function get_free_coupon_config($uid, $token)
	{
//		$api_code = '0703';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token, TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		$user = $result['user'];

		// 获取体验券的配置参数
		$this->load->service('vip_service');
		$output['config'] = $this->vip_service->get_free_coupon_config($uid, $user['gender']);

		// 获取该用户的体验券数量
		$this->load->model('coupon_model');
		$current_date = date('Y-m-d');
		$output['coupon_num'] = $this->coupon_model->count(array('uid'=>$uid, 'menu_id'=>FREE_COUPON_MENU_ID, 'invite_flag'=>0, 'consume_flag'=>0, 'end_date >='=>$current_date));

		// 如果用户是会员，或者是女生，那么肯定允许用户购买体验券
		if ($user['gender']==2 || $this->user_model->is_vip($user)) {
			$output['allow_free_coupon'] = 1;
		}
		// 如果不是会员，则需要进行进一步判断
		else {
			$output['allow_free_coupon'] = (int) $this->coupon_model->allow_free_coupon($uid, FALSE);
		}

		$output['state'] = TRUE;
		return $output;
	}

	/**
	 *
	 * 接口名称：request_buy_free_coupon
	 * 接口功能：用户请求购买体验券
	 * 接口编号：0704
	 * 接口加密名称：cFKc0S49W36NxhI9
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$id				体验券对应的id
	 * @param int		$channel		渠道代码，具体见对应表
	 * @param int		$is_discount	是否优惠购买,1是0不是
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['order_no']: 当使用第三方支付时，成功生成订单后会返回该值，为19位的订单号
	 * $output['json']: 当使用第三方支付时，成功生成订单后会返回该值，为Ping++返回给服务器的信息，供客户端使用
	 *
	 */
	public function request_buy_free_coupon($uid, $token, $id, $channel, $is_discount = 0)
	{
		$api_code = '0704';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token, TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		$user = $result['user'];

		// 首先获取体验券配置
		$id = (int)$id;
		$this->load->service('vip_service');
		$config_parameters = $this->vip_service->get_free_coupon_config($uid, $user['gender'], $id);

		// 如果获取失败
		if (!isset($config_parameters['price'])) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "id不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 如果是男生，需要进行进一步的判断
		if ($user['gender'] != 2) {
			// 如果只买一张，那么即使不是会员，只要是第一次购买，就可以，即只有用户既不是会员，且不是第一次购买的时候才返回错误
			if ($config_parameters['coupon_num'] == 1) {
				$this->load->model('coupon_model');
				if (!$this->user_model->is_vip($user) && !$this->coupon_model->allow_free_coupon($uid, FALSE)) {
					// 输出错误
					$output['state'] = FALSE;
					$output['error'] = "您没有会员权限";
					$output['ecode'] = "0000011";
					return $output;
				}
			}
			// 如果买多张，那么一定需要是会员
			else {
				if (!$this->user_model->is_vip($user)) {
					// 输出错误
					$output['state'] = FALSE;
					$output['error'] = "您没有会员权限";
					$output['ecode'] = "0000011";
					return $output;
				}
			}
		}

		// 判断渠道是否合法
		if (!is_id($channel)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "渠道不合法";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 请求Ping++，生成订单
		$this->load->service('pay_service');
		$this->load->model('pingpp_order_model');
		//首单购买体验卷失败时，有一次优惠购买的机会
		if ($is_discount == 1 && $this->pingpp_order_model->get_is_first($uid)){
			$config_parameters['price'] = round($config_parameters['price'] / 100 * DISCOUNT_PAY, 2);
		}
		$remark['config'] = $config_parameters;
		$remark = serialize($remark);	//保存购物配置
		$result = $this->pay_service->add_pingpp_order($uid, ORDER_TYPE_FREE_COUPON, $config_parameters['coupon_num'], 0, $config_parameters['price'], $channel, $remark);
		// 如果请求支付时发生错误
		if ($result['state'] == FALSE) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = $result['error'];
			$output['ecode'] = $api_code . "003";
			return $output;
		}

		// 如果成功了，构建返回值
		$output['order_no'] = $result['pingpp_order']['order_no'];
		$output['json'] = $result['json'];

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 * 接口名称：get_search_list
	 * 接口功能：获取搜索列表
	 * 接口编号：0705
	 * 接口加密名称：0OxAWQn4P5uziRaT
	 *
	 * @param 	array	$data			请求参数
	 * param	int		$uid			用户id
	 * param	string	$token			用户token
	 * param	int		$last_time	默认0，type为1时传上次取数据最后一个的last_time，为2时传上次取数据第一个的last_time，格式为14位数字，纳秒级，取前十位就是秒了
	 * param	int		$type		默认1，1为历史数据，2为新增数据
	 * param	int		$target_age_min	目标年龄最小值
	 * param	int		$target_age_max	目标年龄最大值
	 * param	int		$target_education	目标学历
	 * param	int		$target_height_min	目标身高最小值
	 * param	int		$target_height_max	目标身高最大值
	 * param	int		$target_salary	目标收入
	 * param	int		$target_look	目标外貌
	 * param	int		$is_bi			是否双向匹配
	 *
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['search']: 搜索列表
	 *
	 */
	public function get_search_list($data){
//		$api_code = '0705';
		$this->load->model('pingpp_order_model');
		$this->load->helper('date');

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
		$user = $result['user'];

		//非高级会员直接给杭州的
		if (!$this->user_model->is_vip($user) || $user['vip_lv'] == 0) {
			//固定取杭州的
			$user['province'] = 33;
			$user['city'] = 1;
		}

		$search_list = $this->user_model->get_search_list($user, $data);

		//返回结果
		$output['state'] = TRUE;
		$this->load->library('send');
		$output['search'] = $this->send->send($search_list, 'search');
		//非高级会员才返回
		if (!$this->user_model->is_vip($user) || $user['vip_lv'] == 0){
			$this->user_model->set_table_name('p_userdetail');
			$count_city = array(
				'gender' => ($user['gender'] == 1 ? 2 : 1),
				'province' => 33,
				'city' => 1
			);
			$total = $this->user_model->count($count_city);	//参与匹配的异性人数 即数据库中的所有异性
			$output['total'] = $this->user_model->get_search_list($user, $data, true);
			$output['percentage'] = round($output['total'] / $total * 100, 2);		//匹配到的人数占比 即匹配到的人数/参与匹配的异性人数
		}

		return $output;

	}

	/**
	 * 接口名称：get_order_list
	 * 接口功能：获取订单列表
	 * 接口编号：0706
	 * 接口加密名称：VJuLet1t4zQhQ5QQ
	 *
	 * @param 	array	$data			请求参数
	 * param	int		$uid			用户id
	 * param	string	$token			用户token
	 * param	int		$update_time	默认0，type为1时传上次取数据最后一个的update_time，为2时传上次取数据第一个的update_time，格式为10位时间戳
	 * param	int		$type		默认1，1为历史数据，2为新增数据
	 *
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['order']: 订单列表
	 *
	 */
	public function get_order_list($data){
//		$api_code = '0706';
		$this->load->model('pingpp_order_model');
		$this->load->helper('date');

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
		$user = $result['user'];

		$update_time = isset($data['update_time']) ? $data['update_time'] : 0;
		$type = isset($data['type']) ? $data['type'] : 1;

		$where = array('uid'=>$data['uid'], 'state' => 2, 'type <>' => ORDER_TYPE_WEIXIN_CASH);

		//排序
		if($type == 2){
			$order = 'update_time ASC';
		} else {
			$order = 'update_time DESC';
		}

		if($update_time != 0){
			$flag_where = $type == 2 ? 'update_time >= ' : 'update_time <= ';
			$where[$flag_where] = $update_time;
		}

		$order_list = $this->pingpp_order_model->select($where, FALSE, '*', MAX_ORDER_PER_GET, $order);

		$output['order'] = array();
		foreach ($order_list as $key=>$val){
			$flag_arr = array(
				'type' => $val['type'],
				'order_no' => $val['order_no'],
				'amount' => $val['amount'],
				'update_time' => $val['update_time']
			);
			$info = @unserialize($val['remark']);
			switch ($val['type']) {
				case ORDER_TYPE_MENU_FOR_SEND:
				case ORDER_TYPE_MENU_FOR_RECIEVE:
					//邀请类
					$flag_arr['name'] = '邀请（' . $info['menu']['topic'] . '）';
					break;
				case ORDER_TYPE_VIP:
					//会员类
					$flag_arr['name'] = '会员（' . ($val['id_1'] == 365 ? '1年' : ($val['id_1']/30).'个月') . '）';
					break;
				case ORDER_TYPE_FREE_COUPON:
					//免单劵...
					if ($user['gender'] == 1){
						$flag_arr['name'] = '体验券（' . $val['id_1'] . '张）';
					} else {
						$flag_arr['name'] = '心动券（' . $val['id_1'] . '张）';
					}
					break;
				case ORDER_TYPE_ACTIVITY:
					//活动类...
					$flag_arr['name'] = '活动（' . $info['activity']['name'] . '）';
					$this->load->model('activity_model');
					$token = $this->activity_model->encrypt($val['id_1'], $data['uid']);
					$flag_arr['url'] = config_item('base_url').'home/activity?aid='.$val['id_1'].'&uid='.$data['uid']."&token=".$token;
					break;
				case ORDER_TYPE_SERVICE:
					//服务类
					$flag_arr['name'] = '服务（' . $info['service']['name'] . '）';
					$flag_arr['url'] = $this->get_url($val['id_1'], $data['uid']);
					break;
				default:
					$flag_arr['name'] = '其他消费';
					break;
			}
			$output['order'][] = $flag_arr;
		}

		// 支付成功订单数量
		$output['order_num'] = $this->pingpp_order_model->count(array('uid'=>$data['uid'], 'state'=>2,  'type <>' => ORDER_TYPE_WEIXIN_CASH));

		//返回结果
		$output['state'] = TRUE;
		return $output;

	}

	//获取相应服务的url地址
	public function get_url($id, $uid){
		$url = '';
		switch ($id) {
			case 1:
				$url = config_item('base_url').'home/love_entrust';
				break;
			case 2:
				$url = config_item('base_url').'home/qingfeng';
				break;
			case 3:
				$url = config_item('base_url').'home/dating_simulation';
				break;
			default:
				break;
		}
		$this->load->model("ad_model");
		$token = $this->ad_model->encrypt($uid);
		$url .= '?uid='.$uid."&token=".$token;;

		return $url;
	}

}