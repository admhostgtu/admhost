<?php
/**
 * Modèle Session — tokens hashés SHA-256, expiration 24h par défaut.
 *
 * Le token en clair (hex 64 chars) n'est JAMAIS stocké en base.
 * Seul hash('sha256', $token) est persisté.
 */

declare(strict_types=1);

namespace Backend\Models;

use Shared\Core\Model;

class SessionModel extends Model
{
    protected string $table = 'sessions';

    /** Durée par défaut : 24 heures */
    private const DEFAULT_LIFETIME = 86400;

    /**
     * Génère un token sécurisé, le stocke hashé et retourne le token en clair.
     * Format transmission client : hex (64 caractères) issu de random_bytes(32).
     */
    public function create(int $userId, string $ip, string $userAgent): string
    {
        $token     = bin2hex(random_bytes(32));
        $tokenHash = self::hashToken($token);
        $lifetime  = (int) env('SESSION_LIFETIME', self::DEFAULT_LIFETIME);

        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} (user_id, token_hash, ip_address, user_agent, expires_at)
            VALUES (:user_id, :token_hash, :ip, :ua, DATE_ADD(NOW(), INTERVAL :lifetime SECOND))
        ");
        $stmt->execute([
            'user_id'    => $userId,
            'token_hash' => $tokenHash,
            'ip'         => $ip,
            'ua'         => substr($userAgent, 0, 512),
            'lifetime'   => $lifetime,
        ]);

        return $token;
    }

    /**
     * Valide un token : vérifie le hash ET l'expiration.
     */
    public function validate(string $token): ?int
    {
        if (!$this->isValidTokenFormat($token)) {
            return null;
        }

        $tokenHash = self::hashToken($token);
        $stmt = $this->db->prepare("
            SELECT user_id, expires_at FROM {$this->table}
            WHERE token_hash = :hash
            LIMIT 1
        ");
        $stmt->execute(['hash' => $tokenHash]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        // Double vérification expiration côté PHP
        if (strtotime($row['expires_at']) <= time()) {
            $this->destroyByHash($tokenHash);
            return null;
        }

        return (int) $row['user_id'];
    }

    /**
     * Refresh token : invalide l'ancien, crée une nouvelle session.
     */
    public function refresh(string $token, string $ip, string $userAgent): ?array
    {
        $userId = $this->validate($token);
        if (!$userId) {
            return null;
        }

        $this->destroy($token);
        $newToken = $this->create($userId, $ip, $userAgent);

        return [
            'user_id' => $userId,
            'token'   => $newToken,
        ];
    }

    /**
     * Invalide un token (logout).
     */
    public function destroy(string $token): bool
    {
        if (!$this->isValidTokenFormat($token)) {
            return false;
        }
        return $this->destroyByHash(self::hashToken($token));
    }

    /**
     * Supprime les sessions expirées.
     */
    public function purgeExpired(): int
    {
        return $this->db->exec("DELETE FROM {$this->table} WHERE expires_at <= NOW()");
    }

    /**
     * Supprime toutes les sessions d'un utilisateur.
     */
    public function destroyAllForUser(int $userId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE user_id = :uid");
        return $stmt->execute(['uid' => $userId]);
    }

    /**
     * Hash SHA-256 constant pour stockage.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Valide le format du token (hex 64 chars = 32 bytes).
     */
    private function isValidTokenFormat(string $token): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/', $token);
    }

    private function destroyByHash(string $tokenHash): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE token_hash = :hash");
        return $stmt->execute(['hash' => $tokenHash]);
    }
}
