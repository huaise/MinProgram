<?php
/**
 * 拓展的加密类库，区别有：
 * 1、修复了系统自带库设置key无效的bug
 * 2、在iv配置了的情况下，用给定iv，而不需要将iv信息包含在数据中
 * 3、去除了hmac_digest
 * 4、目前仅支持openssl
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/3/4
 * Time: 14:17
 */
defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Encryption extends CI_Encryption {
	/**
	 * Encryption iv
	 *
	 * @var	string
	 */
	protected $_iv;

	/**
	 * Initialize
	 *
	 * @param	array	$params	Configuration parameters
	 * @return	CI_Encryption
	 */
	public function initialize(array $params)
	{
		empty($params['iv']) OR $this->_iv = $params['iv'];
		parent::initialize($params);
		return $this;
	}

	/**
	 * Get params
	 *
	 * @param	array	$params	Input parameters
	 * @return	array
	 */
	protected function _get_params($params)
	{
		$output = parent::_get_params($params);

		unset($output['hmac_digest']);
		unset($output['hmac_key']);

		if (!isset($output['key']) && isset($this->_key) && $this->_key!='') {
			$output['key'] = $this->_key;
		}

		if (!isset($output['iv'])) {
			if (isset($params['iv']) && $params['iv']!='') {
				$output['iv'] = $params['iv'];
			}
			else if (isset($this->_iv) && $this->_iv!='') {
				$output['iv'] = $this->_iv;
			}
		}

		return $output;
	}

	// --------------------------------------------------------------------

	/**
	 * Encrypt via OpenSSL
	 *
	 * @param	string	$data	Input data
	 * @param	array	$params	Input parameters
	 * @return	string
	 */
	protected function _openssl_encrypt($data, $params)
	{
		if (empty($params['handle']))
		{
			return FALSE;
		}

		$iv_size = openssl_cipher_iv_length($params['handle']);
		$append_iv_flag = FALSE;
		if ($iv_size > 0) {
			if (isset($params['iv']) && strlen($params['iv'])==$iv_size) {
				$iv = $params['iv'];
			}
			else {
				$iv = $this->create_key($iv_size);
				$append_iv_flag = TRUE;
			}
		}
		else {
			$iv = NULL;
		}

		$data = openssl_encrypt(
			$data,
			$params['handle'],
			$params['key'],
			1, // DO NOT TOUCH!
			$iv
		);

		if ($data === FALSE)
		{
			return FALSE;
		}

		return $append_iv_flag ? $iv.$data : $data;
	}

	/**
	 * Decrypt via OpenSSL
	 *
	 * @param	string	$data	Encrypted data
	 * @param	array	$params	Input parameters
	 * @return	string
	 */
	protected function _openssl_decrypt($data, $params)
	{
		if ($iv_size = openssl_cipher_iv_length($params['handle']))
		{
			if (isset($params['iv']) && strlen($params['iv'])==$iv_size) {
				$iv = $params['iv'];
			}
			else {
				$iv = self::substr($data, 0, $iv_size);
				$data = self::substr($data, $iv_size);
			}
		}
		else
		{
			$iv = NULL;
		}

		return empty($params['handle'])
			? FALSE
			: openssl_decrypt(
				$data,
				$params['handle'],
				$params['key'],
				1, // DO NOT TOUCH!
				$iv
			);
	}


	/**
	 * 获取AES加密密钥
	 *
	 * @param	string	$seed	产生密钥的种子，为128bit的随机种子
	 * @return	string	AES加密密钥
	 */
	public function get_aes_key($seed) {
		$key = md5('WaZd0XFzgFXn0JFy'.strtolower(bin2hex(substr($seed, 8, 8))).'MLVFEwEWME4sngSp'.strtolower(bin2hex(substr($seed, 0, 8))).'8SMbLW6PpUruWFSo');
		return $this->hex2str($key);
	}

	/**
	 * 十六进制转换为字符串
	 *
	 * @param	string	$hex	十六进制
	 * @return	string	字符串
	 */
	public function hex2str($hex)
	{
		$string='';
		for ($i=0; $i<strlen($hex)-1; $i+=2) {
			$string.=chr(hexdec($hex[$i].$hex[$i+1]));
		}
		return $string;
	}
}
