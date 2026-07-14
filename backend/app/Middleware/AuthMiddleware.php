<?php
/**
 * Middleware d'authentification — vérifie token, expiration, purge sessions.
 */

declare(strict_types=1);

namespace Backend\Middleware;

use Backend\Services\AuthService;
use Shared\Core\Auth;
use Shared\Core\MiddlewareInterface;
use Shared\Core\Request;
use Shared\Core\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        $authService = new AuthService();

        // Purge opportuniste des tokens expirés
        $authService->maybePurgeExpiredSessions();

        $token = $authService->extractToken(
            $request->header('Authorization'),
            $_COOKIE['auth_token'] ?? null
        );

        if (!$token) {
            Response::json(['error' => 'Authentification requise.'], 401)->send();
            return false;
        }

        $user = $authService->resolveToken($token);
        if (!$user) {
            Response::json(['error' => 'Token invalide ou expiré.'], 401)->send();
            return false;
        }

        Auth::setUser($user);
        return true;
    }
}
