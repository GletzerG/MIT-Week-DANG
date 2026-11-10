@echo off
setlocal
title Guardian Genesis

rem ---------------------------------------------------------------
rem  Cari php.exe tanpa bergantung pada PATH Windows.
rem ---------------------------------------------------------------
set "PHP=C:\laragon\bin\php\php-8.4.11-nts-Win32-vs17-x64\php.exe"
if not exist "%PHP%" set "PHP=C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\php.exe"

if not exist "%PHP%" (
  echo.
  echo   [X] php.exe tidak ditemukan di folder Laragon.
  echo       Buka Laragon - Menu - Preferences - Environment,
  echo       atau install PHP lewat Laragon.
  echo.
  pause
  exit /b 1
)

cd /d "%~dp0"

if not exist "vendor\autoload.php" (
  echo.
  echo   [X] Folder vendor\ kosong. Dependency belum ada.
  echo.
  pause
  exit /b 1
)

if not exist "database\database.sqlite" (
  echo   [!] database.sqlite belum ada, membuat...
  type nul > "database\database.sqlite"
  "%PHP%" artisan migrate --seed --force
)

echo.
echo   Guardian Genesis
echo   ---------------------------------------------
echo   PHP    : %PHP%
echo   Buka   : http://127.0.0.1:8000
echo   Login  : guardian@genesis.test / password
echo   Arena  : http://127.0.0.1:8000/quest
echo   ---------------------------------------------
echo   Tutup jendela ini untuk mematikan server.
echo.

rem Buka browser otomatis setelah server sempat hidup.
start "" cmd /c "timeout /t 3 >nul & start http://127.0.0.1:8000"

"%PHP%" artisan serve --port=8000
pause