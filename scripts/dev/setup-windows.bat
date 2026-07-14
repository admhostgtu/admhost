@echo off
REM =============================================================================
REM Setup automatique AdmHost — Windows
REM Lance setup-windows.ps1 (PHP PATH + php.ini + BDD + serveurs)
REM =============================================================================
REM
REM Clic droit > Executer en tant qu'administrateur (recommande pour winget)
REM
REM Ou en ligne de commande :
REM   scripts\dev\setup-windows.bat
REM   scripts\dev\setup-windows.bat C:\php
REM   scripts\dev\setup-windows.bat C:\php C:\xampp\mysql\bin\mysql.exe
REM =============================================================================

# Chemin PHP par defaut (modifiable)
set "DEFAULT_PHP_PATH=C:\Program Files\PHP"

cd /d "%~dp0..\.."

set PHP_PATH=%DEFAULT_PHP_PATH%
set MYSQL_PATH=
if not "%~1"=="" set PHP_PATH=%~1
if not "%~2"=="" set MYSQL_PATH=%~2

echo.
echo === AdmHost — Setup Windows automatique ===
echo.

where powershell >nul 2>&1
if errorlevel 1 (
    echo [ERREUR] PowerShell introuvable.
    pause
    exit /b 1
)

set PS_ARGS=-ExecutionPolicy Bypass -File "%~dp0setup-windows.ps1"
if not "%PHP_PATH%"=="" set PS_ARGS=%PS_ARGS% -PhpPath "%PHP_PATH%"
if not "%MYSQL_PATH%"=="" set PS_ARGS=%PS_ARGS% -MySqlPath "%MYSQL_PATH%"

powershell %PS_ARGS%

if errorlevel 1 (
    echo.
    echo [ERREUR] Setup echoue. Consultez les messages ci-dessus.
    pause
    exit /b 1
)

echo.
pause
