<?php
/**
 * Cron — nettoyage sessions expirées, tentatives login, logs anciens.
 * Planifier : 0 3 * * * php /chemin/scripts/cron/cleanup.php
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/autoload.php';

use Backend\Models\SessionModel;
use Shared\Core\Database;

$logFile = dirname(__DIR__, 2) . '/storage/logs/cron.log';

function cronLog(string $message, string $logFile): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND);
    echo $line;
}

cronLog('Démarrage du nettoyage...', $logFile);

try {
    $db = Database::getInstance();

    // Sessions expirées
    $sessions = new SessionModel();
    $purged = $sessions->purgeExpired();
    cronLog("Sessions expirées supprimées : $purged", $logFile);

    // Tentatives login > 30 jours
    $purged = $db->exec("DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    cronLog("Login attempts supprimées : $purged", $logFile);

    // Logs sécurité > 90 jours
    $purged = $db->exec("DELETE FROM security_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    cronLog("Security logs supprimés : $purged", $logFile);

    // Activity logs > 90 jours
    $purged = $db->exec("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    cronLog("Activity logs supprimés : $purged", $logFile);

    // Abonnements expirés
    $purged = $db->exec("
        UPDATE subscriptions SET status = 'expired'
        WHERE status = 'active' AND current_period_end IS NOT NULL AND current_period_end < NOW()
    ");
    cronLog("Abonnements expirés : $purged", $logFile);

    // Cache storage > 24h
    $cacheDir = dirname(__DIR__, 2) . '/storage/cache';
    $count = 0;
    if (is_dir($cacheDir)) {
        foreach (glob("$cacheDir/*") as $file) {
            if (is_file($file) && filemtime($file) < time() - 86400) {
                unlink($file);
                $count++;
            }
        }
    }
    cronLog("Fichiers cache purgés : $count", $logFile);

    cronLog('Nettoyage terminé.', $logFile);

} catch (Throwable $e) {
    cronLog('ERREUR : ' . $e->getMessage(), $logFile);
    exit(1);
}
