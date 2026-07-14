<?php
/**
 * Contrôleur admin API — gestion utilisateurs, services, abonnements.
 */

declare(strict_types=1);

namespace Backend\Controllers;

use Backend\Models\Service;
use Backend\Models\Subscription;
use Backend\Models\User;
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
            $result = $provisioner->provisionFull((int) $id, (int) $service['id'], $user['email']);

            if ($result['success']) {
                $serviceModel->activate((int) $service['id'], $result['credentials']);
                $service = $serviceModel->find((int) $service['id']);
            }
        }

        $this->json(['message' => 'Service attribué', 'data' => $service], 201);
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
}
