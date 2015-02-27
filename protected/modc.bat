@echo off

@setlocal

set MOD_PATH=%~dp0

if "%PHP_COMMAND%" == "" set PHP_COMMAND=php.exe

"%PHP_COMMAND%" "%MOD_PATH%yiic" %*

@endlocal