<?php
/**
 * Protection CSRF — tokens synchronisés par session.
 */

declare(strict_types=1);

namespace Shared\Core;

class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function validate(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $expected = $_SESSION[self::SESSION_KEY] ?? '';

        if ($expected === '' || $token === null || $token === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    /**
     * Valide le token ou termine avec 403.
     */
    public static function assertValid(?string $token): void
    {
        if (!self::validate($token)) {
            http_response_code(403);
            exit('Jeton CSRF invalide ou expiré.');
        }
    }

    public static function rotate(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
    }
}
