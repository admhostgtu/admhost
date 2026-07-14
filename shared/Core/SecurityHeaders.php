<?php
/**
 * Helper — envoi des headers de sécurité HTTP.
 *
 * Usage (dans index.php de chaque app) :
 *   SecurityHeaders::send();
 */

declare(strict_types=1);

namespace Shared\Core;

class SecurityHeaders
{
    /**
     * Envoie les headers de sécurité recommandés.
     *
     * @param bool $isApi true pour API (CSP plus stricte, pas de frames)
     */
    public static function send(bool $isApi = false): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        // HSTS uniquement en HTTPS
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        if ($isApi) {
            header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
        } else {
            header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
        }
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }
}
