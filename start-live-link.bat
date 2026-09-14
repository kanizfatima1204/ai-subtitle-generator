@echo off
title Cloudflare Live Tunnel Launcher
echo ====================================================
echo   Starting Cloudflare Live Public Link...
echo ====================================================
cd /d "%~dp0"
.\cloudflared.exe tunnel --url http://127.0.0.1:8000
pause
