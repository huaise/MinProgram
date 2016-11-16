<?php defined('IN_ADMIN') or exit('No permission resources.');
if (!defined('BASEPATH')) exit('No direct script access allowed'); ?>

<body class="bg" style="background-image: url(<?php echo config_item('static_url')."img/".ADMINDIR."bg".$_SESSION['bg'].".jpg"?>)">


<div class="container-fluid ht100">
	<div class="row ht100">
		<div class="col-xs-2 ht100 sidebar">
			<button id='click_all' type="button" class="btn btn-info btn-sm" style="margin: 5px auto; display: block">展开全部</button>

			<div class="panel-group" id="accordion">
				<?php foreach ($admin_nav as $index=>$nav) { ?>
					<div class="panel panel-default" >
						<div class="panel-heading">
							<div class="panel-title">
								<a data-toggle="collapse"
								   href="#collapse<?php echo $index;?>">
									<?php echo $nav['name'];?>
								</a>
							</div>
						</div>
						<div id="collapse<?php echo $index;?>" class="panel-collapse collapse">
							<div class="panel-body">
								<?php foreach ($nav['child'] as $node) {
									if ($node['name'] == '退出后台') {
										?>
										<div class="text-left"><a onClick="return confirm('提示：您确定要退出系统吗？')" href='<?php echo $node['url']; ?>' target=_parent><span
													class="glyphicon glyphicon-map-marker"
													aria-hidden="true"></span>&nbsp;<?php echo $node['name']; ?></a></div>

									<?php
									} else {
										?>
										<div class="text-left"><a href='<?php echo $node['url']; ?>' target=main><span
													class="glyphicon glyphicon-map-marker"
													aria-hidden="true"></span>&nbsp;<?php echo $node['name']; ?></a></div>
									<?php }
								}
								?>
							</div>
						</div>
					</div>
				<?php } ?>
			</div>

		</div>

		<div class="col-xs-10 ht100 iframe-wrap">
			<iframe id="iframepage" class="ht100" name="main" src="<?php echo config_item('base_url').ADMINDIR."main/welcome";?>" scrolling="yes" frameborder="0" marginheight="0" marginwidth="0"></iframe>
		</div>
	</div>
</div>