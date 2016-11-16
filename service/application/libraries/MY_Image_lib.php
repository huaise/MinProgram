<?php
/**
 * 拓展的图片处理类库
 * Created by PhpStorm.
 * User: hvcheng
 * Date: 2016/4/13
 * Time: 12:06
 */
defined('BASEPATH') OR exit('No direct script access allowed');


class MY_Image_lib extends CI_Image_lib
{

	/**
	 * 图片裁剪生成缩略图
	 * @param   string 	$thumb_image_name 	缩略图目标路径
	 * @param   string 	$image 				大图路径
	 * @param   int 	$crop_width 		截取区域宽
	 * @param   int 	$crop_height 		截取区域高
	 * @param   int 	$start_width 		开始截取区域横坐标
	 * @param   int 	$start_height 		开始截取区纵坐标
	 * @param   int 	$target_width 		缩略图宽
	 * @param   int 	$target_height 		缩略图高
	 * @return	string	缩略图目标路径
	 */
	function resizeThumbnailImage($thumb_image_name, $image, $crop_width, $crop_height, $start_width, $start_height, $target_width, $target_height) {
		//根据图片类型不同，调用不同的的打开函数
		$str = explode(".",$thumb_image_name);
		$type_key = $str[count($str)-1];

		if (config_item('use_imagick')) {
			$imagick = new Imagick();
			$imagick->readImage($image);

			try{
				//截取
				$imagick->cropImage($crop_width,$crop_height,$start_width,$start_height);
				//缩放
				$imagick->resizeImage($target_width, $target_height, Imagick::FILTER_CATROM, 1, true);
				if ($type_key == "jpg" || $type_key == "jpeg") {
					$imagick->setImageFormat('JPEG');
					$imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
					if ($target_height <= 200 && $target_width <= 200) {
						$a = 90;
					}
					else {
						$a = $imagick->getImageCompressionQuality();
						if ($a <= 0 || $a > 90) {
							$a = 90;
						}
					}
					$imagick->setImageCompressionQuality($a);
				}elseif ($type_key == 'png') {
					$flag = $imagick->getImageAlphaChannel();
					if (imagick::ALPHACHANNEL_UNDEFINED == $flag||imagick::ALPHACHANNEL_DEACTIVATE == $flag) {
						$imagick->setImageType(imagick::IMGTYPE_PALETTE);
						$imagick->setCompressionQuality(50);
					}
				}
				//去除图片信
				$imagick->stripImage();
				$imagick->writeImage($thumb_image_name);

				$imagick->clear();
				$imagick->destroy();
			}
			catch(ImagickException $e) {
				die ("图片格式不正确！");
			}

		}else{
			$newImage = imagecreatetruecolor($target_width,$target_height);
			if ($type_key == "png") {
				//保持透明层
				$side_color = imagecolorallocatealpha($newImage, 255, 0, 0, 127);
				imagefill($newImage, 0, 0, $side_color);
				imagealphablending($newImage, false);
				imagesavealpha($newImage, true);
				$source = @ImageCreateFromPNG($image) or die ("图片格式不正确！");
			}
			elseif ($type_key == "jpg" || $type_key == "jpeg") {
				$source = @ImageCreateFromjpeg($image) or die ("图片格式不正确！");
			}

			elseif ($type_key == "gif") {
				$source = @ImageCreateFromgif ($image) or die ("图片格式不正确！");
			}

			if (isset($source)) {
				imagecopyresampled($newImage,$source,0,0,$start_width,$start_height,$target_width,$target_height,$crop_width,$crop_height);

				if ($type_key == "png") {
					imagepng($newImage,$thumb_image_name,5);
				}
				elseif ($type_key == "jpg" || $type_key == "jpeg") {
					imagejpeg($newImage,$thumb_image_name,90);
				}

				elseif ($type_key == "gif") {
					imagegif ($newImage,$thumb_image_name);
				}
			}
		}

		chmod($thumb_image_name, 0777);
		return $thumb_image_name;
	}


	/**
	 * 获取图片的高
	 * @param   string 	$image 		图片路径
	 * @return	int		图片高度
	 */
	function getHeight($image) {
		$sizes = getimagesize($image);
		$height = $sizes[1];
		return $height;
	}

	/**
	 * 获取图片的宽
	 * @param   string 	$image 		图片路径
	 * @return	int		图片宽度
	 */
	function getWidth($image) {
		$sizes = getimagesize($image);
		$width = $sizes[0];
		return $width;
	}

	/**
	 * 图片裁剪缩放辅助函数
	 * @param   int 	$x  				裁剪x轴坐标
	 * @param   int 	$y 					裁剪y轴坐标
	 * @param   int 	$w  				裁剪宽度
	 * @param   int 	$h 					裁剪高度
	 * @param   int 	$xrb  				页面图片实际长度
	 * @param   int 	$yrb 				页面图片实际高度
	 * @param   int 	$origin_width 		原图宽度
	 * @param   int 	$origin_height 		原图高度
	 * @return	array	$output				裁剪缩放的一系列参数
	 */
	function getResizeThumbSize($x, $y, $w, $h, $xrb, $yrb, $origin_width, $origin_height) {
		//目标图片宽高比
		$rate_x = $origin_width/$xrb;
		$rate_y = $origin_height/$yrb;

		$width = $origin_width/$xrb*$w;
		$height = $origin_height/$yrb*$h;

		$output['crop_width'] = $width;
		$output['crop_height'] = $height;
		$output['start_width'] = $x*$rate_x;
		$output['start_height'] = $y*$rate_y;

		return $output;
	}


	/**
	 * 图片旋转函数
	 * @param   string 	$filename  			文件地址
	 * @param   int 	$rotate 			旋转角度(默认顺时针旋转)
	 * @return  mixed 	$array 			旋转角度(默认顺时针旋转)
	 * */
	public function image_rotate($filename, $rotate) {
		if (!file_exists($filename)) {
			$output['state'] = false;
			$output['error'] = "文件不存在";
		}

		//首选获取图片类型
		$image_type  = getimagesize($filename);

		if ($image_type['mime'] == "image/jpeg" || $image_type['mime'] == "image/jpg") {
			$resource = imagecreatefromjpeg($filename);
			$rotate = imagerotate($resource,$rotate,0);
			imagejpeg($rotate,$filename);
		} else if ($image_type['mime'] == "image/png") {
			$resource = imagecreatefrompng($filename);
			$rotate = imagerotate($resource,$rotate,0);
			imagepng($rotate,$filename);
		} else {
			$output['state'] = false;
			$output['error'] = "文件类型不支持";
		}

		$output['state'] = true;
		return $output;

	}


}
