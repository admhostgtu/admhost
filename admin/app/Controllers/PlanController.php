<?php
/**
 * Gestion des plans tarifaires (admin).
 */

declare(strict_types=1);

namespace Admin\Controllers;

use Admin\Middleware\AdminAuth;
use Admin\Services\ApiClient;
use Shared\Core\Controller;
use Shared\Core\Request;

class PlanController extends Controller
{
    private ApiClient $api;

    public function __construct()
    {
        $this->api = new ApiClient();
    }

    public function index(Request $request): never
    {
        AdminAuth::check();
        $response = $this->api->get('/api/admin/plans');

        $this->view('plans.index', [
            'title' => 'Plans & tarifs',
            'admin' => AdminAuth::user(),
            'plans' => $response['data'] ?? [],
        ]);
    }

    public function create(Request $request): never
    {
        AdminAuth::check();
        $error = null;

        if ($request->method() === 'POST') {
            $this->validateCsrf($request);
            $response = $this->api->post('/api/admin/plans', [
                'slug'                   => $request->input('slug'),
                'name'                   => $request->input('name'),
                'description'            => $request->input('description'),
                'type'                   => $request->input('type', 'hosting'),
                'price_monthly'          => $request->input('price_monthly'),
                'price_annual'           => $request->input('price_annual'),
                'stripe_price_id'        => $request->input('stripe_price_id'),
                'stripe_price_id_annual' => $request->input('stripe_price_id_annual'),
                'features'               => $request->input('features'),
            ]);

            if (isset($response['data'])) {
                redirect(admin_path('plans'));
            }
            $error = $response['error'] ?? 'Erreur';
        }

        $this->view('plans.create', [
            'title' => 'Nouveau plan',
            'admin' => AdminAuth::user(),
            'error' => $error,
        ]);
    }

    public function edit(Request $request, string $id): never
    {
        AdminAuth::check();
        $plans = $this->api->get('/api/admin/plans');
        $plan  = null;
        foreach ($plans['data'] ?? [] as $p) {
            if ((string) $p['id'] === $id) {
                $plan = $p;
                break;
            }
        }

        if ($request->method() === 'POST') {
            $this->validateCsrf($request);
            $response = $this->api->put("/api/admin/plans/{$id}", [
                'name'                   => $request->input('name'),
                'description'            => $request->input('description'),
                'type'                   => $request->input('type'),
                'price_monthly'          => $request->input('price_monthly'),
                'price_annual'           => $request->input('price_annual'),
                'stripe_price_id'        => $request->input('stripe_price_id'),
                'stripe_price_id_annual' => $request->input('stripe_price_id_annual'),
                'features'               => $request->input('features'),
                'is_active'              => $request->input('is_active', 1),
            ]);

            if (isset($response['message'])) {
                redirect(admin_path('plans'));
            }
        }

        $this->view('plans.edit', [
            'title' => 'Modifier le plan',
            'admin' => AdminAuth::user(),
            'plan'  => $plan,
        ]);
    }
}
