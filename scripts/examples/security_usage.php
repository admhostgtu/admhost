<?php
/**
 * Exemples d'utilisation des modules de sécurité.
 *
 * Exécution CLI : php scripts/examples/security_usage.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/autoload.php';

echo "=== Exemples modules sécurité AdmHost ===\n\n";

// -------------------------------------------------------------------------
// 1. CryptoService — chiffrement credentials
// -------------------------------------------------------------------------
echo "--- CryptoService ---\n";
try {
    $crypto = new \Backend\Services\CryptoService();
    $encrypted = $crypto->encrypt('SshSecretPass123!');
    $decrypted = $crypto->decrypt($encrypted);
    echo "Chiffrement OK : " . ($decrypted === 'SshSecretPass123!' ? 'oui' : 'non') . "\n";
    echo "Format stocké  : " . substr($encrypted, 0, 40) . "...\n";
} catch (\Throwable $e) {
    echo "CryptoService : " . $e->getMessage() . "\n";
    echo "(Définissez APP_ENCRYPTION_KEY dans .env)\n";
}

// -------------------------------------------------------------------------
// 2. AuthService — tokens
// -------------------------------------------------------------------------
echo "\n--- AuthService tokens ---\n";
echo "Format token : bin2hex(random_bytes(32)) = 64 caractères hex\n";
echo "Stockage BDD : hash('sha256', \$token) uniquement\n";
echo "Expiration   : SESSION_LIFETIME (défaut 86400s = 24h)\n";
echo "Refresh      : POST /api/auth/refresh avec Bearer token\n";

// -------------------------------------------------------------------------
// 3. RateLimitService
// -------------------------------------------------------------------------
echo "\n--- RateLimitService ---\n";
echo "Config .env  : LOGIN_MAX_ATTEMPTS=5, LOGIN_WINDOW_SECONDS=900\n";
echo "Verrouillage : LOGIN_LOCKOUT_SECONDS=1800 après 5 échecs\n";

// -------------------------------------------------------------------------
// 4. IpWhitelist admin
// -------------------------------------------------------------------------
echo "\n--- IpWhitelist ---\n";
echo "Config .env  : ADMIN_ALLOWED_IPS=203.0.113.1,192.168.1.0/24\n";
echo "IP locale    : " . (\Shared\Core\IpWhitelist::isAllowed('127.0.0.1') ? 'autorisée' : 'bloquée') . "\n";

// -------------------------------------------------------------------------
// 5. SecurityLogger
// -------------------------------------------------------------------------
echo "\n--- SecurityLogger ---\n";
\Backend\Services\SecurityLogger::log('example_event', ['demo' => true], '127.0.0.1');
echo "Log écrit dans security_logs (ou storage/logs/security.log)\n";

echo "\n=== Fin ===\n";
