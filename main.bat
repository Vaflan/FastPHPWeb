@echo off
cd %~dp0
title FastPHPWeb

echo Try start FastPHPWeb...

:reboot
php.exe -c php.ini main.php 9000

echo.
echo Try reboot FastPHPWeb...
goto reboot