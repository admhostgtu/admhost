@echo off
REM Demarrage local AdmHost — Windows
REM Prerequis : PHP dans le PATH, MySQL actif

cd /d "%~dp0..\.."

echo === AdmHost — Demarrage local ===
echo.

where php >nul 2>&1
if errorlevel 1 (
    if exist "C:\Program Files\PHP\php.exe" (
        set "PATH=C:\Program Files\PHP;%PATH%"
        echo [INFO] PHP utilise depuis C:\Program Files\PHP
    ) else (
        echo [ERREUR] PHP introuvable. Lancez : scripts\dev\setup-php-programfiles.bat
        pause
        exit /b 1
    )
)

if not exist .env (
    echo Creation .env depuis .env.example...
    copy .env.example .env
)

REM Verifier pdo_mysql
php -r "exit(extension_loaded('pdo_mysql')?0:1);" >nul 2>&1
if errorlevel 1 (
    echo [ERREUR] Extension pdo_mysql inactive.
    echo Lancez d'abord : scripts\dev\setup-windows.bat
    pause
    exit /b 1
)

echo Migration BDD...
php scripts\migrate.php
if errorlevel 1 (
    echo [WARN] Migration echouee — verifiez MySQL et .env
    echo Continuer sans BDD ? Les pages statiques fonctionneront.
)

echo Seed donnees test...
php scripts\seed.php 2>nul

echo.
echo Demarrage des serveurs :
echo   Frontend : http://localhost:8000
echo   Backend  : http://localhost:8001
echo   Admin    : http://localhost:8002
echo.
echo Comptes test :
echo   Admin  : admin@example.com / admin123
echo   Client : jean@example.com / password123
echo.

start "AdmHost Frontend" cmd /k php -S localhost:8000 -t frontend/public frontend/public/router.php
start "AdmHost Backend"  cmd /k php -S localhost:8001 -t backend/public backend/public/router.php
start "AdmHost Admin"    cmd /k php -S localhost:8002 -t admin/public admin/public/router.php

echo Serveurs lances dans des fenetres separees.
