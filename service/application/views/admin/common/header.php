<?php defined('IN_ADMIN') or exit('No permission resources.');
if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>管理后台</title>

	<!-- Bootstrap -->
	<link rel="stylesheet" href="<?php echo config_item('static_url')."css/".ADMINDIR."bootstrap.min.css"?>"/>
	<!--<link rel="stylesheet" href="//cdn.bootcss.com/bootstrap/3.3.5/css/bootstrap.min.css">-->
	<link rel="stylesheet" href="<?php echo config_item('static_url')."css/".ADMINDIR."bootstrapValidator.min.css"?>"/>
	<link rel="stylesheet" href="<?php echo config_item('static_url')."css/".ADMINDIR."common.css"?>"">
</head>