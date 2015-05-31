<?php
namespace FastPHPWeb\engine;
define('FASTPHPWEB_PORT', !empty($argv[1]) ? intval($argv[1]) : '80');
define('FASTPHPWEB_CONTENT', 'web');
define('FASTPHPWEB_MIME_TYPES', 'mime.types');
define('FASTPHPWEB_404', '404 Object Not Found');
define('FASTPHPWEB_TIMEZONE', 'Europe/Riga');
define('FASTPHPWEB_EVAL_PHP', 'php,phps,phtml');
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
	echo $log = date('[d.m.Y H:i:s] ').$message."\n";
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
function shutdown_service() {
	$e = error_get_last();
	if($e == null)
		$e = array('message' => 'No errors', 'line' => 0);
	console_log($e['message'].' [line: '.$e['line'].']');
}
function create_response($content, $status, $headers=array()) {
	return $status. FASTPHPWEB_CRLF .
			'Server: FastPHPWeb' . FASTPHPWEB_CRLF .
			(count($headers) > 0 ? implode(FASTPHPWEB_CRLF, $headers).FASTPHPWEB_CRLF : '') .
			'Content-Length: ' . strlen($content) . FASTPHPWEB_CRLF .
			'Connection: close' . FASTPHPWEB_CRLF .
			FASTPHPWEB_CRLF . $content;
}
function response_header($header) {
	global $response_headers;
	$key = strtolower(current(explode(':', $header, 2)));
	$response_headers[$key] = $header; 
}
function fix_php_contents($content, $what, $to) {
	return preg_replace_callback(
		"|<\?(?:\s[^>]*)?([^?]+)\?>|i",
		function ($matches) use ($what, $to) {
			return '<?'.str_replace($what, $to, $matches[1]).'?>';
		},
		$content);
}



/* Engine */
$eval_namespace = 0;
date_default_timezone_set(FASTPHPWEB_TIMEZONE);
register_shutdown_function('FastPHPWeb\engine\shutdown_service');
$mime_types = load_types(FASTPHPWEB_MIME_TYPES);
$socket = stream_socket_server('tcp://0.0.0.0:'.FASTPHPWEB_PORT, $errno, $errstr);
$memory_unit = array('b', 'kb', 'mb', 'gb', 'tb', 'pb');
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
		$response_headers = array();

		$request = '';
		while($buffer = rtrim(fgets($connect))) {
			$request .= $buffer."\n";
		}

		$request_header_array = explode("\n", $request);
		$header_get = explode(' ', trim($request_header_array[0]));
		if(!empty($request_header_array[0]))
			console_log($request_header_array[0]);
		elseif(count($request_header_array) > 1)
			console_log($request_header_array);

		/* show User Agent */
		if(FASTPHPWEB_SHOW_AGENT && count($request_header_array) > 1) {
			foreach($request_header_array AS $request_header_row) {
				$request_header_row = explode(':', trim($request_header_row), 2);
				switch(strtolower($request_header_row[0])) {
					case 'host':
						console_log('Host: '.trim($request_header_row[1]));
						break;
					case 'user-agent':
						console_log('User-Agent: '.trim($request_header_row[1]));
						break;
				}
			}
		}

		if(count($header_get) > 2 && strstr(strtolower($header_get[2]), 'http/1.1')) {
			if(in_array(strtolower($header_get[0]), array('get', 'post'))) {
				$location = current(explode('?', $header_get[1], 2));
				if(substr(str_replace('\\', '/', $location), -1) == '/') {
					$location .= FASTPHPWEB_INDEX;
				}
				$location = str_replace('/', DIRECTORY_SEPARATOR, $location);
				if(substr(DIRECTORY_SEPARATOR, 0 , 1) != DIRECTORY_SEPARATOR)
					$location = DIRECTORY_SEPARATOR.$location;

				$get_request = FASTPHPWEB_CONTENT.$location;
				$current_file_location = dirname(__FILE__).DIRECTORY_SEPARATOR.FASTPHPWEB_CONTENT.$location;
				if(is_file($get_request)) {
					$type = array_pop(explode('.', $get_request));
					$content = file_get_contents($get_request);
					if(in_array($type, explode(',', FASTPHPWEB_EVAL_PHP))) {
						$content = fix_php_contents($content, '__FILE__', '\''.$current_file_location.'\'');
						$content = fix_php_contents($content, '__DIR__', '\''.dirname($current_file_location).'\'');
						$content = fix_php_contents($content, array(' header(', "\n".'header(', "\t".'header('), array(' \FastPHPWeb\engine\response_header(', "\n".'\FastPHPWeb\engine\response_header(', "\t".'\FastPHPWeb\engine\response_header('));
						$remote_addr = explode(':', stream_socket_get_name($connect, true), 2);
						$_SERVER['REMOTE_PORT'] = array_pop($remote_addr);
						$_SERVER['REMOTE_ADDR'] = current($remote_addr);
						$_SERVER['SERVER_PORT'] = FASTPHPWEB_PORT;
						if(count($request_header_array) > 1) {
							foreach($request_header_array AS $request_header_row) {
								$request_header_row = explode(':', trim($request_header_row), 2);
								if(count($request_header_row) > 1) {
									$request_header_row[0] = strtoupper(trim($request_header_row[0]));
									$request_header_row[1] = trim($request_header_row[1]);
									if(in_array($request_header_row[0], array('HOST', 'USER-AGENT', 'COOKIE', 'CONNECTION', 'CACHE-CONTROL', 'ACCEPT', 'ACCEPT-LANGUAGE', 'ACCEPT-ENCODING'))) {
										$_SERVER['HTTP_'.str_replace('-', '_', $request_header_row[0])] = $request_header_row[1];
									}
								}
							}
						}
						response_header('Content-Type: '.$mime_types['html'].'; charset=utf-8');
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
						response_header('Content-Type: '.$mime_types[$type]);
						console_log('Response with mime.type: '.$mime_types[$type]);
					}
					$response = create_response($content, 'HTTP/1.0 200 OK', $response_headers);
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
		unset($response_headers);
		unset($request);
		unset($content);

		$momory_size = memory_get_peak_usage();
		console_log('Peak of memory: ' . @round($momory_size/pow(1024,($i=floor(log($momory_size, 1024)))), 2).$memory_unit[$i]);
	}
}

fclose($server);