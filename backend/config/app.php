<?php
/**
 * Configuration Backend API.
 */

declare(strict_types=1);

return [
    'name'    => env('APP_NAME', 'AdmHost API'),
    'type'    => 'api',
    'env'     => env('APP_ENV', 'local'),
    'debug'   => env('APP_DEBUG', false),
    'api_key' => env('API_KEY', ''),
];
