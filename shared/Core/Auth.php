<?php
/**
 * Gestionnaire d'authentification global — stocke l'utilisateur courant de la requête.
 */

declare(strict_types=1);

namespace Shared\Core;

class Auth
{
    private static ?array $user = null;

    public static function setUser(array $user): void
    {
        self::$user = $user;
    }

    public static function user(): ?array
    {
        return self::$user;
    }

    public static function id(): ?int
    {
        return self::$user ? (int) self::$user['id'] : null;
    }

    public static function check(): bool
    {
        return self::$user !== null;
    }

    public static function isAdmin(): bool
    {
        return self::$user !== null && (self::$user['role'] ?? '') === 'admin';
    }

    public static function clear(): void
    {
        self::$user = null;
    }
}
