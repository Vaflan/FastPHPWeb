<?php
header('Cache-Control: Max-Age=3600');
?>
<html>
<head>
	<title>FastPHPWeb</title>
</head>
<body>
	<h1>Hello to FastPHPWeb!</h1>
	<img src="/favicon.ico" /><br />
	Go To: <a href="/dir/subindex">SubIndex</a><br />
	Get Headers: <a href="/header.php">Headers</a><br />
	Download: <a href="/test.zip">test.zip</a><br />
	Current date & time: <?php echo date("d.m.Y, H:i:s");?><br />
	Remote Address: <?php echo $_SERVER['REMOTE_ADDR'].':'.$_SERVER['REMOTE_PORT'];?><br />
	__FILE__: <?php echo __FILE__;?><br />
	User Agent: <?php echo $_SERVER['HTTP_USER_AGENT'];?><br />
	<br />
	<hr />
	Working on PHP <?php echo PHP_VERSION;?> version!
</body>
</html>