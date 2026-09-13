@echo off
title RJM Boardinghouse System Launcher
echo ===================================================================
echo     RJM Boardinghouse Rent, Maintenance & Security System
echo ===================================================================
echo.
echo [1/3] Please verify MySQL is running in your XAMPP Control Panel.
echo.
echo [2/3] Starting Python Scoring Microservice on http://127.0.0.1:5000 ...
start "RJM Scoring Service (Port 5000)" cmd /k "cd /d "%~dp0scoring_service" && python main.py"

echo [3/3] Starting PHP Web Server on http://127.0.0.1:8000 ...
start "RJM PHP Web Application (Port 8000)" cmd /k "cd /d "%~dp0" && C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public"

echo.
echo Waiting 2 seconds before launching the browser...
timeout /t 2 >nul
start http://127.0.0.1:8000/login

echo ===================================================================
echo System launched! 
echo Keep the two opened command prompt windows running while using the system.
echo ===================================================================
echo.
pause
