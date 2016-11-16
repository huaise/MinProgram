<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class MY_Model extends CI_Model{
	protected $table_name = '';		//表名
	protected $field = '';			//列信息
	protected $primary = '';		//主键

	function __construct() {
		$this->table_name = $this->db->dbprefix.$this->table_name;
		$this->field = '*';
		$this->primary = 'id';
		parent::__construct();
	}

	/**
	 * 通过id获取一行记录
	 * @param int			$id 			要查找的id
	 * @return array		查询结果集数组，如果不存在则返回NULL
	 */
	public function get_row_by_id($id)
	{
		return $this->get_one(array($this->primary . ' = ' => $id), FALSE, $this->field);
	}

	/**
	 * 设置数据库连接，除非要连接非默认数据库，否则一般不会用到
	 *
	 * @param	mixed	$db_config		数据库配置项
	 * @return	null
	 */
	function set_db($db_config='')
	{
		$this->db= $this->load->database($db_config,TRUE);
	}
	
	/**
	 * 设置表名，除非要修改model中的默认表名，或者是一个模型对应多个表，否则一般不会用到
	 *
	 * @param	string	$tablename		表名
	 * @param	string	$tablepre		前缀
	 * @return	null
	 */
	function set_table_name($tablename='', $tablepre=NULL)
	{
		if ($tablepre === NULL) {
			$this->table_name = $tablename;
		}
		else {
			$this->table_name = $tablepre.$this->table_name;
		}
	}

	/**
	 * 执行sql查询，不支持多表联查
	 * @param array|string	$where 		查询条件，如果是数组，无需转义，如：array('name !=' => $name, 'id' => array(1,2,3), 'date >' => $date)，其中的value如果是数组会转为in查询，如果是字符串，需要转义，例如"name='Jo\'e' OR nick='Jo\'e'"
	 * @param bool			$is_or 		如果是数组，则表示多个where条件之间是不是用or连接，1为or，0为and
	 * @param string		$data 		需要查询的字段值，如：name,gender,birthday，不需要加`，会自动加上
	 * @param string		$limit 		返回结果范围，如：10或10,10 默认为空
	 * @param string		$order 		排序方式，如：'title DESC, name ASC'
	 * @param array|string	$group 		分组方式，可以是数组或者字符串，如：'title' 或者 array('title', 'date')
	 * @param string		$key        某个字段名，该字段的内容会作为返回数组中的键名
	 * @return array		查询结果集数组
	 */
	final public function select($where = array(), $is_or = FALSE, $data = '*', $limit = '', $order = '', $group = '', $key='') {

		// where部分
		if (!empty($where))
		{
			if (is_array($where)) {
				foreach ($where as $where_key => $where_value) {
					// 如果是数组则转换为in查询
					if (is_array($where_value)) {
						if ($is_or) {
							$this->db->or_where_in($where_key, $where_value);
						}
						else {
							$this->db->where_in($where_key, $where_value);
						}
					}
					else {
						if ($is_or) {
							$this->db->or_where($where_key, $where_value);
						}
						else {
							$this->db->where($where_key, $where_value);
						}
					}
				}
			}
			else {
				$this->db->where($where);
			}

		}

		// 查询的字段部分
		$data=str_replace("，",",",$data);
		$this->db->select($data);

		// limit部分
		if (!empty($limit))
		{
			$limit_arr=explode(",", $limit);
			if (count($limit_arr)==1)
				$this->db->limit($limit);
			else
				$this->db->limit($limit_arr[1],$limit_arr[0]);
		}

		// 排序部分
		if (!empty($order)) {
			$this->db->order_by($order);
		}

		// 分组部分
		if (!empty($group)) {
			$this->db->group_by($group);
		}

		// 表名
		$this->db->from($this->table_name);

		// 开始查询
		$datalist =array();
		$Q = $this->db->get();
		if ($Q->num_rows() > 0)
		{
			foreach ($Q->result_array() as $rs)
			{
				if ($key) {
					$datalist[$rs[$key]] = $rs;
				} else {
					$datalist[] = $rs;
				}
			}
		}

		$Q->free_result();
		return $datalist;
	}


	/**
	 * 获取单条记录查询，不支持多表联查
	 * @param array|string	$where 		查询条件，如果是数组，无需转义，如：array('name !=' => $name, 'id' => array(1,2,3), 'date >' => $date)，其中的value如果是数组会转为in查询，如果是字符串，需要转义，例如"name='Jo\'e' OR nick='Jo\'e'"
	 * @param bool			$is_or 		如果是数组，则表示多个where条件之间是不是用or连接，1为or，0为and
	 * @param string		$data 		需要查询的字段值，如：name,gender,birthday，不需要加`，会自动加上
	 * @param string		$order 		排序方式，如：'title DESC, name ASC'
	 * @param array|string	$group 		分组方式，可以是数组或者字符串，如：'title' 或者 array('title', 'date')
	 * @return array|null	数据查询结果集,如果不存在，则返回空
	 */
	final public function get_one($where = array(), $is_or = FALSE, $data = '*', $order = '', $group = '') {
		$datainfo = $this->select($where, $is_or, $data, '1', $order , $group);
		if (count($datainfo) > 0) {
			return $datainfo[0];
		}
		else {
			return NULL;
		}
	}

	/**
	 * 计算记录数，不支持多表联查
	 * @param array|string	$where 		查询条件，如果是数组，无需转义，如：array('name !=' => $name, 'id' => array(1,2,3), 'date >' => $date)，其中的value如果是数组会转为in查询，如果是字符串，需要转义，例如"name='Jo\'e' OR nick='Jo\'e'"
	 * @param bool			$is_or 	如果是数组，则表示多个where条件之间是不是用or连接，1为or，0为and
	 * @return int					记录的条数
	 */
	final public function count($where = array(), $is_or = FALSE) {
		$r = $this->get_one($where, $is_or, "COUNT(*) AS num");
		return (isset($r['num']) ? $r['num'] : 0);
	}


	/**
	 * 处理sql查询结果
	 * @param mixed			$query			结果集，如$this->db->get()的返回结果，或者是$this->db->query("YOUR QUERY")的返回结果
	 * @param string		$key        	某个字段名，该字段的内容会作为返回数组中的键名
	 * @return array		查询结果集数组
	 */
	final public function process_sql($query, $key='') {
		$datalist =array();
		if ($query->num_rows() > 0)
		{
			foreach ($query->result_array() as $rs)
			{
				if ($key != '') {
					$datalist[$rs[$key]] = $rs;
				} else {
					$datalist[] = $rs;
				}
			}
		}

		$query->free_result();
		return $datalist;
	}

	/**
	 * 获取mysql的UUID_SHORT()
	 * @return	string	获取到的uuid_short，为unsigned long long
	 */
	public function get_uuid_short() {
		$query = $this->db->query('SELECT UUID_SHORT()');
		if ($query->num_rows() > 0)
		{
			$row = $query->row_array();
			if (isset($row))
			{
				return bcsub($row['UUID_SHORT()'], config_item('uuid_offset'));
			}
		}

		return NULL;
	}

	/**
	 * 添加一条记录
	 * @param	array		$data 				要增加的数据，参数为数组。数组key为字段值，数组值为数据取值，无需转义，如：array('title' => 'My title', 'name'  => 'My Name','date'  => 'My date');
	 * @param	bool		$return_insert_id 	是否返回新建ID号
	 * @return	bool|int	如果返回新建ID号，则为ID号，否则为成功(TRUE)/失败(FALSE)
	 */
	public function insert($data, $return_insert_id = true) {
		$result = $this->db->insert($this->table_name, $data);
		if ($return_insert_id) {
			return $this->db->insert_id();
		}
		else {
			return $result;
		}
	}

	/**
	 * 添加一条记录，当表中有非自增主键或者唯一索引时使用
	 * @param	array		$data 				要增加的数据，参数为数组。数组key为字段值，数组值为数据取值，无需转义，如：array('title' => 'My title', 'name'  => 'My Name','date'  => 'My date');
	 * @param	bool		$return_insert_id 	是否返回新建ID号
	 * @return	bool|int	如果返回新建ID号，则为ID号，否则为成功(TRUE)/失败(FALSE)
	 */
	public function insert_with_unique_key($data, $return_insert_id = true) {

		// 如果是数据库debug开启，需要先关闭，最后再开启
		if ($this->db->db_debug) {
			$this->db->db_debug = FALSE;
			$result = $this->insert($data, $return_insert_id);
			$this->db->db_debug = TRUE;
			return $result;
		}

		return $this->insert($data, $return_insert_id);
	}

	/**
	 * 批量添加记录
	 * @param 	array		$data	要增加的数据，数组的每一个元素为一条要增加的记录，无需转义，如：array( array('title' => 'My title','name' => 'My Name','date' => 'My date'), array('title' => 'Another title','name' => 'Another Name','date' => 'Another date'));
	 * @return	int			插入的条数
	 */
	public function insert_batch($data)
	{
		return $this->db->insert_batch($this->table_name, $data);
	}
	

	/**
	 * 执行更新记录操作
	 * @param array			$data 		要更新的数据内容，无需转义，为数组，如: array('name'=>'phpcms', 'base'=>'-=1')，程序会自动解析为`name` = `name` + 1, `base` = `base` - 1
	 * @param array|string	$where 		查询条件，如果是数组，无需转义，如：array('name !=' => $name, 'id' => array(1,2,3), 'date >' => $date)，其中的value如果是数组会转为in查询，如果是字符串，需要转义，例如"name='Jo\'e' OR nick='Jo\'e'"
	 * @return bool						成功(TRUE)/失败(FALSE)
	 */
	public function update($data, $where = array()) {
		// where部分
		if (!empty($where))
		{
			if (is_array($where)) {
				foreach ($where as $where_key => $where_value) {
					// 如果是数组则转换为in查询
					if (is_array($where_value)) {
						$this->db->where_in($where_key, $where_value);
					}
					else {
						$this->db->where($where_key, $where_value);
					}
				}
			}
			else {
				$this->db->where($where);
			}
		}

		if (is_array($data)) {
			foreach ($data as $k => $v) {
				switch (substr($v, 0, 2)) {
					case '+=':
						$this->db->set($k, $k . "+" . str_replace("+=", "", $v), false);
						unset($data[$k]);
						break;
					case '-=':
						$this->db->set($k, $k . "-" . str_replace("-=", "", $v), false);
						unset($data[$k]);
						break;
					case '<>':
						$this->db->set($k, $k . "<>" . $v, false);
						unset($data[$k]);
						break;
					case '<=':
						$this->db->set($k, $k . "<=" . $v, false);
						unset($data[$k]);
						break;
					case '>=':
						$this->db->set($k, $k . ">=" . $v, false);
						unset($data[$k]);
						break;
					case '^1':
						$this->db->set($k, $k . "^1", false);
						unset($data[$k]);
						break;
					case 'in':
						if (substr($v, 0, 3) == "in(") {
							$this->db->where_in($k, $v, false);
							unset($data[$k]);
							break;
						} else {
							break;
						}

					default:
						$this->db->set($k, $v, true);
				}
			}
		}
		
		return $this->db->update($this->table_name, $data);
	}
	

	/**
	 * 执行删除记录操作
	 * @param array|string	$where 		查询条件，如果是数组，无需转义，如：array('name !=' => $name, 'id' => array(1,2,3), 'date >' => $date)，其中的value如果是数组会转为in查询，如果是字符串，需要转义，例如"name='Jo\'e' OR nick='Jo\'e'"
	 * @return 	bool					成功(TRUE)/失败(FALSE)
	 */
	public function delete($where) {
		return $this->db->delete($this->table_name, $where);
	}
}
