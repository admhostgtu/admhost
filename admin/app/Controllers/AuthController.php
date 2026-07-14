<?php
/**
 * Authentification admin via l'API backend.
 */

declare(strict_types=1);

namespace Admin\Controllers;

use Admin\Services\ApiClient;
use Shared\Core\Controller;
use Shared\Core\Request;

class AuthController extends Controller
{
    private ApiClient $api;

    public function __construct()
    {
        $this->api = new ApiClient();
    }

    public function showLogin(Request $request): never
    {
        $this->view('auth.login', ['title' => 'Admin — Connexion']);
    }

    public function login(Request $request): never
    {
        $response = $this->api->post('/api/login', [
            'email'    => $request->input('email'),
            'password' => $request->input('password'),
        ]);

        if (isset($response['token']) && ($response['user']['role'] ?? '') === 'admin') {
            $_SESSION['admin_token'] = $response['token'];
            $_SESSION['admin']       = $response['user'];
            redirect('/admin/dashboard');
        }

        $this->view('auth.login', [
            'title' => 'Admin — Connexion',
            'error' => $response['error'] ?? 'Identifiants invalides ou accès non autorisé',
        ]);
    }

    public function logout(Request $request): never
    {
        if (!empty($_SESSION['admin_token'])) {
            $this->api->post('/api/logout', []);
        }
        unset($_SESSION['admin'], $_SESSION['admin_token']);
        redirect('/admin/login');
    }
}
