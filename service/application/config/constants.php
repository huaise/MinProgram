<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Display Debug backtrace
|--------------------------------------------------------------------------
|
| If set to TRUE, a backtrace will be displayed along with php errors. If
| error_reporting is disabled, the backtrace will not display, regardless
| of this setting
|
*/
defined('SHOW_DEBUG_BACKTRACE') OR define('SHOW_DEBUG_BACKTRACE', TRUE);

/*
|--------------------------------------------------------------------------
| File and Directory Modes
|--------------------------------------------------------------------------
|
| These prefs are used when checking and setting modes when working
| with the file system.  The defaults are fine on servers with proper
| security, but you may wish (or even need) to change the values in
| certain environments (Apache running a separate process for each
| user, PHP under CGI with Apache suEXEC, etc.).  Octal values should
| always be used to set the mode correctly.
|
*/
defined('FILE_READ_MODE')  OR define('FILE_READ_MODE', 0644);
defined('FILE_WRITE_MODE') OR define('FILE_WRITE_MODE', 0666);
defined('DIR_READ_MODE')   OR define('DIR_READ_MODE', 0755);
defined('DIR_WRITE_MODE')  OR define('DIR_WRITE_MODE', 0755);

/*
|--------------------------------------------------------------------------
| File Stream Modes
|--------------------------------------------------------------------------
|
| These modes are used when working with fopen()/popen()
|
*/
defined('FOPEN_READ')                           OR define('FOPEN_READ', 'rb');
defined('FOPEN_READ_WRITE')                     OR define('FOPEN_READ_WRITE', 'r+b');
defined('FOPEN_WRITE_CREATE_DESTRUCTIVE')       OR define('FOPEN_WRITE_CREATE_DESTRUCTIVE', 'wb'); // truncates existing file data, use with care
defined('FOPEN_READ_WRITE_CREATE_DESTRUCTIVE')  OR define('FOPEN_READ_WRITE_CREATE_DESTRUCTIVE', 'w+b'); // truncates existing file data, use with care
defined('FOPEN_WRITE_CREATE')                   OR define('FOPEN_WRITE_CREATE', 'ab');
defined('FOPEN_READ_WRITE_CREATE')              OR define('FOPEN_READ_WRITE_CREATE', 'a+b');
defined('FOPEN_WRITE_CREATE_STRICT')            OR define('FOPEN_WRITE_CREATE_STRICT', 'xb');
defined('FOPEN_READ_WRITE_CREATE_STRICT')       OR define('FOPEN_READ_WRITE_CREATE_STRICT', 'x+b');

/*
|--------------------------------------------------------------------------
| Exit Status Codes
|--------------------------------------------------------------------------
|
| Used to indicate the conditions under which the script is exit()ing.
| While there is no universal standard for error codes, there are some
| broad conventions.  Three such conventions are mentioned below, for
| those who wish to make use of them.  The CodeIgniter defaults were
| chosen for the least overlap with these conventions, while still
| leaving room for others to be defined in future versions and user
| applications.
|
| The three main conventions used for determining exit status codes
| are as follows:
|
|    Standard C/C++ Library (stdlibc):
|       http://www.gnu.org/software/libc/manual/html_node/Exit-Status.html
|       (This link also contains other GNU-specific conventions)
|    BSD sysexits.h:
|       http://www.gsp.com/cgi-bin/man.cgi?section=3&topic=sysexits
|    Bash scripting:
|       http://tldp.org/LDP/abs/html/exitcodes.html
|
*/
defined('EXIT_SUCCESS')        OR define('EXIT_SUCCESS', 0); // no errors
defined('EXIT_ERROR')          OR define('EXIT_ERROR', 1); // generic error
defined('EXIT_CONFIG')         OR define('EXIT_CONFIG', 3); // configuration error
defined('EXIT_UNKNOWN_FILE')   OR define('EXIT_UNKNOWN_FILE', 4); // file not found
defined('EXIT_UNKNOWN_CLASS')  OR define('EXIT_UNKNOWN_CLASS', 5); // unknown class
defined('EXIT_UNKNOWN_METHOD') OR define('EXIT_UNKNOWN_METHOD', 6); // unknown class member
defined('EXIT_USER_INPUT')     OR define('EXIT_USER_INPUT', 7); // invalid user input
defined('EXIT_DATABASE')       OR define('EXIT_DATABASE', 8); // database error
defined('EXIT__AUTO_MIN')      OR define('EXIT__AUTO_MIN', 9); // lowest automatically-assigned error code
defined('EXIT__AUTO_MAX')      OR define('EXIT__AUTO_MAX', 125); // highest automatically-assigned error code

// 是否是开发或者是测试环境
defined('DEBUG')				OR define('DEBUG', ENVIRONMENT==='production'?FALSE:TRUE);

// 默认每页数量
defined('ADMIN_PER_PAGE')		OR define('ADMIN_PER_PAGE', 2);	// 管理后台默认每页数量

// 目录名称
defined('HOMEDIR')				OR define('HOMEDIR', 'home/');			// 前台的目录名称
defined('ADMINDIR')				OR define('ADMINDIR', 'admin/');		// 管理后台的目录名称
defined('APIDIR')				OR define('APIDIR', 'api/');			// 接口的目录名称

// 静态文件版本号
defined('JS_VERSION_ADMIN')		OR define('JS_VERSION_ADMIN', 1);		// 管理后台的JS版本号
defined('JS_VERSION_HOME')		OR define('JS_VERSION_HOME', 1);		// 前台的JS版本号

//畅卓的账户名称、密码、签名
defined('CHANZOR_ACCOUNT')		OR define("CHANZOR_ACCOUNT",'用户名');				// 畅卓账户名
defined('CHANZOR_PASSWORD')		OR define("CHANZOR_PASSWORD",'密码');				// 畅卓密码
defined('CHANZOR_ACCOUNT_YX')	OR define("CHANZOR_ACCOUNT_YX",'营销账号用户名');		// 畅卓营销账号账户名
defined('CHANZOR_PASSWORD_YX')	OR define("CHANZOR_PASSWORD_YX",'营销账号密码');		// 畅卓营销账号密码
defined('CHANZOR_SIGN')			OR define("CHANZOR_SIGN",'【签名】');					// 畅卓签名

//平台代码
defined('PLATFORM_ANDROID')			OR define("PLATFORM_ANDROID",1);				//安卓
defined('PLATFORM_IOS_INHOUSE')		OR define("PLATFORM_IOS_INHOUSE",2);			//IOS企业版
defined('PLATFORM_IOS_APPSTORE')	OR define("PLATFORM_IOS_APPSTORE",3);			//IOS的APPSTORE版(旧版)
