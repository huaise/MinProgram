<?php defined('IN_ADMIN') or exit('No permission resources.');
if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ?>
<body class="bg" style="background-image: url(<?php echo config_item('static_url')."img/".ADMINDIR."bg".$_SESSION['bg'].".jpg"?>)">

<div class="container wrap-sm">
	<div class="jumbotron">
		<form class="form-horizontal" id="form_admin" role="form" method="post" action="<?php echo $form_action_url;?>">
			<h2 class="form-signin-heading">请登录</h2>

			<div class="form-group">
				<label for="password" class="col-sm-5 control-label">用户名</label>
				<div class="col-sm-7">
					<input type="text" class="form-control" id="username" name="username" value="<?php echo set_value('username');?>" class="form-control" placeholder="请输入用户名">
					<?php echo form_error('username'); ?>
				</div>
			</div>

			<div class="form-group">
				<label for="password" class="col-sm-5 control-label">密码</label>
				<div class="col-sm-7">
					<input type="password" class="form-control" id="password" name="password" placeholder="请输入密码" >
					<?php echo form_error('password'); ?>
				</div>
			</div>

			<input name="form_token" value="<?php echo $form_token;?>" style="display: none">

			<div class="form-group">
				<div class="col-sm-offset-5 col-sm-7">
					<button class="btn btn-lg btn-primary btn-block" type="submit">登录</button>
				</div>
			</div>
		</form>
	</div>
</div>
