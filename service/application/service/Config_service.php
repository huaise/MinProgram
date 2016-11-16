<?php
/**
 * 配置服务
 * Created by PhpStorm.
 * User: CaiZhenYu
 * Date: 2016/5/10
 * Time: 11:55
 */

class Config_service extends MY_Service {

	public $_api_code = 1800;	//编码在看...

	public $_user;				//用户信息

	public function __construct()
	{
		parent::__construct();
		$this->load->model('user_model');
		$this->_log_tag = 'user';
	}

	/**
	 * 获取一些基础配置信息
	 *
	 * @param array	$field	需要获取的配置列表
	 *
	 * education
	 * salary
	 * trade
	 * position
	 * height
	 * weight
	 * constellation
	 * blood
	 * only_child
	 * look
	 * salary_vs_look
	 * target_education
	 *
	 * @return array
	 */
	public function get_config($field)
	{
		$return = array();

		foreach ($field as $val){
			//调用相应方法
			$fun = 'config_'.$val;
			if (method_exists($this, $fun)) {
				$return[$val] = $this->$fun();
			} else {
				//不存在该方法直接过滤
			}
		}

		return $return;
	}

	//学历配置
	public function config_education()
	{
		return $this->_format($this->user_model->get_education_lookup_table());
	}

	//对方学历配置
	public function config_target_education()
	{
		return $this->_format($this->user_model->get_target_education_lookup_table());
	}

	//对方收入配置
	public function config_target_salary()
	{
		return $this->_format($this->user_model->get_target_salary_lookup_table());
	}

	//收入配置
	public function config_salary()
	{
		return $this->_format($this->user_model->get_salary_lookup_table());
	}

	//行业配置
	public function config_trade()
	{
		return $this->_format($this->user_model->get_industry_lookup_table(), 'id', 'name');
	}

	//身高配置
	public function config_height()
	{
		return array('height_min' => 140, 'height_max' => 220);
	}

	//体重配置
	public function config_weight()
	{
		return array('weight_min' => 30, 'weight_max' => 200);
	}

	//年龄配置
	public function config_age()
	{
		return array('age_min' => 18, 'age_max' => 45);
	}

	//星座配置
	public function config_constellation()
	{
		return $this->_format($this->user_model->get_constellation_lookup_table());
	}

	//血型配置
	public function config_blood()
	{
		return $this->_format($this->user_model->get_blood_lookup_table());
	}

	//是否独生子女配置
	public function config_only_child()
	{
		return $this->_format(array(1 => '是', 2 => '不是'));
	}

	//外貌配置
	public function config_look()
	{
		return $this->_format($this->user_model->get_look_lookup_table());
	}

	//收入外貌哪个重要配置
	public function config_salary_vs_look()
	{
		return $this->_format($this->user_model->get_salary_vs_look_lookup_table());
	}

	/**
	 * 生成省市区数据
	 */
	public function get_area()
	{
		$this->load->model('area_model');
		//获取数据库数据
		$this->area_model->set_table_name('p_area_district');
		$district = $this->area_model->select();

		$this->area_model->set_table_name('p_area_city');
		$city = $this->area_model->select();


		$this->area_model->set_table_name('p_area_province');
		$province = $this->area_model->select();

		//开始组装 从区开始
		$flag_district = array();
		$flag_city = array();
		$flag_province = array();
		foreach ($district as $val){
			$flag_district[$val['pid']][$val['cid']][] = array(
				'value' => $val['did'],
				'text' => $val['name']
			);
		}

		foreach ($city as $val){
			$flag_city[$val['pid']][] = array(
				'value' => $val['cid'],
				'text' => $val['name'],
				'children' => (isset($flag_district[$val['pid']][$val['cid']]) ? $flag_district[$val['pid']][$val['cid']] : array())
			);
		}

		foreach ($province as $val){
			$flag_province[] = array('value' => $val['pid'], 'text' => $val['name'], 'children' => (isset($flag_city[$val['pid']]) ? $flag_city[$val['pid']] : array()));
		}

//		$str = 'var cityData3 = ' . json_encode($flag_province);

		return $flag_province;

	}

	/**
	 * 生成职位三级联动数据
	 */
	public function get_position()
	{
		$this->load->model('position_model');
		//获取数据库数据
		$this->position_model->set_table_name('p_position_3');
		$position_3 = $this->position_model->select();

		$this->position_model->set_table_name('p_position_2');
		$position_2 = $this->position_model->select();


		$this->position_model->set_table_name('p_position_1');
		$position_1 = $this->position_model->select();

		//开始组装 从区开始
		$flag_district = array();
		$flag_city = array();
		$flag_province = array();
		foreach ($position_3 as $val){
			$flag_district[$val['position_1']][$val['position_2']][] = array(
				'value' => $val['position_3'],
				'text' => $val['name']
			);
		}

		foreach ($position_2 as $val){
			$flag_city[$val['position_1']][] = array(
				'value' => $val['position_2'],
				'text' => $val['name'],
				'children' => (isset($flag_district[$val['position_1']][$val['position_2']]) ? $flag_district[$val['position_1']][$val['position_2']] : array())
			);
		}

		foreach ($position_1 as $val){
			$flag_province[] = array('value' => $val['position_1'], 'text' => $val['name'], 'children' => (isset($flag_city[$val['position_1']]) ? $flag_city[$val['position_1']] : array()));
		}

		$str = 'var positionDate = ' . json_encode($flag_province);
		die($str);

//		return $flag_province;

	}

	/**
	 * 助手类 将数组转化成前端需要的格式:[{value: 'value',text:'text'}]
	 * ps：不支持多维数组转换
	 *
	 * @param array 		$data		需要转化的数组
	 * @param string 		$value		非key-val结构的需要指定取数组中的哪个值
	 * @param string 		$text		非key-val结构的需要指定取数组中的哪个值
	 * @param bool	 		$filter		过滤配置中的0未设定选项， 移动端不需要
	 *
	 * @return array
	 */
	private function _format($data, $value = '', $text = '', $filter = TRUE)
	{
		$return = array();
		foreach ($data as $key=>$val){
			if($filter && $key == 0 && $val == '未设定'){
				continue;
			}
			if (is_array($val)) {
				if (!$value || !$text) return array();	//未指定取哪个值 直接返回空
				if (!isset($val[$value]) || !isset($val[$text])) return array();	//不存在指定的值 直接返回空
				$return[] = array('value' => $val[$value], 'text' => $val[$text]);
			} else {
				$return[] = array('value' => $key, 'text' => $val);
			}
		}
		return $return;
	}

}