@echo off
title Stop AI Subtitle Generator
echo Stopping AI Subtitle Generator services...
taskkill /FI "WINDOWTITLE eq AI Service (FastAPI)*" /T /F >nul 2>&1
taskkill /FI "WINDOWTITLE eq Laravel Queue Worker*" /T /F >nul 2>&1
taskkill /FI "WINDOWTITLE eq Laravel Web Server*" /T /F >nul 2>&1
echo Done.
