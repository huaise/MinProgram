<?php defined('IN_ADMIN') or exit('No permission resources.');
if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ?>
<body>

<script type="text/javascript" language="javascript">
	var delete_url = "<?php echo $delete_url;?>";
	var search_url = "<?php echo $search_url;?>";
</script>

<div class="container wrap">
	<div class="panel panel-default">
		<div class="panel-heading">用户列表</div>
		<div class="panel-body">

			<form class="form-inline pull-right search-form" role="form" id="search_form">
				<div class="form-group">
					<input type="text" class="form-control" id="phone" name="phone" placeholder="请输入手机号" value="<?php echo set_value('phone', isset($search['phone'])?$search['phone']:'');?>">
				</div>
				<div class="form-group">
					<select class="form-control" id="gender" name="gender">
						<option value="" <?php echo  set_select('gender', NULL, isset($search['gender'])?$search['gender']:NULL, TRUE); ?>>请选择性别</option>
						<option value="0" <?php echo  set_select('gender', 0, isset($search['gender'])?$search['gender']:NULL); ?>>未设定</option>
						<option value="1" <?php echo  set_select('gender', 1, isset($search['gender'])?$search['gender']:NULL); ?>>男</option>
						<option value="2" <?php echo  set_select('gender', 2, isset($search['gender'])?$search['gender']:NULL); ?>>女</option>
					</select>
				</div>
				<button type="submit" class="btn btn-primary">搜索</button>
			</form>

			<table class="table table-condensed table-hover table-bordered table-striped" style="text-align: center">
				<thead>
				<tr>
					<th class="th-normal">手机号</th>
					<th class="th-normal">性别</th>
					<th class="th-normal">常住地</th>
					<th class="th-normal">注册时间</th>
					<th class="th-normal">操作</th>
				</tr>
				</thead>
				<tbody >
				<?php foreach ($user_list as $row){?>
					<tr>
						<td><?php echo $row['phone'];?></td>
						<td><?php echo $row['gender_display'];?></td>
						<td><?php echo $row['location'];?></td>
						<td><?php echo $row['register_time'];?></td>
						<td><a href='<?php echo $edit_url."?uid=".$row['uid'];?>'>详细</a>
							/ <button type="button" class="btn-xs btn-danger del" data-uid="<?php echo $row['uid'];?>" data-inform="<?php echo $row['phone'];?>">删除</button></td>
					</tr>
				<?php }?>
				</tbody>
			</table>
			<div class="pull-right">
				<?php echo $pagination;?>
			</div>
		</div>
	</div>
</div>