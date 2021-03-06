# FastPHPWeb
Rapid method to heave up the web server with PHP. Default port 9000.

It all tested on:

	Windows XP/7/8.1/2012
	PHP 5.3.27/5.4.25
	Microsoft Visual C++ 2008 Redistributable

![Screen of directory](https://github.com/Vaflan/FastPHPWeb/blob/master/example.png?raw=true)


## Example of the use with included PHP
 - Check your Microsoft Visual C++ 2008 Redistributable Package for PHP
 - Unzip to any empty directory and run main.bat from this directory


## Example of the use with custom PHP version
 - You need to get any release of PHP 5.3+, it is possible from an official web-site http://windows.php.net/
 - to dispose this project next to php.exe (without: php.exe, php.ini, php5.dll)
 - to check what php.ini was next to php.exe, if it is not - to copy or create
 - and to start main.bat from this directory
 - or command line: CURRENT_DIR/php.exe -c php.ini main.php 9000



### Configurations
 - FASTPHPWEB_PORT: port to listen
 - FASTPHPWEB_CONTENT: folder directory site
 - FASTPHPWEB_MIME_TYPES: file type extensions list
 - FASTPHPWEB_404: a message when 404
 - FASTPHPWEB_TIMEZONE: setting Time Zone
 - FASTPHPWEB_EVAL_PHP: extensions executed as PHP
 - FASTPHPWEB_INDEX: file in the root of the executable as the index
 - FASTPHPWEB_LOG_REQUEST: log all header requests to the server
 - FASTPHPWEB_FAST_THREAD: asynchronous execution, a maximum of 64kb for a response
 - FASTPHPWEB_SHOW_AGENT: show in the console client request agent
 - FASTPHPWEB_ERROR_BEEP: the signal when the package or incomprehensible error
 - FASTPHPWEB_CRLF: separator packet headers


### Sources
mime.types from: was copied from the project of nginx

Favicon from: http://www.iconarchive.com/show/senary-icons-by-arrioch/Internet-php-icon.html
