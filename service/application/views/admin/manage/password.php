<?php defined('IN_ADMIN') or exit('No permission resources.');
if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ?>
<body>

<div class="container wrap">
	<div class="panel panel-default">
		<div class="panel-heading">修改密码</div>
		<div class="panel-body">

			<?php if (isset($validation_errors)) {?>
				<h4 class="text-danger"><?php echo $validation_errors;?></h4>
			<?php }?>

			<form class="form-horizontal" id="form_admin" role="form" method="post" action="<?php echo $form_action_url;?>">

				<div class="form-group">
					<label for="password" class="col-sm-2 control-label">密码</label>
					<div class="col-sm-10">
						<input type="password" class="form-control" id="password" name="password" placeholder="请输入密码" >
						<?php echo form_error('password'); ?>
					</div>
				</div>

				<div class="form-group">
					<label for="password_confirm" class="col-sm-2 control-label">确认密码</label>
					<div class="col-sm-10">
						<input type="password" class="form-control" id="password_confirm" name="password_confirm" placeholder="请确认密码" >
						<?php echo form_error('password_confirm'); ?>
					</div>
				</div>

				<div class="form-group text-center">
					<div>
						<button type="submit" class="btn btn-primary" name="action" value="edit">确定</button>
						<button type="button" class="btn btn-danger" id="form_admin_cancel">取消</button>
					</div>
				</div>
			</form>

		</div>
	</div>
</div>
