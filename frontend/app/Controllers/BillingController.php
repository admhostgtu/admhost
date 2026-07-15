<?php
/**
 * Facturation client — abonnements et factures.
 */

declare(strict_types=1);

namespace Frontend\Controllers;

use Frontend\Services\ApiClient;
use Shared\Core\Controller;
use Shared\Core\Csrf;
use Shared\Core\Request;

class BillingController extends Controller
{
    private ApiClient $api;

    public function __construct()
    {
        $this->api = new ApiClient();
    }

    public function index(Request $request): never
    {
        if (empty($_SESSION['user'])) {
            redirect('/login');
        }

        $subs     = $this->api->get('/api/account/subscriptions');
        $payments = $this->api->get('/api/account/payments');
        $sub      = $this->api->get('/api/subscription');

        $this->view('billing.index', [
            'title'        => 'Facturation',
            'user'         => $_SESSION['user'],
            'subscription' => $sub['data'] ?? null,
            'subscriptions'=> $subs['data'] ?? [],
            'payments'     => $payments['data'] ?? [],
        ], 'console');
    }

    public function portal(Request $request): never
    {
        if (empty($_SESSION['user'])) {
            redirect('/login');
        }
        $this->validateCsrf($request);

        $response = $this->api->post('/api/stripe/portal', []);
        if (!empty($response['data']['portal_url'])) {
            redirect($response['data']['portal_url']);
        }

        redirect('/billing');
    }
}
