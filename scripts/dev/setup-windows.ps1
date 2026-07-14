# =============================================================================
# Setup complet environnement AdmHost — Windows
# =============================================================================
#
# Ce script automatise :
#   1. Détection / installation PHP (winget si absent)
#   2. Ajout PHP au PATH utilisateur
#   3. Configuration php.ini (pdo_mysql, mysqli, extension_dir)
#   4. Création base de données MySQL "admhost"
#   5. Migration + seed
#   6. Lancement start-local.bat
#
# Usage (PowerShell en administrateur RECOMMANDÉ pour winget/PATH) :
#   powershell -ExecutionPolicy Bypass -File scripts\dev\setup-windows.ps1
#
# Usage sans admin (PATH utilisateur uniquement) :
#   powershell -ExecutionPolicy Bypass -File scripts\dev\setup-windows.ps1 -SkipWinget
#
# =============================================================================

param(
    [string]$PhpPath = "",
    [string]$MySqlPath = "",
    [switch]$SkipWinget,
    [switch]$SkipStart,
    [switch]$CreateDbUser
)

$ErrorActionPreference = "Stop"
$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot "../..")
Set-Location $ProjectRoot

function Write-Step($msg)  { Write-Host "`n==> $msg" -ForegroundColor Cyan }
function Write-Ok($msg)    { Write-Host "[OK] $msg" -ForegroundColor Green }
function Write-Warn($msg)  { Write-Host "[WARN] $msg" -ForegroundColor Yellow }
function Write-Err($msg)   { Write-Host "[ERREUR] $msg" -ForegroundColor Red }

Write-Host "============================================" -ForegroundColor White
Write-Host "  AdmHost — Setup automatique Windows" -ForegroundColor White
Write-Host "  Projet : $ProjectRoot" -ForegroundColor Gray
Write-Host "============================================" -ForegroundColor White

# ---------------------------------------------------------------------------
# 1. PHP — détection ou installation
# ---------------------------------------------------------------------------
Write-Step "1/6 — PHP"

function Find-PhpDirectory {
    param([string]$ExplicitPath)

    if ($ExplicitPath -and (Test-Path (Join-Path $ExplicitPath "php.exe"))) {
        return (Resolve-Path $ExplicitPath).Path
    }

    $cmd = Get-Command php -ErrorAction SilentlyContinue
    if ($cmd) { return Split-Path $cmd.Source -Parent }

    $candidates = @(
        "C:\Program Files\PHP",
        "C:\Program Files (x86)\PHP",
        "C:\php",
        "C:\tools\php",
        "$env:LOCALAPPDATA\Programs\PHP",
        "C:\xampp\php",
        "C:\laragon\bin\php\php-8.3*",
        "C:\laragon\bin\php\php-8.2*",
        "C:\laragon\bin\php\php-8.1*"
    )

    foreach ($pattern in $candidates) {
        $resolved = Resolve-Path $pattern -ErrorAction SilentlyContinue
        foreach ($dir in $resolved) {
            if (Test-Path (Join-Path $dir "php.exe")) {
                return $dir.Path
            }
        }
    }
    return $null
}

$phpDir = Find-PhpDirectory -ExplicitPath $PhpPath

if (-not $phpDir -and -not $SkipWinget) {
    Write-Warn "PHP introuvable — tentative installation via winget..."
    $winget = Get-Command winget -ErrorAction SilentlyContinue
    if ($winget) {
        try {
            winget install --id PHP.PHP.8.3 --accept-package-agreements --accept-source-agreements --silent
            Start-Sleep -Seconds 5
            $phpDir = Find-PhpDirectory
        } catch {
            Write-Warn "winget a echoue : $_"
        }
    } else {
        Write-Warn "winget non disponible. Installez PHP manuellement : https://windows.php.net/download/"
    }
}

if (-not $phpDir) {
    Write-Err "PHP introuvable. Relancez avec : -PhpPath 'C:\chemin\vers\php'"
    Write-Host "  Exemple : powershell -ExecutionPolicy Bypass -File scripts\dev\setup-windows.ps1 -PhpPath C:\php"
    exit 1
}

Write-Ok "PHP trouve : $phpDir"
$phpExe = Join-Path $phpDir "php.exe"
& $phpExe -v | Select-Object -First 1

# ---------------------------------------------------------------------------
# 2. PATH Windows (utilisateur)
# ---------------------------------------------------------------------------
Write-Step "2/6 — PATH Windows"

$userPath = [Environment]::GetEnvironmentVariable("Path", "User")
$pathParts = $userPath -split ";" | Where-Object { $_ -ne "" }

if ($pathParts -notcontains $phpDir) {
    $newPath = ($pathParts + $phpDir) -join ";"
    [Environment]::SetEnvironmentVariable("Path", $newPath, "User")
    $env:Path = "$phpDir;$env:Path"
    Write-Ok "PHP ajoute au PATH utilisateur : $phpDir"
    Write-Warn "Redemarrez le terminal si 'php' n'est pas reconnu apres ce script."
} else {
    Write-Ok "PHP deja dans le PATH"
    $env:Path = "$phpDir;$env:Path"
}

# ---------------------------------------------------------------------------
# 3. php.ini — pdo_mysql + extension_dir
# ---------------------------------------------------------------------------
Write-Step "3/6 — php.ini (pdo_mysql)"

$iniOutput = & $phpExe --ini 2>&1 | Out-String
$iniLoaded = $null

if ($iniOutput -match "Loaded Configuration File:\s+(.+)") {
    $iniLoaded = $Matches[1].Trim()
}

if (-not $iniLoaded -or $iniLoaded -eq "(none)") {
    $devIni  = Join-Path $phpDir "php.ini-development"
    $prodIni = Join-Path $phpDir "php.ini-production"
    $target  = Join-Path $phpDir "php.ini"

    if (-not (Test-Path $target)) {
        if (Test-Path $devIni) {
            Copy-Item $devIni $target
            Write-Ok "php.ini cree depuis php.ini-development"
        } elseif (Test-Path $prodIni) {
            Copy-Item $prodIni $target
            Write-Ok "php.ini cree depuis php.ini-production"
        } else {
            Write-Err "Impossible de creer php.ini dans $phpDir"
            exit 1
        }
    }
    $iniLoaded = $target
}

Write-Ok "php.ini : $iniLoaded"

$iniContent = Get-Content $iniLoaded -Raw
$changed = $false

# extension_dir (Windows)
$extDir = Join-Path $phpDir "ext"
if ($iniContent -notmatch "^\s*extension_dir\s*=" -or $iniContent -match ';extension_dir\s*=') {
    $iniContent = $iniContent -replace "(?m)^;?\s*extension_dir\s*=.*$", "extension_dir = `"$extDir`""
    $changed = $true
    Write-Ok "extension_dir = $extDir"
}

# Extensions MySQL
$extensions = @("pdo_mysql", "mysqli", "openssl", "mbstring", "curl")
foreach ($ext in $extensions) {
    $pattern = "(?m)^;\s*extension=$ext\s*$"
    if ($iniContent -match $pattern) {
        $iniContent = $iniContent -replace $pattern, "extension=$ext"
        $changed = $true
        Write-Ok "Extension activee : $ext"
    } elseif ($iniContent -notmatch "(?m)^extension=$ext\s*$") {
        $iniContent += "`nextension=$ext`n"
        $changed = $true
        Write-Ok "Extension ajoutee : $ext"
    } else {
        Write-Ok "Extension deja active : $ext"
    }
}

if ($changed) {
    Set-Content -Path $iniLoaded -Value $iniContent -Encoding UTF8
    Write-Ok "php.ini mis a jour"
}

# Verification pdo_mysql
$pdoCheck = & $phpExe -r "echo extension_loaded('pdo_mysql') ? 'OK' : 'KO';" 2>&1
if ($pdoCheck -eq "OK") {
    Write-Ok "pdo_mysql charge avec succes"
} else {
    Write-Warn "pdo_mysql non charge — verifiez que ext/php_pdo_mysql.dll existe dans $extDir"
}

# ---------------------------------------------------------------------------
# 4. .env
# ---------------------------------------------------------------------------
Write-Step "4/6 — Fichier .env"

if (-not (Test-Path ".env")) {
    Copy-Item ".env.example" ".env"
    Write-Ok ".env cree depuis .env.example"
} else {
    Write-Ok ".env existe deja"
}

# Ajuster URLs locales si necessaire
$envContent = Get-Content ".env" -Raw
$envContent = $envContent -replace "APP_URL=.*", "APP_URL=http://localhost:8000"
$envContent = $envContent -replace "API_URL=.*", "API_URL=http://localhost:8001"
Set-Content ".env" $envContent -NoNewline
Write-Ok "URLs locales configurees (8000 / 8001)"

# ---------------------------------------------------------------------------
# 5. MySQL — CREATE DATABASE admhost
# ---------------------------------------------------------------------------
Write-Step "5/6 — Base de donnees MySQL"

function Find-MySqlExe {
    param([string]$ExplicitPath)

    if ($ExplicitPath -and (Test-Path $ExplicitPath)) { return $ExplicitPath }

    $cmd = Get-Command mysql -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }

    $candidates = @(
        "C:\xampp\mysql\bin\mysql.exe",
        "C:\laragon\bin\mysql\mysql-8*\bin\mysql.exe",
        "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe",
        "C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe",
        "C:\Program Files\MariaDB 10*\bin\mysql.exe"
    )

    foreach ($pattern in $candidates) {
        $resolved = Resolve-Path $pattern -ErrorAction SilentlyContinue | Select-Object -First 1
        if ($resolved) { return $resolved.Path }
    }
    return $null
}

$mysqlExe = Find-MySqlExe -ExplicitPath $MySqlPath
$dbName   = "admhost"
$dbUser   = "admhost_user"
$dbPass   = "admhost_local_dev"

$sqlSetup = "CREATE DATABASE IF NOT EXISTS $dbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

if ($CreateDbUser) {
    $sqlSetup += @"

CREATE USER IF NOT EXISTS '$dbUser'@'localhost' IDENTIFIED BY '$dbPass';
GRANT ALL PRIVILEGES ON ${dbName}.* TO '$dbUser'@'localhost';
FLUSH PRIVILEGES;
"@
}

$dbCreated = $false

if ($mysqlExe) {
    Write-Ok "mysql trouve : $mysqlExe"

    # Tentative root sans mot de passe (XAMPP/Laragon local)
    $attempts = @(
        @{ Args = @("-u", "root", "-e", $sqlSetup); Label = "root (sans mot de passe)" },
        @{ Args = @("-u", "root", "-proot", "-e", $sqlSetup); Label = "root / root" },
        @{ Args = @("-u", "root", "-p", "-e", $sqlSetup); Label = "root (prompt)" }
    )

    foreach ($attempt in $attempts[0..1]) {
        try {
            $proc = Start-Process -FilePath $mysqlExe -ArgumentList $attempt.Args -NoNewWindow -Wait -PassThru -RedirectStandardError "NUL"
            if ($proc.ExitCode -eq 0) {
                Write-Ok "Base '$dbName' creee ($($attempt.Label))"
                $dbCreated = $true
                break
            }
        } catch { }
    }

    if (-not $dbCreated) {
        Write-Warn "Connexion mysql auto echouee — tentative interactive..."
        Write-Host "Entrez le mot de passe MySQL root si demande." -ForegroundColor Yellow
        & $mysqlExe -u root -p -e $sqlSetup
        if ($LASTEXITCODE -eq 0) { $dbCreated = $true }
    }
} else {
    Write-Warn "mysql.exe introuvable — creation BDD via PHP..."
}

# Fallback : creation via PHP PDO
if (-not $dbCreated) {
    $phpDbScript = @'
<?php
$host = getenv('DB_HOST') ?: '127.0.0.1';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$name = getenv('DB_NAME') ?: 'admhost';
try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass ?: null);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS {$name} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "OK";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage());
    exit(1);
}
'@
    $tmpPhp = Join-Path $env:TEMP "admhost_create_db.php"
    Set-Content $tmpPhp $phpDbScript

    # Lire .env basique
    foreach (Get-Content ".env") {
        if ($_ -match '^DB_HOST=(.+)$') { $env:DB_HOST = $Matches[1] }
        if ($_ -match '^DB_USER=(.+)$') { $env:DB_USER = $Matches[1] }
        if ($_ -match '^DB_PASS=(.+)$') { $env:DB_PASS = $Matches[1] }
        if ($_ -match '^DB_NAME=(.+)$') { $env:DB_NAME = $Matches[1] }
    }

    $result = & $phpExe $tmpPhp 2>&1
    Remove-Item $tmpPhp -ErrorAction SilentlyContinue

    if ($result -eq "OK") {
        Write-Ok "Base '$dbName' creee via PHP PDO"
        $dbCreated = $true
    } else {
        Write-Warn "Creation BDD echouee : $result"
        Write-Host "  Executez manuellement : CREATE DATABASE admhost;" -ForegroundColor Yellow
    }
}

# Migration + seed
Write-Step "Migration et seed"
& $phpExe scripts/migrate.php
if ($LASTEXITCODE -ne 0) {
    Write-Warn "Migration echouee — verifiez DB_USER/DB_PASS dans .env"
} else {
    & $phpExe scripts/seed.php
    Write-Ok "Donnees de test inserees"
}

# ---------------------------------------------------------------------------
# 6. Lancement serveurs locaux
# ---------------------------------------------------------------------------
Write-Step "6/6 — Demarrage serveurs"

if ($SkipStart) {
    Write-Ok "Demarrage ignore (-SkipStart). Lancez : scripts\dev\start-local.bat"
} else {
    $startBat = Join-Path $PSScriptRoot "start-local.bat"
    if (Test-Path $startBat) {
        Write-Ok "Lancement start-local.bat..."
        Start-Process -FilePath "cmd.exe" -ArgumentList "/c", "`"$startBat`"" -WorkingDirectory $ProjectRoot
    } else {
        Write-Warn "start-local.bat introuvable"
    }
}

Write-Host ""
Write-Host "============================================" -ForegroundColor Green
Write-Host "  Setup termine !" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""
Write-Host "  Frontend : http://localhost:8000" -ForegroundColor White
Write-Host "  Backend  : http://localhost:8001/api/health" -ForegroundColor White
Write-Host "  Admin    : http://localhost:8002/admin/login" -ForegroundColor White
Write-Host ""
Write-Host "  Admin  : admin@example.com / admin123" -ForegroundColor Yellow
Write-Host "  Client : jean@example.com / password123" -ForegroundColor Yellow
Write-Host ""
