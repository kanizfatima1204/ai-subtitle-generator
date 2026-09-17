@echo off
title Railway Deployment - AI Subtitle Generator
echo ====================================================
echo   AI Subtitle Generator - Railway Live Link Deploy
echo ====================================================
echo.
cd /d "%~dp0"

echo [1/3] Checking Railway login status...
call npx --yes @railway/cli whoami >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo Please log in to your Railway account in the browser that opens:
    call npx @railway/cli login
    if %ERRORLEVEL% NEQ 0 (
        echo [ERROR] Railway login failed. Please try again.
        pause
        exit /b 1
    )
)

echo.
echo [2/3] Linking or initializing Railway project...
call npx @railway/cli status >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo No linked Railway project found. Initializing a new one...
    call npx @railway/cli init
)

echo.
echo [3/3] Uploading and deploying project to Railway...
call npx @railway/cli up

echo.
echo ====================================================
echo Generating public live link domain...
call npx @railway/cli domain
echo.
echo Opening your live Railway app...
call npx @railway/cli open
echo ====================================================
pause
