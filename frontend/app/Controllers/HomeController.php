<?php
/**
 * Contrôleur des pages publiques (accueil, tarifs).
 */

declare(strict_types=1);

namespace Frontend\Controllers;

use Shared\Core\Controller;
use Shared\Core\Request;

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
     * Page des tarifs / plans d'abonnement.
     */
    public function pricing(Request $request): never
    {
        $plans = [
            ['name' => 'Starter',  'price' => 9,  'features' => ['1 site', '10 Go stockage', 'Support email']],
            ['name' => 'Pro',      'price' => 29, 'features' => ['5 sites', '50 Go stockage', 'Support prioritaire']],
            ['name' => 'Business', 'price' => 79, 'features' => ['Illimité', '200 Go stockage', 'Support 24/7']],
        ];

        $this->view('home.pricing', [
            'title' => 'Tarifs',
            'plans' => $plans,
        ]);
    }
}
