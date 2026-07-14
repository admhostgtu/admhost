<?php
/**
 * Configuration Admin panel.
 */

declare(strict_types=1);

return [
    'name'        => env('APP_NAME', 'AdmHost') . ' Admin',
    'type'        => 'web',
    'api_url'     => env('API_URL', 'http://localhost:8001'),
    'admin_email' => env('ADMIN_EMAIL', 'admin@example.com'),
];
