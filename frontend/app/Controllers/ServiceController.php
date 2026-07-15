<?php
/**
 * Détail et configuration d'un service client.
 */

declare(strict_types=1);

namespace Frontend\Controllers;

use Frontend\Services\ApiClient;
use Shared\Core\Controller;
use Shared\Core\Request;

class ServiceController extends Controller
{
    private ApiClient $api;

    public function __construct()
    {
        $this->api = new ApiClient();
    }

    public function show(Request $request, string $id): never
    {
        if (empty($_SESSION['user'])) {
            redirect('/login');
        }

        $response = $this->api->get("/api/services/{$id}");
        if (empty($response['data'])) {
            redirect('/dashboard');
        }

        $this->view('services.show', [
            'title'   => 'Mon service',
            'user'    => $_SESSION['user'],
            'service' => $response['data'],
            'success' => $request->query('saved'),
        ], 'console');
    }

    public function updateConfig(Request $request, string $id): never
    {
        if (empty($_SESSION['user'])) {
            redirect('/login');
        }
        $this->validateCsrf($request);

        $metadata = [
            'site_title'   => $request->input('site_title'),
            'php_version'  => $request->input('php_version'),
            'notes'        => $request->input('notes'),
        ];

        $response = $this->api->put("/api/services/{$id}/config", ['metadata' => $metadata]);

        if (isset($response['message'])) {
            redirect("/services/{$id}?saved=1");
        }

        redirect("/services/{$id}");
    }
}
