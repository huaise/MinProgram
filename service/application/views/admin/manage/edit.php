<?php defined('IN_ADMIN') or exit('No permission resources.');
if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ?>
<body>

<script type="text/javascript" language="javascript">
	var edit_mode = <?php echo ($edit_mode ? "true" : "false");?>;
</script>

<div class="container wrap">
	<div class="panel panel-default">
		<div class="panel-heading"><?php echo ($edit_mode ? "编辑" : "添加"); ?>管理员</div>
		<div class="panel-body">

			<form class="form-horizontal" id="form_admin" role="form" method="post" action="<?php echo $form_action_url;?>">
				<div class="form-group">
					<label for="username" class="col-sm-2 control-label">用户名</label>
					<div class="col-sm-10">
						<input type="text" class="form-control" id="username" name="username" placeholder="请输入用户名" value="<?php echo set_value('username', isset($superadmin['username'])?$superadmin['username']:'');?>">
						<?php echo form_error('username'); ?>
					</div>
				</div>

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


				<div class="form-group">
					<label for="authority" class="col-sm-2 control-label">权限</label>
					<div class="col-sm-10">
						<select class="form-control" id="authority" name="authority">
							<?php
							foreach ($authority_lookup_table as $value=>$str) {
								echo "<option value='".$value."' ".set_select('authority', $value, isset($superadmin['authority'])?$superadmin['authority']:NULL, $value==1).">".$str."</option>";
							}
							?>
						</select>
						<?php echo form_error('authority'); ?>
					</div>
				</div>

				<div class="form-group text-center">
					<div>
						<button type="submit" class="btn btn-primary" name="action" value="<?php echo ($edit_mode ? "edit" : "add"); ?>">确定</button>
						<button type="button" class="btn btn-danger" id="form_admin_cancel">取消</button>
					</div>
				</div>
			</form>

		</div>
	</div>
</div>
