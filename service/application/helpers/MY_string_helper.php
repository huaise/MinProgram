<?php
/**
 * 拓展的字符串辅助函数
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/8
 * Time: 16:06
 */

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 格式邮箱
 *
 * @param	string	$email	要格式的邮箱，指针形式
 * @return	null
 */
function format_email(&$email) {
	//去除空白字符
	$email = trim($email);
	$email = trim_center($email);

	//$email = preg_replace('/x{a0}/', '', $email);
	//如果有/ 、, ，等分隔符，只取该分割符前面的部分
	mb_internal_encoding("UTF-8");
	$email_temp = "";
	$str_length = mb_strlen($email);
	for($i=0; $i<$str_length; $i++) {
		$str_temp = mb_substr($email, $i, 1);
		//检测到了分隔符
		if ($str_temp=="/" || $str_temp=="\\" || $str_temp=="," || $str_temp=="(" || $str_temp==")"
			|| $str_temp=="，" || $str_temp=="。" || $str_temp=="、" || $str_temp=="（" || $str_temp=="）") {
			break;
		}
		else{
			$email_temp .= $str_temp;
		}
	}
	$email = $email_temp;

	//全部转化为小写
	$email = strtolower($email);
}



/**
 * 对数组内容进行格式化。对尖括号分号进行转义，防止script脚本注入
 *
 * @param	string|array	$str_obj	需要转义的字符串或者数组，指针形式
 * @return	null
 */
function format_html(&$str_obj) {
	if (is_array($str_obj)) {
		foreach ($str_obj as $str_key => &$str_val) {
			format_html($str_val);
		}
		unset($str_val);
	}
	else{
		//此处为防止多次转移引起的符号混乱，先进行反转义
		$str_obj = html_entity_decode($str_obj,ENT_NOQUOTES,"utf-8");
		$str_obj = htmlentities($str_obj,ENT_NOQUOTES,"utf-8");
	}
}


/**
 * 对数组内容进行反转义，用于服务器向客户端发送数据时进行反转义
 *
 * @param	string|array	$html_obj	需要反转义的字符串或者数组，指针形式
 * @return	null
 */
function format_html_decode(&$html_obj) {
	if (is_array($html_obj)) {
		foreach ($html_obj as $html_key => &$html_val) {
			format_html_decode($html_val);
		}
		unset($html_val);
	}
	else{
		$html_obj = html_entity_decode($html_obj,ENT_NOQUOTES,"utf-8");
	}
}


/**
 * 格式化手机号
 *
 * @param	string|array	$phone	要格式的手机号，指针形式
 * @return	null
 */
function format_phone(&$phone) {
	mb_internal_encoding("UTF-8");

	//转为半角
	$phone = qj2bj($phone);

	//去除空白字符
	$phone = trim($phone);
	$phone = trim_center($phone);

	//去除+86
	$phone = str_replace("+86-", "", $phone);
	$phone = str_replace("+86", "", $phone);
	//去除-
	$phone = str_replace("-", "", $phone);

	//去除/以及/之后的内容
	while(true) {
		$index = mb_strpos($phone, "/");
		if ($index !== false) {
			$phone = mb_substr($phone, 0, $index);
		}
		else{
			break;
		}
	}

	//去除\以及\之后的内容
	while(true) {
		$index = mb_strpos($phone, "\\");
		if ($index !== false) {
			$phone = mb_substr($phone, 0, $index);
		}
		else{
			break;
		}
	}
	//去除括号（包括中英文）以及括号之间的内容，如果没有反括号，则去除括号之后的内容
	while(true) {
		$index_first = mb_strpos($phone, "（");
		if ($index_first !== false && $index_first>0) {
			$index_last = mb_strpos($phone, "）");
			if ($index_last !== false && $index_first<$index_last) {
				if (($index_last-$index_first)<6) {
					$phone = mb_substr($phone, 0, $index_first).mb_substr($phone, $index_first+1, $index_last-$index_first-1).mb_substr($phone, $index_last+1);
				}
				else{
					$phone = mb_substr($phone, 0, $index_first).mb_substr($phone, $index_last+1);
				}
			}
			else{
				$phone = mb_substr($phone, 0, $index_first);
			}

		}
		elseif ($index_first===0) {
			$index_last = mb_strpos($phone, "）");
			if ($index_last !== false && $index_first<$index_last) {
				$phone = mb_substr($phone, 1, $index_last-1).mb_substr($phone, $index_last+1);
			}
			else{
				$phone = str_replace("（", "", $phone);
			}
		}
		else{
			break;
		}
	}
	while(true) {
		$index_first = mb_strpos($phone, "(");
		if ($index_first !== false && $index_first>0) {
			$index_last = mb_strpos($phone, ")");
			if ($index_last !== false && $index_first<$index_last) {
				if (($index_last-$index_first)<6) {
					$phone = mb_substr($phone, 0, $index_first).mb_substr($phone, $index_first+1, $index_last-$index_first-1).mb_substr($phone, $index_last+1);
				}
				else{
					$phone = mb_substr($phone, 0, $index_first).mb_substr($phone, $index_last+1);
				}
			}
			else{
				$phone = mb_substr($phone, 0, $index_first);
			}

		}
		elseif ($index_first===0) {
			$index_last = mb_strpos($phone, ")");
			if ($index_last !== false && $index_first<$index_last) {
				$phone = mb_substr($phone, 1, $index_last-1).mb_substr($phone, $index_last+1);
			}
			else{
				$phone = str_replace("(", "", $phone);
			}
		}
		else{
			break;
		}
	}
}


/**
 * 获得一个由时间产生的路径，如20140906/12/10/35/
 *
 * @return	string 	产生的路径，以/结尾，不以/开头
 */
function generate_path_by_time() {
	$time = time();
	return date("Ymd", $time)."/".date("H", $time)."/".date("i", $time)."/".date("s", $time)."/";
}


/**
 * 提取出字符串中的数字部分
 *
 * @param	string	$str	字符串
 * @return	string	字符串中的数字部分
 */
function get_num_in_str($str) {
	preg_match_all('/[0-9]/', $str, $array);
	$num = implode('',$array[0]);
	return $num;
}


/**
 * 由性别编码得到性别字符串
 *
 * @param	int		$gender	性别编码，1为男，2为女
 * @return	string	性别字符串，比如"男","女"
 */
function get_str_from_gender($gender) {
	$output = "";
	if ($gender!="") {
		if ($gender=="1") {
			$output = "男";
		}
		elseif ($gender=="2") {
			$output = "女";
		}
		else{
			$output = "";
		}
	}
	return $output;
}

/**
 * 由平台代码得到平台字符串
 *
 * @param	int		$platform	平台代码
 * @return	string	平台字符串
 */
function get_string_from_platform($platform) {
	switch ($platform) {
		case PLATFORM_ANDROID:
			$string = '安卓';
			break;
		case PLATFORM_IOS_INHOUSE:
			$string = 'iOS(企业版)';
			break;
		case PLATFORM_IOS_APPSTORE:
			$string = 'iOS(AppStore版)';
			break;
		case PLATFORM_WEIXIN:
			$string = '微信公众号';
			break;
		default:
			$string = '未知平台';
			break;
	}

	return $string;
}


/**
 * 由版本号7位数字得到版本号的字符串
 *
 * @param	int		$version	版本号7位数字
 * @return	string	版本号的字符串
 */
function get_string_from_version($version) {
	$string = "";
	$string .= floor($version/100000);
	$string .= ".";
	$string .= floor($version/1000)%100;
	$string .= ".";
	$string .= $version%100;

	return $string;
}

/**
 * 手机是否合法
 *
 * @param	string	$phone	用户手机号
 * @return	bool	合法(TRUE)/不合法(FALSE)
 */
function is_phone($phone)
{
	return (preg_match('/^1\d{10}$/',$phone)) ? TRUE : FALSE;
}


/**
 * 电话号码是否合法
 *
 * @param	string	$phone	用户电话号码
 * @return	bool	合法(TRUE)/不合法(FALSE)
 */
function is_phone_general($phone)
{
	return (preg_match('/^[0-9-]{3,13}$/',$phone)) ? TRUE : FALSE;
}


/**
 * 判断字符串是不是UTF-8
 *
 * @param	string	$str	字符串
 * @return	bool	是(TRUE)/不是(FALSE)
 */
function is_utf8($str)
{
	$len=strlen($str);
	for($i=0; $i<$len; $i++) {
		$c=ord($str[$i]);
		if ($c > 128) {
			if (($c >= 254)) return false;
			elseif ($c >= 252) $bits=6;
			elseif ($c >= 248) $bits=5;
			elseif ($c >= 240) $bits=4;
			elseif ($c >= 224) $bits=3;
			elseif ($c >= 192) $bits=2;
			else return false;
			if (($i+$bits) > $len) return false;
			while($bits > 1) {
				$i++;
				$b=ord($str[$i]);
				if ($b < 128 || $b > 191) return false;
				$bits--;
			}
		}
	}
	return true;
}


/**
 * 全角替换成半角
 *
 * @param	string	$string	要转换的字符串
 * @return	string	转化后的字符串
 */
function qj2bj($string) {
	$qj2bj = array(
		'１' => '1', '２' => '2', '３' => '3', '４' => '4', '５' => '5',
		'６' => '6', '７' => '7', '８' => '8', '９' => '9', '０' => '0',
		'ａ' => 'a', 'ｂ' => 'b', 'ｃ' => 'c', 'ｄ' => 'd', 'ｅ' => 'e',
		'ｆ' => 'f', 'ｇ' => 'g', 'ｈ' => 'h', 'ｉ' => 'i', 'ｊ' => 'j',
		'ｋ' => 'k', 'ｌ' => 'l', 'ｍ' => 'm', 'ｎ' => 'n', 'ｏ' => 'o',
		'ｐ' => 'p', 'ｑ' => 'q', 'ｒ' => 'r', 'ｓ' => 's', 'ｔ' => 't',
		'ｕ' => 'u', 'ｖ' => 'v', 'ｗ' => 'w', 'ｘ' => 'x', 'ｙ' => 'y',
		'ｚ' => 'z', 'Ａ' => 'A', 'Ｂ' => 'B', 'Ｃ' => 'C', 'Ｄ' => 'D',
		'Ｅ' => 'E', 'Ｆ' => 'F', 'Ｇ' => 'G', 'Ｈ' => 'H', 'Ｉ' => 'I',
		'Ｊ' => 'J', 'Ｋ' => 'K', 'Ｌ' => 'L', 'Ｍ' => 'M', 'Ｎ' => 'N',
		'Ｏ' => 'O', 'Ｐ' => 'P', 'Ｑ' => 'Q', 'Ｒ' => 'R', 'Ｓ' => 'S',
		'Ｔ' => 'T', 'Ｕ' => 'U', 'Ｖ' => 'V', 'Ｗ' => 'W', 'Ｘ' => 'X',
		'Ｙ' => 'Y', 'Ｚ' => 'Z', '　' => ' '
	);
	return strtr($string, $qj2bj);
}

/**
 * 去除Emoji表情
 *
 * @param	string	$text	待处理字符串
 * @return	string			去除Emoji表情后的字符串
 */
function removeEmoji($text) {
	// Match Emoticons
	$regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
	$clean_text = preg_replace($regexEmoticons, '', $text);

	// Match Miscellaneous Symbols and Pictographs
	$regexSymbols = '/[\x{1F300}-\x{1F5FF}]/u';
	$clean_text = preg_replace($regexSymbols, '', $clean_text);

	// Match Transport And Map Symbols
	$regexTransport = '/[\x{1F680}-\x{1F6FF}]/u';
	$clean_text = preg_replace($regexTransport, '', $clean_text);

	return $clean_text;
}


/**
 * 去除字符串中间部分的空格
 *
 * @param	string	$str	字符串
 * @return	string			去除中间部分空格之后的字符串
 */
function trim_center($str) {
	$keywords = array ("\\u00a0" => "", "\\u3000" => "");
	$str = str_replace(" ", "", $str);
	$str = json_decode(str_replace(array_keys($keywords), array_values($keywords),json_encode($str)));
	return $str;
}
