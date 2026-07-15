<?php
/**
 * Service d'authentification — login, register, tokens, rate limiting.
 */

declare(strict_types=1);

namespace Backend\Services;

use Backend\Models\User;
use Backend\Models\SessionModel;
use Shared\Core\ApiException;
use Shared\Core\Auth;

class AuthService
{
    private User $users;
    private SessionModel $sessions;
    private RateLimitService $rateLimit;

    public function __construct()
    {
        $this->users     = new User();
        $this->sessions  = new SessionModel();
        $this->rateLimit = new RateLimitService();
    }

    public function register(string $name, string $email, string $password, string $role = 'user'): array
    {
        if (strlen($password) < 8) {
            throw new ApiException('Le mot de passe doit contenir au moins 8 caractères.', 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException('Email invalide.', 422);
        }

        if ($this->users->findByEmail($email)) {
            throw new ApiException('Impossible de créer le compte avec ces informations.', 409);
        }

        return $this->users->create([
            'name'     => $name,
            'email'    => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'role'     => $role,
        ]);
    }

    /**
     * Authentification avec rate limiting et verrouillage compte.
     */
    public function login(string $email, string $password, string $ip, string $userAgent): array
    {
        $this->rateLimit->assertCanAttempt($email, $ip);

        $user = $this->users->findByEmail($email);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->rateLimit->recordFailure($email, $ip);
            throw new ApiException('Identifiants invalides.', 401);
        }

        if ($user['status'] !== 'active') {
            throw new ApiException('Compte suspendu ou inactif.', 403);
        }

        // Vérifier verrouillage explicite
        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            throw new ApiException('Compte temporairement verrouillé.', 429);
        }

        $this->rateLimit->recordSuccess($email, $ip, (int) $user['id']);
        $this->users->recordLogin((int) $user['id']);

        // Token : random_bytes(32) → hex, stocké hashé SHA-256
        $token = $this->sessions->create((int) $user['id'], $ip, $userAgent);

        unset($user['password']);

        return [
            'user'       => $user,
            'token'      => $token,
            'expires_in' => (int) env('SESSION_LIFETIME', 86400),
        ];
    }

    /**
     * Invalide le token en base (logout).
     */
    public function logout(string $token): void
    {
        $this->sessions->destroy($token);
        SecurityLogger::log('logout', [], null, Auth::id());
    }

    /**
     * Renouvelle un token valide (rotation).
     */
    public function refreshToken(string $token, string $ip, string $userAgent): array
    {
        $result = $this->sessions->refresh($token, $ip, $userAgent);

        if (!$result) {
            throw new ApiException('Token invalide ou expiré.', 401);
        }

        $user = $this->users->findPublic($result['user_id']);
        $this->assertActiveUser($user);

        return [
            'token'      => $result['token'],
            'user'       => $user,
            'expires_in' => (int) env('SESSION_LIFETIME', 86400),
        ];
    }

    public function resolveToken(string $token): ?array
    {
        $userId = $this->sessions->validate($token);
        if (!$userId) {
            return null;
        }

        $user = $this->users->findPublic($userId);
        if (!$user || ($user['status'] ?? '') !== 'active') {
            $this->sessions->destroy($token);
            return null;
        }

        if (!empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time()) {
            $this->sessions->destroy($token);
            return null;
        }

        return $user;
    }

    private function assertActiveUser(?array $user): void
    {
        if (!$user || ($user['status'] ?? '') !== 'active') {
            throw new ApiException('Compte suspendu ou inactif.', 403);
        }

        if (!empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time()) {
            throw new ApiException('Compte temporairement verrouillé.', 429);
        }
    }

    /**
     * Extrait le token Bearer (hex 64 chars) ou cookie.
     */
    public function extractToken(?string $authHeader, ?string $cookieToken): ?string
    {
        if ($authHeader && preg_match('/Bearer\s+([a-f0-9]{64})/i', $authHeader, $m)) {
            return strtolower($m[1]);
        }

        if ($cookieToken && preg_match('/^[a-f0-9]{64}$/i', $cookieToken)) {
            return strtolower($cookieToken);
        }

        return null;
    }

    /**
     * Limite les inscriptions par IP (anti-spam).
     */
    public function assertRegisterAllowed(string $ip): void
    {
        $this->rateLimit->assertIpActionLimit($ip, 'register', 10, 3600);
    }

    /**
     * Purge probabiliste des tokens expirés (1 % des requêtes).
     */
    public function maybePurgeExpiredSessions(): void
    {
        if (random_int(1, 100) === 1) {
            $this->sessions->purgeExpired();
        }
    }
}
