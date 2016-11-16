<?php
/**
 * 会员相关服务
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/3/9
 * Time: 11:17
 */

class Vip_service extends MY_Service {


	/**
	 * 获取会员的配置信息
	 * @param int			$user	会员信息
	 * @param int			$id		会员配置的id，不为正整数时表示获取所有的配置
	 * @param int			$version		版本号
	 * @return mixed		当id为正整数时，返回指定id的配置，若指定id的配置不存在则返回NULL，当id不为正整数时，返回所有的配置
	 * 						包含：id、days、price、coupon_num等字段
	 */
	public function get_vip_config($user, $id = NULL, $version = 0)
	{

		//兼容老版本
		if ($version < VIP_ACTIVITY_VERSION) {
			$config_parameters = $this->_vip_config($version)[$user['gender']];
		} else {
			$config_parameters = $this->_vip_config($version);
			//优惠日期前或者注册未满N天享受一级优惠
			//注册超过N天，且为首次购买享受二级优惠
			//注册超过N天，已购买会员享受普通优惠
			if (VIP_ACTIVITY_TIME > date('Y-m-d') || $user['register_time'] >= strtotime('-' . (VIP_ACTIVITY_REGISTER-1) . ' day 00:00:00')) {
				$type = 1;
			} elseif ($user['vip_lv'] == 0) {
				$type = 2;
			} else {
				$type = 3;
			}
			$config_parameters = $config_parameters[$type];
		}

		$id = (int)$id;

		// 当id为正整数时
		if (is_id($id)) {
			// 如果存在对应的配置，则返回配置
			if (isset($config_parameters[$id])) {
				return $config_parameters[$id];
			}
			// 如果不存在对应的配置，则返回NULL
			else {
				return NULL;
			}
		}
		// 当id不为正整数时，返回所有的配置参数
		else {
			return array_values($config_parameters);
		}
	}

	//vip优惠配置 先放这...
	private function _vip_config($version){
		if ($version < VIP_ACTIVITY_VERSION) {
			$config_parameters[1][1] = array('id'=>1, 'days'=>30, 'price'=>9800, 'coupon_num'=>1);
			$config_parameters[1][2] = array('id'=>2, 'days'=>90, 'price'=>14800, 'coupon_num'=>2);
			$config_parameters[1][3] = array('id'=>3, 'days'=>365, 'price'=>18800, 'coupon_num'=>3);
			$config_parameters[2][1] = array('id'=>1, 'days'=>30, 'price'=>9800, 'coupon_num'=>3);
			$config_parameters[2][2] = array('id'=>2, 'days'=>90, 'price'=>14800, 'coupon_num'=>6);
			$config_parameters[2][3] = array('id'=>3, 'days'=>365, 'price'=>18800, 'coupon_num'=>10);
		} else {

			//一级优惠配置
			$config_parameters[1] = array(
				'1' => array(
					'id'=>1, 'days'=>30, 'price'=>9800,
					'coupon'=>array('act' => array('amount' => 1000, 'num' => 1)),
					'coupon_activity'=>array('act' => array('amount' => 10000, 'num' => 1), 'def' => array('amount' => 5000, 'num' => 1))
				),
				'2' => array(
					'id'=>2, 'days'=>90, 'price'=>14800,
					'coupon'=>array('act' => array('amount' => 1000, 'num' => 3), 'def' => array('amount' => 1000, 'num' => 2)),
					'coupon_activity'=>array('act' => array('amount' => 10000, 'num' => 1))
				),
				'3' => array(
					'id'=>3, 'days'=>365, 'price'=>18800,
					'coupon'=>array('act' => array('amount' => 1000, 'num' => 4), 'def' => array('amount' => 1000, 'num' => 3)),
					'coupon_activity'=>array('act' => array('amount' => 10000, 'num' => 1))
				)
			);
			//二级优惠配置
			$config_parameters[2] = array(
				'1' => array(
					'id'=>1, 'days'=>30, 'price'=>9800,
					'coupon'=>array('act' => array('amount' => 1000, 'num' => 1)),
					'coupon_activity'=>array('act' => array('amount' => 5000, 'num' => 1))
				),
				'2' => array(
					'id'=>2, 'days'=>90, 'price'=>14800,
					'coupon'=>array('act' => array('amount' => 1000, 'num' => 3), 'def' => array('amount' => 1000, 'num' => 2)),
					'coupon_activity'=>array('act' => array('amount' => 10000, 'num' => 1))
				),
				'3' => array(
					'id'=>3, 'days'=>365, 'price'=>18800,
					'coupon'=>array('act' => array('amount' => 1000, 'num' => 4), 'def' => array('amount' => 1000, 'num' => 3)),
					'coupon_activity'=>array('act' => array('amount' => 10000, 'num' => 1))
				)
			);
			//三级优惠配置
			$config_parameters[3] = array(
				'1' => array(
					'id'=>1, 'days'=>30, 'price'=>9800,
					'coupon'=>array('act' => array('amount' => 1000, 'num' => 1)),
					'coupon_activity'=>array('act' => array('amount' => 5000, 'num' => 1))
				),
				'2' => array(
					'id'=>2, 'days'=>90, 'price'=>14800,
					'coupon'=>array('act' => array('amount' => 1000, 'num' => 2)),
					'coupon_activity'=>array('act' => array('amount' => 10000, 'num' => 1))
				),
				'3' => array(
					'id'=>3, 'days'=>365, 'price'=>18800,
					'coupon'=>array('act' => array('amount' => 1000, 'num' => 3)),
					'coupon_activity'=>array('act' => array('amount' => 10000, 'num' => 1))
				)
			);
		}

		return $config_parameters;

	}


	/**
	 * 获取体验券的配置信息
	 * @param int			$uid		用户的id
	 * @param int			$gender		用户的性别
	 * @param int			$id			体验券配置的id，不为正整数时表示获取所有的配置
	 * @return mixed		当id为正整数时，返回指定id的配置，若指定id的配置不存在则返回NULL，当id不为正整数时，返回所有的配置
	 * 						包含：id、price、coupon_num等字段
	 */
	public function get_free_coupon_config($uid, $gender, $id = NULL)
	{
		$this->load->model('coupon_model');

		// 如果是男生第一次购买
		if ($gender==1 && $this->coupon_model->allow_free_coupon($uid, FALSE)) {
			$config_parameters[1] = array('id'=>1, 'price'=>100, 'coupon_num'=>1);
			$config_parameters[2] = array('id'=>2, 'price'=>4000, 'coupon_num'=>5);
			$config_parameters[3] = array('id'=>3, 'price'=>8000, 'coupon_num'=>12);
		}
		else {
			$config_parameters[1] = array('id'=>1, 'price'=>1000, 'coupon_num'=>1);
			$config_parameters[2] = array('id'=>2, 'price'=>4000, 'coupon_num'=>5);
			$config_parameters[3] = array('id'=>3, 'price'=>8000, 'coupon_num'=>12);
		}

		$id = (int)$id;

		// 当id为正整数时
		if (is_id($id)) {
			// 如果存在对应的配置，则返回配置
			if (isset($config_parameters[$id])) {
				return $config_parameters[$id];
			}
			// 如果不存在对应的配置，则返回NULL
			else {
				return NULL;
			}
		}
		// 当id不为正整数时，返回所有的配置参数
		else {
			return array_values($config_parameters);
		}
	}

	/**
	 * 获取弹框信息
	 * @param array			$user		用户信息
	 * @return mixed		返回弹框配置信息
	 * */
	public function get_bounced($user) {
		//活动时间之前 或者 活动时间之后，且注册时间未超过
		if ((time()<strtotime(VIP_ACTIVITY_TIME)) || ((time()>=strtotime(VIP_ACTIVITY_TIME) && (time()-$user['register_time'])<VIP_ACTIVITY_REGISTER*86400))) {
			$data['title'] = "98元超值会员限时购";
			if (time()<strtotime(VIP_ACTIVITY_TIME)) {
				$data['desc'] = date("m月d日", strtotime(VIP_ACTIVITY_TIME))."前有效";
			} else {
				$data['desc'] = "新用户注册前".VIP_ACTIVITY_REGISTER."天有效";
			}
			$data['content'] = array(
				"高级匹配任意使用",
				"100元活动抵用券1张",
				"10元邀请".($user['gender']==1?"体验":"心动")."券1张",
				"各种折扣特权等福利"
			);
		}
		//活动时间之后，且注册时间超过
		else {
			$data['title'] = "超值年费会员";
			$data['desc'] = "首次购买更有优惠";
			$data['content'] = array(
				"高级匹配任意使用",
				"100元活动抵用券1张",
				"10元邀请".($user['gender']==1?"体验":"心动")."券4张",
				"各种折扣特权等福利"
			);
		}

		return json_encode($data);
	}

	/**
	 * 获取心动卡信息
	 * @return mixed		返回心动卡配置信息
	 * */
	public function get_heartbeat_config() {
		$data[1] = array("id"=>1, "price"=>990, "red_envelope_min"=>300, "red_envelope_max"=>990);
		$data[2] = array("id"=>2, "price"=>5200, "red_envelope_min"=>2000, "red_envelope_max"=>5200);
		$data[3] = array("id"=>3, "price"=>13140, "red_envelope_min"=>5000, "red_envelope_max"=>13140);

		return array_values($data);
	}

}