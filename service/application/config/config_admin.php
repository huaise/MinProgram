<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Session Variables
|--------------------------------------------------------------------------
|
| 'sess_driver'
|
|	The storage driver to use: files, database, redis, memcached
|
| 'sess_cookie_name'
|
|	The session cookie name, must contain only [0-9a-z_-] characters
|
| 'sess_expiration'
|
|	The number of SECONDS you want the session to last.
|	Setting to 0 (zero) means expire when the browser is closed.
|
| 'sess_save_path'
|
|	The location to save sessions to, driver dependent.
|
|	For the 'files' driver, it's a path to a writable directory.
|	WARNING: Only absolute paths are supported!
|
|	For the 'database' driver, it's a table name.
|	Please read up the manual for the format with other session drivers.
|
|	IMPORTANT: You are REQUIRED to set a valid save path!
|
| 'sess_match_ip'
|
|	Whether to match the user's IP address when reading the session data.
|
|	WARNING: If you're using the database driver, don't forget to update
|	         your session table's PRIMARY KEY when changing this setting.
|
| 'sess_time_to_update'
|
|	How many seconds between CI regenerating the session ID.
|
| 'sess_regenerate_destroy'
|
|	Whether to destroy session data associated with the old session ID
|	when auto-regenerating the session ID. When set to FALSE, the data
|	will be later deleted by the garbage collector.
|
| Other session cookie settings are shared with the rest of the application,
| except for 'cookie_prefix' and 'cookie_httponly', which are ignored here.
|
*/
$config['sess_driver'] = 'files';
$config['sess_cookie_name'] = 'ci_session';
$config['sess_expiration'] = 7200;
$config['sess_save_path'] = APPPATH.'session/admin';
$config['sess_match_ip'] = TRUE;
$config['sess_time_to_update'] = 300;
$config['sess_regenerate_destroy'] = TRUE;

/*
|--------------------------------------------------------------------------
| Cookie Related Variables
|--------------------------------------------------------------------------
|
| 'cookie_prefix'   = Set a cookie name prefix if you need to avoid collisions
| 'cookie_domain'   = Set to .your-domain.com for site-wide cookies
| 'cookie_path'     = Typically will be a forward slash
| 'cookie_secure'   = Cookie will only be set if a secure HTTPS connection exists.
| 'cookie_httponly' = Cookie will only be accessible via HTTP(S) (no javascript)
|
| Note: These settings (with the exception of 'cookie_prefix' and
|       'cookie_httponly') will also affect sessions.
|
*/
$config['cookie_prefix']	= '';
$config['cookie_domain']	= '';
$config['cookie_path']		= '/';
$config['cookie_secure']	= FALSE;
$config['cookie_httponly'] 	= FALSE;


/*
|--------------------------------------------------------------------------
| 后台目录权限
|--------------------------------------------------------------------------
|
| 第一层为控制器名，第二层为方法名，第三层为允许的权限
| 如：array(0)表示只允许超级管理员，array(0,1)表示允许超级管理员和普通管理员，array(-1)表示允许所有已登录用户
|
*/
$config['admin_authority'] = array(
	// 默认控制器
	'welcome' => array(
		"index" => array(-1),		// 默认页面
	),

	// 后台主页
	'main' => array(
		"index" => array(-1),		// 后台主页
		"welcome" => array(-1),		// 欢迎页面
	),

	// 基础操作
	'manage' => array(
		"config" => array(0),		// 系统配置
		"password" => array(-1),	// 修改密码
		"lists" => array(0),		// 管理员列表
		"edit" => array(0),			// 添加/编辑管理员
		"delete" => array(0),		// 删除管理员
		"logout" => array(-1),		// 用户登出
	),

	// 用户管理
	'user' => array(
		"lists" => array(-1),		// 用户列表
		"edit" => array(-1),		// 添加/编辑用户
		"delete" => array(0),		// 删除用户
	),
);

/*
|--------------------------------------------------------------------------
| 后台导航栏配置
|--------------------------------------------------------------------------
|
| 第一层为父导航目录名称，第二层为子项目名称，第三层为数组，第一个元素是控制器路径，第二个元素是方法名
|
*/
$config['admin_nav'] = array(
	"用户管理" => array(
		"用户列表" => array('user', 'lists'),
		"添加用户" => array('user', 'edit'),
	),

	"基础操作" => array(
		"系统配置" => array('manage', 'config'),
		"修改密码" => array('manage', 'password'),
		"管理员列表" => array('manage', 'lists'),
		"添加管理员" => array('manage', 'edit'),
		"退出后台" => array('manage', 'logout'),
	),
);