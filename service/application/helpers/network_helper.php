<?php
/**
 * 网络辅助函数
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/8
 * Time: 16:06
 */

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 发起一个HTTP POST请求
 *
 * @param	string			$url		完整url，以http://、https://或者ssl://开头
 * @param	array|string	$data		数组形式的参数，键值对格式，或是json字符串
 * @param	bool			$block_flag	0为非阻塞模式，1为阻塞模式
 * @param	string			$referer	来路信息
 * @return	mixed
 * 返回结果: 1. $output['state']: 成功(TRUE)/失败(FALSE)，成功时还会返回$output['header']和$output['content']
 * 2.$output['error']：失败的原因
 * 3.$output['header']：返回信息的头部
 * 4.$output['content']: 返回信息的内容
 */
function post_request($url, $data, $block_flag, $referer='') {
	// Convert the data array into URL Parameters like a=b&foo=bar etc.
	if (is_array($data)) {
		$data = http_build_query($data);
	}

	$result = '';
	$header = '';
	$content = '';

	// parse the given URL
	$url = parse_url($url);

	// extract host and path:
	$host = $url['host'];
	$port = isset($url['port']) ? $url['port'] : 80;
	$path = $url['path'];
	if ($url['query'] != '') {
		$path .= '?'.$url['query'];
	}

	if ($url['scheme'] == 'ssl' || $url['scheme'] == 'https') {
		$port = 443;
		$host = 'ssl://'.$host;
	}

	// open a socket connection on port 80 - timeout: 30 sec
	$fp = fsockopen($host, $port, $errno, $errstr, 30);

	if ($fp) {
		stream_set_blocking($fp, 1);

		$para_array['url'] = $url;

		// send the request headers:
		fputs($fp, "POST $path HTTP/1.1\r\n");
		fputs($fp, "Host: $host\r\n");

		if ($referer != '')
			fputs($fp, "Referer: $referer\r\n");

		fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
		fputs($fp, "Content-length: ". strlen($data) ."\r\n");
		fputs($fp, "Connection: close\r\n\r\n");
		fputs($fp, $data);
		if ($block_flag) {
			while(!feof($fp)) {
				// receive the results of the request
				$result .= fgets($fp, 128);
			}
		}
	}
	else {
		$output['state'] = FALSE;
		$output['error'] = "$errstr ($errno)";
		return $output;
	}

	// close the socket connection:
	fclose($fp);

	// split the result header from the content
	if ($block_flag) {
		$result = explode("\r\n\r\n", $result, 2);
		$header = isset($result[0]) ? $result[0] : '';
		$content = isset($result[1]) ? $result[1] : '';
	}

	// return as structured array:
	$output['state'] = TRUE;
	$output['header'] = $header;
	$output['content'] = $content;

	return $output;
}


/**
 * 使用curl发起一个HTTP POST请求
 *
 * @param	string	$url		完整url，以http://开头
 * @param	array	$data		数组形式的参数，键值对格式
 * @param	array	$file		上传的文件,数组形式，如array('upfile'=>'1.jpg')或者array('upfile'=>array('1.jpg','2.jpg'))
 * @param	bool	$block_flag	0为非阻塞模式，1为阻塞模式
 * @param	string	$referer	来路信息
 * @param	int		$timeout	超时时间，以秒为单位
 * @return	mixed				对方服务器返回的结果
 */
function post_request_by_curl($url, $data, $file, $block_flag, $referer='', $timeout = 30) {

	if (!empty($file)) {
		$CI = get_instance();
		$CI->load->helper('mime');

		foreach ($file as $key=>$value) {
			if (is_array($value)) {
				foreach ($value as $sub_key=>$sub_value) {
					$path_parts = pathinfo($sub_value);
					$cfile = curl_file_create(realpath($sub_value), getMimeType($sub_value), $path_parts['basename']); // try adding
					$data[$key.'['.$sub_key.']'] = $cfile;
				}
			}
			else {
				$path_parts = pathinfo($value);
				$cfile = curl_file_create(realpath($value), getMimeType($value), $path_parts['basename']); // try adding
				$data[$key] = $cfile;
			}
		}
	}

	$ch = curl_init();															//初始化curl
	curl_setopt($ch, CURLOPT_URL, $url);										//抓取指定网页
	curl_setopt($ch, CURLOPT_HEADER, false);									//设置header
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, $block_flag);						//要求结果为字符串且输出到屏幕上
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);								//超时时间
	curl_setopt($ch, CURLOPT_POST, true);										//post提交方式
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);								//设置post参数
	curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);

	if ($referer != '') {
		curl_setopt($ch, CURLOPT_REFERER, $referer);							//设置referer参数
	}

	$data = curl_exec($ch);														//运行curl
	curl_close($ch);

	return $data;
}

/**
 * 提交https的post请求
 *
 * @param	string	$url		post的地址
 * @param	string	$data		post的数据内容，json格式
 * @param	int		$timeout	超时时间，以秒为单位
 * @param	bool	$CA			是否只信任CA颁布的证书
 * @return	mixed				对方服务器返回的结果
 */
function post_request_https($url, $data, $timeout = 30, $CA = false) {
	$cacert = getcwd() . '/cacert.pem'; //CA根证书
	$SSL = substr($url, 0, 8) == "https://" ? true : false;

	$CI =& get_instance();
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout - 2);
	if ($SSL && $CA) {
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // 只信任CA颁布的证书
		curl_setopt($ch, CURLOPT_CAINFO, $cacert); // CA根证书（用来验证的网站证书是否是CA颁布）
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // 检查证书中是否设置域名，并且是否与提供的主机名匹配
	} else if ($SSL && !$CA) {
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 信任任何证书
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); // 检查证书中是否设置域名
	}
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:')); //避免data数据过长问题
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	//curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); //data with URLEncode

	$ret = curl_exec($ch);
	//var_dump(curl_error($ch));  //查看报错信息
	$CI->log->write_log_json(array("ret"=>$ret),"post");
	$CI->log->write_log_json(array("err"=>curl_error($ch)),"post");

	curl_close($ch);
	return $ret;
}
