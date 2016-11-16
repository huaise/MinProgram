<?php defined('IN_ADMIN') or exit('No permission resources.');
if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ?>
<?php
if (!empty($css)) {
	foreach ($css as $file) {?>
		<link rel="stylesheet" href="<?php echo $file."?v=".JS_VERSION_ADMIN?>">
	<?php
	}
}
?>

<!--[if lt IE 9]>
<script src="<?php echo config_item('static_url')."js/".ADMINDIR."lib/ie8-responsive-file-warning.js"?>"></script>
<![endif]-->
<script src="<?php echo config_item('static_url')."js/".ADMINDIR."lib/ie-emulation-modes-warning.js"?>"></script>

<!--[if lt IE 9]>
<script src="<?php echo config_item('static_url')."js/".ADMINDIR."lib/html5shiv.min.js"?>"></script>
<script src="<?php echo config_item('static_url')."js/".ADMINDIR."lib/respond.min.js"?>"></script>
<![endif]-->

<script src="<?php echo config_item('static_url')."js/".ADMINDIR."lib/jquery.min.js"?>"></script>
<script src="<?php echo config_item('static_url')."js/".ADMINDIR."lib/bootstrap.min.js"?>"></script>
<script src="<?php echo config_item('static_url')."js/".ADMINDIR."lib/ie10-viewport-bug-workaround.js"?>"></script>
<script src="<?php echo config_item('static_url')."js/".ADMINDIR."lib/bootstrapValidator.min.js"?>"></script>
<?php
if (!empty($js)) {
	foreach ($js as $file) {?>
<script src="<?php echo $file."?v=".JS_VERSION_ADMIN?>"></script>
<?php
	}
}
?>

</body>
</html>