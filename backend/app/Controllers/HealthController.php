<?php
/**
 * Contrôleur de santé de l'API — endpoint de monitoring.
 */

declare(strict_types=1);

namespace Backend\Controllers;

use Shared\Core\Controller;
use Shared\Core\Request;

class HealthController extends Controller
{
    /**
     * GET /api/health — Vérifie que l'API est opérationnelle.
     */
    public function index(Request $request): never
    {
        $this->json([
            'status'  => 'ok',
            'service' => 'AdmHost API',
            'version' => '1.0.0',
            'time'    => date('c'),
        ]);
    }
}
