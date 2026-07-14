<?php
/**
 * Journalisation des événements de sécurité.
 *
 * Usage :
 *   SecurityLogger::log('login_failed', ['email' => $email], $ip);
 *   SecurityLogger::log('webhook_received', ['event_id' => $id]);
 */

declare(strict_types=1);

namespace Backend\Services;

use Shared\Core\Database;

class SecurityLogger
{
    public static function log(string $action, array $details = [], ?string $ip = null, ?int $userId = null, ?string $email = null): void
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                INSERT INTO security_logs (user_id, email, action, details, ip_address)
                VALUES (:uid, :email, :action, :details, :ip)
            ");
            $stmt->execute([
                'uid'     => $userId,
                'email'   => $email,
                'action'  => $action,
                'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
                'ip'      => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? null),
            ]);
        } catch (\Throwable) {
            // Fallback fichier si BDD indisponible
            $line = sprintf(
                "[%s] %s ip=%s user=%s email=%s %s\n",
                date('Y-m-d H:i:s'),
                $action,
                $ip ?? '-',
                $userId ?? '-',
                $email ?? '-',
                json_encode($details)
            );
            $logFile = dirname(__DIR__, 3) . '/storage/logs/security.log';
            file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        }
    }
}
