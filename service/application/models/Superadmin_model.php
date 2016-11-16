<?php
/**
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/4
 * Time: 16:22
 */

class Superadmin_model extends MY_Model {

	public function __construct()
	{
		$this->table_name = 'p_superadmin';
		parent::__construct();
	}

	/**
	 * 通过id获取一行记录
	 * @param int			$id 			要查找的id
	 * @param string		$data 			需要查询的字段值，如：name,gender,birthday，不需要加`，会自动加上
	 * @return array		查询结果集数组，如果不存在则返回NULL
	 */
	public function get_row_by_id($id, $data='*')
	{
		if (!is_id($id)) {
			return NULL;
		}

		return $this->get_one(array('uid =' => $id), FALSE, $data);
	}


	/**
	 * 管理员通过用户名和密码登录
	 * @param string		$username 		用户名
	 * @param string		$password 		用户输入的密码
	 * @return	mixed
	 * 返回结果: 1. $output['state']: 成功(TRUE)/失败(FALSE)，成功时还会返回$output['superadmin']和$output['content']，失败还会返回$output['error']
	 * 2.$output['superadmin']：管理员的信息，包含uid，password，authoriy等
	 */
	public function check_superadmin_login($username, $password)
	{
		$username = trim($username);

		$superadmin = $this->get_one(array('username' => $username));

		if (empty($superadmin)) {
			$output['state'] = FALSE;
			$output['error'] = '用户名或密码错误';
			return $output;
		}

		if (!password_verify($this->get_superadmin_password_seed($password), $superadmin['password'])) {
			$output['state'] = FALSE;
			$output['error'] = '用户名或密码错误';
			return $output;
		}

		$output['state'] = TRUE;
		$output['superadmin'] = $superadmin;
		return $output;
	}

	/**
	 * 获取产生密码的种子
	 * @param string		$password 		用户输入的密码
	 * @return	string		产生密码的种子，用于产生数据库中的密码
	 */
	public function get_superadmin_password_seed($password)
	{
		return md5('Ol7kCxFYPbJS6GeW'.$password.'geuhqj24zCTzB4Gm');
	}


	/**
	 * 获取产生token的种子
	 * @param int			$uid 			用户id
	 * @param string		$password 		用户密码的hash值，即数据库中存储的值
	 * @return	string		管理员的token的种子，用于产生SESSION中的token
	 */
	public function get_superadmin_token_seed($uid, $password)
	{
		return md5('gzlBEyhuFfX6i8De'.$password.'kJTbh3rfWuBMfMl5'.$uid.'Q7MlshwPoQxt6iB0');
	}

	/**
	 * 获取权限和其对应字符串的查找表
	 * @return string		对应的字符串
	 */
	public function get_authority_lookup_table()
	{
		return array(0=>'超级管理员', 1=>'普通管理员', 99=>'客服');
	}

	/**
	 * 根据类型编码，获取其对应的字符串
	 * @param int			$code			编码
	 * @return string		对应的字符串
	 */
	public function get_authority_str($code)
	{
		$lookup_table = $this->get_authority_lookup_table();
		return isset($lookup_table[$code]) ? $lookup_table[$code] : "未知权限";
	}

}