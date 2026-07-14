<?php
/**
 * Vérification IP whitelist pour l'accès admin.
 *
 * Usage :
 *   IpWhitelist::assertAllowed($ip);  // lance ApiException si refusé
 *   IpWhitelist::isAllowed($ip);      // retourne bool
 *
 * Config .env :
 *   ADMIN_ALLOWED_IPS=203.0.113.1,203.0.113.2
 *   (vide = whitelist désactivée)
 */

declare(strict_types=1);

namespace Shared\Core;

class IpWhitelist
{
    /**
     * Vérifie que l'IP est autorisée, sinon lance une exception HTTP 403.
     */
    public static function assertAllowed(?string $ip = null): void
    {
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        if (!self::isAllowed($ip)) {
            throw new ApiException('Accès refusé depuis cette adresse IP.', 403);
        }
    }

    /**
     * Retourne true si l'IP est autorisée (ou si whitelist désactivée).
     */
    public static function isAllowed(?string $ip = null): bool
    {
        $allowed = trim(env('ADMIN_ALLOWED_IPS', ''));
        if ($allowed === '') {
            return true;
        }

        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $list = array_filter(array_map('trim', explode(',', $allowed)));

        foreach ($list as $entry) {
            if (self::matchIp($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match IP exacte ou CIDR simplifié (ex: 192.168.1.0/24).
     */
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
