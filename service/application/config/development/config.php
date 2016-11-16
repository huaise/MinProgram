<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Base Site URL
|--------------------------------------------------------------------------
|
| URL to your CodeIgniter root. Typically this will be your base URL,
| WITH a trailing slash:
|
|	http://example.com/
|
| If this is not set then CodeIgniter will try guess the protocol, domain
| and path to your installation. However, you should always configure this
| explicitly and never rely on auto-guessing, especially in production
| environments.
|
*/
$config['base_url'] = 'http://localhost/WeApp_master_hail/service/';


/*
|--------------------------------------------------------------------------
| Static URL
|--------------------------------------------------------------------------
|
| 存放静态文件的url，以/结尾
| 如果没有配置直接指向assets文件夹的域名，则此项就是assets文件夹的地址
| 如果配置了直接指向assets文件夹的域名，如static.example.com，则此项填写该域名
| 所有使用静态文件的地方，请统一使用该配置项，不要再写一遍路径
|
*/
$config['static_url'] = 'http://localhost/WeApp_master_hail/service/assets/';


/*
|--------------------------------------------------------------------------
| 图片、语音等附件的链接，以/结尾
|--------------------------------------------------------------------------
|
| 存放上传附件的链接地址
| 如果没有配置直接指向uploads文件夹的域名，则此项就是uploads文件夹的地址
| 如果配置了直接指向uploads文件夹的域名，如uploads.example.com，则此项填写该域名
| 所有使用附件地址的地方，请统一使用该配置项，不要再写一遍路径
|
*/
$config['attachment_url'] = 'http://localhost/WeApp_master_hail/service//uploads/';

/*
|--------------------------------------------------------------------------
| 拓展库配置
|--------------------------------------------------------------------------
|
|
*/
$config['use_imagick'] = FALSE;											// 是否使用ImageMagick


/*
|--------------------------------------------------------------------------
| Error Logging Threshold
|--------------------------------------------------------------------------
|
| You can enable error logging by setting a threshold over zero. The
| threshold determines what gets logged. Threshold options are:
|
|	0 = Disables logging, Error logging TURNED OFF
|	1 = Error Messages (including PHP errors)
|	2 = Debug Messages
|	3 = Informational Messages
|	4 = All Messages
|
| You can also pass an array with threshold levels to show individual error types
|
| 	array(2) = Debug Messages, without Error Messages
|
| For a live site you'll usually only enable Errors (1) to be logged otherwise
| your log files will fill up very fast.
|
*/
$config['log_threshold'] = 2;

// 加密字符串
$config['encryption_key'] = hex2bin('8e9e8345fd8228810fb4db123d0a3ef4');
