@echo off
title AI Subtitle Generator Launcher
echo ====================================================
echo   AI Subtitle & SRT Generator - Startup Launcher
echo ====================================================
echo.

:: 1. Start Python AI Service on 8001
echo [1/3] Starting Python AI Service (Port 8001)...
start "AI Service (FastAPI)" cmd /k "cd /d "%~dp0ai-service" && ..\.venv\Scripts\uvicorn.exe app.main:app --host 127.0.0.1 --port 8001"

:: 2. Start Laravel Queue Worker
echo [2/3] Starting Laravel Queue Worker...
start "Laravel Queue Worker" cmd /k "cd /d "%~dp0backend" && php artisan queue:work"

:: 3. Start Laravel Web Server on 8000
echo [3/3] Starting Laravel Web Server (Port 8000)...
start "Laravel Web Server" cmd /k "cd /d "%~dp0backend" && php artisan serve --host=127.0.0.1 --port=8000"

echo.
echo ====================================================
echo All services are starting up!
echo Opening http://127.0.0.1:8000 in your browser...
echo ====================================================
timeout /t 3 >nul
start http://127.0.0.1:8000
