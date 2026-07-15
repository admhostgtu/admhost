<?php
/**
 * Gestion des utilisateurs (admin).
 */

declare(strict_types=1);

namespace Admin\Controllers;

use Admin\Middleware\AdminAuth;
use Admin\Services\ApiClient;
use Shared\Core\Controller;
use Shared\Core\Request;

class UserController extends Controller
{
    private ApiClient $api;

    public function __construct()
    {
        $this->api = new ApiClient();
    }

    public function index(Request $request): never
    {
        AdminAuth::check();
        $response = $this->api->get('/api/admin/users');

        $this->view('users.index', [
            'title' => 'Utilisateurs',
            'admin' => AdminAuth::user(),
            'users' => $response['data'] ?? [],
        ]);
    }

    public function create(Request $request): never
    {
        AdminAuth::check();

        if ($request->method() === 'POST') {
            $this->validateCsrf($request);
            $response = $this->api->post('/api/admin/users', [
                'name'     => $request->input('name'),
                'email'    => $request->input('email'),
                'password' => $request->input('password'),
                'role'     => $request->input('role', 'user'),
            ]);

            if (isset($response['data'])) {
                redirect(admin_path('users'));
            }

            $this->view('users.create', [
                'title' => 'Créer un utilisateur',
                'admin' => AdminAuth::user(),
                'error' => $response['error'] ?? 'Erreur lors de la création',
            ]);
        }

        $this->view('users.create', [
            'title' => 'Créer un utilisateur',
            'admin' => AdminAuth::user(),
        ]);
    }

    public function assignService(Request $request, string $id): never
    {
        AdminAuth::check();

        if ($request->method() === 'POST') {
            $this->validateCsrf($request);
            $response = $this->api->post("/api/admin/users/{$id}/services", [
                'name'      => $request->input('name'),
                'type'      => $request->input('type', 'hosting'),
                'plan_id'   => $request->input('plan_id'),
                'provision' => $request->input('provision', '1'),
            ]);

            if (isset($response['data'])) {
                redirect(admin_path('users'));
            }
        }

        $users = $this->api->get('/api/admin/users');
        $user  = null;
        foreach ($users['data'] ?? [] as $u) {
            if ((string) $u['id'] === $id) {
                $user = $u;
                break;
            }
        }

        $this->view('users.assign', [
            'title'  => 'Attribuer un service',
            'admin'  => AdminAuth::user(),
            'user'   => $user,
            'userId' => $id,
        ]);
    }
}
