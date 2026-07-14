<?php
/**
 * Seed — données de test (admin, utilisateur, service, abonnement).
 * Usage CLI : php scripts/seed.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/autoload.php';

use Shared\Core\Database;

echo "=== Seed AdmHost SaaS ===\n\n";

try {
    $db = Database::getInstance();

    // Admin
    $adminEmail = env('ADMIN_EMAIL', 'admin@example.com');
    $stmt = $db->prepare("
        INSERT IGNORE INTO users (name, email, password, role, status)
        VALUES ('Administrateur', :email, :pwd, 'admin', 'active')
    ");
    $stmt->execute([
        'email' => $adminEmail,
        'pwd'   => password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]),
    ]);
    echo "[OK] Admin : $adminEmail / admin123\n";

    // Utilisateur test
    $stmt->execute([
        'email' => 'jean@example.com',
        'pwd'   => password_hash('password123', PASSWORD_BCRYPT, ['cost' => 12]),
    ]);
    // Fix name for jean
    $db->exec("UPDATE users SET name = 'Jean Dupont' WHERE email = 'jean@example.com'");
    echo "[OK] User : jean@example.com / password123\n";

    $userId = (int) $db->query("SELECT id FROM users WHERE email = 'jean@example.com'")->fetchColumn();
    $planId = (int) $db->query("SELECT id FROM service_plans WHERE slug = 'pro'")->fetchColumn();

    // Chiffrement des credentials si APP_ENCRYPTION_KEY configurée
    $sshPass  = 'SshPass123!';
    $smtpPass = 'SmtpPass456!';
    try {
        $crypto   = new \Backend\Services\CryptoService();
        $sshPass  = $crypto->encrypt($sshPass);
        $smtpPass = $crypto->encrypt($smtpPass);
        echo "[OK] Credentials chiffrés pour le seed.\n";
    } catch (\Throwable) {
        echo "[--] APP_ENCRYPTION_KEY absente — credentials en clair (dev only).\n";
    }

    // Service actif avec credentials
    $db->prepare("
        INSERT IGNORE INTO services
        (user_id, plan_id, name, type, status, ssh_host, ssh_port, ssh_username, ssh_password,
         smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, linux_username, provisioned_at)
        SELECT :uid, :pid, 'Hébergement Pro', 'hosting', 'active', 'srv.admhost.local', 22, 'jean_a1b2', :ssh,
               'mail.admhost.local', 587, 'jean@example.com', :smtp, 'tls', 'jean_a1b2', NOW()
        FROM DUAL
        WHERE NOT EXISTS (SELECT 1 FROM services WHERE user_id = :uid2 AND name = 'Hébergement Pro')
    ")->execute(['uid' => $userId, 'uid2' => $userId, 'pid' => $planId, 'ssh' => $sshPass, 'smtp' => $smtpPass]);
    echo "[OK] Service actif pour Jean Dupont.\n";

    // Abonnement actif
    $serviceId = (int) $db->query("SELECT id FROM services WHERE user_id = $userId LIMIT 1")->fetchColumn();
    $db->prepare("
        INSERT IGNORE INTO subscriptions
        (user_id, service_id, plan_id, plan_slug, status, amount, currency, current_period_start, current_period_end)
        VALUES
        (:uid, :sid, :pid, 'pro', 'active', 29.00, 'EUR', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY))
    ")->execute(['uid' => $userId, 'sid' => $serviceId, 'pid' => $planId]);
    echo "[OK] Abonnement Pro actif.\n";

    echo "\n=== Seed terminé ===\n";

} catch (Throwable $e) {
    echo "[ERREUR] " . $e->getMessage() . "\n";
    exit(1);
}
