<?php
/**
 * Contrôleur Stripe — checkout et webhooks.
 */

declare(strict_types=1);

namespace Backend\Controllers;

use Backend\Services\StripeService;
use Shared\Core\ApiException;
use Shared\Core\Auth;
use Shared\Core\Controller;
use Shared\Core\Request;
use Shared\Core\Response;

class StripeController extends Controller
{
    private StripeService $stripe;

    public function __construct()
    {
        $this->stripe = new StripeService();
    }

    /**
     * POST /api/stripe/checkout — crée une session de paiement.
     */
    public function checkout(Request $request): never
    {
        $priceId  = $request->input('price_id');
        $planSlug = $request->input('plan_slug', 'starter');

        if (!$priceId) {
            $this->json(['error' => 'price_id requis'], 422);
        }

        try {
            $session = $this->stripe->createCheckoutSession(Auth::id(), $priceId, $planSlug);
            $this->json(['data' => $session]);
        } catch (ApiException $e) {
            $e->render();
        }
    }

    /**
     * POST /api/stripe/webhook — endpoint webhook Stripe (sans auth middleware).
     */
    public function webhook(Request $request): never
    {
        $payload   = file_get_contents('php://input') ?: '';
        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        try {
            $result = $this->stripe->handleWebhook($payload, $signature);
            Response::json(['received' => true])->send();
        } catch (ApiException $e) {
            $e->render();
        }
    }

    /**
     * GET /api/stripe/plans — liste les plans disponibles.
     */
    public function plans(Request $request): never
    {
        $db = \Shared\Core\Database::getInstance();
        $stmt = $db->query("SELECT * FROM service_plans WHERE is_active = 1 ORDER BY price_monthly ASC");
        $this->json(['data' => $stmt->fetchAll()]);
    }
}
