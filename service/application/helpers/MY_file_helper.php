<?php
/**
 * 拓展的文件辅助函数
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2016/1/8
 * Time: 16:06
 */

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * 更改一个目录以及目录下的所有文件的权限，也可以更改一个文件
 *
 * @param	string	$path		目录路径
 * @param	int		$filemode	权限代码，四位数字，第一位为0，例如0777
 * @return	bool	TRUE(成功)/FALSE(失败)
 */
function chmodr($path, $filemode) {
	if (!is_dir($path)) {
		return chmod($path, $filemode);
	}
	$dh = opendir($path);
	while(($file = readdir($dh)) !== false) {
		if ($file != '.' && $file != '..') {
			$fullpath = $path.'/'.$file;
			if (is_link($fullpath)) {
				return FALSE;
			}
			elseif (!is_dir($fullpath) && !chmod($fullpath, $filemode)) {
				return FALSE;
			}
			elseif (!chmodr($fullpath, $filemode)) {
				return FALSE;
			}
		}
	}
	closedir($dh);
	if (chmod($path, $filemode)) {
		return TRUE;
	}
	else{
		return FALSE;
	}
}

/**
 * 删除一个目录以及目录下的所有文件
 *
 * @param	string	$dir		目录路径
 * @return	bool	TRUE(成功)/FALSE(失败)
 */
function deldir($dir) {
	// 如果文件夹不存在，则返回
	if (!is_dir($dir)) {
		return true;
	}

	// 先删除目录下的文件：
	$dh = opendir($dir);
	while ($file = readdir($dh)) {
		if ($file!="." && $file!="..") {
			$fullpath = $dir."/".$file;
			if (!is_dir($fullpath)) {
				unlink($fullpath);
			}
			else{
				deldir($fullpath);
			}
		}
	}
	closedir($dh);
	// 删除当前文件夹：
	if (rmdir($dir)) {
		return true;
	}
	else{
		return false;
	}
}


/**
 * 由缩略图文件名得到原始图的文件名
 *
 * @param	string	$thumb		缩略图文件名
 * @return	string	原始图的文件名
 */
function get_full_from_thumb($thumb) {
	$full = "";
	$str_temp = explode(".", $thumb);
	for($i=0; $i<count($str_temp); $i++) {
		$full .= $str_temp[$i];
		if ($i == count($str_temp)-2) {
			$full .= "_full";
		}
		if ($i < count($str_temp)-1) {
			$full .= ".";
		}
	}
	return $full;
}

/**
 * 由缩略图文件名得到大图的文件名
 *
 * @param	string	$thumb		缩略图文件名
 * @return	string	大图的文件名
 */
function get_large_from_thumb($thumb) {
	$large = "";
	$str_temp = explode(".", $thumb);
	for($i=0; $i<count($str_temp); $i++) {
		$large .= $str_temp[$i];
		if ($i == count($str_temp)-2) {
			$large .= "_large";
		}
		if ($i < count($str_temp)-1) {
			$large .= ".";
		}
	}
	return $large;
}

/**
 * 由缩略图文件名得到大图的文件名
 *
 * @param	string	$full		原始图的文件名
 * @return	string	缩略图的文件名
 */
function get_thumb_from_full($full) {
	$thumb = str_replace("_full", "", $full);
	return $thumb;
}

/**
 * 由大图的文件名得到缩略图文件名
 *
 * @param	string	$large		大图的文件名
 * @return	string	缩略图的文件名
 */
function get_thumb_from_large($large) {
	$thumb = str_replace("_large", "", $large);
	return $thumb;
}