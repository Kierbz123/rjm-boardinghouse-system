@echo off
setlocal
title RJM Boardinghouse System Launcher
cd /d "%~dp0claude-code-project-files"

set "PHP=C:\xampp\php\php.exe"
set "MYSQL_START=C:\xampp\mysql_start.bat"
set "PORT=8000"

echo ===================================================================
echo     RJM Boardinghouse Rent, Maintenance ^& Security System
echo ===================================================================
echo.

echo [1/5] Checking PHP ...
if not exist "%PHP%" (
    echo       [ERROR] PHP not found at %PHP%. Install XAMPP or edit PHP= at the top of this file.
    goto :fail
)
echo       [OK] %PHP%

echo [2/5] Checking MySQL on port 3306 ...
call :port_open 3306
if errorlevel 1 (
    if exist "%MYSQL_START%" (
        echo       MySQL is not running - starting XAMPP MySQL ...
        start "XAMPP MySQL" /min cmd /c "%MYSQL_START%"
        call :wait_port 3306 20
    )
)
call :port_open 3306
if errorlevel 1 (
    echo       [ERROR] MySQL is not reachable. Start it from the XAMPP Control Panel, then run this again.
    goto :fail
)
echo       [OK] MySQL is running.

echo [3/5] Applying database migrations ...
"%PHP%" database\migrate.php
if errorlevel 1 (
    echo       [ERROR] Migrations failed - see the message above.
    goto :fail
)

echo [4/5] Checking the optional local AI assistant (Ollama) ...
call :port_open 11434
if errorlevel 1 (
    echo       [INFO] Ollama is not running. The app works without it; AI helper buttons will say "offline".
) else (
    echo       [OK] Ollama is online.
)

echo [5/5] Starting the web app on http://127.0.0.1:%PORT% ...
call :port_open %PORT%
if not errorlevel 1 (
    echo       [INFO] Something is already serving port %PORT% - opening it.
) else (
    start "RJM Boardinghouse - web server (keep this window open)" cmd /k ""%PHP%" -S 127.0.0.1:%PORT% -t public"
    call :wait_port %PORT% 15
)

start "" http://127.0.0.1:%PORT%/
echo.
echo ===================================================================
echo  Running. Keep the "web server" window open while using the system.
echo  Close that window to stop the app.
echo ===================================================================
echo.
pause
exit /b 0

:fail
echo.
pause
exit /b 1

rem --- helpers -------------------------------------------------------
:port_open
rem errorlevel 0 when something is listening on 127.0.0.1:%1
powershell -NoProfile -Command "try { (New-Object Net.Sockets.TcpClient).Connect('127.0.0.1', %1); exit 0 } catch { exit 1 }"
exit /b %errorlevel%

:wait_port
rem wait up to %2 seconds for port %1
for /l %%i in (1,1,%2) do (
    call :port_open %1
    if not errorlevel 1 exit /b 0
    timeout /t 1 >nul
)
exit /b 1
