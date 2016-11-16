<?php defined('IN_ADMIN') or exit('No permission resources.');
if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ?>
<body>

<script type="text/javascript" language="javascript">
	var edit_mode = <?php echo ($edit_mode ? "true" : "false");?>;
	var province = <?php echo (isset($user['province']) ? $user['province'] : 0);?>;
	var city = <?php echo (isset($user['city']) ? $user['city'] : 0);?>;
	var district = <?php echo (isset($user['district']) ? $user['district'] : 0);?>;
</script>

<div class="container wrap">
	<div class="panel panel-default">
		<div class="panel-heading"><?php echo ($edit_mode ? "编辑" : "添加"); ?>用户</div>
		<div class="panel-body">

			<?php if(isset($diplayed_error) && $diplayed_error!='') {?>
				<h4 class="text-danger"><?php echo $diplayed_error; ?></h4>
			<?php }?>

			<form class="form-horizontal" id="form_admin" role="form" enctype="multipart/form-data" method="post" action="<?php echo $form_action_url;?>">
				<div class="form-group">
					<label for="phone" class="col-sm-2 control-label">手机号</label>
					<div class="col-sm-10">
						<input type="text" class="form-control" id="phone" name="phone" placeholder="请输入手机号" value="<?php echo set_value('phone', isset($user['phone'])?$user['phone']:'');?>">
						<?php echo form_error('phone'); ?>
					</div>
				</div>

				<div class="form-group">
					<label for="gender" class="col-sm-2 control-label">性别</label>
					<div class="col-sm-10">
						<select class="form-control" id="gender" name="gender">
							<option value="0" <?php echo  set_select('gender', 0, isset($user['gender'])?$user['gender']:NULL, TRUE); ?>>未设定</option>
							<option value="1" <?php echo  set_select('gender', 1, isset($user['gender'])?$user['gender']:NULL); ?>>男</option>
							<option value="2" <?php echo  set_select('gender', 2, isset($user['gender'])?$user['gender']:NULL); ?>>女</option>
						</select>
					</div>
				</div>

				<div class="form-group">
					<label for="birthday" class="col-sm-2 control-label">生日</label>
					<div class="col-sm-10">
						<input type="text" class="form-control" id="birthday" name="birthday" value="<?php echo set_value('birthday', isset($user['birthday'])?$user['birthday']:'');?>">
						<?php echo form_error('birthday'); ?>
					</div>
				</div>

				<?php if($edit_mode) {?>
				<div class="form-group">
					<label class="col-sm-2 control-label">星座</label>
					<div class="col-sm-10">
						<p class="form-control-static"><?php echo isset($user['constellation'])?$user['constellation']:'';?></p>
					</div>
				</div>
				<?php }?>

				<div class="form-group">
					<label for="province" class="col-sm-2 control-label">常住地</label>
					<div class="col-sm-10">
						<select class="form-control" id="province" name="province"></select>
						<select class="form-control" id="city" name="city"></select>
						<select class="form-control" id="district" name="district"></select>
					</div>
				</div>

				<div class="form-group">
					<label for="sign" class="col-sm-2 control-label">个性签名</label>
					<div class="col-sm-10">
						<textarea class="form-control" id="sign" name="sign" rows="3"><?php echo set_value('sign', isset($user['sign'])?$user['sign']:'');?></textarea>
					</div>
				</div>

				<?php if($edit_mode && $user['avatar']!='') {?>
					<div class="form-group">
						<label class="col-sm-2 control-label">当前头像</label>
						<div class="col-sm-10">
							<img src="<?php echo isset($user['avatar'])?$user['avatar']:'';?>" class="img-responsive">
						</div>
					</div>
				<?php }?>

				<div class="form-group">
					<label for="avatar" class="col-sm-2 control-label">上传头像</label>
					<div class="col-sm-10">
						<div id="avatar_uploader"></div>
						<p class="help-block">请上传100*100的png或者jpg文件，100KB以内</p>
					</div>
				</div>

				<?php if($edit_mode && !empty($user['album'])) {?>
					<div class="form-group">
						<label class="col-sm-2 control-label">当前相册</label>
						<div class="col-sm-10">
							<?php foreach ($user['album'] as $value) {?>
								<img src="<?php echo $value;?>" class="img-responsive" style="margin-bottom: 10px">
							<?php }?>
						</div>
					</div>
				<?php }?>

				<div class="form-group">
					<label for="avatar" class="col-sm-2 control-label">上传相册</label>
					<div class="col-sm-10">
						<div id="album_uploader"></div>
					</div>
				</div>

				<?php if($edit_mode) {?>
					<div class="form-group">
						<label class="col-sm-2 control-label">注册时间</label>
						<div class="col-sm-10">
							<p class="form-control-static"><?php echo isset($user['register_time'])?$user['register_time']:'';?></p>
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label">token</label>
						<div class="col-sm-10">
							<p class="form-control-static"><?php echo isset($user['token'])?$user['token']:'';?></p>
						</div>
					</div>
				<?php }?>

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
