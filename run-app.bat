@echo off
title AI Subtitle Generator Launcher
echo ====================================================
echo   AI Subtitle & SRT Generator - Startup Launcher
echo ====================================================
echo.

:: 1. Start Python AI Service on 8001
echo [1/3] Starting Python AI Service (Port 8001)...
start "AI Service (FastAPI)" /D "%~dp0ai-service" cmd /k "\"%~dp0.venv\Scripts\python.exe\" -m uvicorn app.main:app --host 127.0.0.1 --port 8001 --timeout-keep-alive 300"

:: Wait for the AI service before starting Laravel.
echo Waiting for AI Service to become ready...
set "AI_READY=0"
for /l %%i in (1,1,60) do (
    powershell -NoProfile -Command "try { Invoke-WebRequest -UseBasicParsing -TimeoutSec 2 http://127.0.0.1:8001/health | Out-Null; exit 0 } catch { exit 1 }"
    if not errorlevel 1 (
        set "AI_READY=1"
        goto :ai_ready
    )
    timeout /t 2 /nobreak >nul
)

:ai_ready
if "%AI_READY%"=="0" (
    echo ERROR: AI Service did not start on port 8001.
    echo Check the AI Service window for the startup error.
    pause
    exit /b 1
)

:: 2. Start Laravel Queue Worker
echo [2/3] Starting Laravel Queue Worker...
start "Laravel Queue Worker" /D "%~dp0backend" cmd /k "php artisan queue:work --tries=3 --backoff=5 --sleep=2 --timeout=3900"

:: 3. Start Laravel Web Server on 8000
echo [3/3] Starting Laravel Web Server (Port 8000)...
start "Laravel Web Server" /D "%~dp0backend" cmd /k "php artisan serve --host=127.0.0.1 --port=8000"

echo.
echo ====================================================
echo All services are starting up!
echo Opening http://127.0.0.1:8000 in your browser...
echo ====================================================
timeout /t 3 >nul
start http://127.0.0.1:8000
