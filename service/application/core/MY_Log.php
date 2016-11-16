<?php
/**
 * 拓展的核心日志类
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/6
 * Time: 20:56
 */

defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Log extends CI_Log {

	/**
	 * 以json形式写入日志
	 *
	 * @param	string|array	$msg 		要写入的内容，可以为字符串，也可以为数组
	 * @param	string			$tag		日志标签，会分文件存储
	 * @return	bool
	 */
	public function write_log_json($msg, $tag='')
	{
		if ($this->_enabled === FALSE)
		{
			return FALSE;
		}

		if ($tag=='') {
			$tag = 'default';
		}

		$filepath = $this->_log_path.'log-'.date('Y-m-d').'_'.$tag.'.'.$this->_file_ext;
		$message = '';

		if ( ! file_exists($filepath))
		{
			$newfile = TRUE;
			// Only add protection to php files
			if ($this->_file_ext === 'php')
			{
				$message .= "<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>\n\n";
			}
		}

		if ( ! $fp = @fopen($filepath, 'ab'))
		{
			return FALSE;
		}

		// Instantiating DateTime with microseconds appended to initial date is needed for proper support of this format
		if (strpos($this->_date_fmt, 'u') !== FALSE)
		{
			$microtime_full = microtime(TRUE);
			$microtime_short = sprintf("%06d", ($microtime_full - floor($microtime_full)) * 1000000);
			$date = new DateTime(date('Y-m-d H:i:s.'.$microtime_short, $microtime_full));
			$date = $date->format($this->_date_fmt);
		}
		else
		{
			$date = date($this->_date_fmt);
		}

		// 支持数组和字符串
		if (!is_array($msg)) {
			$message_array = array();
			$message_array['log'] = $msg;
		}
		else {
			$message_array = $msg;
		}

		$message_array['log_date'] = $date;
		$message_array['log_url'] = $_SERVER["PHP_SELF"];

		$message .= json_encode($message_array, JSON_UNESCAPED_UNICODE)."\n";

		flock($fp, LOCK_EX);

		$result = FALSE;
		for ($written = 0, $length = strlen($message); $written < $length; $written += $result)
		{
			if (($result = fwrite($fp, substr($message, $written))) === FALSE)
			{
				break;
			}
		}

		flock($fp, LOCK_UN);
		fclose($fp);

		if (isset($newfile) && $newfile === TRUE)
		{
			chmod($filepath, $this->_file_permissions);
		}

		return is_int($result);
	}

	/**
	 * 以字符串形式写入日志
	 *
	 * @param	string			$message	要写入的内容，字符串
	 * @param	string			$tag		日志标签，会分文件存储
	 * @return	bool
	 */
	public function write_log_string($message, $tag='')
	{
		if ($this->_enabled === FALSE)
		{
			return FALSE;
		}

		if ($tag=='') {
			$tag = 'default';
		}

		$filepath = $this->_log_path.'log-'.$tag.'.'.$this->_file_ext;

		return file_put_contents($filepath, $message);
	}

	/**
	 * 获取日志
	 *
	 * @param	string			$tag		日志标签，会分文件存储
	 * @return	bool
	 */
	public function get_log_string($tag='')
	{
		if ($this->_enabled === FALSE)
		{
			return FALSE;
		}

		if ($tag=='') {
			$tag = 'default';
		}

		$filepath = $this->_log_path.'log-'.$tag.'.'.$this->_file_ext;

		if ( ! file_exists($filepath))
		{
			return FALSE;
		}

		return file_get_contents($filepath);
	}



	/**
	 * 解析以json形式记录的日志
	 *
	 * @param	string	$str	log字符串
	 * @return	array
	 * 返回结果: 键值对
	 */
	public function parse_log_json($str) {
		$output = json_decode($str, TRUE);
		$output['timestamp'] = strtotime($output['log_time']);
		return $output;
	}

}
