@echo off
setlocal enabledelayedexpansion

cd /d "%~dp0"

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0client-pharmacy.ps1"

exit /b
