<?php
/**
 * Middleware admin panel — session + IP whitelist.
 */

declare(strict_types=1);

namespace Admin\Middleware;

use Backend\Services\SecurityLogger;
use Shared\Core\IpWhitelist;

class AdminAuth
{
    public static function check(): void
    {
        if (empty($_SESSION['admin'])) {
            redirect('/admin/login');
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!IpWhitelist::isAllowed($ip)) {
            SecurityLogger::log('admin_panel_ip_blocked', ['ip' => $ip], $ip);
            http_response_code(403);
            exit('Accès refusé depuis cette adresse IP.');
        }
    }

    public static function user(): ?array
    {
        return $_SESSION['admin'] ?? null;
    }
}
