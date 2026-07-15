<?php
/**
 * Liste des services provisionnés (admin).
 */

declare(strict_types=1);

namespace Admin\Controllers;

use Admin\Middleware\AdminAuth;
use Admin\Services\ApiClient;
use Shared\Core\Controller;
use Shared\Core\Request;

class ServiceAdminController extends Controller
{
    private ApiClient $api;

    public function __construct()
    {
        $this->api = new ApiClient();
    }

    public function index(Request $request): never
    {
        AdminAuth::check();
        $response = $this->api->get('/api/admin/services');

        $this->view('services.index', [
            'title'    => 'Services',
            'admin'    => AdminAuth::user(),
            'services' => $response['data'] ?? [],
        ]);
    }
}
