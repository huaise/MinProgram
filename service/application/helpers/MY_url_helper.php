<?php
/**
 * 拓展的URL辅助函数
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/8
 * Time: 16:06
 */

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 当URL中缺少协议前缀部分时，保证链接以 http:// 开头
 * CodeIgniter的prep_url方法，不支持"itms-services:///"，建议用该方法
 *
 * @param	string	$url	需要处理的url，指针形式
 * @return	null
 */
function format_url(&$url) {
	$url = trim($url);

	//检测有无itms-services:///
	if (strpos($url, "itms-services:///")!==false) {
		//do nothing
		return;
	}

	if ($url === 'http://' OR $url === '')
	{
		$url = '';
		return;
	}

	$parse_result = parse_url($url);

	if ( ! $parse_result OR ! isset($parse_result['scheme']))
	{
		$url = 'http://'.$url;
	}
}



/**
 * 获取图片、语音等附件的完整路径
 *
 * @param	string	$filename		附件的文件名
 * @param	string	$folder_name	附件的文件夹名称，如avatar,bbs等，不要包含路径分隔符/或\
 * @param	string	$style			样式名称，会拼接在路径的最后
 * @return	string	完整路径
 */
function get_attachment_url($filename, $folder_name, $style='') {
	$output = "";

	if ($filename == '') {
		return $output;
	}

	$output = config_item('attachment_url').$folder_name."/".$filename.$style;

	return $output;
}


/**
 * 根据邮箱地址，找到邮箱主页
 *
 * @param	string	$email		邮箱地址
 * @return	string	邮箱主页链接
 */
function get_email_url($email) {
	$url = "";

	//首先获取邮箱后缀
	$str_temp = explode("@", $email);
	$email_suffix = $str_temp[count($str_temp)-1];

	if ($email_suffix!="") {
		$mapping_table = Array(Array("163.com", "http://mail.163.com")
		,Array("126.com", "http://www.126.com")
		,Array("sina.", "http://mail.sina.com.cn")
		,Array("qq.com", "https://mail.qq.com")
		,Array("gmail.com", "https://mail.google.com")
		,Array("hotmail.com", "https://www.hotmail.com")
		,Array("yahoo.", "https://mail.yahoo.com")
		,Array("msn.com", "https://mail.msn.com")
		,Array("21cn.com", "http://mail.21cn.com")
		,Array("sohu.com", "http://mail.sohu.com")
		,Array("tom.com", "http://mail.tom.com")
		,Array("etang.com", "http://mail.etang.info")
		,Array("eyou.com", "http://www.eyou.com")
		,Array("56.com", "http://www.56.com/home.html")
		,Array("chinaren.com", "http://mail.chinaren.com")
		,Array("sogou.com", "http://mail.sogou.com")
		,Array("zju.edu.cn", "http://zjuem.zju.edu.cn")
		,Array("citiz.com", "http://www.citiz.net")
		,Array("aol.com", "http://mail.aol.com")
		,Array("mail.com", "http://www.mail.com/")
		,Array("inbox.com", "http://www.inbox.com/"));
		foreach ($mapping_table as $email_service) {
			if (stristr($email_service[0], $email_suffix)) {
				$url = $email_service[1];
				break;
			}
		}
	}

	if ($url=="") {
		$url = "http://www.".$email_suffix;
	}

	return $url;
}


/**
 * 转短链接
 *
 * @param	string	$long_url	要转换的长链接
 * @return	string	短链接
 */
function get_shorturl($long_url) {
	$apiUrl='https://api.weibo.com/2/short_url/shorten.json?source=728900748&url_long='.$long_url;
	$response = file_get_contents($apiUrl);
	$json = json_decode($response,true);
	return $json['urls'][0]['url_short'];
}

/**
 * 传入url获取参数
 *
 * @param	string	$url	要获取参数的url
 * @return	array	参数的数组 key为参数名，value为参数值
 */
function get_para_from_url($url) {
	$url_arr = parse_url($url);
	//不存在返回空
	if (!isset($url_arr['query']))
		return array();
	$queryParts = explode('&', $url_arr['query']);
	$params = array();
	foreach ($queryParts as $param)
	{
		$item = explode('=', $param);
		$params[$item[0]] = $item[1];
	}
	return $params;
}