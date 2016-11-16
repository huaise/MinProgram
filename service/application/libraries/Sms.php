<?php
/**
 * 短信发送类库（目前仅支持畅卓短信平台）
 * 使用之前请先在/application/config/constants.php中配置CHANZOR_ACCOUNT、CHANZOR_PASSWORD、CHANZOR_SIGN
 * 如果要使用营销短信，还需要配置CHANZOR_ACCOUNT_YX和CHANZOR_PASSWORD_YX（这两个需要联系畅卓开通）
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/8
 * Time: 16:06
 */
defined('BASEPATH') OR exit('No direct script access allowed');

class Sms {

	/**
	 * 发送自定内容的短信，但需要在畅卓后台报备
	 *
	 * @param	string	$content	内容
	 * @param	string	$phone		手机号码
	 * @return	array
	 * 返回结果: 1. $output['state']: 成功(TRUE)/失败(FALSE)，失败时还会返回$output['error']
	 * 2.$output['error']：失败的原因
	 */
	public function send_custom_content_message($content, $phone) {
		// 畅卓要求加签名，且短信文案要备案
		$content = $content.CHANZOR_SIGN;
		$result = $this->_send_chanzor_message($phone, $content, FALSE);
		return $result;
	}


	/**
	 * 用户报名恋爱委托短信提醒运营
	 *
	 * @param	string	$phone		手机号码
	 * @param	int		$uid		用户id
	 * @return	array
	 * 返回结果: 1. $output['state']: 成功(TRUE)/失败(FALSE)，失败时还会返回$output['error']
	 * 2.$output['error']：失败的原因
	 */
	public function send_love_entrust_message($phone, $uid) {
		// 畅卓要求加签名，且短信文案要备案
		$content = "用户id：".$uid."发起了恋爱委托。".CHANZOR_SIGN;
		$result = $this->_send_chanzor_message($phone, $content, FALSE);
		return $result;
	}

	/**
	 * 活动报名成功发送通知短信
	 *
	 * @param	string	$phone		手机号码
	 * @param	string	$name		活动名称
	 * @return	array
	 * 返回结果: 1. $output['state']: 成功(TRUE)/失败(FALSE)，失败时还会返回$output['error']
	 * 2.$output['error']：失败的原因
	 */
	public function send_activity_success_message($phone, $name) {
		// 畅卓要求加签名，且短信文案要备案
		$content = "您好，您已成功报名“".$name."”，如有疑问请联系客服 0571-89774604".CHANZOR_SIGN;
		$result = $this->_send_chanzor_message($phone, $content, FALSE);
		return $result;
	}

	/**
	 * 发送验证短信
	 *
	 * @param	string	$phone		手机号码
	 * @param	string	$valid_code	验证码
	 * @return	array
	 * 返回结果: 1. $output['state']: 成功(TRUE)/失败(FALSE)，失败时还会返回$output['error']
	 * 2.$output['error']：失败的原因
	 */
	public function send_valid_message($phone, $valid_code) {
		// 畅卓要求加签名，且短信文案要备案
		$content = "您的验证码是：".$valid_code."，该验证码5分钟内使用有效，感谢您的使用。".CHANZOR_SIGN;
		$result = $this->_send_chanzor_message($phone, $content, FALSE);
		return $result;
	}


	/**
	 * 畅卓发送短信的函数，私有函数，如果要发送短信，请按照上面的例子，封装特定的发送短信的公共函数
	 *
	 * @param	string	$phone			手机号码
	 * @param	string	$content		短信内容
	 * @param	bool	$market_flag	是否营销短信。1表示是营销短信，根据畅卓的规定，除了验证码之类都是营销短信
	 * @param	string	$send_time		定时发送时间，如2010-10-24 09:08:10
	 * @return	array
	 * 返回结果: 1. $output['state']: 成功(TRUE)/失败(FALSE)，失败时还会返回$output['error']
	 * 2.$output['error']：失败的原因
	 */
	private function _send_chanzor_message($phone, $content, $market_flag=FALSE, $send_time="") {
		$target = "http://sms.chanzor.com:8001/sms.aspx"; //畅卓的发送短信接口地址

		// 根据是否是营销短信，使用不同的账号
		if ($market_flag) {
			$account = CHANZOR_ACCOUNT_YX;
			$password = CHANZOR_PASSWORD_YX;
		}
		else {
			$account = CHANZOR_ACCOUNT;
			$password = CHANZOR_PASSWORD;
		}

		//构建发送链接
		$post_data = "action=send&userid=&account=$account&password=$password&mobile=$phone&sendTime=$send_time&content=".rawurlencode("$content");

		$gets = $this->_post($post_data, $target);
		$start = strpos($gets,"<?xml");
		$data = substr($gets,$start);
		$xml = simplexml_load_string($data);

		if ($xml->returnstatus == "Success") {
			$output['state'] = TRUE;
		}
		else {
			$output['state'] = FALSE;
			$output['error'] = (string) ($xml->message);
		}

		// 写日志
		$CI =& get_instance();
		$CI->log->write_log_json(array('phone'=>$phone, 'content'=>$content,
			'ip'=>$CI->input->ip_address(), 'output'=>$output), 'sms');

		return $output;
	}


	/**
	 * 畅卓短信发送的post请求私有函数
	 *
	 * @param	string	$data	要发送的数据，url字符串
	 * @param	string	$target	post请求的地址
	 * @return	bool	是(TRUE)/不是(FALSE)
	 */
	private function _post($data, $target) {
		$url_info = parse_url($target);
		$httpheader = "POST " . $url_info['path'] . " HTTP/1.0\r\n";
		$httpheader .= "Host:" . $url_info['host'] . "\r\n";
		$httpheader .= "Content-Type:application/x-www-form-urlencoded\r\n";
		$httpheader .= "Content-Length:" . strlen($data) . "\r\n";
		$httpheader .= "Connection:close\r\n\r\n";
		//$httpheader .= "Connection:Keep-Alive\r\n\r\n";
		$httpheader .= $data;

		$fd = fsockopen($url_info['host'], 80);
		fwrite($fd, $httpheader);
		$gets = "";
		while(!feof($fd)) {
			$gets .= fread($fd, 128);
		}
		fclose($fd);
		return $gets;
	}
}
