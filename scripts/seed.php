<?php
/**
 * Seed — données de test (DEV/STAGING uniquement).
 * Usage : SEED_CONFIRM=1 php scripts/seed.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/autoload.php';

use Shared\Core\Database;

if (env('APP_ENV', 'local') === 'production') {
    fwrite(STDERR, "[REFUSÉ] seed.php ne doit pas être exécuté en production.\n");
    exit(1);
}

if (!getenv('SEED_CONFIRM')) {
    fwrite(STDERR, "[REFUSÉ] Confirmez avec : SEED_CONFIRM=1 php scripts/seed.php\n");
    exit(1);
}

echo "=== Seed AdmHost SaaS (dev/staging) ===\n\n";

try {
    $db = Database::getInstance();

    $adminPass = getenv('SEED_ADMIN_PASSWORD') ?: bin2hex(random_bytes(8));
    $userPass  = getenv('SEED_USER_PASSWORD') ?: bin2hex(random_bytes(8));

    $adminEmail = env('ADMIN_EMAIL', 'admin@example.com');
    $stmt = $db->prepare("
        INSERT IGNORE INTO users (name, email, password, role, status)
        VALUES ('Administrateur', :email, :pwd, 'admin', 'active')
    ");
    $stmt->execute([
        'email' => $adminEmail,
        'pwd'   => password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]),
    ]);
    echo "[OK] Admin : $adminEmail (mot de passe généré ci-dessous)\n";

    $stmt->execute([
        'email' => 'jean@example.com',
        'pwd'   => password_hash($userPass, PASSWORD_BCRYPT, ['cost' => 12]),
    ]);
    $db->exec("UPDATE users SET name = 'Jean Dupont' WHERE email = 'jean@example.com'");
    echo "[OK] User : jean@example.com (mot de passe généré ci-dessous)\n";

    $userId = (int) $db->query("SELECT id FROM users WHERE email = 'jean@example.com'")->fetchColumn();
    $planId = (int) $db->query("SELECT id FROM service_plans WHERE slug = 'pro'")->fetchColumn();

    $sshPass  = 'SshPass123!';
    $smtpPass = 'SmtpPass456!';
    try {
        $crypto   = new \Backend\Services\CryptoService();
        $sshPass  = $crypto->encrypt($sshPass);
        $smtpPass = $crypto->encrypt($smtpPass);
    } catch (\Throwable) {
        echo "[--] APP_ENCRYPTION_KEY absente — credentials en clair (dev only).\n";
    }

    $db->prepare("
        INSERT IGNORE INTO services
        (user_id, plan_id, name, type, status, ssh_host, ssh_port, ssh_username, ssh_password,
         smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, linux_username, provisioned_at)
        SELECT :uid, :pid, 'Hébergement Pro', 'hosting', 'active', 'srv.admhost.local', 22, 'jean_a1b2', :ssh,
               'mail.admhost.local', 587, 'jean@example.com', :smtp, 'tls', 'jean_a1b2', NOW()
        FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM services WHERE user_id = :uid2 AND name = 'Hébergement Pro')
    ")->execute(['uid' => $userId, 'uid2' => $userId, 'pid' => $planId, 'ssh' => $sshPass, 'smtp' => $smtpPass]);

    $serviceId = (int) $db->query("SELECT id FROM services WHERE user_id = $userId LIMIT 1")->fetchColumn();
    $db->prepare("
        INSERT IGNORE INTO subscriptions
        (user_id, service_id, plan_id, plan_slug, status, amount, currency, current_period_start, current_period_end)
        VALUES (:uid, :sid, :pid, 'pro', 'active', 29.00, 'EUR', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))
    ")->execute(['uid' => $userId, 'sid' => $serviceId, 'pid' => $planId]);

    echo "\n=== Mots de passe (notez-les, non affichés ailleurs) ===\n";
    echo "  Admin : $adminPass\n";
    echo "  User  : $userPass\n";
    echo "\n=== Seed terminé ===\n";

} catch (Throwable $e) {
    echo "[ERREUR] " . $e->getMessage() . "\n";
    exit(1);
}
