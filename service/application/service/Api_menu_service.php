<?php
/**
 * 套餐消费接口服务，如查看套餐详情、获取消费券列表、查看消费券详情、查看单品券等
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/7
 * Time: 21:19
 */

class Api_menu_service extends Api_Service {

	/**
	 *
	 * 接口名称：get_menu_detail
	 * 接口功能：用户获取套餐详情
	 * 接口编号：0601
	 * 接口加密名称：Jm6Z3NlsTbSRtZZl
	 *
	 * @param int		$uid				用户id
	 * @param string	$token				用户token
	 * @param int		$menu_id			要查看的套餐的uid
	 * @param int		$request_coupon		为1表示要获取该用户是否有该套餐的可用消费券，为0表示不用获取
	 * @param string	$key				本次秘钥，长度必须为16位，由大小写字母和随机数生成
	 * @param string	$key_pw				密码，由to_uid和key生成，全部小写
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['menu']: 调用成功时返回，包含了套餐的详情
	 * $output['coupon_num']: 调用成功且$request_coupon为1时返回，表示该用户拥有的该套餐的可用消费券的数量
	 *
	 */
	public function get_menu_detail($uid, $token, $menu_id, $request_coupon, $key, $key_pw)
	{
		$api_code = '0601';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}

//		$user = $result['user'];

		// 检查menu_uid是否合法
		if (!is_id($menu_id)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "套餐id不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}


		// 检查秘钥和密码是否合法
		$result = $this->check_key_and_pw($key, $key_pw, array($menu_id, $key), array('9MwGcOrH3ZhCoW2f','2bZxBnommsY8dbg7','1759Qogm3Uy7aFaV'));
		if ($result['state'] == FALSE) {
			return $result;
		}

		// 获取要查看的套餐的信息
		$this->load->model('menu_model');
		$menu = $this->menu_model->get_row_by_id($menu_id);

		if (empty($menu)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "找不到该套餐";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 返回套餐信息
		$output['menu'] = $this->menu_model->send_for_detail($menu);

		// 如果要获取该用户是否有该套餐的可用消费券
		if ($request_coupon) {
			$this->load->model('coupon_model');
			$current_date = date('Y-m-d');
			$output['coupon_num'] = $this->coupon_model->count(array('uid'=>$uid, 'menu_id'=>$menu_id, 'invite_flag'=>0, 'consume_flag'=>0, 'end_date >='=>$current_date));
		}

		$output['state'] = TRUE;
		return $output;
	}

	/**
	 *
	 * 接口名称：get_coupon_list
	 * 接口功能：用户获取消费券列表
	 * 接口编号：0602
	 * 接口加密名称：ytFsq8Q3lXxVHO9i
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$type			要获取的数据类型，1为获取可用消费券，2为获取不可用消费券
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['coupon_list']: 调用成功时返回，包含了用户的消费券记录:
	 * 		可用消费券包括以下字段：消费券的coupon_id，套餐的menu_id，套餐主题topic，套餐主图image
	 * 		不可用消费券包括以下字段：消费券的coupon_id，是否用于邀请invite_flag，是否消费consume_flag，过期时间end_date，套餐的menu_id，邀请的状态state，套餐主题topic，对方头像avatar
	 *
	 */
	public function get_coupon_list($uid, $token, $type)
	{
		$api_code = '0602';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token, TRUE);
		if ($result['state'] == FALSE) {
			return $result;
		}

		$user = $result['user'];

		// 判断类型是否合法
		$type = (int)$type;
		if ($type!=1 && $type!=2) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "类型不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 返回消费券记录
		$this->load->model('coupon_model');
		$output['coupon_list'] = $this->coupon_model->get_history_by_uid($uid, $user['gender'], $type);

		if ($output['coupon_list'] == NULL) {
			$output['coupon_list'] = array();
		}

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：get_coupon_detail
	 * 接口功能：用户获取消费券详情
	 * 接口编号：0603
	 * 接口加密名称：LAy8XbswN9oRHip8
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$id				要查看id
	 * @param int		$type			id类型，为1表示用消费券的coupon_id获取，为2表示用邀请的iid获取
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['coupon']: 调用成功时返回，包含了消费券的详情：主键coupon_id, 券码code, 有效期start_date和end_date，链接url
	 * 					其中，如果是自生成券，那么url用来生成二维码；
	 * 					如果是单品券，无url字段
	 * 					如果是图片套餐，url为数组，数组的每一个元素为一张图片的链接
	 * $output['menu_single']:调用成功时且套餐为单品券时返回，返回有效可兑换的单品券类别的详情：单品券类别id：msid，单品券类别名：name，单品券类别图片image;
	 * $output['consumed_menu_single']:调用成功时且套餐为单品券时返回，返回已经兑换的单品券详情：单品券类别名：name，单品券类别图片image，单品券地址url, 单品券是否过期状态expire_flag;
	 * $output['unavailable_menu_single']:调用成功时且套餐为单品券时返回，返回已经有效不可兑换的单品券详情：单品券类别名：name 单品券是否过期状态expire_flag;
	 *
	 */
	public function get_coupon_detail($uid, $token, $id, $type)
	{
		$api_code = '0603';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}

//		$user = $result['user'];

		// 检查id是否合法
		if (!is_id($id)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "id不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 判断类型是否合法
		$type = (int)$type;
		if ($type!=1 && $type!=2) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "类型不合法";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 如果是用邀请id获取
		if ($type == 2) {
			// 先获取该邀请
			$this->load->model('invite_model');
			$invite = $this->invite_model->get_row_by_id($id, 'uid_1, uid_2, coupon_id');

			// 如果找不到邀请
			if (empty($invite)) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "邀请不合法";
				$output['ecode'] = $api_code."003";
				return $output;
			}

			// 如果邀请对应的uid或者消费券id不对
			if (!is_id($invite['coupon_id']) || ($invite['uid_1']!=$uid && $invite['uid_2']!=$uid)) {
				// 输出错误
				$output['state'] = FALSE;
				$output['error'] = "邀请不合法";
				$output['ecode'] = $api_code."003";
				return $output;
			}

			$coupon_id = $invite['coupon_id'];
		}
		// 如果是用消费券id获取
		else {
			$coupon_id = $id;
		}

		// 获取消费券信息
		$this->load->model('coupon_model');
		$coupon = $this->coupon_model->get_row_by_id($coupon_id, 'menu_id, uid, coupon_id, code, start_date, end_date');
		// 如果找不到消费券
		if (empty($coupon)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "消费券不合法";
			$output['ecode'] = $api_code."004";
			return $output;
		}

		// 如果购买者不是该用户
		if ($coupon['uid'] != $uid) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "您没有权限";
			$output['ecode'] = $api_code."005";
			return $output;
		}

		//查询该单品券套餐类型
		$this->load->model('menu_model');
		$menu = $this->menu_model->get_row_by_id($coupon['menu_id'], 'menu_id, type');

		//如果套餐类型为单品券套餐
		if ($menu['type'] == 1) {
			$output['menu_single'] = array();
			$output['consumed_menu_single'] = array();
			$output['unavailable_menu_single'] = array();

			//首先获取可用的单品券类别
			$this->load->model('menu_single_model');
			$menu_single = $this->menu_single_model->get_available_menu_single($menu['menu_id']);

			//循环赋值给output
			foreach($menu_single as $ms) {
				$output['menu_single'][] = $this->menu_single_model->send($ms);
			}

			//再获取缺货的单品券类别
			$unavailable_menu_single = $this->menu_single_model->get_unavailable_menu_single($menu['menu_id']);

			//循环赋值给output
			foreach($unavailable_menu_single as $ums) {
				$output['unavailable_menu_single'][] = $this->menu_single_model->send($ums);
			}

			//再获取已兑换的单品券
			$this->load->model('coupon_single_model');
			$consumed_menu_single = $this->coupon_single_model->get_consumed_coupon_single($coupon['coupon_id']);

			//循环赋值给output
			foreach($consumed_menu_single as $consumed_menu) {
				$output['consumed_menu_single'][] = $this->coupon_single_model->send($consumed_menu);
			}

		}

		// 返回消费券信息
		$output['coupon'] = $this->coupon_model->send_for_detail($coupon, $menu['type']);

		$output['state'] = TRUE;
		return $output;
	}

	/**
	 *
	 * 接口名称：choose_menu_single
	 * 接口功能：用户选择使用单品券
	 * 接口编号：0604
	 * 接口加密名称：DgKdKAGX5URYq26M
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$coupon_id		消费券id
	 * @param int		$msid			单品券类别id
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['url']: 调用成功时返回，返回单品券的url
	 *
	 */
	public function choose_menu_single($uid, $token, $coupon_id, $msid)
	{
		$api_code = '0604';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token, FALSE);
		if ($result['state'] == FALSE) {
			return $result;
		}

//		$user = $result['user'];

		// 检查id是否合法
		if (!is_id($msid)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "单品券类别id不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 获取消费券信息
		$this->load->model('coupon_model');
		$coupon = $this->coupon_model->get_row_by_id($coupon_id);
		// 如果找不到消费券
		if (empty($coupon)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "消费券不合法";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 如果购买者不是该用户
		if ($coupon['uid'] != $uid) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "您没有权限";
			$output['ecode'] = $api_code."003";
			return $output;
		}

		//判断该消费券已经兑换几张单品券了
		$this->load->model('coupon_single_model');
		if ($this->coupon_single_model->count(array('coupon_id'=>$coupon_id)) >= 2) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "您最多只能使用2张电子券";
			$output['ecode'] = $api_code."004";
			return $output;
		}

		//判断该消费券是否过期
		if (strtotime($coupon['end_date'] < time())) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "您的消费券已过期";
			$output['ecode'] = $api_code."005";
			return $output;
		}


		//锁定一张快要过期的单品券用户使用
		$coupon_single_url = $this->coupon_single_model->lock_by_use($coupon_id,$msid);

		// 如果绑定失败
		if ($coupon_single_url=='' || $coupon_single_url==NULL) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "该类别单品券已售罄";
			$output['ecode'] = $api_code."005";
			return $output;
		}


		$output['url'] = $coupon_single_url;
		$output['state'] = TRUE;
		return $output;
	}

	/**
	 * 接口名称：get_coupon_activity_list
	 * 接口功能：用户获取活动抵用券列表
	 * 接口编号：0605
	 * 接口加密名称：CTgBTo5kqdtwHF9T
	 *
	 * @param array		$data			请求参数
	 * param int		$uid			用户id
	 * param string		$token			用户token
	 * param int		$type			要获取的数据类型，默认0，0为可用，1为不可用
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['coupon_activity']: 活动抵用券列表
	 *
	 */
	public function get_coupon_activity_list($data){
		$this->load->model('coupon_activity_model');
		$type = isset($data['type']) ? $data['type'] : 0;

		if(!isset($data['uid']) || !isset($data['token'])){
			$output['state'] = FALSE;
			$output['error'] = "缺少必要参数";
			$output['ecode'] = "0000012";
			return $output;
		}

		// 用户鉴权
		$result = $this->user_model->check_authority($data['uid'], $data['token']);
		if ($result['state'] == FALSE) {
			return $result;
		}

		//获取用户列表
		$output['coupon_activity'] = $this->coupon_activity_model->get_list(array('uid' => $data['uid'], 'state' => $type));

		//返回结果
		$output['state'] = TRUE;

		return $output;

	}

}