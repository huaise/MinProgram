<?php
/**
 * Created by PhpStorm.
 * User: LvPeng
 * Date: 2015/12/4
 * Time: 18:17
 */
?>
<html>
<head>
	<title><?php echo $title;?></title>
</head>
<body>
<h1><?php echo $heading;?></h1>

<h3>My Todo List</h3>

<ul>
	<?php foreach ($todo_list as $item):?>

		<li><?php echo $item;?></li>

	<?php endforeach;?>
</ul>

<?php echo $alert;?>

</body>
</html>