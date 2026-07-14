# Demarrage local AdmHost — PowerShell
# Usage : .\scripts\dev\start-local.ps1

$Root = Resolve-Path (Join-Path $PSScriptRoot "../..")
Set-Location $Root

Write-Host "=== AdmHost — Demarrage local ===" -ForegroundColor Cyan

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    Write-Host "[ERREUR] PHP introuvable dans le PATH." -ForegroundColor Red
    Write-Host "Installez PHP : https://windows.php.net/download/"
    exit 1
}

if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Host ".env cree depuis .env.example"
}

php scripts/migrate.php
php scripts/seed.php

Write-Host ""
Write-Host "URLs :" -ForegroundColor Green
Write-Host "  Frontend : http://localhost:8000"
Write-Host "  Backend  : http://localhost:8001/api/health"
Write-Host "  Admin    : http://localhost:8002/admin/login"
Write-Host ""
Write-Host "Comptes test :" -ForegroundColor Yellow
Write-Host "  Admin  : admin@example.com / admin123"
Write-Host "  Client : jean@example.com / password123"
Write-Host ""

Start-Process php -ArgumentList "-S","localhost:8000","-t","frontend/public","frontend/public/router.php" -WindowStyle Normal
Start-Process php -ArgumentList "-S","localhost:8001","-t","backend/public","backend/public/router.php" -WindowStyle Normal
Start-Process php -ArgumentList "-S","localhost:8002","-t","admin/public","admin/public/router.php" -WindowStyle Normal

Write-Host "Serveurs demarres." -ForegroundColor Green
