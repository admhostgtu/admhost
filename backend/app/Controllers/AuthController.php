<?php
/**
 * Contrôleur d'authentification API.
 */

declare(strict_types=1);

namespace Backend\Controllers;

use Backend\Services\AuthService;
use Shared\Core\ApiException;
use Shared\Core\Auth;
use Shared\Core\Controller;
use Shared\Core\Request;

class AuthController extends Controller
{
    private AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    public function login(Request $request): never
    {
        $errors = $this->validate($request, ['email', 'password']);
        if ($errors) {
            $this->json(['errors' => $errors], 422);
        }

        try {
            $result = $this->auth->login(
                $request->input('email'),
                $request->input('password'),
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            );

            $this->json([
                'message'    => 'Connexion réussie',
                'token'      => $result['token'],
                'expires_in' => $result['expires_in'],
                'user'       => $result['user'],
            ]);
        } catch (ApiException $e) {
            $e->render();
        }
    }

    public function register(Request $request): never
    {
        $errors = $this->validate($request, ['name', 'email', 'password']);
        if ($errors) {
            $this->json(['errors' => $errors], 422);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->auth->assertRegisterAllowed($ip);

        try {
            $user = $this->auth->register(
                $request->input('name'),
                $request->input('email'),
                $request->input('password')
            );

            $this->json(['message' => 'Inscription réussie', 'user' => $user], 201);
        } catch (ApiException $e) {
            $e->render();
        }
    }

    public function logout(Request $request): never
    {
        $token = $this->auth->extractToken(
            $request->header('Authorization'),
            $_COOKIE['auth_token'] ?? null
        );

        if ($token) {
            $this->auth->logout($token);
        }

        Auth::clear();
        $this->json(['message' => 'Déconnexion réussie']);
    }

    /**
     * POST /api/auth/refresh — rotation du token (ancien invalidé).
     */
    public function refresh(Request $request): never
    {
        $token = $this->auth->extractToken(
            $request->header('Authorization'),
            $_COOKIE['auth_token'] ?? null
        );

        if (!$token) {
            $this->json(['error' => 'Token requis.'], 401);
        }

        try {
            $result = $this->auth->refreshToken(
                $token,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            );

            $this->json([
                'message'    => 'Token renouvelé',
                'token'      => $result['token'],
                'expires_in' => $result['expires_in'],
                'user'       => $result['user'],
            ]);
        } catch (ApiException $e) {
            $e->render();
        }
    }

    public function me(Request $request): never
    {
        $user = Auth::user();
        if (!$user) {
            $this->json(['error' => 'Non authentifié'], 401);
        }

        $this->json(['data' => $user]);
    }
}
