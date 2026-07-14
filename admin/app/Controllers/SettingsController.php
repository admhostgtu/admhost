<?php
/**
 * Contrôleur des paramètres de l'application (admin).
 */

declare(strict_types=1);

namespace Admin\Controllers;

use Shared\Core\Controller;
use Shared\Core\Request;
use Admin\Middleware\AdminAuth;

class SettingsController extends Controller
{
    /**
     * Affiche la page de paramètres.
     */
    public function index(Request $request): never
    {
        AdminAuth::check();

        $this->view('settings.index', [
            'title' => 'Paramètres',
            'admin' => AdminAuth::user(),
            'settings' => [
                'app_name'  => env('APP_NAME', 'AdmHost'),
                'app_env'   => env('APP_ENV', 'local'),
                'api_url'   => env('API_URL', ''),
            ],
        ]);
    }

    /**
     * Met à jour les paramètres (TODO : persistance en BDD).
     */
    public function update(Request $request): never
    {
        AdminAuth::check();

        // TODO : sauvegarder les paramètres en base de données
        redirect('/admin/settings');
    }
}
