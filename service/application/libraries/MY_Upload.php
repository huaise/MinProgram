<?php
/**
 * 拓展的文件上传类库
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/8
 * Time: 16:06
 */
defined('BASEPATH') OR exit('No direct script access allowed');

use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
use Qiniu\Storage\BucketManager;

class MY_Upload extends CI_Upload {

	/**
	 * 将多文件上传转换为CI_Upload兼容的形式
	 *
	 * @param	string		$key	要转换的多图上传的部分在FILE中的名称
	 * @return	NULL
	 */
	public function multifile_array($key)
	{
		if (count($_FILES) == 0) {
			return;
		}

		foreach ((array)$_FILES[$key]['name'] as $index => $filename) {
			if ($filename == '') {
				continue;
			}

			$temp = array();
			$temp['name'] = $filename;
			$temp['type'] = $_FILES[$key]['type'][$index];
			$temp['tmp_name'] = $_FILES[$key]['tmp_name'][$index];
			$temp['error'] = $_FILES[$key]['error'][$index];
			$temp['size'] = $_FILES[$key]['size'][$index];

			$_FILES[$key."__".$index] = $temp;
		}
	}


	/**
	 * 将文件上传的七牛
	 *
	 * @param	array		$file_array		要上传的文件数组，每个元素包含以下两个字段：path：要上传的文件的物理地址，即路径; key：该文件在服务器上面的路径，例如avatar/123abcd.png
	 * @param	string		$bucket_name	空间名称
	 * @return	NULL
	 */
	public function upload_to_qiniu($file_array, $bucket_name = '')
	{
		require_once APPPATH.'third_party/qiniu/autoload.php';

		if (empty($file_array)) {
			return;
		}

		//取得密钥
		$accessKey = config_item('qn_accesskey');
		$secretKey = config_item('qn_secretkey');

		// 构建鉴权对象
		$auth = new Auth($accessKey, $secretKey);

		//如果没有指定bucket_name 那么上传到默认的空间
		if ($bucket_name == '') {
			$bucket_name = config_item('qn_bucketname'); //上传的空间地址
		}

		// 生成上传 Token
		$token = $auth->uploadToken($bucket_name);

		// 初始化 UploadManager 对象并进行文件的上传。
		$uploadMgr = new UploadManager();

		//上传图片
		foreach ($file_array as $file) {
			try{
				$uploadMgr->putFile($token, $file['key'], $file['path']);
			}catch (Exception $e) {
				continue;
			}
		}

	}


	/**
	 * 将文件从七牛删除
	 *
	 * @param	array		$file_array		要上传的文件数组，每个元素是该文件在服务器上面的路径，例如avatar/123abcd.png
	 * @param	string		$bucket_name	空间名称
	 * @return	NULL
	 */
	public function delete_from_qiniu($file_array, $bucket_name = '')
	{
		require_once APPPATH.'third_party/qiniu/autoload.php';

		if (empty($file_array)) {
			return;
		}

		//取得密钥
		$accessKey = config_item('qn_accesskey');
		$secretKey = config_item('qn_secretkey');

		//如果没有指定bucket_name 那么上传到默认的空间
		if ($bucket_name == '') {
			$bucket_name = config_item('qn_bucketname'); //上传的空间地址
		}

		// 构建鉴权对象
		$auth = new Auth($accessKey, $secretKey);

		// 初始化BucketManager
		$bucketMgr = new BucketManager($auth);

		//上传图片
		foreach ($file_array as $file) {
			try{
				$bucketMgr->delete($bucket_name, $file);
			}catch (Exception $e) {
				continue;
			}
		}

	}
}
