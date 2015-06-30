<?php
namespace FastPHPWeb\engine;
define('FASTPHPWEB_PORT', !empty($argv[1]) ? intval($argv[1]) : '9000');
define('FASTPHPWEB_CONTENT', 'web');
define('FASTPHPWEB_MIME_TYPES', 'mime.types');
define('FASTPHPWEB_404', '404 Object Not Found');
define('FASTPHPWEB_TIMEZONE', 'Europe/Riga');
define('FASTPHPWEB_EVAL_PHP', 'php,phps,phtml');
define('FASTPHPWEB_INDEX', 'index.php');
define('FASTPHPWEB_LOG_REQUEST', false);
define('FASTPHPWEB_FAST_THREAD', false);
define('FASTPHPWEB_SHOW_AGENT', false);
define('FASTPHPWEB_ERROR_BEEP', false);
define('FASTPHPWEB_CRLF', "\r\n");
/* Function */
function console_log($message) {
	global $chcp;
	$message = trim(print_r($message, true));
	if(!empty($chcp)) {
		$message = iconv('UTF-8', 'cp'.$chcp.'//IGNORE', $message);
	}
	if(func_num_args() > 1) {
		for($i=1; $i<func_num_args(); $i++) {
			$message = str_replace('%'.$i.'%', func_get_arg($i), $message);
		}
	}
	echo $log = date('[d.m.Y H:i:s] ').$message.PHP_EOL;
	return $log;
}
function load_types($file) {
	$types = array();
	$load = explode('{', file_get_contents($file), 2);
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
			'Connection: keep-alive' . FASTPHPWEB_CRLF .
			FASTPHPWEB_CRLF . $content;
}
function response_header($header) {
	global $response_headers;
	$key = strtolower(trim(current(explode(':', $header, 2))));
	$response_headers[$key] = $header; 
}
function file_bytes($bytes) {
	global $memory_unit;
	return @round($bytes/pow(1024, ($i=floor(log($bytes, 1024)))), 2).$memory_unit[$i];
}
function fix_php_contents($content, $what, $to, $includes=false) {
	return preg_replace_callback("|\<\?([^\?]+[^\>]+)|i",
		function ($matches) use ($what, $to, $includes) {
			if($includes) {
				return preg_replace_callback("|([\s\t\n\;])(".$what."(_once)?)[\s\t\n]*([\(\'\"])(.*)([\'\"\)])|i",
					function ($php_matches) use ($what, $to) {
						unset($php_matches[0]);
						if(strstr($php_matches[3], '_once')) {
							unset($php_matches[3]);
							$php_matches[2] = str_replace($what.'_once', $to, $php_matches[2]);
						}
						else
							$php_matches[2] = str_replace($what, $to, $php_matches[2]);
						return implode('', $php_matches).')';
					},
					$matches[0]);
			}
			else {
				return preg_replace($what, $to, $matches[0]);
			}
		},
		$content);
}
function fix_includes($type, $file) {
	global $current_file_location;
	$file = str_replace('\\', '/', $file);
	if(dirname($file) == '.' || strstr($file, dirname($file))) {
		$file = dirname($current_file_location).DIRECTORY_SEPARATOR.$file;
	}
	switch($type) {
		case 'include':
			include($file);
			break;
		case 'require':
			require($file);
			break;
	}
}
/* Engine */
$eval_namespace = 0;
date_default_timezone_set(FASTPHPWEB_TIMEZONE);
register_shutdown_function('FastPHPWeb\engine\shutdown_service');
$mime_types = load_types(FASTPHPWEB_MIME_TYPES);
$socket = stream_socket_server('tcp://0.0.0.0:'.FASTPHPWEB_PORT, $errno, $errstr);
if(FASTPHPWEB_FAST_THREAD)
	stream_set_blocking($socket, false);
$memory_unit = array('b', 'kb', 'mb', 'gb', 'tb', 'pb');
if(!$socket) {
	die($errstr.' ('.$errno.')');
}
if(strstr(strtolower(PHP_OS), 'win')) {
	$chcp = trim(array_pop(explode(':', exec('chcp'))));
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
			if(FASTPHPWEB_FAST_THREAD)
				stream_set_blocking($connect, false); /* set asynchronous method */
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
		while($buffer = fgets($connect)) {
			$request .= trim($buffer)."\n";
			if(strstr($request, "\n\n"))
				break;
		}
		unset($buffer);
		if(strstr(strtolower($request), 'content-length:')) {
			$get_content_length = explode('content-length:', strtolower($request), 2);
			$get_content_length = explode("\n", $get_content_length[1], 2);
			$get_content_length = intval($get_content_length[0]) + 1;
			console_log('Getting content from client: '.file_bytes($get_content_length));
			$request .= fgets($connect, $get_content_length);
		}
		if(FASTPHPWEB_LOG_REQUEST)
			file_put_contents('request.log', file_get_contents('request.log').$request.FASTPHPWEB_CRLF.FASTPHPWEB_CRLF.FASTPHPWEB_CRLF);
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
			$header_get[0] = strtoupper($header_get[0]);
			$header_get[1] = explode('?', $header_get[1], 2);
			if(in_array($header_get[0], array('GET', 'POST'))) {
				$location = $header_get[1][0];
				if(substr($location, -1) == '/') {
					$location .= FASTPHPWEB_INDEX;
				}
				$location = str_replace('/', DIRECTORY_SEPARATOR, $location);
				if(substr($location, 0 , 1) != DIRECTORY_SEPARATOR) {
					$location = DIRECTORY_SEPARATOR.$location;
				}
				$get_request = FASTPHPWEB_CONTENT.$location;
				$current_file_location = dirname(__FILE__).DIRECTORY_SEPARATOR.$get_request;
				if(is_file($get_request)) {
					$type = array_pop(explode('.', $get_request));
					$content = file_get_contents($get_request);
					if(in_array($type, explode(',', FASTPHPWEB_EVAL_PHP))) {
						$_GET = $_POST = $_COOKIE = array();
						$content = fix_php_contents($content, '/\b__FILE__\b/', '\''.$current_file_location.'\'');
						$content = fix_php_contents($content, '/\b__DIR__\b/', '\''.dirname($current_file_location).'\'');
						$content = fix_php_contents($content, '/\bheader\(/i', '\FastPHPWeb\engine\response_header(');
						$content = fix_php_contents($content, 'include', '\FastPHPWeb\engine\fix_includes(\'include\',', true);
						$content = fix_php_contents($content, 'require', '\FastPHPWeb\engine\fix_includes(\'require\',', true);
						$remote_addr = explode(':', stream_socket_get_name($connect, true), 2);
						$_SERVER['REMOTE_PORT'] = array_pop($remote_addr);
						$_SERVER['REMOTE_ADDR'] = current($remote_addr);
						$_SERVER['SERVER_PORT'] = FASTPHPWEB_PORT;
						$_SERVER['REQUEST_METHOD'] = $header_get[0];
						$_SERVER['SCRIPT_NAME'] = $header_get[1][0];
						$_SERVER['REQUEST_URI'] = implode('?', $header_get[1]);
						if(!empty($header_get[1][1])) {
							$_SERVER['QUERY_STRING'] = $header_get[1][1];
							parse_str($header_get[1][1], $_GET);
						}
						if(strstr($header_get[0], 'POST')) {
							$request_body = trim(array_pop(explode("\n\n", $request)));
							parse_str($request_body, $_POST);
						}
						$_REQUEST = array_merge($_GET, $_POST, $_COOKIE);
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
						unset($_REQUEST, $_COOKIE, $_POST, $_GET);
					}
					else if(!empty($mime_types[$type])) {
						response_header('Content-Type: '.$mime_types[$type]);
						console_log('Response with mime.type: '.$mime_types[$type]);
					}
					$response = create_response($content, 'HTTP/1.0 200 OK', $response_headers);
					unset($content);
				}
				else {
					$response = create_response(FASTPHPWEB_404, 'HTTP/1.0 404 Not Found');
				}
				fwrite($connect, $response);
				$response_size = strlen($response);
				console_log('Response size: '.file_bytes($response_size).' ('.ceil($response_size/65535).' packets)');
			}
			elseif(FASTPHPWEB_ERROR_BEEP) {
				echo "\x07";
			}
		}
		elseif(FASTPHPWEB_ERROR_BEEP) {
			echo "\x07";
		}
		fclose($connect);
		unset($connects[array_search($connect, $connects)], $response_headers, $request);
		console_log('Peak of memory: ' . file_bytes(memory_get_peak_usage()));
	}
}
fclose($server);