<?php
/**
 * Paramètres compte client.
 */

declare(strict_types=1);

namespace Frontend\Controllers;

use Frontend\Services\ApiClient;
use Shared\Core\Controller;
use Shared\Core\Request;

class AccountController extends Controller
{
    private ApiClient $api;

    public function __construct()
    {
        $this->api = new ApiClient();
    }

    public function settings(Request $request): never
    {
        $this->requireAuth();
        $profile = $this->api->get('/api/me');

        $this->view('account.settings', [
            'title'   => 'Paramètres',
            'user'    => $profile['data'] ?? $_SESSION['user'],
            'success' => $request->query('saved'),
        ], 'console');
    }

    public function updateProfile(Request $request): never
    {
        $this->requireAuth();
        $this->validateCsrf($request);

        $response = $this->api->put('/api/account/profile', [
            'name' => $request->input('name'),
        ]);

        if (isset($response['data'])) {
            $_SESSION['user'] = $response['data'];
            redirect('/settings?saved=profile');
        }

        $this->view('account.settings', [
            'title' => 'Paramètres',
            'user'  => $_SESSION['user'],
            'error' => $response['error'] ?? 'Erreur',
        ], 'console');
    }

    public function updatePassword(Request $request): never
    {
        $this->requireAuth();
        $this->validateCsrf($request);

        $response = $this->api->put('/api/account/password', [
            'current_password' => $request->input('current_password'),
            'new_password'     => $request->input('new_password'),
        ]);

        if (isset($response['message'])) {
            redirect('/settings?saved=password');
        }

        $this->view('account.settings', [
            'title' => 'Paramètres',
            'user'  => $_SESSION['user'],
            'error' => $response['error'] ?? 'Erreur',
        ], 'console');
    }

    private function requireAuth(): void
    {
        if (empty($_SESSION['user'])) {
            redirect('/login');
        }
    }
}
