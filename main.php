<?php
namespace FastPHPWeb\engine;
define('FASTPHPWEB_PORT', !empty($argv[1]) ? intval($argv[1]) : '80');
define('FASTPHPWEB_CONTENT', 'web');
define('FASTPHPWEB_MIME_TYPES', 'mime.types');
define('FASTPHPWEB_404', '404 Object Not Found');
define('FASTPHPWEB_TIMEZONE', 'Europe/Riga');
define('FASTPHPWEB_INDEX', 'index.php');
define('FASTPHPWEB_SHOW_AGENT', true);
define('FASTPHPWEB_ERROR_BEEP', true);
define('FASTPHPWEB_CRLF', "\r\n");





/* Function */
function console_log($message) {
	$message = iconv('UTF-8', 'cp866//IGNORE', trim(print_r($message, true)));
	if(func_num_args() > 1) {
		for($i=1; $i<func_num_args(); $i++) {
			$message = str_replace('%'.$i.'%', func_get_arg($i), $message);
		}
	}
	echo $log = date("[d.m.Y H:i:s] ").$message."\n";
	return $log;
}
function load_types($file) {
	$types = array();
	$load = file_get_contents($file);
	$load = explode('{', $load, 2);
	$load = current(explode('}', end($load)));
	$load = trim(preg_replace('/\s+/', ' ', str_replace("\t", ' ', $load)));
	$load = explode(';', $load);
	foreach($load AS $value) {
		$value = trim($value);
		if(!empty($value)) {
			$value = explode(' ', $value);
			for($i=1; $i<count($value); $i++) {
				$types[$value[$i]] = $value[0];
			}
		}
	}
	return $types;
}
function create_response($content, $status, $headers=array()) {
	return	$status. FASTPHPWEB_CRLF .
			'Server: FastPHPWeb' . FASTPHPWEB_CRLF .
			(count($headers) > 0 ? implode(FASTPHPWEB_CRLF, $headers).FASTPHPWEB_CRLF : '') .
			'Content-Length: ' . strlen($content) . FASTPHPWEB_CRLF .
			'Connection: close' . FASTPHPWEB_CRLF .
			FASTPHPWEB_CRLF . $content;
}
function shutdown_service() {
	$e = error_get_last();
	if($e == null)
		$e = array('message' => 'No errors', 'line' => 0);
	console_log($e['message'].' [line: '.$e['line'].']');
}



/* Engine */
$eval_namespace = 0;
date_default_timezone_set(FASTPHPWEB_TIMEZONE);
register_shutdown_function('FastPHPWeb\engine\shutdown_service');
$mime_types = load_types(FASTPHPWEB_MIME_TYPES);
$socket = stream_socket_server('tcp://0.0.0.0:'.FASTPHPWEB_PORT, $errno, $errstr);
if(!$socket) {
	die($errstr.' ('.$errno.')');
}
console_log('FastPHPWeb started on '.FASTPHPWEB_PORT.' port!');

$connects = array();
while(true) {
	/* form the array of the listened sockets */
	$read = $connects;
	$read[] = $socket;
	$write = $except = null;

	try {
		if(!stream_select($read, $write, $except, null)) { /* expect sockets accessible for reading (without a timeout) */
			break;
		}

		if(in_array($socket, $read)) { /* there is new connection */
			$connect = stream_socket_accept($socket, -1); /* accept new connection */
			$connects[] = $connect; /* add him to the list necessary for treatment */
			unset($read[array_search($socket, $read)]);
		}
	}
	catch(Exeption $e) {
		console_log($e->getMessage());
	}

	foreach($read as $connect) { /* process all connections */
		console_log('Connection accepted from '.stream_socket_get_name($connect, true));
		$headers = '';
		while($buffer = rtrim(fgets($connect))) {
			$headers .= $buffer."\n";
		}

		$header_array = explode("\n", $headers);
		$header_get = explode(" ", trim($header_array[0]));
		if(!empty($header_array[0]))
			console_log($header_array[0]);
		elseif(count($header_array) > 1)
			console_log($header_array);

		/* show User Agent */
		if(FASTPHPWEB_SHOW_AGENT && count($header_array) > 1) {
			foreach($header_array AS $header_row) {
				$header_row = explode(':', trim($header_row), 2);
				$header_row[0] = strtolower($header_row[0]);
				if(strstr($header_row[0], 'host')) {
					console_log('Host: '.trim($header_row[1]));
				}
				else if(strstr($header_row[0], 'agent')) {
					console_log('User-Agent: '.trim($header_row[1]));
				}
			}
		}

		if(count($header_get) > 2 && strstr(strtolower($header_get[2]), 'http/1.1')) {
			if(strtolower($header_get[0]) == 'get') {
				$header_get[1] = current(explode('?', $header_get[1]));
				if($header_get[1] == '/' || $header_get[1] == '\\') {
					$header_get[1] = FASTPHPWEB_INDEX;
				}

				$get_request = FASTPHPWEB_CONTENT.DIRECTORY_SEPARATOR.$header_get[1];
				if(is_file($get_request)) {
					$type = explode('.', $get_request);
					$type = end($type);
					$content = file_get_contents($get_request);
					$headers = array();
					if($type == 'php') {
						$remote_addr = explode(':', stream_socket_get_name($connect, true), 2);
						$_SERVER['REMOTE_PORT'] = array_pop($remote_addr);
						$_SERVER['REMOTE_ADDR'] = current($remote_addr);
						$_SERVER['SERVER_PORT'] = FASTPHPWEB_PORT;
						if(count($header_array) > 1) {
							foreach($header_array AS $header_row) {
								$header_row = explode(':', trim($header_row), 2);
								if(count($header_row) > 1) {
									$header_row[1] = trim($header_row[1]);
									switch(strtolower(trim($header_row[0]))) {
										case 'host':
											$_SERVER['HTTP_HOST'] = $header_row[1];
											break;
										case 'user-agent':
											$_SERVER['HTTP_USER_AGENT'] = $header_row[1];
											break;
										case 'cookie':
											$_SERVER['HTTP_COOKIE'] = $header_row[1];
											break;
										case 'connection':
											$_SERVER['HTTP_CONNECTION'] = $header_row[1];
											break;
										case 'cache-control':
											$_SERVER['HTTP_CACHE_CONTROL'] = $header_row[1];
											break;
										case 'accept':
											$_SERVER['HTTP_ACCEPT'] = $header_row[1];
											break;
										case 'accept-language':
											$_SERVER['HTTP_ACCEPT_LANGUAGE'] = $header_row[1];
											break;
										case 'accept-encoding':
											$_SERVER['HTTP_ACCEPT_ENCODING'] = $header_row[1];
											break;
									}
								}
							}
						}
						$headers[] = 'Content-Type: '.$mime_types['html'].'; charset=utf-8';
						console_log('Eval php code, namespace ID: '.$eval_namespace);
						ob_start();
							if(@eval('namespace EvalSpace\id'.($eval_namespace++).';?>'.$content) === false) {
								$error_get_last = error_get_last();
								if($error_get_last)
									echo '<br />'.FASTPHPWEB_CRLF.'FastPHPWeb error: '.$error_get_last['message'].' on line '.$error_get_last['line'].FASTPHPWEB_CRLF;
							}
							$content = ob_get_contents();
						ob_end_clean();
					}
					else if(!empty($mime_types[$type])) {
						$headers[] = 'Content-Type: '.$mime_types[$type];
						console_log('Response with mime.type: '.$mime_types[$type]);
					}
					$response = create_response($content, 'HTTP/1.0 200 OK', $headers);
				}
				else {
					$response = create_response(FASTPHPWEB_404, 'HTTP/1.0 404 Not Found');
				}
				fwrite($connect, $response);
			}
			elseif(FASTPHPWEB_ERROR_BEEP) {
				echo "\x07";
			}
		}
		elseif(FASTPHPWEB_ERROR_BEEP) {
			echo "\x07";
		}

		fclose($connect);
		unset($connects[array_search($connect, $connects)]);
		unset($headers);
		unset($content);
	}
}

fclose($server);