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
        $planSlug = $request->input('plan_slug', 'starter');
        $interval = $request->input('interval', 'monthly'); // monthly | annual
        $priceId  = $request->input('price_id');

        if (!$priceId) {
            $planModel = new \Backend\Models\Plan();
            $plan = $planModel->findBySlug($planSlug);
            if (!$plan) {
                $this->json(['error' => 'Plan introuvable'], 404);
            }
            $priceId = ($interval === 'annual')
                ? ($plan['stripe_price_id_annual'] ?? null)
                : ($plan['stripe_price_id'] ?? null);

            if (!$priceId) {
                $this->json(['error' => 'Ce plan n\'est pas encore configuré dans Stripe. Contactez l\'administrateur.'], 422);
            }
        }

        try {
            $session = $this->stripe->createCheckoutSession(
                Auth::id(),
                $priceId,
                $planSlug,
                $interval
            );
            $this->json(['data' => $session]);
        } catch (ApiException $e) {
            $e->render();
        }
    }

    /**
     * POST /api/stripe/portal — portail client Stripe (factures, CB, annulation).
     */
    public function portal(Request $request): never
    {
        try {
            $url = $this->stripe->createBillingPortalSession(Auth::id());
            $this->json(['data' => ['portal_url' => $url]]);
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
