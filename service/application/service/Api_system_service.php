<?php
/**
 * 系统功能接口服务，如获取编码、检测版本更新等
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/7
 * Time: 21:19
 */

class Api_system_service extends Api_Service {

	/**
	 *
	 * 接口名称：get_area_state
	 * 接口功能：用户获取城市的开通状态
	 * 接口编号：0301
	 * 接口加密名称：phcFzKhmLudDDkhw
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$baidu			百度的城市编码，当使用百度城市编码时，下面三个参数可以不传，当使用下面三个参数时该参数请设为0
	 * @param int		$province		省份id，当不使用百度城市编码时需要该参数，必填
	 * @param int		$city			城市id，当不使用百度城市编码时需要该参数
	 * @param int		$district		区id，当不使用百度城市编码时需要该参数
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['area_state']: 调用成功时返回，最多包括以下三个字段
	 * 		open_state（0表示未开通，1表示开通但是人数不够，2表示开通且人数足够）
	 * 		current（open_state为1时才返回，表示当前人数）
	 * 		required（open_state为1时才返回，表示需要的人数）
	 * $output['province']: 当使用百度城市编码且调用成功时返回，表示百度城市编码对应的省份id
	 * $output['city']: 当使用百度城市编码且调用成功时返回，表示百度城市编码对应的城市id
	 *
	 */
	public function get_area_state($uid, $token, $baidu, $province, $city, $district)
	{
		$api_code = '0301';

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}

		// 如果使用百度城市编码
		if (is_id($baidu)) {
			$province = 0;
			$city = 0;
			$this->load->model('area_model');
			$this->area_model->get_province_and_city_from_baidu($baidu, $province, $city);
		}

		// 检查省份id是否合法
		if (!is_id($province)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "省份信息不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 如果是直辖市
		$this->load->model('area_model');
		if ($this->area_model->is_municipality($province)) {
			$city = 1;
		}

		// 检查城市id是否合法
		if (!is_id($city)) {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "城市信息不合法";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 修改用户个人信息
		$this->user_model->update_userdetail(array('province'=>$province, 'city'=>$city, 'district'=>$district), $uid);

		// 检查该城市状态
		$output['area_state'] = $this->area_model->get_area_state($province, $city);

		// 如果使用百度城市编码
		if (is_id($baidu)) {
			$output['state'] = TRUE;
			$output['province'] = $province;
			$output['city'] = $city;
		}

		$output['state'] = TRUE;
		return $output;
	}


	/**
	 *
	 * 接口名称：get_system_state
	 * 接口功能：用户获取系统状态
	 * 接口编号：0302
	 * 接口加密名称：ztMfk0d2er3ADQcW
	 *
	 * @param int		$platform		对应平台
	 * @param int		$version		版本号，格式：AABBCCC
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['lover_flag']: 为1表示正常模式，为0表示审查员模式
	 *
	 */
	public function get_system_state($platform, $version)
	{
		$output['state'] = TRUE;
		$output['lover_flag'] = $this->get_lover_flag($platform, $version);

		return $output;
	}

	/**
	 *
	 * 接口名称：report_bi
	 * 接口功能：汇报BI
	 * 接口编号：0303
	 * 接口加密名称：H718VoQ1qgy6f9HO
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param array		$bi_list		用户的BI，数组形式，每个元素是一条BI记录，包括type、value、id、time等字段
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 *
	 */
	public function report_bi($uid, $token, $bi_list)
	{
		$api_code = "0303";

		//BI列表不能为空
		if (empty($bi_list)) {
			$output['state'] = FALSE;
			$output['error'] = "BI列表为空";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}


		// 填充数据
		foreach($bi_list as &$bi) {
			$bi['uid'] = $uid;
		}
		unset($bi);

		// 添加bi
		$this->load->model('bi_model');
		$this->bi_model->insert_bi_batch($bi_list);

		$output['state'] = TRUE;
		return $output;
	}

	/**
	 *
	 * 接口名称：check_version
	 * 接口功能：检测版本更新
	 * 接口编号：0304
	 * 接口加密名称：vSL2rMSBnR6GOY8N
	 *
	 * @param int		$uid			用户id
	 * @param string	$token			用户token
	 * @param int		$platform		平台代码
	 * @param int		$version		版本号
	 * @return mixed
	 * $output['state']: 调用成功/失败
	 * $output['error']: 失败原因
	 * $output['ecode']: 错误代码
	 * $output['version']: 调用成功且有新版本时返回，新版本号
	 * $output['feature']: 调用成功且有新版本时返回，新版本特性
	 * $output['url']: 调用成功且有新版本时返回，新版本下载地址
	 *
	 */
	public function check_version($uid, $token, $platform, $version)
	{
		$api_code = "0304";

		// 用户鉴权
		$result = $this->user_model->check_authority($uid, $token);
		if ($result['state'] == FALSE) {
			return $result;
		}

		// 检测平台是否合法
		$platform = (int)$platform;
		if ($platform<PLATFORM_ANDROID || $platform>PLATFORM_IOS_APPSTORE) {
			$output['state'] = FALSE;
			$output['error'] = "平台不合法";
			$output['ecode'] = $api_code."001";
			return $output;
		}

		// 检测版本是否合法
		$version = (int)$version;
		if (!is_id($version)) {
			$output['state'] = FALSE;
			$output['error'] = "版本不合法";
			$output['ecode'] = $api_code."002";
			return $output;
		}

		// 如果是安卓1.1.3以下，那么不返回，因为这个版本有bug，版本更新会挂
		if ($platform==PLATFORM_ANDROID && $version <= 101003) {
			$output['state'] = TRUE;
			return $output;
		}

		// 检测是否有新版本
		$this->load->model('version_model');
		$result = $this->version_model->get_one(array('platform'=>$platform, 'version > '=>$version), FALSE, 'version,feature', 'version DESC');

		// 如果没有新版本
		if (empty($result)) {
			$output['state'] = TRUE;
			return $output;
		}


		// 如果有新版本，则构建返回值
		$output['version'] = $result['version'];
		$output['feature'] = $result['feature'];
		$output['url'] = "http://a.app.qq.com/o/simple.jsp?pkgname=com.seeme.lovers";

		$output['state'] = TRUE;
		return $output;
	}
}