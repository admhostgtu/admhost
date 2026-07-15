<?php
/**
 * Bootstrap — Espace client console.admhost.fr
 * ~/console.admhost.fr/public_html/index.php
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 2) . '/admhost');
define('FRONTEND_DIR', APP_ROOT . '/frontend');
define('APP_SITE', 'console');

if (!is_dir(APP_ROOT . '/shared')) {
    http_response_code(500);
    exit('Configuration serveur incorrecte.');
}

require_once APP_ROOT . '/shared/autoload.php';

use Shared\Core\App;
use Shared\Core\View;
use Shared\Core\ErrorHandler;
use Shared\Core\SecurityHeaders;

ErrorHandler::register();
SecurityHeaders::send();

View::setBasePath(FRONTEND_DIR . '/templates');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    session_start();
}

$config = require FRONTEND_DIR . '/config/app.php';
$routes = require FRONTEND_DIR . '/routes/web.console.php';

$app = new App($config, $routes);
$app->run();
