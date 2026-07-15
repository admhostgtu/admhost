<?php
/**
 * Contrôleur des pages publiques (accueil, tarifs).
 */

declare(strict_types=1);

namespace Frontend\Controllers;

use Shared\Core\Controller;
use Shared\Core\Request;
use Shared\Services\HttpApiClient;

class HomeController extends Controller
{
    /**
     * Page d'accueil du SaaS.
     */
    public function index(Request $request): never
    {
        $this->view('home.index', [
            'title'   => 'Accueil',
            'appName' => env('APP_NAME', 'AdmHost'),
        ]);
    }

    /**
     * Accueil console — redirige vers dashboard ou login.
     */
    public function consoleHome(Request $request): never
    {
        if (!empty($_SESSION['user'])) {
            redirect('/dashboard');
        }
        redirect('/login');
    }

    /**
     * Page des tarifs / plans d'abonnement.
     */
    public function pricing(Request $request): never
    {
        $api   = new HttpApiClient();
        $resp  = $api->get('/api/stripe/plans');
        $plans = $resp['data'] ?? [];

        if ($plans === []) {
            $plans = [
                ['slug' => 'starter', 'name' => 'Starter', 'price_monthly' => 9, 'price_annual' => 90, 'features' => '["1 site","10 Go","SSH"]'],
                ['slug' => 'pro', 'name' => 'Pro', 'price_monthly' => 29, 'price_annual' => 290, 'features' => '["5 sites","50 Go","SSH + SMTP"]'],
                ['slug' => 'business', 'name' => 'Business', 'price_monthly' => 79, 'price_annual' => 790, 'features' => '["Illimité","200 Go","Docker"]'],
            ];
        }

        $layout = app_site() === 'console' ? 'console' : null;
        $data = [
            'title' => 'Tarifs',
            'plans' => $plans,
            'user'  => $_SESSION['user'] ?? null,
            'error' => $request->query('error'),
        ];

        if ($layout === 'console' && !empty($_SESSION['user'])) {
            $this->view('home.pricing', $data, 'console');
        }

        $this->view('home.pricing', $data);
    }
}
