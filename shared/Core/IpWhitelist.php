<?php
/**
 * Vérification IP whitelist pour l'accès admin.
 *
 * En production : ADMIN_ALLOWED_IPS obligatoire (sinon accès admin refusé).
 */

declare(strict_types=1);

namespace Shared\Core;

class IpWhitelist
{
    public static function assertAllowed(?string $ip = null): void
    {
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        if (!self::isAllowed($ip)) {
            throw new ApiException('Accès refusé depuis cette adresse IP.', 403);
        }
    }

    public static function isAllowed(?string $ip = null): bool
    {
        $allowed = trim(env('ADMIN_ALLOWED_IPS', ''));
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        // Production : whitelist admin obligatoire
        if (env('APP_ENV', 'production') === 'production' && $allowed === '') {
            return false;
        }

        if ($allowed === '') {
            return true;
        }

        $list = array_filter(array_map('trim', explode(',', $allowed)));

        foreach ($list as $entry) {
            if (self::matchIp($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    private static function matchIp(string $ip, string $entry): bool
    {
        if ($ip === $entry) {
            return true;
        }

        if (str_contains($entry, '/')) {
            [$subnet, $bits] = explode('/', $entry, 2);
            $bits = (int) $bits;
            $ipLong     = ip2long($ip);
            $subnetLong = ip2long($subnet);
            if ($ipLong === false || $subnetLong === false) {
                return false;
            }
            $mask = -1 << (32 - $bits);
            return ($ipLong & $mask) === ($subnetLong & $mask);
        }

        return false;
    }
}
