<?php
/**
 * Middleware admin — role admin + IP whitelist optionnelle.
 */

declare(strict_types=1);

namespace Backend\Middleware;

use Backend\Services\SecurityLogger;
use Shared\Core\Auth;
use Shared\Core\IpWhitelist;
use Shared\Core\MiddlewareInterface;
use Shared\Core\Request;
use Shared\Core\Response;

class AdminMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        if (!Auth::check()) {
            Response::json(['error' => 'Authentification requise.'], 401)->send();
            return false;
        }

        if (!Auth::isAdmin()) {
            SecurityLogger::log('admin_access_denied', ['reason' => 'not_admin'], null, Auth::id());
            Response::json(['error' => 'Accès administrateur requis.'], 403)->send();
            return false;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!IpWhitelist::isAllowed($ip)) {
            SecurityLogger::log('admin_ip_blocked', ['ip' => $ip], $ip, Auth::id());
            Response::json(['error' => 'Accès refusé depuis cette adresse IP.'], 403)->send();
            return false;
        }

        return true;
    }
}
