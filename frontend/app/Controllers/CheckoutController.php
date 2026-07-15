<?php
/**
 * Checkout Stripe depuis l'espace client.
 */

declare(strict_types=1);

namespace Frontend\Controllers;

use Frontend\Services\ApiClient;
use Shared\Core\Controller;
use Shared\Core\Csrf;
use Shared\Core\Request;

class CheckoutController extends Controller
{
    private ApiClient $api;

    public function __construct()
    {
        $this->api = new ApiClient();
    }

    public function subscribe(Request $request): never
    {
        if (empty($_SESSION['user'])) {
            redirect('/login?redirect=/pricing');
        }
        $this->validateCsrf($request);

        $response = $this->api->post('/api/stripe/checkout', [
            'plan_slug' => $request->input('plan_slug', 'starter'),
            'interval'  => $request->input('interval', 'monthly'),
        ]);

        if (!empty($response['data']['checkout_url'])) {
            redirect($response['data']['checkout_url']);
        }

        redirect('/pricing?error=' . urlencode($response['error'] ?? 'checkout'));
    }
}
