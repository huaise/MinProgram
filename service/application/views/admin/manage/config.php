<?php defined('IN_ADMIN') or exit('No permission resources.');
if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ?>
<body>

<div class="container wrap-lg">
	<div class="panel panel-default">
		<div class="panel-heading">系统配置</div>
		<div class="panel-body">

			<form class="form-horizontal" id="form_admin" role="form" method="post" action="<?php echo $form_action_url;?>">

				<?php
				if (!empty($config_editable_list)) {
					foreach ($config_editable_list as $row) {
				?>
						<div class="form-group">
							<label for="<?php echo $row['name'];?>" class="col-sm-2 control-label"><?php echo $row['remark'];?></label>
							<div class="col-sm-10">
								<input type="text" class="form-control" id="<?php echo $row['name'];?>" name="<?php echo $row['name'];?>" value="<?php echo set_value($row['name'], isset($row['values'])?$row['values']:'');?>">
								<?php echo form_error($row['name']); ?>
							</div>
						</div>
				<?php
					}
				}
				?>

				<?php
				if (!empty($config_static_list)) {
					foreach ($config_static_list as $row) {
						?>
						<div class="form-group">
							<label class="col-sm-2 control-label"><?php echo $row['remark'];?></label>
							<div class="col-sm-10">
								<p class="form-control-static"><?php echo isset($row['values'])?$row['values']:'';?></p>
							</div>
						</div>
					<?php
					}
				}
				?>

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
