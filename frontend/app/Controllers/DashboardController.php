<?php
/**
 * Dashboard client — services actifs, SSH/SMTP, abonnement.
 */

declare(strict_types=1);

namespace Frontend\Controllers;

use Frontend\Services\ApiClient;
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
        if (empty($_SESSION['user'])) {
            redirect('/login');
        }

        $services     = $this->api->get('/api/services');
        $subscription = $this->api->get('/api/subscription');
        $profile      = $this->api->get('/api/me');

        $this->view('dashboard.index', [
            'title'        => 'Mon espace',
            'user'         => $profile['data'] ?? $_SESSION['user'],
            'services'     => $services['data'] ?? [],
            'subscription' => $subscription['data'] ?? null,
        ], 'console');
    }
}
