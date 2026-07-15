<?php
/**
 * Rate limiting et protection brute-force sur le login.
 *
 * Usage dans AuthService::login() :
 *   $rateLimit = new RateLimitService();
 *   $rateLimit->assertCanAttempt($email, $ip);
 *   // ... login ...
 *   $rateLimit->recordSuccess($email, $ip);
 *   // ou en cas d'échec :
 *   $rateLimit->recordFailure($email, $ip);
 */

declare(strict_types=1);

namespace Backend\Services;

use Shared\Core\ApiException;
use Shared\Core\Database;

class RateLimitService
{
    private int $maxAttempts;
    private int $windowSeconds;
    private int $lockoutSeconds;

    public function __construct()
    {
        $this->maxAttempts    = (int) env('LOGIN_MAX_ATTEMPTS', 5);
        $this->windowSeconds  = (int) env('LOGIN_WINDOW_SECONDS', 900);   // 15 min
        $this->lockoutSeconds = (int) env('LOGIN_LOCKOUT_SECONDS', 1800); // 30 min
    }

    /**
     * Vérifie si une tentative de login est autorisée.
     *
     * @throws ApiException si limite atteinte ou compte verrouillé
     */
    public function assertCanAttempt(string $email, string $ip): void
    {
        $db = Database::getInstance();

        // Vérifier verrouillage compte
        $stmt = $db->prepare("SELECT locked_until FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $lockedUntil = $stmt->fetchColumn();

        if ($lockedUntil && strtotime($lockedUntil) > time()) {
            SecurityLogger::log('login_blocked_locked', ['email' => $email], $ip, null, $email);
            throw new ApiException('Compte temporairement verrouillé. Réessayez plus tard.', 429);
        }

        // Compter échecs récents (email + IP)
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE success = 0
              AND created_at > DATE_SUB(NOW(), INTERVAL :window SECOND)
              AND (email = :email OR ip_address = :ip)
        ");
        $stmt->execute([
            'window' => $this->windowSeconds,
            'email'  => $email,
            'ip'     => $ip,
        ]);

        if ((int) $stmt->fetchColumn() >= $this->maxAttempts) {
            SecurityLogger::log('login_rate_limited', ['email' => $email], $ip, null, $email);
            throw new ApiException('Trop de tentatives. Réessayez dans quelques minutes.', 429);
        }
    }

    /**
     * Enregistre une tentative échouée et verrouille le compte si seuil atteint.
     */
    public function recordFailure(string $email, string $ip): void
    {
        $this->insertAttempt($email, $ip, false);
        SecurityLogger::log('login_failed', ['email' => $email], $ip, null, $email);

        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE email = :email AND success = 0
              AND created_at > DATE_SUB(NOW(), INTERVAL :window SECOND)
        ");
        $stmt->execute(['email' => $email, 'window' => $this->windowSeconds]);
        $failures = (int) $stmt->fetchColumn();

        if ($failures >= $this->maxAttempts) {
            $db->prepare("
                UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL :lockout SECOND),
                                 failed_login_attempts = :count
                WHERE email = :email
            ")->execute([
                'lockout' => $this->lockoutSeconds,
                'count'   => $failures,
                'email'   => $email,
            ]);
            SecurityLogger::log('account_locked', ['email' => $email, 'failures' => $failures], $ip, null, $email);
        }
    }

    /**
     * Enregistre un login réussi et réinitialise le compteur.
     */
    public function recordSuccess(string $email, string $ip, int $userId): void
    {
        $this->insertAttempt($email, $ip, true);

        Database::getInstance()->prepare("
            UPDATE users SET locked_until = NULL, failed_login_attempts = 0 WHERE email = :email
        ")->execute(['email' => $email]);

        SecurityLogger::log('login_success', ['email' => $email], $ip, $userId, $email);
    }

    private function insertAttempt(string $email, string $ip, bool $success): void
    {
        $db = Database::getInstance();
        $db->prepare("
            INSERT INTO login_attempts (email, ip_address, success) VALUES (:email, :ip, :success)
        ")->execute([
            'email'   => $email,
            'ip'      => $ip,
            'success' => $success ? 1 : 0,
        ]);
    }

    /**
     * Rate limit générique par IP + action (ex: register).
     */
    public function assertIpActionLimit(string $ip, string $action, int $maxAttempts, int $windowSeconds): void
    {
        $db = Database::getInstance();
        $marker = '__' . $action . '__';

        $stmt = $db->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE email = :marker AND ip_address = :ip
              AND created_at > DATE_SUB(NOW(), INTERVAL :window SECOND)
        ");
        $stmt->execute([
            'marker' => $marker,
            'ip'     => $ip,
            'window' => $windowSeconds,
        ]);

        if ((int) $stmt->fetchColumn() >= $maxAttempts) {
            throw new ApiException('Trop de requêtes. Réessayez plus tard.', 429);
        }

        $db->prepare("
            INSERT INTO login_attempts (email, ip_address, success) VALUES (:email, :ip, 0)
        ")->execute(['email' => $marker, 'ip' => $ip]);
    }
}
