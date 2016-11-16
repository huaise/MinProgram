<?php
/**
 * 核心服务类
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/6
 * Time: 20:56
 */

defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Service extends CI_Controller {
	/**
	 * 日志字符串
	 *
	 * @var	string
	 */
	protected $_log_string = '';

	/**
	 * 日志标签名
	 *
	 * @var	int
	 */
	protected $_log_tag = '';

	public function __construct()
	{

		log_message('debug', "Service Class Initialized");
	}

	function __get($key)
	{
		$CI = & get_instance();
		return $CI->$key;
	}

	/**
	 * 获取日志字符串
	 *
	 * @return string		日志字符串
	 */
	public function get_log()
	{
		return $this->_log_string;
	}

	/**
	 * 写日志
	 *
	 * @param string	$message		日志内容
	 * @param bool		$write_to_file	日志是否写入文件
	 * @return null
	 */
	protected function _log($message, $write_to_file = TRUE)
	{
		$this->_log_string .= $message."<br>";

		if ($write_to_file) {
			$this->log->write_log_json(array('message'=>$message), $this->_log_tag);
		}
	}

}


class Api_service extends MY_Service {

	public function __construct()
	{
		parent::__construct();
		$this->load->model('user_model');
	}


	/**
	 * 检测密钥和密码是否合法(校验规则为对“盐值1、数据1、盐值2、数据2……”这样的数据进行MD5)
	 *
	 * @param string	$key			本次秘钥，长度必须为16位，由大小写字母和随机数生成
	 * @param string	$key_pw			密码，全部小写
	 * @param array		$data			用来产生密码的数据
	 * @param array		$salt			盐值
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 */
	protected function check_key_and_pw($key, $key_pw, $data, $salt)
	{
		if (defined('IN_WX')) {
			$output['state'] = TRUE;
			return $output;
		}

		// 检查秘钥是否合法
		if (strlen($key)!=16) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "秘钥不合法";
			$output['ecode'] = "0000005";
			return $output;
		}

		// 检查密码是否正确
		if ($key_pw == '') {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "秘钥密码错误";
			$output['ecode'] = "0000006";
			return $output;
		}

		if (empty($data) || empty($salt)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "秘钥密码错误";
			$output['ecode'] = "0000006";
			return $output;
		}

		// 拼装字符串
		$str = '';
		foreach ($salt as $index=>$salt_str) {
			$str .= $salt_str;
			if (isset($data[$index])) {
				$str .= $data[$index];
			}
		}

		if ($key_pw !== md5($str)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "秘钥密码错误";
			$output['ecode'] = "0000006";
			return $output;
		}


		$output['state'] = TRUE;
		return $output;
	}

	/**
	 * 获取是否是正常模式
	 *
	 * @param int		$platform			对应平台
	 * @param int		$version			版本号，格式：AABBCCC
	 * @return bool		是正常模式(TRUE)/不是正常模式(FALSE)
	 */
	protected function get_lover_flag($platform, $version) {
		// 只有APPstore版，才可能不是正常模式
		if ($platform != PLATFORM_IOS_APPSTORE) {
			return TRUE;
		}

		if ($version < IN_REVIEW_VERSION) {
			return TRUE;
		}
		return FALSE;
	}
}