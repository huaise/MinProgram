<?php
/**
 * 用户服务
 * Created by PhpStorm.
 * User: CaiZhenYu
 * Date: 2016/5/10
 * Time: 11:55
 */

class User_service extends MY_Service {

	public $_api_code = 1700;	//编码在看...

	public $_user;				//用户信息

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('date');
		$this->load->model('user_model');
		$this->load->model('position_model');
		$this->load->model('area_model');
		$this->load->model('user_album_model');
		$this->_log_tag = 'user';
	}

	/**
	 * 认证图标排序
	 * @return array 排序结果
	 *
	 */
	public function get_auth_icon()
	{
		$_authenticated = array();	//已认证
		$_unauthorized = array();	//未认证
		if ($this->_user['auth_identity'] > 0) {
			$_authenticated[] = 'identity';
		} else {
			$_unauthorized[] = 'identity';
		}
		if ($this->_user['auth_education'] > 0) {
			$_authenticated[] = 'education';
		} else {
			$_unauthorized[] = 'education';
		}
		if ($this->_user['auth_car'] > 0) {
			$_authenticated[] = 'car';
		} else {
			$_unauthorized[] = 'car';
		}
		if ($this->_user['auth_house'] > 0) {
			$_authenticated[] = 'house';
		} else {
			$_unauthorized[] = 'house';
		}

		return array_merge($_authenticated, $_unauthorized);
	}

	/**
	 * 是否填写了交友要求
	 * @return int 		1是0否
	 */
	public function get_isset_target()
	{
		$_isset_target = 0;
		if ($this->_user['target_education'] != 0 || $this->_user['target_salary'] != 0
			|| $this->_user['target_look'] != 0 || $this->_user['salary_vs_look'] != 0
			|| $this->_user['target_age_min'] != 0 || $this->_user['target_age_max'] != 99
			|| $this->_user['target_height_min'] != 0 || $this->_user['target_height_max'] != 255) {
			$_isset_target = 1;
		}
		return $_isset_target;
	}

	/**
	 * 计算个人资料完整度
	 * @return int
	 */
	public function get_info_complete()
	{
		$_set_num = 0;	//已设置数量
		$_total = 0;	//总数
		$_field_array = array('gender', 'username', 'education', 'province',
			'height', 'weight', 'salary', 'weixin_id', 'sign', 'industry', 'position_1',
			'only_child', 'blood_type', 'avatar',
			'province_n', 'province_r', 'hobby');
		if ($this->_user['birthday'] == '0000-00-00'){
			$_total++;
		} else {
			$_total++;
			$_set_num++;
		}
		foreach ($_field_array as $val){
			if ($this->_user[$val]) {
				$_set_num++;
			}
			$_total++;
		}
		$_info_complete = intval($_set_num / $_total * 100);
		return $_info_complete;
	}

	/**
	 * 获取年龄
	 */
	public function get_age()
	{
		return get_age($this->_user['birthday']);
	}

	/**
	 * 获取相册数量是否满足要求
	 *
	 * @param	int	$num	要求数量
	 *
	 * @return bool
	 */
	public function get_album($num = 3)
	{
		$album_list = $this->user_album_model->get_list($this->_user['uid'], 0, $num, TRUE);
		if (count($album_list) == $num){
			return true;
		} else {
			$album_count_flag = 0;
			foreach ($album_list as $key=>$val){
				$album_count_flag += count(explode(',', $val['image']));
			}
			if ($album_count_flag < 3) {
				return false;
			} else {
				return true;
			}
		}
	}

	/**
	 * 获取星座
	 */
	public function get_constellation()
	{
		return get_constellation($this->_user['birthday']);
	}

	/**
	 * 获取学历
	 */
	public function get_education()
	{
		return $this->user_model->get_education_str($this->_user['education']);
	}

	/**
	 * 获取认证学历
	 */
	public function get_auth_education()
	{
		return $this->user_model->get_education_str($this->_user['auth_education']);
	}

	/**
	 * 获取收入
	 */
	public function get_salary()
	{
		return $this->user_model->get_salary_str($this->_user['salary']);
	}

	/**
	 * 获取收入
	 */
	public function get_industry()
	{
		return $this->user_model->get_industry_str($this->_user['industry']);
	}

	/**
	 * 获取血型
	 */
	public function get_blood()
	{
		return $this->user_model->get_blood_str($this->_user['blood_type']);
	}

	/**
	 * 获取职位
	 */
	public function get_position_3()
	{
		$position = $this->position_model->get_position_3_by_id($this->_user['position_1'], $this->_user['position_2'], $this->_user['position_3']);

		if(isset($position['name']))
			return $position['name'];
		return '未设定';
	}

	/**
	 * 获取行业
	 */
	public function get_position_2()
	{
		$position = $this->position_model->get_position_2_by_id($this->_user['position_1'], $this->_user['position_2']);

		if(isset($position['name']))
			return $position['name'];
		return '未设定';
	}

	/**
	 * 获取居住地
	 */
	public function get_address()
	{
		return $this->area_model->get_string_by_id($this->_user['province'], $this->_user['city'], $this->_user['district'], '-');
	}

	/**
	 * 获取籍贯
	 */
	public function get_origin()
	{
		return $this->area_model->get_string_by_id($this->_user['province_n'], $this->_user['city_n']);
	}

	/**
	 * 获取户口
	 */
	public function get_registered()
	{
		return $this->area_model->get_string_by_id($this->_user['province_r'], $this->_user['city_r']);
	}

	/**
	 * 获取目标学历
	 */
	public function get_target_education()
	{
		return $this->user_model->get_target_education_str($this->_user['target_education']);
	}

	/**
	 * 获取目标收入
	 */
	public function get_target_salary()
	{
		return $this->user_model->get_target_salary_str($this->_user['target_salary']);
	}

	/**
	 * 获取目标外貌
	 */
	public function get_target_look()
	{
		return $this->user_model->get_look_str($this->_user['target_look']);
	}

	/**
	 * 获取目标收入和外貌哪个重要
	 */
	public function get_salary_vs_look()
	{
		return $this->user_model->get_salary_vs_look_str($this->_user['salary_vs_look']);
	}

	//对用户数据做整体的转换
	public function get_user_info()
	{
		//计算下年龄
		$this->_user['age'] = $this->get_age();

		//星座转换
		$this->_user['constellation_content'] = $this->get_constellation();

		//认证图标排序
		$this->_user['auth_icon'] = $this->get_auth_icon();

		//学历转换
		$this->_user['education_content'] = $this->get_education();

		//认证学历转换...
		$this->_user['auth_education_content'] = $this->get_auth_education();

		//收入转换
		$this->_user['salary_content'] = $this->get_salary();

		//血型转换
		$this->_user['blood_content'] = $this->get_blood();

		//职位转换
		$this->_user['position_3_content'] = $this->get_position_3();

		//行业转换
		$this->_user['industry_content'] =  $this->get_industry();

		//居住地转换
		$this->_user['address'] = $this->get_address();

		//籍贯
		$this->_user['origin'] = $this->get_origin();

		//户口
		$this->_user['registered'] = $this->get_registered();

		//目标学历
		$this->_user['target_education_content'] = $this->get_target_education();

		//目标收入
		$this->_user['target_salary_content'] = $this->get_target_salary();

		//目标外貌
		$this->_user['target_look_content'] = $this->get_target_look();

		//目标收入与外貌哪个重要
		$this->_user['target_salary_vs_look_content'] = $this->get_salary_vs_look();

		return $this->_user;
	}

}