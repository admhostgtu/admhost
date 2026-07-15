<?php
/**
 * Authentification frontend via l'API backend + session PHP sécurisée.
 */

declare(strict_types=1);

namespace Frontend\Controllers;

use Frontend\Services\ApiClient;
use Shared\Core\Controller;
use Shared\Core\Csrf;
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
        $this->view('auth.login', ['title' => 'Connexion']);
    }

    public function login(Request $request): never
    {
        $this->validateCsrf($request);

        $response = $this->api->post('/api/login', [
            'email'    => $request->input('email'),
            'password' => $request->input('password'),
        ]);

        if (isset($response['token'])) {
            session_regenerate_id(true);
            $_SESSION['token'] = $response['token'];
            $_SESSION['user']  = $response['user'];
            redirect('/dashboard');
        }

        $this->view('auth.login', [
            'title' => 'Connexion',
            'error' => $response['error'] ?? 'Identifiants invalides',
        ]);
    }

    public function showRegister(Request $request): never
    {
        $this->view('auth.register', ['title' => 'Inscription']);
    }

    public function register(Request $request): never
    {
        $this->validateCsrf($request);

        $response = $this->api->post('/api/register', [
            'name'     => $request->input('name'),
            'email'    => $request->input('email'),
            'password' => $request->input('password'),
        ]);

        if (isset($response['user'])) {
            redirect('/login');
        }

        $this->view('auth.register', [
            'title' => 'Inscription',
            'error' => $response['error'] ?? 'Erreur lors de l\'inscription',
        ]);
    }

    public function logout(Request $request): never
    {
        if (!empty($_SESSION['token'])) {
            $this->api->post('/api/logout', []);
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        redirect('/');
    }
}
