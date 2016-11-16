<?php
/**
 * MIME辅助函数
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/8
 * Time: 16:06
 */

defined('BASEPATH') OR exit('No direct script access allowed');


/**
 * 获取Mime类型
 *
 * @param	string	$path		文件路径
 * @return	string				Mime类型
 */
function getMimeType($path)
{
	$mime = array(
		//applications
		'ai' => 'application/postscript',
		'eps' => 'application/postscript',
		'exe' => 'application/octet-stream',
		'doc' => 'application/vnd.ms-word',
		'xls' => 'application/vnd.ms-excel',
		'ppt' => 'application/vnd.ms-powerpoint',
		'pps' => 'application/vnd.ms-powerpoint',
		'pdf' => 'application/pdf',
		'xml' => 'application/xml',
		'odt' => 'application/vnd.oasis.opendocument.text',
		'swf' => 'application/x-shockwave-flash',
		// archives
		'gz' => 'application/x-gzip',
		'tgz' => 'application/x-gzip',
		'bz' => 'application/x-bzip2',
		'bz2' => 'application/x-bzip2',
		'tbz' => 'application/x-bzip2',
		'zip' => 'application/zip',
		'rar' => 'application/x-rar',
		'tar' => 'application/x-tar',
		'7z' => 'application/x-7z-compressed',
		// texts
		'txt' => 'text/plain',
		'php' => 'text/x-php',
		'html' => 'text/html',
		'htm' => 'text/html',
		'js' => 'text/javascript',
		'css' => 'text/css',
		'rtf' => 'text/rtf',
		'rtfd' => 'text/rtfd',
		'py' => 'text/x-python',
		'java' => 'text/x-java-source',
		'rb' => 'text/x-ruby',
		'sh' => 'text/x-shellscript',
		'pl' => 'text/x-perl',
		'sql' => 'text/x-sql',
		// images
		'bmp' => 'image/x-ms-bmp',
		'jpg' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'gif' => 'image/gif',
		'png' => 'image/png',
		'tif' => 'image/tiff',
		'tiff' => 'image/tiff',
		'tga' => 'image/x-targa',
		'psd' => 'image/vnd.adobe.photoshop',
		//audio
		'mp3' => 'audio/mpeg',
		'mid' => 'audio/midi',
		'ogg' => 'audio/ogg',
		'mp4a' => 'audio/mp4',
		'wav' => 'audio/wav',
		'wma' => 'audio/x-ms-wma',
		// video
		'avi' => 'video/x-msvideo',
		'dv' => 'video/x-dv',
		'mp4' => 'video/mp4',
		'mpeg' => 'video/mpeg',
		'mpg' => 'video/mpeg',
		'mov' => 'video/quicktime',
		'wm' => 'video/x-ms-wmv',
		'flv' => 'video/x-flv',
		'mkv' => 'video/x-matroska'
	);

	$fmime = _getMimeDetect();
	$type = '';
	switch ($fmime) {
		case 'finfo':
			$finfo = finfo_open(FILEINFO_MIME);
			if ($finfo)
				$type = @finfo_file($finfo, $path);
			break;
		case 'mime_content_type':
			$type = mime_content_type($path);
			break;
		case 'linux':
			$type = exec('file -ib ' . escapeshellarg($path));
			break;
		case 'bsd':
			$type = exec('file -Ib ' . escapeshellarg($path));
			break;
		default:
			$pinfo = pathinfo($path);
			$ext = isset($pinfo['extension']) ? strtolower($pinfo['extension']) : '';
			$type = isset($mime[$ext]) ? $mime[$ext] : 'unkown';
			break;
	}
	$type = explode(';', $type);
	//需要加上这段，因为如果使用mime_content_type函数来获取一个不存在的$path时会返回'application/octet-stream'
	if ($fmime != 'internal' AND $type[0] == 'application/octet-stream') {
		$pinfo = pathinfo($path);
		$ext = isset($pinfo['extension']) ? strtolower($pinfo['extension']) : '';
		if (!empty($ext) AND !empty($mime[$ext])) {
			$type[0] = $mime[$ext];
		}
	}
	return $type[0];
}

function _getMimeDetect()
{
	if (class_exists('finfo')) {
		return 'finfo';
	} else if (function_exists('mime_content_type')) {
		return 'mime_content_type';
	} else if (function_exists('exec')) {
		$result = exec('file -ib ' . escapeshellarg(__FILE__));
		if (0 === strpos($result, 'text/x-php') OR 0 === strpos($result, 'text/x-c++')) {
			return 'linux';
		}
		$result = exec('file -Ib ' . escapeshellarg(__FILE__));
		if (0 === strpos($result, 'text/x-php') OR 0 === strpos($result, 'text/x-c++')) {
			return 'bsd';
		}
	}
	return 'internal';
}


