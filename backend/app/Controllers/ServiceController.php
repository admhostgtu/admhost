<?php
/**
 * Contrôleur REST pour les services utilisateur.
 * Endpoints : GET /api/services, GET /api/services/{id}
 */

declare(strict_types=1);

namespace Backend\Controllers;

use Backend\Models\Service;
use Backend\Models\Subscription;
use Shared\Core\Auth;
use Shared\Core\Controller;
use Shared\Core\Request;

class ServiceController extends Controller
{
    private Service $services;

    public function __construct()
    {
        $this->services = new Service();
    }

    /**
     * GET /api/services — liste les services de l'utilisateur connecté.
     */
    public function index(Request $request): never
    {
        $userId = Auth::id();

        // Admin peut voir tous les services via query ?all=1
        if (Auth::isAdmin() && $request->query('all') === '1') {
            $this->json(['data' => $this->services->allWithUsers()]);
        }

        $this->json(['data' => $this->services->findByUser($userId)]);
    }

    /**
     * GET /api/services/{id}
     */
    public function show(Request $request, string $id): never
    {
        $service = $this->services->findDecrypted((int) $id);

        if (!$service) {
            $this->json(['error' => 'Service introuvable'], 404);
        }

        if (!Auth::isAdmin() && (int) $service['user_id'] !== Auth::id()) {
            $this->json(['error' => 'Accès refusé'], 403);
        }

        $this->json(['data' => $service]);
    }

    /**
     * GET /api/subscription — abonnement actif de l'utilisateur.
     */
    public function subscription(Request $request): never
    {
        $subModel = new Subscription();
        $sub = $subModel->findByUser(Auth::id());

        $this->json(['data' => $sub]);
    }
}
