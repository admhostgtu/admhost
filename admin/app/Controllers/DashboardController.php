<?php
/**
 * Dashboard admin — statistiques globales.
 */

declare(strict_types=1);

namespace Admin\Controllers;

use Admin\Middleware\AdminAuth;
use Admin\Services\ApiClient;
use Shared\Core\Controller;
use Shared\Core\Request;

class DashboardController extends Controller
{
    private ApiClient $api;

    public function __construct()
    {
        $this->api = new ApiClient();
    }

    public function index(Request $request): never
    {
        AdminAuth::check();

        $users         = $this->api->get('/api/admin/users');
        $subscriptions = $this->api->get('/api/admin/subscriptions');
        $services      = $this->api->get('/api/admin/services');
        $health        = $this->api->get('/api/health');

        $activeSubs = array_filter($subscriptions['data'] ?? [], fn($s) => $s['status'] === 'active');

        $this->view('dashboard.index', [
            'title'          => 'Tableau de bord',
            'admin'          => AdminAuth::user(),
            'userCount'      => count($users['data'] ?? []),
            'subCount'       => count($subscriptions['data'] ?? []),
            'activeSubCount' => count($activeSubs),
            'serviceCount'   => count($services['data'] ?? []),
            'health'         => $health,
        ]);
    }
}
