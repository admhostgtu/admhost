<?php
/**
 * Contrôleur admin API — gestion utilisateurs, services, abonnements.
 */

declare(strict_types=1);

namespace Backend\Controllers;

use Backend\Models\Service;
use Backend\Models\Subscription;
use Backend\Models\User;
use Backend\Models\Plan;
use Backend\Services\AuthService;
use Backend\Services\ProvisioningService;
use Shared\Core\ApiException;
use Shared\Core\Controller;
use Shared\Core\Request;

class AdminController extends Controller
{
    /**
     * GET /api/admin/users
     */
    public function users(Request $request): never
    {
        $userModel = new User();
        $this->json(['data' => $userModel->allPublic()]);
    }

    /**
     * POST /api/admin/users — créer un utilisateur.
     */
    public function createUser(Request $request): never
    {
        $errors = $this->validate($request, ['name', 'email', 'password']);
        if ($errors) {
            $this->json(['errors' => $errors], 422);
        }

        try {
            $auth = new AuthService();
            $user = $auth->register(
                $request->input('name'),
                $request->input('email'),
                $request->input('password'),
                $request->input('role', 'user')
            );
            $this->json(['message' => 'Utilisateur créé', 'data' => $user], 201);
        } catch (ApiException $e) {
            $e->render();
        }
    }

    /**
     * POST /api/admin/users/{id}/services — attribuer/provisionner un service.
     */
    public function assignService(Request $request, string $id): never
    {
        $userModel = new User();
        $user = $userModel->findPublic((int) $id);
        if (!$user) {
            $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        $serviceModel = new Service();
        $service = $serviceModel->create([
            'user_id'  => (int) $id,
            'plan_id'  => $request->input('plan_id'),
            'name'     => $request->input('name', 'Service ' . $user['name']),
            'type'     => $request->input('type', 'hosting'),
            'status'   => 'pending',
        ]);

        // Provisionnement automatique si demandé
        if ($request->input('provision') === '1') {
            $provisioner = new ProvisioningService();
            $type = $request->input('type', 'hosting');
            $result = $provisioner->provisionByType((int) $id, (int) $service['id'], $user['email'], $type);

            if ($result['success']) {
                $serviceModel->activate((int) $service['id'], $result['credentials']);
            }
        }

        $public = $serviceModel->find((int) $service['id']);
        if ($public) {
            foreach (['ssh_password', 'smtp_password'] as $secret) {
                if (!empty($public[$secret])) {
                    $public[$secret] = '********';
                }
            }
        }

        $this->json(['message' => 'Service attribué', 'data' => $public], 201);
    }

    /**
     * GET /api/admin/subscriptions
     */
    public function subscriptions(Request $request): never
    {
        $subModel = new Subscription();
        $this->json(['data' => $subModel->allWithUsers()]);
    }

    /**
     * GET /api/admin/services
     */
    public function services(Request $request): never
    {
        $serviceModel = new Service();
        $this->json(['data' => $serviceModel->allWithUsers()]);
    }

    /**
     * GET /api/admin/users/{id}
     */
    public function showUser(Request $request, string $id): never
    {
        $userModel = new User();
        $user = $userModel->findPublic((int) $id);
        if (!$user) {
            $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        $serviceModel = new Service();
        $subModel = new Subscription();

        $this->json([
            'data' => [
                'user'          => $user,
                'services'      => $serviceModel->findByUser((int) $id, false),
                'subscriptions' => $subModel->allByUser((int) $id),
            ],
        ]);
    }

    /**
     * PUT /api/admin/users/{id}
     */
    public function updateUser(Request $request, string $id): never
    {
        $userModel = new User();
        if (!$userModel->findPublic((int) $id)) {
            $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        $data = [];
        if ($request->input('name')) {
            $data['name'] = $request->input('name');
        }
        if ($request->input('status')) {
            $data['status'] = $request->input('status');
        }
        if ($request->input('role')) {
            $data['role'] = $request->input('role');
        }

        if ($data === []) {
            $this->json(['error' => 'Aucune donnée à mettre à jour'], 422);
        }

        $userModel->update((int) $id, $data);
        $this->json(['message' => 'Utilisateur mis à jour', 'data' => $userModel->findPublic((int) $id)]);
    }

    /**
     * PUT /api/admin/services/{id}
     */
    public function updateService(Request $request, string $id): never
    {
        $serviceModel = new Service();
        if (!$serviceModel->find((int) $id)) {
            $this->json(['error' => 'Service introuvable'], 404);
        }

        $fields = [
            'name', 'status', 'type', 'plan_id', 'ssh_host', 'ssh_port', 'ssh_username',
            'smtp_host', 'smtp_port', 'smtp_username', 'subdomain', 'web_url', 'docker_image',
        ];
        $data = [];
        foreach ($fields as $f) {
            if ($request->input($f) !== null && $request->input($f) !== '') {
                $data[$f] = $request->input($f);
            }
        }
        if ($request->input('ssh_password')) {
            $data['ssh_password'] = $request->input('ssh_password');
        }
        if ($request->input('smtp_password')) {
            $data['smtp_password'] = $request->input('smtp_password');
        }
        if (is_array($request->input('metadata'))) {
            $data['metadata'] = json_encode($request->input('metadata'));
        }

        $serviceModel->update((int) $id, $data);
        $this->json(['message' => 'Service mis à jour']);
    }

    /**
     * GET /api/admin/plans
     */
    public function plans(Request $request): never
    {
        $planModel = new Plan();
        $stmt = $planModel->all();
        $this->json(['data' => $stmt]);
    }

    /**
     * POST /api/admin/plans
     */
    public function createPlan(Request $request): never
    {
        $errors = $this->validate($request, ['slug', 'name', 'price_monthly']);
        if ($errors) {
            $this->json(['errors' => $errors], 422);
        }

        $planModel = new Plan();
        $features = $request->input('features');
        if (is_string($features)) {
            $features = array_filter(array_map('trim', explode("\n", $features)));
        }

        $plan = $planModel->create([
            'slug'                   => $request->input('slug'),
            'name'                   => $request->input('name'),
            'description'            => $request->input('description'),
            'type'                   => $request->input('type', 'hosting'),
            'price_monthly'          => $request->input('price_monthly'),
            'price_annual'           => $request->input('price_annual', 0),
            'stripe_price_id'        => $request->input('stripe_price_id'),
            'stripe_price_id_annual' => $request->input('stripe_price_id_annual'),
            'features'               => is_array($features) ? $features : [],
            'is_active'              => $request->input('is_active', 1),
        ]);

        $this->json(['message' => 'Plan créé', 'data' => $plan], 201);
    }

    /**
     * PUT /api/admin/plans/{id}
     */
    public function updatePlan(Request $request, string $id): never
    {
        $planModel = new Plan();
        if (!$planModel->find((int) $id)) {
            $this->json(['error' => 'Plan introuvable'], 404);
        }

        $data = [];
        foreach (['slug', 'name', 'description', 'type', 'price_monthly', 'price_annual',
            'stripe_price_id', 'stripe_price_id_annual', 'is_active'] as $f) {
            if ($request->input($f) !== null && $request->input($f) !== '') {
                $data[$f] = $request->input($f);
            }
        }

        $features = $request->input('features');
        if (is_string($features)) {
            $data['features'] = array_filter(array_map('trim', explode("\n", $features)));
        } elseif (is_array($features)) {
            $data['features'] = $features;
        }

        $planModel->update((int) $id, $data);
        $this->json(['message' => 'Plan mis à jour', 'data' => $planModel->find((int) $id)]);
    }
}
