<?php defined('IN_ADMIN') or exit('No permission resources.');
if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ?>
<body>

<script type="text/javascript" language="javascript">
	var delete_url = "<?php echo $delete_url;?>";
	var search_url = "<?php echo $search_url;?>";
</script>

<div class="container wrap">
	<div class="panel panel-default">
		<div class="panel-heading">管理员列表</div>
		<div class="panel-body">

			<form class="form-inline pull-right search-form" role="form" id="search_form">
				<div class="form-group">
					<input type="text" class="form-control" id="username" name="username" placeholder="请输入用户名" value="<?php echo set_value('username', isset($search['username'])?$search['username']:'');?>">
				</div>
				<div class="form-group">
					<select class="form-control" id="authority" name="authority">
						<option value="" <?php echo  set_select('authority', NULL, isset($search['authority'])?$search['authority']:NULL, TRUE); ?>>请选择权限</option>
						<?php
						foreach ($authority_lookup_table as $value=>$str) {
							echo "<option value='".$value."' ".set_select('authority', $value, isset($search['authority'])?$search['authority']:NULL).">".$str."</option>";
						}
						?>
					</select>
				</div>
				<button type="submit" class="btn btn-primary">搜索</button>
			</form>

			<table class="table table-condensed table-hover table-bordered table-striped" style="text-align: center">
				<thead>
				<tr>
					<th class="th-normal">用户名</th>
					<th class="th-normal">权限</th>
					<th class="th-normal">操作</th>
				</tr>
				</thead>
				<tbody >
				<?php foreach ($superadmin_list as $row){?>
				<tr>
					<td><?php echo $row['username'];?></td>
					<td><?php echo $row['authority_display'];?></td>
					<td><a href='<?php echo $edit_url."?uid=".$row['uid'];?>'>详细</a>
						/ <button type="button" class="btn-xs btn-danger del" data-uid="<?php echo $row['uid'];?>" data-inform="<?php echo $row['username'];?>">删除</button></td>
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