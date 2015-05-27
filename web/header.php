<?php
$headers = array();
foreach($_SERVER as $name=>$value) {
	if(substr($name, 0, 5) == 'HTTP_') {
		$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
	}
}

echo '<pre>';
foreach($headers as $name=>$value) {
	echo '['.$name.'] => '.$value.'<br />';
}