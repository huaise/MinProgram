<?php
/**
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/4
 * Time: 16:22
 */

class Area_model extends MY_Model {

	public function __construct()
	{
		$this->table_name = 'p_area_province';
		parent::__construct();
	}

	/**
	 * 通过省份id获取一个省份
	 * @param int			$pid 			要查找的省份id
	 * @param string		$data 			需要查询的字段值，如：name,gender,birthday，不需要加`，会自动加上
	 * @return array		查询结果集数组，如果不存在则返回NULL
	 */
	public function get_province_by_id($pid, $data='*')
	{
		if (!is_id($pid)) {
			return NULL;
		}

		$this->set_table_name('p_area_province');
		return $this->get_one(array('pid =' => $pid), FALSE, $data);
	}

	/**
	 * 通过省份id和城市id获取一个城市
	 * @param int			$pid 			要查找的省份id
	 * @param int			$cid 			要查找的城市id
	 * @param string		$data 			需要查询的字段值，如：name,gender,birthday，不需要加`，会自动加上
	 * @return array		查询结果集数组，如果不存在则返回NULL
	 */
	public function get_city_by_id($pid, $cid, $data='*')
	{
		if (!is_id($pid)) {
			return NULL;
		}
		if (!is_id($cid)) {
			return NULL;
		}

		$this->set_table_name('p_area_city');
		return $this->get_one(array('pid =' => $pid, 'cid =' => $cid), FALSE, $data);
	}

	/**
	 * 通过省份id、城市id和区id获取一个区
	 * @param int			$pid 			要查找的省份id
	 * @param int			$cid 			要查找的城市id
	 * @param int			$did 			要查找的区id
	 * @param string		$data 			需要查询的字段值，如：name,gender,birthday，不需要加`，会自动加上
	 * @return array		查询结果集数组，如果不存在则返回NULL
	 */
	public function get_district_by_id($pid, $cid, $did, $data='*')
	{
		if (!is_id($pid)) {
			return NULL;
		}
		if (!is_id($cid)) {
			return NULL;
		}
		if (!is_id($did)) {
			return NULL;
		}

		$this->set_table_name('p_area_district');
		return $this->get_one(array('pid =' => $pid, 'cid =' => $cid, 'did =' => $did), FALSE, $data);
	}

	/**
	 * 通过省份id、城市id和区id获取一个表示省市区的字符串
	 * @param int			$pid 			要查找的省份id
	 * @param int			$cid 			要查找的城市id
	 * @param int			$did 			要查找的区id
	 * @param string		$glue 			分隔符，如传入'-'，会返回浙江-杭州-西湖区
	 * @return array		一个表示省市区的字符串
	 */
	public function get_string_by_id($pid, $cid, $did, $glue=' ')
	{
		$province = $this->get_province_by_id($pid, 'name');
		$city = $this->get_city_by_id($pid, $cid, 'name');
		$district = $this->get_district_by_id($pid, $cid, $did, 'name');

		$temp_array = array();
		if (isset($province['name'])) {
			$temp_array[] = $province['name'];
		}
		if (isset($city['name'])) {
			$temp_array[] = $city['name'];
		}
		if (isset($district['name'])) {
			$temp_array[] = $district['name'];
		}

		return implode($glue, $temp_array);
	}

	/**
	 * 通过省份id判断是否是直辖市
	 * @param int			$pid 			要查找的省份id
	 * @return bool			TRUE（是直辖市）/FALSE（不是直辖市）
	 */
	public function is_municipality($pid)
	{
		// 北京、天津、上海、重庆、香港、澳门是直辖市
		return in_array($pid, array(11,12,31,50,81,82));
	}

	/**
	 * 通过省份id、城市id判断两个城市是否是同城
	 * @param int			$pid_1 			第一个城市的省份id
	 * @param int			$cid_1 			第一个城市的城市id
	 * @param int			$pid_2 			第二个城市的省份id
	 * @param int			$cid_2 			第二个城市的城市id
	 * @return bool			TRUE（是同城）/FALSE（不是同城）
	 */
	public function is_same_city($pid_1, $cid_1, $pid_2, $cid_2)
	{
		$pid_1 = (int)$pid_1;
		$cid_1 = (int)$cid_1;
		$pid_2 = (int)$pid_2;
		$cid_2 = (int)$cid_2;

		if ($pid_1 != $pid_2) {
			return FALSE;
		}

		if ($this->is_municipality($pid_1)) {
			return TRUE;
		}

		return ($cid_1==$cid_2);

	}


	/**
	 * 由百度的城市编码得到省份和城市id
	 * @param int			$baidu 			百度的城市编码
	 * @param int			$pid 			省份id，指针形式
	 * @param int			$cid 			城市id，指针形式
	 * @return null
	 */
	public function get_province_and_city_from_baidu($baidu, &$pid, &$cid) {
		global $baidu_province;
		global $baidu_city;
		require_once APPPATH.'third_party/baiducity.php';
		$pid = ( isset($baidu_province[$baidu]) ? $baidu_province[$baidu] : 0);
		$cid = ( isset($baidu_city[$baidu]) ? $baidu_city[$baidu] : 0);
	}

	/**
	 * 获取js文件的内容，用于三级联动下拉框
	 * @param bool			$download 			是否下载为文件
	 * @return mixed		如果不下载，则返回文件内容，为一个字符串，如果下载返回值为空
	 */
	public function get_js_file_content($download = FALSE)
	{
		// 初始化变量
		$content = 'var csa1=[];var csa2=[];var csa3=[];csa1[0]="请选择";csa2[0]=[];csa2[0][0]="请选择";csa3[0]=[];csa3[0][0]=[];csa3[0][0][0]="请选择";';

		// 省份信息
		$this->set_table_name('p_area_province');
		$list = $this->select(NULL, FALSE, 'pid,name', '', 'pid ASC');
		foreach ($list as $row) {
			$content .= 'csa1['.$row['pid'].']="'.$row['name'].'";';
		}

		// 城市信息
		$current_pid = 0;
		$this->set_table_name('p_area_city');
		$list = $this->select(NULL, FALSE, 'pid,cid,name', '', 'pid ASC, cid ASC');
		foreach ($list as $row) {
			if ($row['pid'] != $current_pid) {
				$current_pid = $row['pid'];
				$content .= 'csa2['.$row['pid'].']=[];csa2['.$row['pid'].'][0]="请选择";';
			}

			$content .= 'csa2['.$row['pid'].']['.$row['cid'].']="'.$row['name'].'";';
		}

		// 区信息
		$current_pid = 0;
		$current_cid = 0;
		$this->set_table_name('p_area_district');
		$list = $this->select(NULL, FALSE, 'pid,cid,did,name', '', 'pid ASC, cid ASC, did ASC');
		foreach ($list as $row) {
			if ($row['pid'] != $current_pid) {
				$current_pid = $row['pid'];
				$current_cid = 0;
				$content .= 'csa3['.$row['pid'].']=[];';
			}

			if ($row['cid'] != $current_cid) {
				$current_cid = $row['cid'];
				$content .= 'csa3['.$row['pid'].']['.$row['cid'].']=[];';
			}

			$content .= 'csa3['.$row['pid'].']['.$row['cid'].']['.$row['did'].']="'.$row['name'].'";';
		}

		if ($download) {
			$this->load->helper('download');
			$name = 'area_data_min.js';
			force_download($name, $content);
			return NULL;
		}
		else {
			return $content;
		}
	}

	/**
	 * 获取安卓源代码文件
	 * @param bool			$download 			是否下载为文件
	 * @return mixed		如果不下载，则返回文件内容，为一个字符串，如果下载返回值为空
	 */
	public function get_android_code($download = FALSE)
	{
		// 初始化变量
		$content = '';
		if ($download) {
			$break = PHP_EOL;
		}
		else {
			$break = '<br>';
		}

		// 省份信息
		$formatted_list = array();
		$this->set_table_name('p_area_province');
		$list = $this->select(NULL, FALSE, 'pid, name', '', 'pid ASC', '');
		foreach ($list as $row) {
			$formatted_list[$row['pid']] = $row['name'];
		}

		$content .= "province = new JSONObject(\"".str_replace('"', '\"', json_encode($formatted_list, JSON_UNESCAPED_UNICODE))."\");";
		$content .= $break;

		// 城市信息
		$formatted_list = array();
		$this->set_table_name('p_area_city');
		$list = $this->select(NULL, FALSE, 'pid,cid,name', '', 'pid ASC, cid ASC');
		foreach ($list as $row) {
			$formatted_list[$row['pid']][$row['cid']] = $row['name'];
		}
		$content .= "city = new JSONObject(\"".str_replace('"', '\"', json_encode($formatted_list, JSON_UNESCAPED_UNICODE))."\");";
		$content .= $break;

		// 区信息
		$formatted_list = array();
		$this->set_table_name('p_area_district');
		$list = $this->select(NULL, FALSE, 'pid,cid,did,name', '', 'pid ASC, cid ASC, did ASC');
		foreach ($list as $row) {
			$formatted_list[$row['pid']][$row['cid']][$row['did']] = $row['name'];
		}
		$content .= "district = new JSONObject(\"".str_replace('"', '\"', json_encode($formatted_list, JSON_UNESCAPED_UNICODE))."\");";
		$content .= $break;

		if ($download) {
			$this->load->helper('download');
			$name = 'android.txt';
			force_download($name, $content);
			return NULL;
		}
		else {
			return $content;
		}
	}


	/**
	 * 获取iOS源代码文件
	 * @param bool			$download 			是否下载为文件
	 * @return mixed		如果不下载，则返回文件内容，为一个字符串，如果下载返回值为空
	 */
	public function get_ios_code($download = FALSE)
	{
		// 初始化变量
		$content = '<dict>';

		// 省份信息
		$content .= '<key>province</key><array>';
		$this->set_table_name('p_area_province');
		$list = $this->select(NULL, FALSE, 'pid, name', '', 'pid ASC', '');
		foreach ($list as $row) {
			$content .= '<dict>';
			$content .= '<key>pid</key><string>'.$row['pid'].'</string>';
			$content .= '<key>name</key><string>'.$row['name'].'</string>';
			$content .= '</dict>';
		}
		$content .= '</array>';

		// 城市信息
		$content .= '<key>city</key><array>';
		$this->set_table_name('p_area_city');
		$list = $this->select(NULL, FALSE, 'pid,cid,name', '', 'pid ASC, cid ASC');
		foreach ($list as $row) {
			$content .= '<dict>';
			$content .= '<key>pid</key><string>'.$row['pid'].'</string>';
			$content .= '<key>cid</key><string>'.$row['cid'].'</string>';
			$content .= '<key>name</key><string>'.$row['name'].'</string>';
			$content .= '</dict>';
		}
		$content .= '</array>';

		// 区信息
		$content .= '<key>district</key><array>';
		$this->set_table_name('p_area_district');
		$list = $this->select(NULL, FALSE, 'pid,cid,did,name', '', 'pid ASC, cid ASC, did ASC');
		foreach ($list as $row) {
			$content .= '<dict>';
			$content .= '<key>pid</key><string>'.$row['pid'].'</string>';
			$content .= '<key>cid</key><string>'.$row['cid'].'</string>';
			$content .= '<key>did</key><string>'.$row['did'].'</string>';
			$content .= '<key>name</key><string>'.$row['name'].'</string>';
			$content .= '</dict>';
		}
		$content .= '</array>';

		$content .= '</dict>';

		if ($download) {
			$this->load->helper('download');
			$name = 'area.plist';
			force_download($name, $content);
			return NULL;
		}
		else {
			return htmlentities($content);
		}
	}

}