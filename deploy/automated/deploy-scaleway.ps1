# =============================================================================
# Déploiement automatisé VPS complet — PowerShell
# Usage :
#   $env:VPS_PASSWORD = '...'
#   $env:DB_PASSWORD  = '...'
#   .\deploy\automated\deploy-scaleway.ps1
# =============================================================================

$ErrorActionPreference = "Stop"
$Plink = "C:\Program Files\PuTTY\plink.exe"
$Pscp  = "C:\Program Files\PuTTY\pscp.exe"

$VpsHost     = if ($env:VPS_HOST) { $env:VPS_HOST } else { "51.159.66.221" }
$VpsUser     = if ($env:VPS_USER) { $env:VPS_USER } else { "gtusuperuser" }
$VpsPassword = $env:VPS_PASSWORD
$DbPassword  = $env:DB_PASSWORD

if (-not $VpsPassword) { throw "Definir `$env:VPS_PASSWORD" }
if (-not $DbPassword)  { throw "Definir `$env:DB_PASSWORD" }

$remoteScript = @'
#!/bin/bash
set -e
export DB_PASSWORD='__DB_PASSWORD__'
export APP_ENCRYPTION_KEY='__ENC_KEY__'
git config --global --add safe.directory /var/www/admhost
cd /var/www/admhost
git fetch origin && git reset --hard origin/main
bash deploy/deploy-vps.sh
'@

$EncKey = $env:APP_ENCRYPTION_KEY
if (-not $EncKey) {
    $bytes = New-Object byte[] 32
    [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    $EncKey = -join ($bytes | ForEach-Object { $_.ToString("x2") })
}

$remoteScript = $remoteScript.Replace('__DB_PASSWORD__', $DbPassword.Replace("'", "'\\''"))
$remoteScript = $remoteScript.Replace('__ENC_KEY__', $EncKey)

$tmp = [IO.Path]::GetTempFileName() + ".sh"
$remoteScript -replace "`r`n", "`n" | Set-Content -Path $tmp -NoNewline -Encoding utf8

Write-Host "=== Deploiement VPS complet (4 sites) ===" -ForegroundColor Cyan
echo y | & $Pscp -pw $VpsPassword $tmp "${VpsUser}@${VpsHost}:/tmp/admhost_vps_deploy.sh"
Remove-Item $tmp -Force

echo y | & $Plink -batch -ssh "${VpsUser}@${VpsHost}" -pw $VpsPassword `
    "echo $VpsPassword | su -c 'bash /tmp/admhost_vps_deploy.sh; rm -f /tmp/admhost_vps_deploy.sh' root"

Write-Host ""
Write-Host "=== DNS O2Switch (A -> $VpsHost) ===" -ForegroundColor Yellow
Write-Host "  admhost.fr, www, console, manage.console, api"
Write-Host ""
Write-Host "=== SSL ===" -ForegroundColor Green
Write-Host "  certbot --nginx -d admhost.fr -d console.admhost.fr -d manage.console.admhost.fr -d api.admhost.fr"
