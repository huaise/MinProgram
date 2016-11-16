<?php
/**
 * 拓展的用户代理类库
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/8
 * Time: 16:06
 */
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_User_agent extends CI_User_agent {

	/**
	 * 是否用微信内置浏览器访问
	 *
	 * @return	bool	是(TRUE)/不是(FALSE)
	 */
	public function is_weixin() {
		$user_agent = $_SERVER['HTTP_USER_AGENT'];
		$is_weixin = FALSE;

		$mobile_agents = Array("micromessenger");
		foreach ($mobile_agents as $device) {
			if (stristr($user_agent, $device)) {
				$is_weixin = TRUE;
				break;
			}
		}

		return $is_weixin;
	}

	/**
	 * 是否用客户端浏览器访问
	 *
	 * @return	bool	是(TRUE)/不是(FALSE)
	 */
	public function is_jianai() {
		$user_agent = $_SERVER['HTTP_USER_AGENT'];
		$is_jianai = FALSE;

		$mobile_agents = Array("jianailove");
		foreach ($mobile_agents as $device) {
			if (stristr($user_agent, $device)) {
				$is_jianai = TRUE;
				break;
			}
		}

		return $is_jianai;
	}
}
