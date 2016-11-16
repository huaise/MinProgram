<?php
/**
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/4
 * Time: 16:22
 */

class Config_model extends MY_Model {

	public function __construct()
	{
		$this->table_name = 'p_config';
		parent::__construct();
	}

	/**
	 * 通过配置名称获取配置
	 * @param string|array	$name_array 	配置名称，一般数组形式，如果只获取单个配置，也可以为字符串
	 * @return array		查询结果集数组，key-value形式，key为配置表中的name字段，value为配置表中的vaules字段，如array('project_name'=>'示例项目', 'access_token'=>'123456')
	 */
	public function get_rows_by_name_array($name_array)
	{
		if (!is_array($name_array)) {
			if ($name_array == '') {
				return NULL;
			}
		}

		$result = $this->select(array('name' => $name_array), FALSE, 'name,values');

		if (empty($result)) {
			return NULL;
		}

		// 对结果进行处理，处理成key-value形式，方便调用者使用
		foreach ($result as $row) {
			$output[$row['name']] = $row['values'];
		}

		return $output;

	}

	/**
	 * 执行更新记录操作
	 * @param array			$data 		要更新的数据内容，key-value形式，key为配置表中的name字段，value为配置表中的vaules字段，如array('project_name'=>'示例项目', 'access_token'=>'123456', 'times'=>'+=1')等
	 * @return int						成功修改的数量
	 */
	public function update_config($data) {
		$output = 0;
		foreach ($data as $key => $value) {
			if (parent::update(array('values'=>$value), array('name'=>$key))) {
				$output++;
			}
		}

		return $output;
	}

}