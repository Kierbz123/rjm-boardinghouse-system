@echo off
title RJM Boardinghouse System Launcher
echo ===================================================================
echo     RJM Boardinghouse Rent, Maintenance & Security System
echo ===================================================================
echo.
echo [1/4] Checking Ollama AI status on http://127.0.0.1:11434 ...
curl -s -m 2 http://127.0.0.1:11434/api/tags >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo       [OK] Ollama AI is active and online.
) else (
    echo       [INFO] Ollama is not running. Local AI assistant features will be disabled.
    echo              (To enable: install from ollama.com and run 'ollama run llama3.2:3b')
)
echo.
echo [2/4] Please verify MySQL is running in your XAMPP Control Panel.
echo.
echo [3/4] Starting Python Scoring Microservice on http://127.0.0.1:5000 ...
start "RJM Scoring Service (Port 5000)" cmd /k "cd /d "%~dp0scoring_service" && python main.py"

echo [4/4] Starting PHP Web Server on http://127.0.0.1:8000 ...
start "RJM PHP Web Application (Port 8000)" cmd /k "cd /d "%~dp0" && C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public"

echo.
echo Waiting 2 seconds before launching the browser...
timeout /t 2 >nul
start http://127.0.0.1:8000/

echo ===================================================================
echo System launched! 
echo Keep the two opened command prompt windows running while using the system.
echo ===================================================================
echo.
pause
