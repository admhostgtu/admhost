@echo off
REM Setup AdmHost — PHP installe dans C:\Program Files\PHP
REM Executer en tant qu'administrateur (php.ini dans Program Files)

cd /d "%~dp0..\.."

echo === AdmHost — Setup (C:\Program Files\PHP) ===
echo.

powershell -ExecutionPolicy Bypass -File "%~dp0setup-windows.ps1" -PhpPath "C:\Program Files\PHP" %*

if errorlevel 1 pause
