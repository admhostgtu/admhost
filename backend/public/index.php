<?php
/**
 * Point d'entrée Backend API — production Scaleway.
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 2));

require_once APP_ROOT . '/shared/autoload.php';

use Shared\Core\App;
use Shared\Core\ErrorHandler;
use Shared\Core\SecurityHeaders;

ErrorHandler::register();
SecurityHeaders::send(isApi: true);

// CORS — origine frontend uniquement en production
$corsOrigins = array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', env('APP_URL', '')))));
$origin      = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin && in_array($origin, $corsOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
} elseif (env('APP_ENV', 'production') !== 'production') {
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$config = require dirname(__DIR__) . '/config/app.php';
$routes = require dirname(__DIR__) . '/routes/api.php';

$app = new App($config, $routes);
$app->run();
