<?php defined('IN_ADMIN') or exit('No permission resources.');
if ( ! defined('BASEPATH')) exit('No direct script access allowed'); ?>

<?php if ($show_bg) {?>
<body class="bg" style="background-image: url(<?php echo config_item('static_url')."img/".ADMINDIR."bg".$_SESSION['bg'].".jpg"?>)">
<?php } else {?>
<body>
<?php }?>


<div class="container wrap">
<div class="jumbotron">
  <h1>提示!</h1>
  <p><?php echo $msg; ?></p>
  <p>
  	 <?php if($url_forward=='goback' || $url_forward=='') {?>
	<a href="javascript:history.back();" >[返回上一步]</a>
	<?php } elseif($url_forward=="close") {?>
	<input type="button" name="close" value="关闭" onClick="window.close();">
	<?php } elseif($url_forward=="blank") {?>

	<?php } elseif($url_forward!='') {
	?>
	<a href="<?php echo $url_forward?>">点击这里</a>
	<script language="javascript">setTimeout(function(){window.location.href='<?php echo $url_forward?>';},<?php echo $ms?>);</script>
	<?php }?>
  </p>
</div>
</div>