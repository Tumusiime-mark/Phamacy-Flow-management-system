@echo off
setlocal enabledelayedexpansion

:: Configuration
set "PORT=8000"

:: Kill PHP process on the specified port
for /f "tokens=5" %%a in ('netstat -ano ^| findstr ":%PORT%"') do (
    taskkill /PID %%a /F >nul 2>&1
)

:: Show confirmation
set "popup=!temp!\pharmacy_stop_popup.vbs"
(
    echo Set objShell = CreateObject("WScript.Shell"^)
    echo MsgBox "Pharmacy Management System has been stopped.", 64, "System Stopped"
) > "!popup!"

cscript.exe "!popup!" >nul
del /f /q "!popup!" >nul 2>&1

exit /b
