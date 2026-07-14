<?php
/**
 * Gestion des abonnements (admin).
 */

declare(strict_types=1);

namespace Admin\Controllers;

use Admin\Middleware\AdminAuth;
use Admin\Services\ApiClient;
use Shared\Core\Controller;
use Shared\Core\Request;

class SubscriptionController extends Controller
{
    private ApiClient $api;

    public function __construct()
    {
        $this->api = new ApiClient();
    }

    public function index(Request $request): never
    {
        AdminAuth::check();
        $response = $this->api->get('/api/admin/subscriptions');

        $this->view('subscriptions.index', [
            'title'         => 'Abonnements',
            'admin'         => AdminAuth::user(),
            'subscriptions' => $response['data'] ?? [],
        ]);
    }
}
