<?php
/**
 * Contrôleur REST pour la ressource Users (CRUD complet).
 */

declare(strict_types=1);

namespace Backend\Controllers;

use Shared\Core\Controller;
use Shared\Core\Request;
use Backend\Models\User;

class UserController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * GET /api/users — Liste tous les utilisateurs.
     */
    public function index(Request $request): never
    {
        $this->json(['data' => $this->userModel->all()]);
    }

    /**
     * GET /api/users/{id} — Affiche un utilisateur.
     */
    public function show(Request $request, string $id): never
    {
        $user = $this->userModel->find((int) $id);
        if (!$user) {
            $this->json(['error' => 'Utilisateur introuvable'], 404);
        }
        unset($user['password']);
        $this->json(['data' => $user]);
    }

    /**
     * POST /api/users — Crée un utilisateur.
     */
    public function store(Request $request): never
    {
        $errors = $this->validate($request, ['name', 'email', 'password']);
        if ($errors) {
            $this->json(['errors' => $errors], 422);
        }

        $user = $this->userModel->create([
            'name'     => $request->input('name'),
            'email'    => $request->input('email'),
            'password' => password_hash($request->input('password'), PASSWORD_DEFAULT),
        ]);

        $this->json(['message' => 'Utilisateur créé', 'data' => $user], 201);
    }

    /**
     * PUT /api/users/{id} — Met à jour un utilisateur.
     */
    public function update(Request $request, string $id): never
    {
        $user = $this->userModel->find((int) $id);
        if (!$user) {
            $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        $this->userModel->update((int) $id, $request->all());
        $this->json(['message' => 'Utilisateur mis à jour']);
    }

    /**
     * DELETE /api/users/{id} — Supprime un utilisateur.
     */
    public function destroy(Request $request, string $id): never
    {
        if (!$this->userModel->find((int) $id)) {
            $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        $this->userModel->delete((int) $id);
        $this->json(['message' => 'Utilisateur supprimé']);
    }
}
