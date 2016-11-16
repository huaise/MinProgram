<?php
/**
 * 公用服务
 * Created by PhpStorm.
 * User: CaiZhenYu
 * Date: 2016/4/28
 * Time: 11:55
 */

//调用腾讯youtu的文件
use TencentYoutuyun\Youtu;
use TencentYoutuyun\Conf;
//use TencentYoutuyun\Auth;

class Common_service extends MY_Service {

	public $_api_code = 1600;	//编码在看...

	public function __construct()
	{
		parent::__construct();
		$this->_log_tag = 'common';
	}

	/**
	 * 上传文件到七牛
	 *
	 * 请求方式： post
	 * 请求数据：
	 * @param string	$file_key		上传文件的key（默认upfile）
	 * @param string	$allowed_types	允许上传文件的类型（默认jpg|jpeg|png）
	 * @param string	$upload_path	上传文件存放目录（默认avatar）
	 *
	 * @return mixed
	 * $upload_result: 文件信息
	 *
	 */
	public function upload_file_to_qiniu($file_key = 'upfile', $allowed_types = 'jpg|jpeg|png', $upload_path = 'avatar')
	{
		$this->load->library('upload');
		$this->load->helper('string');

		//空验证
		if (!isset($_FILES[$file_key]['name']) || $_FILES[$file_key]['name']=='') {
			// 输出错误
			$output['state'] = FALSE;
			$output['error'] = "找不到上传的文件";
			$output['ecode'] = $this->_api_code."001";
			return $output;
		}

		//配置信息
		$config = array();
		$config['upload_path'] = './uploads/' . $upload_path;
		$config['allowed_types'] = $allowed_types;
		$config['file_name'] = time().strtolower(random_string('alnum',10));
		$config['overwrite'] = FALSE;
		$this->upload->initialize($config);

		// 如果上传失败
		if (!$this->upload->do_upload($file_key)) {
			//输出错误
			$output['state'] = FALSE;
			$output['error'] = "文件上传失败：".$this->upload->display_errors('','');
			$output['ecode'] = $this->_api_code."002";
			return $output;
		}

		$upload_result = $this->upload->data();

		// 上传七牛
		$this->upload->upload_to_qiniu(array(array('path'=>$config['upload_path'].'/'.$upload_result['file_name'],'key'=>$upload_path . '/'.$upload_result['file_name'])));

		return $upload_result;
	}

	/**
	 * 调用face++接口识别人脸 获取结果
	 *
	 * @param array	$upload_result	对图片做人脸识别
	 *
	 * @return mixed
	 * $avatar_state: 识别结果 1不通过 11通过
	 */
	public function get_face($upload_result)
	{
		//调用face++接口识别人脸
		//本地图像地址
		$img = config_item('base_url').'uploads/avatar/'.$upload_result['file_name'];
		//取得密钥
		$accessKey = FACEPP_KEY;
		$secretKey = FACEPP_SECRET;
		//构成调用接口链接
		$face_url = "http://apicn.faceplusplus.com/v2/detection/detect?api_key=$accessKey&api_secret=$secretKey&url=$img&attribute=glass,pose,gender,age,race,smiling";
		$face = file_get_contents($face_url);
		$face = json_decode($face, TRUE);
		// 根据是否识别成功，为头像状态进行赋值
		if (count($face['face'])!= 1) {
			$avatar_state = 1;
		}
		else {
			$avatar_state = 11;
		}

		$output['avatar_state'] = $avatar_state;
		if (isset($face['face'][0])) {
			$gender = $face['face'][0]['attribute']['gender']['value']=="Female"?2:1;
		} else {
			$gender = 0;
		}

		$output['gender'] = $gender;

		return $output;
	}

	/**
	 * 调用youtu接口识别人脸 获取结果
	 *
	 * @param array	$upload_result	对图片做人脸识别
	 *
	 * @return mixed
	 * $avatar_state: 识别结果 1不通过 11通过
	 */
	public function get_youtu($upload_result) {
		//导入第三方库
		require_once APPPATH."third_party/youtu/include.php";

		//本地图像地址
		$img = __DIR__.'/../../uploads/avatar/'.$upload_result['file_name'];
		Conf::setAppInfo(YOUTU_APPID, YOUTU_SECRET_ID, YOUTU_SECRET_KEY, YOUTU_USERID,conf::API_YOUTU_END_POINT);

/*		//首先获取人脸检测结果
		$result_face =  YouTu::detectface($img, 1);
		if (isset($result_face['face']) && !empty($result_face['face'])) {
			$face_flag = 1;
		} else {
			$face_flag = 0;
		}*/

		//然后进行内容检测
		$result_cont = YouTu::imagetag($img);
		$content_flag = 0;
		$male = 0;
		$female = 0;
		$group_photo = 0;
		$posters = 0;
		if (isset($result_cont['errorcode']) && $result_cont['errorcode'] == 0 && $result_cont['errormsg'] == "OK") {
			foreach ($result_cont['tags'] as $tag) {
				if ($tag['tag_name']=="男孩") {
					$male = $tag['tag_confidence'];
				}
				if ($tag['tag_name']=="女孩") {
					$female = $tag['tag_confidence'];
				}
				if ($tag['tag_name']=="合影") {
					$group_photo = $tag['tag_confidence'];
				}
				if ($tag['tag_name']=="海报") {
					$posters = $tag['tag_confidence'];
				}
			}
			if (($male>=10||$female>=10)&&$group_photo<=50&&$posters<=29) {
				$content_flag = 1;
			}
		}

		//判断头像审核状态
		if ($content_flag) {
			$avatar_state = 11;
		} else {
			$avatar_state = 1;
		}

		$output['avatar_state'] = $avatar_state;

		return $output;

	}

	/**
	 * 调用youtu接口识别人脸 获取颜值
	 *
	 * @param array	$upload_result	对图片做人脸识别
	 *
	 * @return mixed
	 * score: 颜值,1-100
	 */
	public function get_youtu_score($upload_result) {

		//导入第三方库
		require_once APPPATH."third_party/youtu/include.php";

		//本地图像地址
		$img = __DIR__.'/../../uploads/avatar/'.$upload_result['file_name'];
		Conf::setAppInfo(YOUTU_APPID, YOUTU_SECRET_ID, YOUTU_SECRET_KEY, YOUTU_USERID,conf::API_YOUTU_END_POINT);

		//首先获取人脸检测结果
		$result_face =  YouTu::detectface($img, 1);
		if (isset($result_face['face']) && !empty($result_face['face'])) {
			$score = $result_face['face'][0]['beauty'];
		} else {
			$score = 0;
		}

		$output['score'] = $score;
		return $output;

	}


	/**
	 * 统一输出方法 简单的封装下 目前只有json输出
	 *
	 * @param mixed		$meaage	输出信息
	 * @param string	$type	输出类型
	 *
	 * @return mixed
	 * 直接输出并终止
	 */
	public function show_message($meaage, $type = 'json')
	{
		if ($type == 'json'){
			die(json_encode($meaage));
		}
	}


	/**
	 * 验证码
	 * @param $data array 数组 如果传入非初始定义参数修改验证码样式等
	 * @return array 结果集
	 */

	public function captcha_code($data = array())
	{
		//加载辅助类
		$this->load->helper('captcha');

			//定义初始值
		$vals = array(
			'word' => rand(1000,100000),
			'img_path' => dirname(__FILE__) . '/../../assets/img/captcha/',
			'img_url' => config_item('base_url').'/assets/img/captcha/',
			//'font_path' => './path/to/fonts/texb.ttf',
			'img_width' => '120',
			'img_height' => 25,
			'expiration' => 120,
			'word_length' => 5,
			'font_size' => 16,
			'img_id' => 'Imageid',
			'pool' => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',

			// 配置颜色
			'colors' => array(
				'background' => array(255, 255, 255),
				'border' => array(255, 255, 255),
				'text' => array(0, 0, 0),
				'grid' => array(255, 40, 40)
			)
		);

		if(!empty($data)) {
			//如果数组不为空则选择后面的
			$vals = array_merge($vals,$data);
		}

		$cap = create_captcha($vals);

		return $cap;
	}

	/**
	 * 计分算法,自身条件
	 *
	 * @param array		$data	用户数据，包含gender,salary,look,height,age,education
	 * @return string	$score  分数
	 */
	public function get_my_score($data) {
		$salary_score = 3;
//		$look_score = 1;
//		$height_score = 1;
		$education_score = 3;
		$age_score = 1;

		$this->load->helper('date');
		$data['age'] = get_age($data['birthday']);
		$data['look'] = $data['look_grade_times']==0 ? 3 : ($data['look_grade_total']/$data['look_grade_times']);

		//计算收入的分数
		switch($data['salary']) {
			case 1:$salary_score = 3;break;
			case 2:$salary_score = 5;break;
			case 3:$salary_score = 6;break;
			case 4:$salary_score = 7;break;
			case 5:$salary_score = 8;break;
			case 6:$salary_score = 10;break;
		}

		//计算外貌的分数
		$look_score = 2*$data['look'];

		//计算学历的分数
		switch($data['education']) {
			case 1:$education_score = 4;break;
			case 2:$education_score = 6;break;
			case 3:$education_score = 7;break;
			case 4:$education_score = 8.5;break;
			case 5:$education_score = 9.5;break;
			case 6:$education_score = 10;break;
		}

		//计算男女不同算分
		if ($data['gender']==1) {
			//计算身高的分数
			if ($data['height']>=180) {
				$height_score = 10-0.2*($data['height']-180);
			} else {
				$height_score = 10-0.4*(180-$data['height']);
			}

			//计算年龄的分数
			if ($data['age']<23) {
				$age_score = 6;
			} else if ($data['age']>35 && $data['age']<=44) {
				$age_score = 6-(0.5*($data['age']-35));
			} else if ($data['age']>44) {
				$age_score = 1;
			}
			else {
				switch ($data['age']) {
					case 23:$age_score = 6.5;  break;
					case 24:$age_score = 7.5;  break;
					case 25:$age_score = 10;  break;
					case 26:$age_score = 9;  break;
					case 27:$age_score = 8;  break;
					case 28:$age_score = 9.5;  break;
					case 29:$age_score = 8.5;  break;
					case 30:$age_score = 7.5;  break;
					case 31:$age_score = 7.2;  break;
					case 32:$age_score = 7;  break;
					case 33:$age_score = 6.5;  break;
					case 34:$age_score = 6.3;  break;
					case 35:$age_score = 6;  break;
				}
			}
			//计算总分
			$score = 0.4*$salary_score+0.3*$look_score+0.1*$age_score+0.1*$height_score+0.1*$education_score;

		}
		else {
			//计算身高的分数
			if ($data['height']>=168) {
				$height_score = 10-0.2*($data['height']-168);
			} else {
				$height_score = 10-0.4*(168-$data['height']);
			}

			//计算年龄的分数
			if ($data['age']<20) {
				$age_score = 6;
			} else if ($data['age']>30 && $data['age']<=37) {
				$age_score = 6-(0.5*($data['age']-30));
			} else if ($data['age']>37) {
				$age_score = 1;
			}
			else {
				switch ($data['age']) {
					case 20:$age_score = 6.5;  break;
					case 21:$age_score = 7.5;  break;
					case 22:$age_score = 10;  break;
					case 23:$age_score = 9;  break;
					case 24:$age_score = 8;  break;
					case 25:$age_score = 9;  break;
					case 26:$age_score = 8;  break;
					case 27:$age_score = 7;  break;
					case 28:$age_score = 6;  break;
					case 29:$age_score = 5.5;  break;
					case 30:$age_score = 5;  break;
				}
			}
			//计算总分
			$score = 0.1*$salary_score+0.5*$look_score+0.2*$age_score+0.1*$height_score+0.1*$education_score;

		}

		$output['score'] = $score;
		$output['salary_score'] = $salary_score;
		$output['look_score'] = $look_score;
		$output['height_score'] = $height_score;
		$output['education_score'] = $education_score;
		$output['age_score'] = $age_score;

		return $output;
	}


	/**
	 * 计分算法,他人条件
	 *
	 * @param array		$data	用户数据，用户自身的基本数据以及要求的数据
	 * @return string	$score  分数
	 */
	public function get_other_score($data) {
		$target_salary_score = 5;
		$target_look_score = 4;
//		$target_height_score = 0;
		$target_education_score = 3;
//		$target_age_score = 0;

		//年龄转换
		$this->load->helper('date');
		$data['age'] = get_age($data['birthday']);

		//计算收入的分数
		switch($data['target_salary']) {
			case 1:$target_salary_score = 5;break;
			case 2:$target_salary_score = 5;break;
			case 3:$target_salary_score = 6;break;
			case 4:$target_salary_score = 7;break;
			case 5:$target_salary_score = 8;break;
			case 6:$target_salary_score = 10;break;
		}

		//计算外貌的分数
		switch($data['target_look']) {
			case 1:$target_look_score = 4;break;
			case 2:$target_look_score = 4;break;
			case 3:$target_look_score = 6;break;
			case 4:$target_look_score = 8;break;
			case 5:$target_look_score = 10;break;
		}

		//计算学历的分数
		switch($data['target_education']) {
			case 1:$target_education_score = 3;break;
			case 2:$target_education_score = 5;break;
			case 3:$target_education_score = 7;break;
			case 4:$target_education_score = 8.5;break;
			case 5:$target_education_score = 9.5;break;
			case 6:$target_education_score = 10;break;
		}

		//计算男女不同算分
		if ($data['gender']==1) {
			//进行身高的计算
			if ($data['target_height_max'] <= $data['height']) {
				$target_height_score = 10 - (($data['target_height_max']-$data['target_height_min']) + 0.2*($data['target_height_min']-$data['height']-10) );
			} else {
				$target_height_score = 10 - (0.5*($data['height']-$data['target_height_min']) - 0.1*($data['height']-$data['target_height_max']));
			}
			$target_height_score = $target_height_score <0?0:$target_height_score;
			$target_height_score = $target_height_score >10?10:$target_height_score;
			$target_height_score = $target_height_score <5?(4.9+0.2*$target_height_score):$target_height_score;

			//进行年龄的计算
			if ($data['target_age_max']<=$data['age']) {
				$target_age_score = 10 - (2*($data['target_age_max']-$data['target_age_min']) + 0.25 * ($data['age']-$data['target_age_max']-2));
			} else {
				$target_age_score = 10 - (1.5*($data['age']-$data['target_age_min']) - 0.15*($data['age']-$data['target_age_max']));
			}
			$target_age_score = $target_age_score <0?0:$target_age_score;
			$target_age_score = $target_age_score >10?10:$target_age_score;
			$target_age_score = $target_age_score <5?(4.9+0.2*$target_age_score):$target_age_score;

			//计算总分
			$target_score = 0.4*$target_salary_score+0.3*$target_look_score+0.1*$target_age_score+0.1*$target_height_score+0.1*$target_education_score;

		}
		else {
			//进行身高的计算
			if ($data['target_height_min'] >= $data['height']) {
				$target_height_score = 10 - (($data['target_height_max']-$data['target_height_min']) - 0.2*($data['target_height_min']-$data['height']-10) );
			} else {
				$target_height_score = 10 - (0.5*($data['target_height_max']-$data['height']) - 0.1*($data['target_height_min']-$data['height']));
			}
			$target_height_score = $target_height_score <0?0:$target_height_score;
			$target_height_score = $target_height_score >10?10:$target_height_score;
			$target_height_score = $target_height_score <5?(4.9+0.2*$target_height_score):$target_height_score;

			//进行年龄的计算
			if ($data['target_age_min']>=$data['age']) {
				$target_age_score = 10 - (2*($data['target_age_max']-$data['target_age_min']) - 0.5 * ($data['target_age_min']-$data['age']+2));
			} else {
				$target_age_score = 10 - (1.5*($data['target_age_max']-$data['age']) - 0.15*($data['target_age_min']-$data['age']));
			}
			$target_age_score = $target_age_score <0?0:$target_age_score;
			$target_age_score = $target_age_score >10?10:$target_age_score;
			$target_age_score = $target_age_score <5?(4.9+0.2*$target_age_score):$target_age_score;

			//计算总分
			$target_score = 0.1*$target_salary_score+0.5*$target_look_score+0.2*$target_age_score+0.1*$target_height_score+0.1*$target_education_score;
		}

		$output['target_score'] = $target_score;
		$output['target_salary_score'] = $target_salary_score;
		$output['target_look_score'] = $target_look_score;
		$output['target_height_score'] = $target_height_score;
		$output['target_education_score'] = $target_education_score;
		$output['target_age_score'] = $target_age_score;

		return $output;
	}


}