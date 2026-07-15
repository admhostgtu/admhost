<?php
/**
 * Compte client — profil, mot de passe, factures.
 */

declare(strict_types=1);

namespace Backend\Controllers;

use Backend\Models\Payment;
use Backend\Models\User;
use Shared\Core\ApiException;
use Shared\Core\Auth;
use Shared\Core\Controller;
use Shared\Core\Request;

class AccountController extends Controller
{
    /**
     * PUT /api/account/password
     */
    public function updatePassword(Request $request): never
    {
        $userId = Auth::id();
        $current = $request->input('current_password');
        $newPass = $request->input('new_password');

        if (!$current || !$newPass) {
            $this->json(['error' => 'Mot de passe actuel et nouveau requis.'], 422);
        }

        if (strlen($newPass) < 8) {
            $this->json(['error' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.'], 422);
        }

        $userModel = new User();
        $user = $userModel->find($userId);

        if (!$user || !password_verify($current, $user['password'])) {
            $this->json(['error' => 'Mot de passe actuel incorrect.'], 401);
        }

        $userModel->updatePassword($userId, password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]));
        $this->json(['message' => 'Mot de passe mis à jour.']);
    }

    /**
     * PUT /api/account/profile
     */
    public function updateProfile(Request $request): never
    {
        $userId = Auth::id();
        $name   = trim((string) $request->input('name'));

        if ($name === '') {
            $this->json(['error' => 'Le nom est requis.'], 422);
        }

        $userModel = new User();
        $userModel->update($userId, ['name' => $name]);

        $this->json(['message' => 'Profil mis à jour.', 'data' => $userModel->findPublic($userId)]);
    }

    /**
     * GET /api/account/payments
     */
    public function payments(Request $request): never
    {
        $paymentModel = new Payment();
        $this->json(['data' => $paymentModel->findByUser(Auth::id())]);
    }

    /**
     * GET /api/account/subscriptions
     */
    public function subscriptions(Request $request): never
    {
        $subModel = new \Backend\Models\Subscription();
        $this->json(['data' => $subModel->allByUser(Auth::id())]);
    }
}
