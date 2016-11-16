<?php
/**
 * 输出转换类
 * Created by PhpStorm.
 * User: caizhenyu
 * Date: 2016/7/4
 * Time: 15:52
 */
defined('BASEPATH') OR exit('No direct script access allowed');

class Send {

	private $_config = array();			//配置信息

	private $_key = '';					//当前转换使用的key标识，以区分相同命名不同转换规则的字段
	private $_key_list = array();		//当前转换使用的配置

	public function __construct(){
		$this->_config = array(
			'task' => array(
				't_id',
				'keyword',
				'describe',
				'vip',
				'state'
			),
			'search' => array(
				'uid',
				'gender',
				'phone',
				'weixin_id',
				'username',
				'birthday',
				'height',
				'salary',
				'education',
				'position_1',
				'position_2',
				'position_3',
				'look_grade_total',
				'salary_vs_look',
				'look_grade_times',
				'province_n',
				'city_n',
				'target_age_min',
				'target_age_max',
				'target_education',
				'target_height_min',
				'target_height_max',
				'target_salary',
				'target_look',
				'avatar',
				'vip_date',
				'vip_flag',
				'last_time',
				'sign'
			)
		);

		$this->ci =& get_instance();
	}

	/**
	 * 字段转换, 顶多俩级不支持递归
	 *
	 * @param array $list	用于筛选的数据
	 * @param string $key	配置标识
	 *
	 * @return array
	 */
	public function send($list, $key){
		$this->_key = $key;
		$this->_key_list = $this->_config[$key];

		$return = $this->translate($list);

		return $return;
	}

	/**
	 * 转换
	 *
	 * @param array $list	用于筛选的列表
	 *
	 * @return array
	 */
	public function translate($list){
		$return = array();

		foreach ($list as $key=>$val){
			if (is_array($val)){
				$return[$key] = $this->_translate($val);
			} else {
				return $this->_translate($list);
			}
		}

		return $return;
	}
	private function _translate($val){
		$return = array();

		foreach ($this->_key_list as $v){
			//存在对应的过滤方法则过滤， 否则直接返回原数据
			if (method_exists($this, $v)){
				$return[$v] = $this->$v($val);
			} else {
				$return[$v] = isset($val[$v]) ? $val[$v] : '';
			}
		}

		return $return;
	}

	/**
	 * 相关过滤实现， 就不分文件了...
	 *
	 * @param	array	$info	需要过滤的数据
	 *
	 * @return string
	 */
	private function avatar($info){
		$this->ci->load->helper('url');
		return get_attachment_url($info['avatar'], 'avatar');
	}

	private function vip_flag($info){
		$this->ci->load->model('user_model');
		return (int) $this->ci->user_model->is_vip($info);
	}

}
