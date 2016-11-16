<?php
/**
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/4
 * Time: 16:22
 */

class User_model extends MY_Model {

	public function __construct()
	{
		$this->table_name = 'p_userbase';
		parent::__construct();
	}

	/**
	 * 通过id获取一行记录
	 * @param int			$id 			要查找的id
	 * @param string		$from 			从哪个表里面查询，默认为空字符串，即从userbase和userdetail中查询，如果要指定某个表，则传入表名，多个表名之间用,分隔
	 * @param string		$data 			需要查询的字段值，如：name,gender,birthday，不需要加`，会自动加上，如果要查询某个在多个表中都出现的字段，则要指定表名，如p_userbase.uid,phone
	 * @return array		查询结果集数组，如果不存在则返回NULL
	 */
	public function get_row_by_id($id, $from = '', $data='*')
	{
		if (!is_id($id)) {
			return NULL;
		}

		if ($from == '') {
			$from = 'p_userbase, p_userdetail';
		}

		$from = explode(",", $from);

		// 如果只从一个表中查询
		if (count($from) == 1) {
			$this->set_table_name($from[0]);
			return $this->get_one(array('uid =' => $id), FALSE, $data);
		}
		// 如果从多个表里面查询
		else {
			// where部分
			$this->db->where(array($from[0].'.uid =' => $id));

			// 查询的字段部分
			$data=str_replace("，",",",$data);
			$this->db->select($data);

			// limit部分
			$this->db->limit(1);

			// 表名
			$this->db->from($from[0]);

			// 联查部分
			for ($i = 1; $i < count($from); $i++) {
				$this->db->join($from[$i], $from[$i-1].'.uid = '.$from[$i].'.uid');
			}

			// 开始查询
			$query = $this->db->get();
			$datalist = $this->process_sql($query);
			return isset($datalist[0]) ? $datalist[0] : NULL;
		}
	}

	/**
	 * 判断两个性别是否是异性
	 * @param int			$gender_1		性别1
	 * @param int			$gender_2		性别2
	 * @return bool			是异性（TRUE）/不是异性（FALSE）
	 */
	public function is_opposite_sex($gender_1, $gender_2)
	{
		$gender_1 = (int)$gender_1;
		$gender_2 = (int)$gender_2;
		return ( ($gender_1==1 && $gender_2==2) || ($gender_1==2 && $gender_2==1) );
	}

	/**
	 * 添加一条记录
	 * @param	array		$data 				要增加的数据，参数为数组。数组key为字段值，数组值为数据取值，无需转义，如：array('title' => 'My title', 'name'  => 'My Name','date'  => 'My date');
	 * @param	bool		$return_insert_id 	是否返回新建ID号
	 * @return	bool|int	如果返回新建ID号，则为ID号，否则为成功(TRUE)/失败(FALSE)
	 */
	public function insert_user($data, $return_insert_id = true) {

		$data['uid'] = $this->get_uuid_short();
		if ($data['uid'] == NULL) {
			return FALSE;
		}

		// 开启事务
		$this->db->trans_start();

		// 首先往userdetail里面插入一条数据
		$this->set_table_name('p_userdetail');
		parent::insert_with_unique_key($data, FALSE);

		// 如果成功，再往userbase里面添加一条数据
		$userbase['uid'] = $data['uid'];
		$this->load->helper('string');
		$userbase['token'] = md5(random_string('alnum', 100));
		$this->set_table_name('p_userbase');
		parent::insert_with_unique_key($userbase, FALSE);

		// 结束事务
		$this->db->trans_complete();

		// 判断事务是否成功
		if ($this->db->trans_status() === FALSE) {
			return $return_insert_id ? NULL : FALSE;
		}
		else {
			return $data['uid'];
		}

	}

	/**
	 * 执行更新记录操作
	 * @param array		$data 		要更新的数据内容，无需转义，为数组，如: array('name'=>'phpcms', 'base'=>'-=1')，程序会自动解析为`name` = `name` + 1, `base` = `base` - 1
	 * @param array		$where 		查询条件，无需转义，为了防止sql注入，只支持数组，如：array('name !=' => $name, 'id <' => $id, 'date >' => $date)
	 * @return bool					成功(TRUE)/失败(FALSE)
	 */
	public function update($data, $where = array()) {
		$this->set_table_name('p_userdetail');
		return parent::update($data, $where);
	}

	/**
	 * 执行删除记录操作
	 * @param 	array|string	$where 	查询条件，如果是数组，无需转义，如：array('name !=' => $name, 'id <' => $id, 'date >' => $date)，如果是字符串，需要转义，例如"name='Jo\'e' OR nick='Jo\'e'"
	 * @return 	bool					成功(TRUE)/失败(FALSE)
	 */
	public function delete($where) {
		// 开启事务
		$this->db->trans_start();

		$this->set_table_name('p_userdetail');
		$result_userdetail = parent::delete($where);
		$this->set_table_name('p_userbase');
		$result_userbase = parent::delete($where);

		// 结束事务
		$this->db->trans_complete();

		// 判断事务是否成功
		if ($this->db->trans_status() === FALSE) {
			return FALSE;
		}
		else {
			return ($result_userdetail && $result_userbase);
		}
	}
}