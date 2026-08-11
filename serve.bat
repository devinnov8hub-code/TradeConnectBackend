@echo off
set "PHP83=C:\Users\innov8 software - Ab\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe"
set "PATH=%PHP83%;%PATH%"
cd /d "%~dp0"
php artisan serve
