<?php
/**
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2015/12/3
 * Time: 16:22
 */

class Api extends MY_Controller {

	private  $_data;

	public function __construct()
	{
		parent::__construct();
		$this->config->load('config_api');
	}

	public function index(){

		$this->_data = $this->input->post();


		if($this->_data == NULL){
			//写入log
			$this->log->write_log_json(array("data"=>$this->_data),'api_error');
			$output['state'] = FALSE;
			$output['error'] = "接口信息为空";
			echo json_encode($output);
			exit();
		}

		//是否需要转换成数组
		if(!is_array($this->_data)){
			$this->_data = json_decode($this->_data,TRUE);
		}

		//判断类型是否存在
		if($this->_data['act'] == NULL){
			//写入log
			$this->log->write_log_json(array("act"=>$this->_data),'api_error');
			$output['state'] = FALSE;
			$output['error'] = "接口信息为空";
			echo json_encode($output);
			exit();
		}

		//写入log
		$this->log->write_log_json(array("data"=>$this->_data), 'api');

		$action = "e_".$this->_data['action'];

		if(!method_exists($this,$action)){
			$output['state'] = FALSE;
			$output['error'] = "接口名不合法";
			echo json_encode($output);
			exit();
		}
		else{
			$output = $this->$action();
		}

		header("Content-Type: text/html; charset=utf-8");
		echo json_encode($output);
		exit();
	}

	/**
	 * 取数据通用函数
	 * @param string $key 键名
	 * @param mixed $default 默认值
	 * @return string
	 * **/

	private function _get_data($key,$default){

		if(isset($this->_data[$key]) && $this->_data[$key] !== NULL)
		{
			return $this->_data[$key];
		}

		return $default;
	}


	/**
	 * 取出一个整数
	 * @param string	$key	键名
	 * @return int
	 */

	private function _get_int($key)
	{
		return $this->_get_data($key, 0);
	}


	/**
	 * 取出一个字符串
	 * @param string	$key	键名
	 * @return string
	 */

	private function _get_string($key)
	{
		return $this->_get_data($key, '');
	}


	/**
	 * 显示调用错误
	 * @return array
	 */

	private function _return_errorArray()
	{
		$api_code = 10010;
		$output['state'] = FALSE;
		$output['error'] = 'act类型错误';
		$output['ecode'] = $api_code."00400";
		return $output;
	}

	/**
	 * 取出一个数组
	 * @param string	$key	键名
	 * @return string
	 */

	private function _get_array($key)
	{
		return $this->_get_data($key, array());
	}


	// 个人信息相关接口
	private function e_D3ZgFGyuseP52HlW()
	{
		//eg:
		/**
		 * 加载api服务类
		 * $this->load->service('api_xxx_service');
		 *
		 * //调用相对应的函数
		 * return $this->api_xxx_service->xxx($this->_get_int('xxx'),$this->_get_string('xxx'),$this->_get_array('xxx'))
		 */
	}

	private function e_D3ZgFGyusewfetEtk()
	{

		if($this->_data['act'] == 'upload'){

			//加载api服务类
			$this->load->service('api_account_service');
			//调用相对应的函数
			return $this->api_account_service->upload_image($this->_data['uploadName']);

		}

			return 	$this->_return_errorArray();

	}



}