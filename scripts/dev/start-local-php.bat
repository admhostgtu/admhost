@echo off
REM Demarrage local avec PHP dans C:\Program Files\PHP

set "PHP=C:\Program Files\PHP\php.exe"
set "PATH=C:\Program Files\PHP;%PATH%"

cd /d "%~dp0..\.."

if not exist "%PHP%" (
    echo [ERREUR] php.exe introuvable : %PHP%
    pause
    exit /b 1
)

"%PHP%" -v
call "%~dp0start-local.bat"
